<?php

namespace App\Http\Controllers;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\LawyerCase;
use App\Models\TeamCaseAccess;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\FirebasePushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Create a new chat group
     * POST /api/chat/groups/create
     */
    public function createGroup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'group_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'member_ids' => 'nullable|array',
                'member_ids.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();

            // Check if user is a lawyer
            if ($user->role !== 'lawyer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only lawyers can create groups'
                ], 403);
            }

            DB::beginTransaction();

            // Handle group image upload
            $groupImagePath = null;
            if ($request->hasFile('group_image')) {
                $image = $request->file('group_image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $groupImagePath = $image->storeAs('chat/groups', $imageName, 'public');
            }

            // Create group
            $group = ChatGroup::create([
                'created_by' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'group_image' => $groupImagePath,
                'status' => 'active'
            ]);

            // Add creator as admin
            ChatGroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'joined_at' => now()
            ]);

            // ── Step 1: Collect member IDs passed in request ────────────────
            $requestMemberIds = collect();
            if ($request->has('member_ids') && is_array($request->member_ids)) {
                $requestMemberIds = collect($request->member_ids);
            }

            // ── Step 2: Fetch existing team members of the creator ──────────
            // teams table: user_id = creator, team_id = JSON array of member IDs
            $existingTeamMemberIds = collect();
            $teamRecords = TeamMember::where('user_id', $user->id)->get();

            foreach ($teamRecords as $teamRecord) {
                $teamIds = is_array($teamRecord->team_id)
                    ? $teamRecord->team_id
                    : json_decode($teamRecord->team_id, true);

                if (is_array($teamIds)) {
                    $existingTeamMemberIds = $existingTeamMemberIds->merge($teamIds);
                }
            }

            // ── Step 3: Merge both — all unique member IDs to add to group ──
            $allMemberIds = $requestMemberIds
                ->merge($existingTeamMemberIds)
                ->unique()
                ->reject(fn($id) => $id == $user->id)
                ->values();

            // ── Step 4: Add everyone to the chat group ──────────────────────
            $addedMembers = [];

            foreach ($allMemberIds as $memberId) {
                $member = User::find($memberId);
                if (!$member || $member->role !== 'lawyer') {
                    continue;
                }

                $alreadyAdded = ChatGroupMember::where('group_id', $group->id)
                    ->where('user_id', $memberId)
                    ->exists();

                if (!$alreadyAdded) {
                    ChatGroupMember::create([
                        'group_id'  => $group->id,
                        'user_id'   => $memberId,
                        'role'      => 'member',
                        'joined_at' => now()
                    ]);
                    $addedMembers[] = (int) $memberId;
                }
            }

            // ── Step 5: Sync teams table ────────────────────────────────────
            // New member IDs that are NOT already in any existing team of this creator
            $newMembersNotInTeam = $requestMemberIds
                ->reject(fn($id) => $existingTeamMemberIds->contains($id))
                ->reject(fn($id) => $id == $user->id)
                ->values();

            if ($newMembersNotInTeam->isNotEmpty()) {
                // Add new IDs into the latest existing team record, or create a fresh one
                $latestTeam = TeamMember::where('user_id', $user->id)->latest()->first();

                if ($latestTeam) {
                    // Merge new IDs into existing team_id array
                    $merged = collect($latestTeam->team_id)
                        ->merge($newMembersNotInTeam)
                        ->unique()
                        ->values()
                        ->toArray();

                    // Update without triggering booted() to avoid duplicate TeamCaseAccess rows
                    TeamMember::where('id', $latestTeam->id)
                        ->update(['team_id' => json_encode($merged)]);
                } else {
                    // No team exists yet — create a brand new team record
                    TeamMember::create([
                        'user_id' => $user->id,
                        'team_id' => json_encode($newMembersNotInTeam->toArray()),
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $group->load(['creator', 'members.user', 'users']);

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'data'    => [
                    'group'                    => $group,
                    'group_image_url'          => $groupImagePath ? url('storage/' . $groupImagePath) : null,
                    'members_added_to_group'   => $addedMembers,
                    'team_synced'              => $newMembersNotInTeam->values()->toArray(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add members to a group
     * POST /api/chat/groups/{groupId}/add-members
     */
    public function addMembers(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_ids' => 'required|array',
                'member_ids.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if user is admin of the group
            $membership = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();

            if (!$membership || $membership->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group admin can add members'
                ], 403);
            }

            $addedMembers = [];
            $alreadyMembers = [];

            foreach ($request->member_ids as $memberId) {
                // Check if user is a lawyer
                $member = User::find($memberId);
                if (!$member || $member->role !== 'lawyer') {
                    continue;
                }

                // Check if already a member
                $exists = ChatGroupMember::where('group_id', $groupId)
                    ->where('user_id', $memberId)
                    ->exists();

                if (!$exists) {
                    ChatGroupMember::create([
                        'group_id' => $groupId,
                        'user_id' => $memberId,
                        'role' => 'member',
                        'joined_at' => now()
                    ]);
                    $addedMembers[] = $member;

                    // ── Sync: also add this member to the group creator's team ──
                    $this->addToTeam((int) $group->created_by, (int) $memberId);
                } else {
                    $alreadyMembers[] = $memberId;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Members processed',
                'data' => [
                    'added_members' => $addedMembers,
                    'already_members' => $alreadyMembers
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove member from group
     * DELETE /api/chat/groups/{groupId}/remove-member/{memberId}
     */
    public function removeMember($groupId, $memberId)
    {
        try {
            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if user is admin
            $membership = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();

            if (!$membership || $membership->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group admin can remove members'
                ], 403);
            }

            // Cannot remove the creator
            if ($memberId == $group->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove group creator'
                ], 403);
            }

            $memberToRemove = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $memberId)
                ->first();

            if (!$memberToRemove) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found in group'
                ], 404);
            }

            $memberToRemove->delete();

            // ── Sync: remove this member from the group creator's team ──────
            $this->removeFromTeam((int) $group->created_by, (int) $memberId);

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Leave group
     * POST /api/chat/groups/{groupId}/leave
     */
    public function leaveGroup($groupId)
    {
        try {
            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Creator cannot leave
            if ($group->created_by == $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group creator cannot leave. Please delete the group instead.'
                ], 403);
            }

            $membership = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();

            if (!$membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this group'
                ], 404);
            }

            $membership->delete();

            // ── Sync: remove this user from the group creator's team ────────
            $this->removeFromTeam((int) $group->created_by, (int) $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Left group successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to leave group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all groups for authenticated user
     * GET /api/chat/groups
     */
    public function getGroups()
    {
        try {
            $user = Auth::user();

            $groups = ChatGroup::whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['creator', 'members.user', 'latestMessage.user'])
            ->withCount('members')
            ->orderBy('updated_at', 'desc')
            ->get();

            $groups = $groups->map(function ($group) use ($user) {
                $latestMessage = $group->latestMessage;

                // Resolve image path so it works for both storage and public uploads
                $groupImageUrl = null;
                if ($group->group_image) {
                    $path = ltrim($group->group_image, '/');

                    if (Str::startsWith($path, ['http://', 'https://'])) {
                        $groupImageUrl = $path;
                    } elseif (Str::startsWith($path, ['storage/', 'uploads/', 'public/'])) {
                        $groupImageUrl = url($path);
                    } else {
                        $groupImageUrl = url('storage/' . $path);
                    }
                }

                // Build a short summary for the latest message
                if ($latestMessage) {
                    switch ($latestMessage->message_type) {
                        case 'image':
                            $latestMessageSummary = '📷 Photo';
                            break;
                        case 'voice':
                            $latestMessageSummary = 'Voice note';
                            break;
                        case 'text':
                        default:
                            $text = trim((string) $latestMessage->message);
                            $latestMessageSummary = $text !== '' ? Str::limit($text, 120) : 'New message';
                            break;
                    }
                } else {
                    $latestMessageSummary = $group->created_by === $user->id
                        ? 'You created this group'
                        : 'You were added to this group';
                }

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'group_image' => $groupImageUrl,
                    'status' => $group->status,
                    'created_by' => $group->creator,
                    'members_count' => $group->members_count,
                    'latest_message' => $latestMessageSummary,
                    'latest_message_type' => $latestMessage?->message_type,
                    'latest_message_details' => $latestMessage ? [
                        'id' => $latestMessage->id,
                        'message' => $latestMessage->message,
                        'file_url' => $latestMessage->file_url,
                        'sender' => $latestMessage->user,
                        'created_at' => $latestMessage->created_at,
                    ] : null,
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Groups retrieved successfully',
                'data' => $groups
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single group details
     * GET /api/chat/groups/{groupId}
     */
    public function getGroupDetails($groupId)
    {
        try {
            $user = Auth::user();

            $group = ChatGroup::with(['creator', 'members.user', 'users'])
                ->withCount('members')
                ->find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if user is a member
            $isMember = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this group'
                ], 403);
            }

            $groupData = [
                'id'            => $group->id,
                'name'          => $group->name,
                'description'   => $group->description,
                'group_image'   => $group->group_image ? url('storage/' . $group->group_image) : null,
                'status'        => $group->status,
                'created_by'    => $group->creator,
                'members_count' => $group->members_count,
                'members'       => $group->members->map(function ($member) {
                    $memberUser = $member->user;

                    // ── Fetch all cases this member can see ─────────────────
                    // 1. Cases they own
                    $ownCaseIds = LawyerCase::where('user_id', $memberUser->id)
                        ->pluck('id')->toArray();

                    // 2. Cases they're tagged as lawyer_ids
                    $taggedCaseIds = LawyerCase::whereRaw(
                        'JSON_CONTAINS(lawyer_ids, ?)',
                        [json_encode((int) $memberUser->id)]
                    )->pluck('id')->toArray();

                    // 3. Cases from TeamCaseAccess
                    $teamCaseIds = TeamCaseAccess::where('user_id', $memberUser->id)
                        ->pluck('lawyer_case_id')->toArray();

                    $allCaseIds = array_unique(array_merge($ownCaseIds, $taggedCaseIds, $teamCaseIds));

                    $cases = LawyerCase::with(['proceedings', 'attachments'])
                        ->whereIn('id', $allCaseIds)
                        ->get()
                        ->map(function ($case) {
                            return [
                                'id'           => $case->id,
                                'case_number'  => $case->case_number,
                                'case_type'    => $case->case_type,
                                'party_a'      => $case->party_a,
                                'party_b'      => $case->party_b,
                                'court_name'   => $case->court_name,
                                'judge_name'   => $case->judge_name,
                                'close_status' => $case->close_status ?? 0,
                                'proceedings'  => $case->proceedings,
                                'attachments'  => $case->attachments,
                            ];
                        });

                    return [
                        'id'        => $member->id,
                        'user'      => $memberUser,
                        'role'      => $member->role,
                        'joined_at' => $member->joined_at,
                        'cases'     => $cases,           // ← all cases of this member
                    ];
                }),
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Group details retrieved successfully',
                'data' => $groupData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve group details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update group details
     * PUT /api/chat/groups/{groupId}
     */
    public function updateGroup(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'group_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if user is admin
            $membership = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();

            if (!$membership || $membership->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group admin can update group details'
                ], 403);
            }

            // Update fields
            if ($request->has('name')) {
                $group->name = $request->name;
            }

            if ($request->has('description')) {
                $group->description = $request->description;
            }

            // Handle group image upload
            if ($request->hasFile('group_image')) {
                // Delete old image if exists
                if ($group->group_image) {
                    Storage::disk('public')->delete($group->group_image);
                }

                $image = $request->file('group_image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $groupImagePath = $image->storeAs('chat/groups', $imageName, 'public');
                $group->group_image = $groupImagePath;
            }

            $group->save();

            return response()->json([
                'success' => true,
                'message' => 'Group updated successfully',
                'data' => [
                    'group' => $group,
                    'group_image_url' => $group->group_image ? url('storage/' . $group->group_image) : null
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete group
     * DELETE /api/chat/groups/{groupId}
     */
    public function deleteGroup($groupId)
    {
        try {
            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Only creator can delete
            if ($group->created_by != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only group creator can delete the group'
                ], 403);
            }

            // Delete group image if exists
            if ($group->group_image) {
                Storage::disk('public')->delete($group->group_image);
            }

            // Delete all message files
            $messages = ChatMessage::where('group_id', $groupId)->get();
            foreach ($messages as $message) {
                if ($message->file_path) {
                    Storage::disk('public')->delete($message->file_path);
                }
            }

            // ── Sync: remove every group member from the creator's team ─────
            $memberIds = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', '!=', $user->id)  // skip creator himself
                ->pluck('user_id');

            foreach ($memberIds as $memberId) {
                $this->removeFromTeam((int) $user->id, (int) $memberId);
            }

            $group->delete();

            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send message (text, image, or voice)
     * POST /api/chat/groups/{groupId}/messages
     */
    public function sendMessage(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'message_type' => 'required|in:text,image,voice,file',
                'message' => 'required_if:message_type,text|nullable|string',
                'file' => 'required_if:message_type,image,voice,file|file|max:20480', // 20MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if user is a member
            $isMember = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this group'
                ], 403);
            }

            $messageData = [
                'group_id'     => $groupId,
                'user_id'      => $user->id,
                'message_type' => $request->message_type,
                'message'      => $request->message,
            ];

            // Handle file upload for image, voice or file
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                // Determine storage folder by message_type
                if ($request->message_type === 'image') {
                    $folder = 'chat/images';
                } elseif ($request->message_type === 'voice') {
                    $folder = 'chat/voice';
                } else {
                    // message_type === 'file' — any document/pdf/etc
                    $folder = 'chat/files';
                }

                $filePath = $file->storeAs($folder, $fileName, 'public');

                $messageData['file_path'] = $filePath;
                $messageData['file_name'] = $file->getClientOriginalName();
                $messageData['file_size'] = $file->getSize();
                $messageData['mime_type'] = $file->getMimeType();
            }

            $message = ChatMessage::create($messageData);
            $message->load('user', 'group');

            // Update group's updated_at to move it to top of chat list
            $group->touch();

            // ── Firebase Push Notification to all group members (except sender) ──
            try {
                $senderName = trim($user->first_name . ' ' . $user->last_name);
                $groupName  = $group->name ?? 'Group Chat';

                // Notification title: "GroupName"
                // Notification body:  "SenderName: message text"  OR  "SenderName: sent a photo/voice/file"
                $notifTitle = $groupName;
                if ($request->message_type === 'text') {
                    $notifBody = $senderName . ': ' . $request->message;
                } elseif ($request->message_type === 'image') {
                    $notifBody = $senderName . ': 📷 Photo';
                } elseif ($request->message_type === 'voice') {
                    $notifBody = $senderName . ': 🎤 Voice message';
                } else {
                    $notifBody = $senderName . ': 📎 File';
                }

                // Get all group members except the sender
                $members = ChatGroupMember::where('group_id', $groupId)
                    ->where('user_id', '!=', $user->id)
                    ->with('user')
                    ->get();

                foreach ($members as $member) {
                    $memberUser = $member->user;
                    if ($memberUser && $memberUser->device_token) {
                        try {
                            $notif = new FirebasePushNotification($notifTitle, $notifBody, $memberUser->device_token);
                            $notif->toFirebase();
                        } catch (\Exception $fcmEx) {
                            \Log::warning('FCM failed for user ' . $memberUser->id . ': ' . $fcmEx->getMessage());
                        }
                    }
                }
            } catch (\Exception $notifEx) {
                \Log::warning('Notification block failed: ' . $notifEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'message' => $message,
                    'file_url' => $message->file_url
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages for a group
     * GET /api/chat/groups/{groupId}/messages
     */
    public function getMessages(Request $request, $groupId)
    {
        try {
            $user = Auth::user();
            $group = ChatGroup::find($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if user is a member
            $isMember = ChatGroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this group'
                ], 403);
            }

            $perPage = $request->get('per_page', 50);
            $messages = ChatMessage::where('group_id', $groupId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Messages retrieved successfully',
                'data' => $messages
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit a message
     * PUT /api/chat/messages/{messageId}
     */
    public function editMessage(Request $request, $messageId)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'message' => 'required|string',
            ]);

            $message = ChatMessage::find($messageId);

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            // Only the sender can edit their own message
            if ($message->user_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own messages'
                ], 403);
            }

            $message->message = $request->message;
            $message->save();

            return response()->json([
                'success'  => true,
                'message'  => 'Message updated successfully',
                'data'     => [
                    'id'        => $message->id,
                    'group_id'  => $message->group_id,
                    'user_id'   => $message->user_id,
                    'message'   => $message->message,
                    'file_url'  => $message->file_url,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to edit message',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a message
     * DELETE /api/chat/messages/{messageId}
     */
    public function deleteMessage($messageId)
    {
        try {
            $user = Auth::user();
            $message = ChatMessage::find($messageId);

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            // Only message sender or group admin can delete
            $membership = ChatGroupMember::where('group_id', $message->group_id)
                ->where('user_id', $user->id)
                ->first();

            if ($message->user_id != $user->id && (!$membership || $membership->role !== 'admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete this message'
                ], 403);
            }

            // Delete file if exists
            if ($message->file_path) {
                Storage::disk('public')->delete($message->file_path);
            }

            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all lawyers (to add to groups)
     * GET /api/chat/lawyers
     */
    public function getLawyers(Request $request)
    {
        try {
            $user = Auth::user();
            $search = $request->get('search', '');

            $lawyers = User::where('role', 'lawyer')
                ->where('id', '!=', $user->id)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('mobile_number', 'like', "%{$search}%");
                    });
                })
                ->select('id', 'first_name', 'last_name', 'mobile_number', 'license_no')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lawyers retrieved successfully',
                'data' => $lawyers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lawyers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark messages as read
     * POST /api/chat/groups/{groupId}/mark-read
     */
    public function markAsRead($groupId)
    {
        try {
            $user = Auth::user();

            ChatMessage::where('group_id', $groupId)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all lawyers with their actual IDs (Helper endpoint)
     * GET /api/chat/lawyers/ids
     */
    public function getLawyersWithIds()
    {
        try {
            $user = Auth::user();

            $lawyers = User::where('role', 'lawyer')
                ->where('id', '!=', $user->id)
                ->select('id', 'first_name', 'last_name', 'mobile_number', 'license_no')
                ->orderBy('first_name')
                ->get();

            // Create a simple list for easy copying
            $simpleList = $lawyers->map(function ($lawyer) {
                return [
                    'id' => $lawyer->id,
                    'name' => $lawyer->first_name . ' ' . $lawyer->last_name,
                    'mobile' => $lawyer->mobile_number
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Lawyers retrieved successfully',
                'total_count' => $lawyers->count(),
                'data' => $lawyers,
                'simple_list' => $simpleList,
                'example_member_ids' => $lawyers->take(3)->pluck('id')->toArray()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lawyers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Remove a user from the creator's team record (teams table).
     * Also cleans up TeamCaseAccess rows for that user.
     *
     * @param int $creatorId  – the group creator / team owner
     * @param int $memberIdToRemove
     */
    private function removeFromTeam(int $creatorId, int $memberIdToRemove): void
    {
        $teamRecords = TeamMember::where('user_id', $creatorId)->get();

        foreach ($teamRecords as $teamRecord) {
            $ids = is_array($teamRecord->team_id)
                ? $teamRecord->team_id
                : json_decode($teamRecord->team_id, true);

            if (!is_array($ids) || !in_array($memberIdToRemove, $ids)) {
                continue;
            }

            // Remove the member from the array
            $updated = array_values(array_filter($ids, fn($id) => $id != $memberIdToRemove));

            // Remove TeamCaseAccess rows for this user + team record
            TeamCaseAccess::where('team_member_id', $teamRecord->id)
                ->where('user_id', $memberIdToRemove)
                ->delete();

            if (empty($updated)) {
                // No members left → delete the whole team row
                $teamRecord->delete();
            } else {
                TeamMember::where('id', $teamRecord->id)
                    ->update(['team_id' => json_encode($updated)]);
            }
        }
    }

    /**
     * Add a user into the creator's latest team record (teams table).
     * If no team record exists yet, create one.
     * Also creates TeamCaseAccess rows so the new member can see creator's cases.
     *
     * @param int $creatorId
     * @param int $memberIdToAdd
     */
    private function addToTeam(int $creatorId, int $memberIdToAdd): void
    {
        $latestTeam = TeamMember::where('user_id', $creatorId)->latest()->first();

        if ($latestTeam) {
            $ids = is_array($latestTeam->team_id)
                ? $latestTeam->team_id
                : json_decode($latestTeam->team_id, true);

            if (!in_array($memberIdToAdd, (array) $ids)) {
                $ids[] = $memberIdToAdd;
                TeamMember::where('id', $latestTeam->id)
                    ->update(['team_id' => json_encode(array_values($ids))]);

                // ── Grant access to all creator's cases for this new member ──
                $cases = LawyerCase::where('user_id', $creatorId)->get();
                foreach ($cases as $case) {
                    $exists = TeamCaseAccess::where('team_member_id', $latestTeam->id)
                        ->where('user_id', $memberIdToAdd)
                        ->where('lawyer_case_id', $case->id)
                        ->exists();

                    if (!$exists) {
                        TeamCaseAccess::create([
                            'team_member_id' => $latestTeam->id,
                            'user_id'        => $memberIdToAdd,
                            'lawyer_case_id' => $case->id,
                        ]);
                    }
                }
            }
        } else {
            // No team exists yet — TeamMember::booted() will create TeamCaseAccess automatically
            TeamMember::create([
                'user_id' => $creatorId,
                'team_id' => json_encode([$memberIdToAdd]),
            ]);
        }
    }

}
