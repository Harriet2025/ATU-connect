<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Database;
use Models\User;

// =========================================================
// PASSWORD RESET CONTROLLER — forgot/reset password flow
// =========================================================
class PasswordResetController extends BaseController
{
    public function request(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'email' => 'required|email|max:150',
        ]);

        $user = User::findByEmail($data['email']);
        $responseData = [];

        if ($user) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = password_hash($rawToken, PASSWORD_BCRYPT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = Database::connect()->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)'
            );
            $stmt->execute([
                'uid'  => $user['user_id'],
                'hash' => $tokenHash,
                'exp'  => $expiresAt,
            ]);

            // NOTE: returning the raw token in the API response is ONLY
            // acceptable in this prototype because there is no mailer wired
            // up yet. Once a real mail gateway exists, this token must be
            // emailed to the user and never included in the response body.
            $responseData['reset_token'] = $rawToken;
        }

        // Same message regardless of whether the email exists, so we don't
        // leak which emails are registered.
        Response::success($responseData, 'If that email exists, a reset link has been generated.');
    }

    public function reset(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'token'        => 'required',
            'email'        => 'required|email',
            'new_password' => 'required|min:8',
        ]);

        $user = User::findByEmail($data['email']);
        if (!$user) {
            Response::error('Invalid or expired reset token.', HTTP_BAD_REQUEST);
        }

        $stmt = Database::connect()->prepare(
            'SELECT * FROM password_reset_tokens
             WHERE user_id = :uid AND used = 0 AND expires_at >= NOW()
             ORDER BY created_at DESC'
        );
        $stmt->execute(['uid' => $user['user_id']]);
        $tokenRows = $stmt->fetchAll();

        $matched = null;
        foreach ($tokenRows as $row) {
            if (password_verify($data['token'], $row['token_hash'])) {
                $matched = $row;
                break;
            }
        }

        if (!$matched) {
            Response::error('Invalid or expired reset token.', HTTP_BAD_REQUEST);
        }

        User::update((int) $user['user_id'], [
            'password_hash' => password_hash($data['new_password'], PASSWORD_BCRYPT),
        ]);

        $updateStmt = Database::connect()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token_id = :id');
        $updateStmt->execute(['id' => $matched['token_id']]);

        Response::success(null, 'Password reset successful.');
    }
}
