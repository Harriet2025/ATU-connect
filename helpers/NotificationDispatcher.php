<?php
namespace Helpers;

use Models\Notification;
use Models\User;
use Helpers\SmsProvider;

// =========================================================
// NOTIFICATION DISPATCHER — in-app row + per-channel outbound reminder
// =========================================================
class NotificationDispatcher
{
    public static function notify(int $userId, string $type, string $title, string $body, string $priority = 'low', ?int $relatedId = null): void
    {
        Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'priority'   => $priority,
            'related_id' => $relatedId,
        ]);

        $user = User::find($userId);
        if (!$user) {
            return;
        }

        if (!empty($user['notify_email'])) {
            self::sendEmail($user['email'] ?? '', $title, $body);
        }
        if (!empty($user['notify_sms'])) {
            self::sendSms($user['phone'] ?? '', $body);
        }
        if (!empty($user['notify_whatsapp'])) {
            self::sendWhatsapp($user['phone'] ?? '', $body);
        }
    }

    // TODO: replace with real gateway call (e.g. Twilio, Hubtel, WhatsApp Cloud API) — logs only for this prototype.
    private static function sendEmail(string $to, string $subject, string $body): void
    {
        if ($to === '') {
            return;
        }
        self::log('EMAIL', $to, $subject, $body);
    }

    // Sends via the configured SMS gateway (Vynfy). If SMS isn't configured
    // (no API key/sender ID in env), SmsProvider::send() returns false and we
    // fall back to logging — so the platform works normally either way.
    private static function sendSms(string $to, string $body): void
    {
        if ($to === '') {
            return;
        }
        if (SmsProvider::send($to, $body)) {
            return;
        }
        self::log('SMS', $to, null, $body);
    }

    // TODO: replace with real gateway call (e.g. Twilio, Hubtel, WhatsApp Cloud API) — logs only for this prototype.
    private static function sendWhatsapp(string $to, string $body): void
    {
        if ($to === '') {
            return;
        }
        self::log('WHATSAPP', $to, null, $body);
    }

    private static function log(string $channel, string $to, ?string $subject, string $body): void
    {
        $logDir = LOG_PATH;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $subjectPart = $subject !== null ? "\"$subject\" — " : '';
        $line = "[$timestamp] $channel to $to: {$subjectPart}$body" . PHP_EOL;

        $file = $logDir . '/notifications_' . date('Y-m-d') . '.log';
        file_put_contents($file, $line, FILE_APPEND);
    }
}
