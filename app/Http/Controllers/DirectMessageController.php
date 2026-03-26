<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Notifications\FirebasePushNotification;

class DirectMessageController extends Controller
{
    /**
     * Send a direct message between guest and lawyer
     * POST /api/direct/messages/{guestId}
     */
    public function sendMessage(Request $request, $guestId)
    {
        try {
            $user = Auth::user();

            // Validate guest exists and is accepted
            $guest = Guest::find($guestId);
            if (!$guest) {
                return response()->json(['success' => false, 'message' => 'Guest request not found'], 404);
            }

            if (!$guest->status_accpet) {
                return response()->json(['success' => false, 'message' => 'Chat not allowed. Lawyer has not accepted the request yet.'], 403);
            }

            // Only the guest user or the lawyer can chat
            if ($user->id != $guest->user_id && $user->id != $guest->lawyer_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Determine receiver
            $receiverId = ($user->id == $guest->user_id) ? $guest->lawyer_id : $guest->user_id;

            $request->validate([
                'message_type' => 'required|in:text,image,voice,file',
                'message'      => 'nullable|string|required_if:message_type,text',
                'file'         => 'nullable|file|max:10240',
            ]);

            $messageData = [
                'guest_id'     => $guestId,
                'sender_id'    => $user->id,
                'receiver_id'  => $receiverId,
                'message_type' => $request->message_type,
                'message'      => $request->message ?? null,
            ];

            // Handle file upload
            if ($request->hasFile('file')) {
                $file     = $request->file('file');
                $folder   = 'direct';
                if ($request->message_type === 'image') $folder = 'direct/images';
                elseif ($request->message_type === 'voice') $folder = 'direct/voice';
                elseif ($request->message_type === 'file')  $folder = 'direct/files';

                $filePath = $file->store($folder, 'public');

                $messageData['file_path'] = $filePath;
                $messageData['file_name'] = $file->getClientOriginalName();
                $messageData['file_size'] = $file->getSize();
                $messageData['mime_type'] = $file->getMimeType();
            }

            $message = DirectMessage::create($messageData);
            $message->load(['sender', 'receiver']);

            // Firebase notification to receiver
            $receiver = User::find($receiverId);
            if ($receiver && $receiver->device_token) {
                try {
                    $notification = new FirebasePushNotification(
                        $user->first_name . ' ' . $user->last_name,
                        $request->message_type === 'text'
                            ? ($request->message ?? 'Sent a message')
                            : 'Sent a ' . $request->message_type,
                        $receiver->device_token
                    );
                    $notification->toFirebase();
                } catch (\Exception $e) {
                    \Log::warning('FCM failed in DirectMessage: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data'    => [
                    'id'           => $message->id,
                    'guest_id'     => $message->guest_id,
                    'sender_id'    => $message->sender_id,
                    'receiver_id'  => $message->receiver_id,
                    'message_type' => $message->message_type,
                    'message'      => $message->message,
                    'file_url'     => $message->file_url,
                    'file_name'    => $message->file_name,
                    'is_read'      => $message->is_read,
                    'created_at'   => $message->created_at,
                    'sender'       => [
                        'id'   => $message->sender->id,
                        'name' => $message->sender->first_name . ' ' . $message->sender->last_name,
                    ],
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all messages for a guest-lawyer conversation
     * GET /api/direct/messages/{guestId}
     */
    public function getMessages($guestId)
    {
        try {
            $user  = Auth::user();
            $guest = Guest::find($guestId);

            if (!$guest) {
                return response()->json(['success' => false, 'message' => 'Guest request not found'], 404);
            }

            // Only the guest user or the lawyer can view
            if ($user->id != $guest->user_id && $user->id != $guest->lawyer_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $messages = DirectMessage::where('guest_id', $guestId)
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($msg) {
                    return [
                        'id'           => $msg->id,
                        'guest_id'     => $msg->guest_id,
                        'sender_id'    => $msg->sender_id,
                        'receiver_id'  => $msg->receiver_id,
                        'message_type' => $msg->message_type,
                        'message'      => $msg->message,
                        'file_url'     => $msg->file_url,
                        'file_name'    => $msg->file_name,
                        'is_read'      => $msg->is_read,
                        'created_at'   => $msg->created_at,
                        'sender'       => [
                            'id'   => $msg->sender->id,
                            'name' => $msg->sender->first_name . ' ' . $msg->sender->last_name,
                        ],
                    ];
                });

            // Mark messages as read for current user
            DirectMessage::where('guest_id', $guestId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'data'    => $messages
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get messages',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a direct message
     * DELETE /api/direct/messages/{messageId}
     */
    public function deleteMessage($messageId)
    {
        try {
            $user    = Auth::user();
            $message = DirectMessage::find($messageId);

            if (!$message) {
                return response()->json(['success' => false, 'message' => 'Message not found'], 404);
            }

            if ($message->sender_id != $user->id) {
                return response()->json(['success' => false, 'message' => 'You can only delete your own messages'], 403);
            }

            if ($message->file_path) {
                Storage::disk('public')->delete($message->file_path);
            }

            $message->delete();

            return response()->json(['success' => true, 'message' => 'Message deleted successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit a direct message
     * PUT /api/direct/messages/{messageId}
     */
    public function editMessage(Request $request, $messageId)
    {
        try {
            $user    = Auth::user();
            $message = DirectMessage::find($messageId);

            if (!$message) {
                return response()->json(['success' => false, 'message' => 'Message not found'], 404);
            }

            if ($message->sender_id != $user->id) {
                return response()->json(['success' => false, 'message' => 'You can only edit your own messages'], 403);
            }

            $request->validate(['message' => 'required|string']);

            $message->message = $request->message;
            $message->save();

            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully',
                'data'    => [
                    'id'       => $message->id,
                    'guest_id' => $message->guest_id,
                    'message'  => $message->message,
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
     * Get all conversations (guest requests where chat is active)
     * GET /api/direct/conversations
     */
    public function getConversations()
    {
        try {
            $user = Auth::user();

            // Get all accepted guest requests where user is either guest or lawyer
            $guests = Guest::where('status_accpet', 1)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('lawyer_id', $user->id);
                })
                ->with(['user', 'guest'])
                ->get()
                ->map(function ($guest) use ($user) {
                    // Get last message
                    $lastMessage = DirectMessage::where('guest_id', $guest->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    // Unread count for current user
                    $unreadCount = DirectMessage::where('guest_id', $guest->id)
                        ->where('receiver_id', $user->id)
                        ->where('is_read', false)
                        ->count();

                    // Other person info
                    $otherId   = ($user->id == $guest->user_id) ? $guest->lawyer_id : $guest->user_id;
                    $otherUser = User::find($otherId);

                    return [
                        'guest_id'     => $guest->id,
                        'title'        => $guest->title,
                        'case_type'    => $guest->case_type,
                        'other_user'   => $otherUser ? [
                            'id'   => $otherUser->id,
                            'name' => $otherUser->first_name . ' ' . $otherUser->last_name,
                        ] : null,
                        'last_message' => $lastMessage ? [
                            'message'      => $lastMessage->message,
                            'message_type' => $lastMessage->message_type,
                            'file_url'     => $lastMessage->file_url,
                            'created_at'   => $lastMessage->created_at,
                            'sender_id'    => $lastMessage->sender_id,
                        ] : null,
                        'unread_count' => $unreadCount,
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => $guests
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversations',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
