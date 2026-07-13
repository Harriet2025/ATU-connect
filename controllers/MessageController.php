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
        Response::success(Conversation::forUser(Auth::id()));
    }

    public function show(Request $request, $conversationId): void
    {
        $this->requireAuth();

        if (!Conversation::isParticipant((int) $conversationId, Auth::id())) {
            Response::error('Forbidden. You are not part of this conversation.', HTTP_FORBIDDEN);
        }

        Response::success(Message::forConversation((int) $conversationId));
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
