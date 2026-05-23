<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatEvent;
use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\Chat;
use App\Models\ChatGallery;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    use ApiResponse;

    /**
     * SEND MESSAGE
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }


        if (
            !$request->filled('message') &&
            !$request->hasFile('image')
        ) {
            return $this->validationError([
                'message' => ['Write a message or attach image.']
            ]);
        }

        $user = Auth::guard('api')->user();
        $authId = $user->id;

        $activeChildId = $request->header('active-child-id') ?? $request->get('active_child_id');

        if ($activeChildId) {
            $childProfile = AthleteProfiles::find($activeChildId);
            if ($childProfile && $childProfile->user_id) {
                $authId = $childProfile->user_id;
            }
        }

        $receiver = User::find($request->receiver_id);

        if ($user->role === 'coach' && in_array($receiver->role, ['player', 'parent'])) {

            $hasMessagedBefore = Chat::where('sender_id', $receiver->id)
                ->where('receiver_id', $user->id)
                ->exists();

            if (! $hasMessagedBefore) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot message this user until they message you first.'
                ], 403);
            }
        }

        try {
            $receiverId = $request->receiver_id;

            $conversationId = implode('-', [
                min($authId, $receiverId),
                max($authId, $receiverId),
            ]);

            $chat = new Chat();
            $chat->sender_id       = $authId;
            $chat->receiver_id     = $receiverId;
            $chat->message         = $request->message;
            $chat->conversation_id = $conversationId;

            $chat->save();


            if ($request->hasFile('image')) {
                $image      = $request->file('image');
                $extension  = $image->getClientOriginalExtension();
                $image_name = time() . '_img.' . $extension;
                $path       = 'uploads/chat_images/';
                $image->move($path, $image_name);

                $chatImage          = new ChatGallery();
                $chatImage->chat_id = $chat->id;
                $chatImage->image   = $path . $image_name;
                $chatImage->save();
            }


            $chat->load('chatimage');


            if ($chat->chatimage) {
                $chat->image_url = asset($chat->chatimage->image);
                $chat->image_id  = $chat->chatimage->id;
            } else {
                $chat->image_url = null;
                $chat->image_id  = null;
            }


            broadcast(new ChatEvent($chat))->toOthers();




            return $this->success($chat, 'Message sent successfully');
        } catch (\Exception $e) {
            Log::error('Chat send error: ' . $e->getMessage());
            return $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * GET CONVERSATION BY CONVERSATION_ID
     */
    public function getConversation(Request $request, $conversation_id)
    {
        try {
            $user = Auth::guard('api')->user();
            $authId = $user->id;

            $activeChildId = $request->header('active-child-id') ?? $request->get('active_child_id');

            if ($activeChildId) {
                $childProfile = AthleteProfiles::find($activeChildId);
                if ($childProfile && $childProfile->user_id) {
                    $authId = $childProfile->user_id;
                }
            }

            // Mark messages as read when opening conversation
            Chat::where('conversation_id', $conversation_id)
                ->where('receiver_id', $authId)
                ->where('is_read', 0)
                ->update(['is_read' => 1]);

            $messages = Chat::where('conversation_id', $conversation_id)
                ->orderBy('created_at', 'asc')
                ->with('chatimage')
                ->get();

            $messages->each(function ($msg) {

                // IMAGE FROM chatimage TABLE
                if ($msg->chatimage) {
                    $msg->image_url = asset($msg->chatimage->image);
                    $msg->image_id  = $msg->chatimage->id;
                } else {
                    $msg->image_url = null;
                    $msg->image_id  = null;
                }
            });

            return $this->success($messages, 'Conversation retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get conversation error: ' . $e->getMessage());
            return $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * DELETE CHAT (FULL MESSAGE)
     */
    public function chatdelete($id)
    {
        try {
            $chat = Chat::find($id);

            if (!$chat) {
                return $this->error('Chat not found', [], 404);
            }

            $chat->delete();

            return $this->success($chat, 'Chat deleted successfully');
        } catch (\Exception $e) {
            Log::error('Chat delete error: ' . $e->getMessage());
            return $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * DELETE ONLY IMAGE
     */
    public function chatImageDelete($id)
    {
        try {
            $chatImage = ChatGallery::find($id);

            if (!$chatImage) {
                return $this->error('Chat image not found', [], 404);
            }

            $chatImage->delete();

            return $this->success($chatImage, 'Chat Image deleted successfully');
        } catch (\Exception $e) {
            Log::error('Chat image delete error: ' . $e->getMessage());
            return $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * CHAT LIST
     */
    public function getchatlist(Request $request)
    {
        $user = Auth::guard('api')->user();
        $authId = $user->id;

        $activeChildId = $request->header('active-child-id') ?? $request->get('active_child_id');

        // If parent is viewing child profile, they should see child's chats
        // Note: Currently AthleteProfiles links to a user_id which is the athlete themselves or a child user
        // If the system uses a separate user account for each child, this works.
        // If child is just a profile under parent's user_id, we need to find the user_id associated with that child.

        $chatUserId = $authId;
        if ($activeChildId) {
            $childProfile = AthleteProfiles::find($activeChildId);
            if ($childProfile && $childProfile->user_id) {
                $chatUserId = $childProfile->user_id;
            }
        }

        $chats = Chat::where(function ($query) use ($chatUserId) {
            $query->where('sender_id', $chatUserId)
                ->orWhere('receiver_id', $chatUserId);
        })
            ->select('conversation_id', DB::raw('MAX(created_at) as latest_time'))
            ->groupBy('conversation_id')
            ->orderByDesc('latest_time')
            ->get()
            ->map(function ($chat) use ($chatUserId) {

                $lastMessage = Chat::where('conversation_id', $chat->conversation_id)
                    ->latest()
                    ->with('chatimage')
                    ->first();

                $otherUserId = $lastMessage->sender_id == $chatUserId
                    ? $lastMessage->receiver_id
                    : $lastMessage->sender_id;

                $otherUser = User::with(['coachProfile', 'athleteProfile', 'club'])->find($otherUserId);

                $me = User::with(['coachProfile', 'athleteProfile', 'club'])->find($chatUserId);

                $unreadCount = Chat::where('conversation_id', $chat->conversation_id)
                    ->where('receiver_id', $chatUserId)
                    ->where('is_read', 0)
                    ->count();

                return [
                    'chat_id'         => $lastMessage->id ?? '',
                    'conversation_id' => $chat->conversation_id,
                    'latest_time'     => Carbon::parse($chat->latest_time)
                        ->timezone(config('app.timezone'))
                        ->format('Y-m-d H:i:s'),
                    'message'         => $lastMessage->message ?? '',
                    'user_name'       => $otherUser->name ?? '',
                    'receiver_id'     => $otherUserId,
                    'user_image'      => $this->resolveUserImage($otherUser),
                    'my_image'        => $this->resolveUserImage($me),
                    'chat_image'      => $lastMessage && $lastMessage->chatimage
                        ? asset($lastMessage->chatimage->image)
                        : '',
                    'image_id'        => $lastMessage->chatimage->id ?? '',
                    'unread_count'    => $unreadCount,
                ];
            });

        return $this->success($chats, 'Chats list retrieved successfully', 200);
    }

    /**
     * Resolve user image from user table or profile tables
     */
    private function resolveUserImage($user)
    {
        if (!$user) {
            return '';
        }

        // 1. Try User table profile image
        if ($user->profile_image) {
            return asset($user->profile_image);
        }

        // 2. Try role based profile images
        if ($user->role === 'coach' && $user->coachProfile) {
            return $user->coachProfile->coach_profile_pic ? asset($user->coachProfile->coach_profile_pic) : '';
        }

        if ($user->role === 'player' && $user->athleteProfile) {
            return $user->athleteProfile->image ? asset($user->athleteProfile->image) : '';
        }

        if ($user->role === 'club' && $user->club) {
            return $user->club->club_logo ? asset($user->club->club_logo) : '';
        }

        return '';
    }

    /**
     * MARK AS READ
     */
    public function markAsRead($conversation_id)
    {
        try {
            $authId = Auth::id();

            $messages = Chat::where('conversation_id', $conversation_id)
                ->where('receiver_id', $authId)
                ->where('is_read', 0)
                ->get();

            Chat::where('conversation_id', $conversation_id)
                ->where('receiver_id', $authId)
                ->where('is_read', 0)
                ->update(['is_read' => 1]);

            $messages->each(function ($msg) {
                $msg->is_read = 1;
            });

            return $this->success($messages, 'Messages marked as read');
        } catch (\Exception $e) {
            Log::error('Chat markAsRead error: ' . $e->getMessage());
            return $this->error($e->getMessage(), [], 500);
        }
    }
}
