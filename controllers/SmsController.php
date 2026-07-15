<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Models\User;
use Helpers\SmsProvider;

// =========================================================
// SMS CONTROLLER — self-service "send me a test text" so you can
// confirm the Vynfy integration works from inside the live app.
// =========================================================
class SmsController extends BaseController
{
    // POST /api/sms/test — texts the logged-in user's own phone number.
    // Sends only to yourself, so there's no way to spam anyone else.
    public function test(Request $request): void
    {
        $this->requireAuth();

        if (!SmsProvider::isConfigured()) {
            Response::error('SMS is not configured on the server (SMS_API_KEY / SMS_SENDER_ID are not set).', HTTP_VALIDATION_ERROR);
        }

        $user = User::find(Auth::id());
        $phone = trim((string) ($user['phone'] ?? ''));
        if ($phone === '') {
            Response::error('You have no phone number on your profile. Add one first, then try again.', HTTP_VALIDATION_ERROR);
        }

        $ok = SmsProvider::send($phone, 'ATU Connect: your SMS test worked. Notifications will reach this number.');
        if (!$ok) {
            Response::error('Vynfy rejected the message. Check the server SMS log for the exact reason.', HTTP_SERVER_ERROR);
        }

        Response::success(['sent_to' => SmsProvider::formatPhone($phone)], 'Test SMS sent — check your phone.');
    }
}
