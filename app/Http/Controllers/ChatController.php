<?php

namespace App\Http\Controllers;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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

            // Add members if provided
            if ($request->has('member_ids') && is_array($request->member_ids)) {
                foreach ($request->member_ids as $memberId) {
                    // Check if user is a lawyer
                    $member = User::find($memberId);
                    if ($member && $member->role === 'lawyer' && $memberId != $user->id) {
                        ChatGroupMember::create([
                            'group_id' => $group->id,
                            'user_id' => $memberId,
                            'role' => 'member',
                            'joined_at' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            // Load relationships
            $group->load(['creator', 'members.user', 'users']);

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'data' => [
                    'group' => $group,
                    'group_image_url' => $groupImagePath ? url('storage/' . $groupImagePath) : null
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

            $groups = $groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'group_image' => $group->group_image ? url('storage/' . $group->group_image) : null,
                    'status' => $group->status,
                    'created_by' => $group->creator,
                    'members_count' => $group->members_count,
                    'latest_message' => $group->latestMessage,
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
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'group_image' => $group->group_image ? url('storage/' . $group->group_image) : null,
                'status' => $group->status,
                'created_by' => $group->creator,
                'members_count' => $group->members_count,
                'members' => $group->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'user' => $member->user,
                        'role' => $member->role,
                        'joined_at' => $member->joined_at,
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
                'message_type' => 'required|in:text,image,voice',
                'message' => 'required_if:message_type,text|nullable|string',
                'file' => 'required_if:message_type,image,voice|file|max:10240', // 10MB max
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
                'group_id' => $groupId,
                'user_id' => $user->id,
                'message_type' => $request->message_type,
                'message' => $request->message,
            ];

            // Handle file upload for image or voice
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                $folder = $request->message_type === 'image' ? 'chat/images' : 'chat/voice';
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
}
