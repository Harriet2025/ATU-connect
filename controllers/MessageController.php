<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Helpers\FileUploader;
use Helpers\NotificationDispatcher;
use Models\Conversation;
use Models\Message;
use Models\User;

// =========================================================
// MESSAGE CONTROLLER — conversations + direct messaging
// =========================================================
class MessageController extends BaseController
{
    public function conversations(Request $request): void
    {
        $this->requireAuth();
        Response::success(Conversation::forUser(Auth::id(), 'inbox'));
    }

    // Pending message requests — unsolicited DMs kept out of the inbox
    // until the recipient accepts them (spam gate).
    public function requests(Request $request): void
    {
        $this->requireAuth();
        Response::success(Conversation::forUser(Auth::id(), 'requests'));
    }

    // { inbox_unread, requests } — drives the nav badge + requests tab count.
    public function unreadCount(Request $request): void
    {
        $this->requireAuth();
        Response::success(Conversation::counts(Auth::id()));
    }

    public function show(Request $request, $conversationId): void
    {
        $this->requireAuth();

        if (!Conversation::isParticipant((int) $conversationId, Auth::id())) {
            Response::error('Forbidden. You are not part of this conversation.', HTTP_FORBIDDEN);
        }

        $messages = Message::forConversation((int) $conversationId);
        // Opening a thread marks it read for this user (clears its badge).
        Conversation::markRead((int) $conversationId, Auth::id());
        Response::success($messages);
    }

    // Accept a pending request → moves the thread into the inbox.
    public function accept(Request $request, $conversationId): void
    {
        $this->requireAuth();
        if (!Conversation::isParticipant((int) $conversationId, Auth::id())) {
            Response::error('Forbidden. You are not part of this conversation.', HTTP_FORBIDDEN);
        }
        Conversation::setParticipantStatus((int) $conversationId, Auth::id(), 'accepted');
        Response::success(null, 'Message request accepted.');
    }

    // Decline a pending request → deletes the thread (cascades to its
    // messages), so a rejected/spam DM disappears for both sides.
    public function decline(Request $request, $conversationId): void
    {
        $this->requireAuth();
        if (Conversation::participantStatus((int) $conversationId, Auth::id()) !== 'pending') {
            Response::error('This conversation is not a pending request.', HTTP_VALIDATION_ERROR);
        }
        Conversation::delete((int) $conversationId);
        Response::success(null, 'Message request declined.');
    }

    public function store(Request $request): void
    {
        $this->requireAuth();

        $data = $request->all();
        $conversationId = $data['conversation_id'] ?? null;
        $toUserId = $data['to_user_id'] ?? null;
        $body = $request->input('body');

        if (!$conversationId && !$toUserId) {
            Response::error('Either conversation_id or to_user_id is required.', HTTP_VALIDATION_ERROR);
        }
        if (!$body && !$request->file('attachment')) {
            Response::error('Message body or attachment is required.', HTTP_VALIDATION_ERROR);
        }

        if (!$conversationId) {
            $conversationId = Conversation::findOrCreateDirect(Auth::id(), (int) $toUserId);
        } elseif (!Conversation::isParticipant((int) $conversationId, Auth::id())) {
            Response::error('Forbidden. You are not part of this conversation.', HTTP_FORBIDDEN);
        }

        $attachmentPath = null;
        $file = $request->file('attachment');
        if ($file) {
            try {
                $attachmentPath = FileUploader::upload($file, 'messages', APP_CONFIG['uploads']['allowed_document'], APP_CONFIG['uploads']['max_size_mb']);
            } catch (\RuntimeException $e) {
                Response::error($e->getMessage(), HTTP_VALIDATION_ERROR);
            }
        }

        $messageId = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => Auth::id(),
            'body'            => $body,
            'attachment_path' => $attachmentPath,
        ]);

        // Sending (or replying) implies engagement, so the sender's own side
        // is always in their inbox — e.g. replying to a request accepts it.
        Conversation::setParticipantStatus((int) $conversationId, Auth::id(), 'accepted');

        $sender = User::find(Auth::id());
        $preview = $body ? mb_substr($body, 0, 120) : 'Sent an attachment.';
        foreach (Conversation::otherParticipantIds($conversationId, Auth::id()) as $recipientId) {
            NotificationDispatcher::notify(
                $recipientId,
                'message',
                'New message from ' . ($sender['full_name'] ?? 'someone'),
                $preview,
                'medium',
                $conversationId
            );
        }

        Response::success(Message::find($messageId), 'Message sent.', HTTP_CREATED);
    }
}
