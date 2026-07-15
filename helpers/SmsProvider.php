<?php
namespace Helpers;

// =========================================================
// SMS PROVIDER — Vynfy (vynfy.com) integration
//
// Fully optional: if the API key / sender ID aren't configured via env,
// isConfigured() is false and send() is a no-op that returns false, so the
// rest of the platform keeps working exactly as before (the caller just
// logs instead). No exception ever escapes this class — a failed SMS must
// never break the request that triggered it (registration, messaging, etc).
//
// Vynfy API (https://sms.vynfy.com): POST /api/v1/send, auth via the
// X-API-Key header, JSON body { message, recipients, sender }, and a JSON
// response with a top-level "success" boolean.
//
// Env (set in Coolify / config/.env.php):
//   SMS_API_KEY       — Vynfy API key (sent as X-API-Key). Required to enable.
//   SMS_SENDER_ID     — approved sender ID, max 11 chars. Required to enable.
//   SMS_API_URL       — override endpoint (default Vynfy v1 send endpoint).
//   SMS_COUNTRY_CODE  — default country code for local numbers (default 233 = Ghana).
// =========================================================
class SmsProvider
{
    private const DEFAULT_URL = 'https://sms.vynfy.com/api/v1/send';
    private const MAX_SENDER_LEN = 11;   // Vynfy sender ID limit
    private const MAX_MESSAGE_LEN = 650; // Vynfy message limit

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '' && self::senderId() !== '';
    }

    private static function apiKey(): string    { return trim((string) getenv('SMS_API_KEY')); }
    private static function senderId(): string  { return trim((string) getenv('SMS_SENDER_ID')); }
    private static function endpoint(): string  { return trim((string) getenv('SMS_API_URL')) ?: self::DEFAULT_URL; }
    private static function countryCode(): string { return trim((string) getenv('SMS_COUNTRY_CODE')) ?: '233'; }

    // Normalise a phone number to the international format SMS gateways
    // expect (country code, no plus, no spaces). Handles the common Ghanaian
    // inputs: "0244123456" -> "233244123456", "+233 244 123456" -> "233244123456",
    // "233244123456" -> unchanged.
    public static function formatPhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === '') {
            return '';
        }
        $cc = self::countryCode();
        if (str_starts_with($digits, $cc)) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return $cc . substr($digits, 1);
        }
        return $digits;
    }

    // Returns true on an accepted send, false if not configured or on any
    // failure (which is also logged). Never throws.
    public static function send(string $to, string $message): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        $phone = self::formatPhone($to);
        if ($phone === '' || !function_exists('curl_init')) {
            return false;
        }

        $payload = json_encode([
            'message'    => mb_substr($message, 0, self::MAX_MESSAGE_LEN),
            'recipients' => [$phone],
            'sender'     => mb_substr(self::senderId(), 0, self::MAX_SENDER_LEN),
        ]);

        try {
            $ch = curl_init(self::endpoint());
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'X-API-Key: ' . self::apiKey(),
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            $decoded = is_string($response) ? json_decode($response, true) : null;
            $accepted = $curlErr === '' && $httpCode >= 200 && $httpCode < 300
                && is_array($decoded) && ($decoded['success'] ?? false) === true;

            if (!$accepted) {
                self::logFailure($phone, $httpCode, $curlErr ?: (string) $response);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            self::logFailure($phone, 0, $e->getMessage());
            return false;
        }
    }

    private static function logFailure(string $phone, int $httpCode, string $detail): void
    {
        if (!defined('LOG_PATH')) {
            return;
        }
        if (!is_dir(LOG_PATH)) {
            @mkdir(LOG_PATH, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . "] SMS FAILED to $phone (HTTP $httpCode): $detail" . PHP_EOL;
        @file_put_contents(LOG_PATH . '/sms_' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    }
}
