<?php
namespace Helpers;

// =========================================================
// FILE UPLOADER — validates and stores uploaded files under UPLOAD_PATH
// =========================================================
class FileUploader
{
    public static function upload(array $file, string $subdir, array $allowedExtensions, int $maxSizeMb): string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed. Please try again.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new \RuntimeException('File type not allowed. Allowed: ' . implode(', ', $allowedExtensions));
        }

        $maxBytes = $maxSizeMb * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new \RuntimeException("File exceeds the maximum size of {$maxSizeMb}MB.");
        }

        $targetDir = rtrim(UPLOAD_PATH, '/') . '/' . trim($subdir, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $destination = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }

        return trim($subdir, '/') . '/' . $filename;
    }
}
