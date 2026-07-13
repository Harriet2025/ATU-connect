<?php

/**
 * Validates and stores an uploaded file outside the web root.
 * Never trusts the client-supplied extension/MIME alone — sniffs the real
 * MIME type with finfo and re-derives the extension from an allow-list.
 */
class FileUpload
{
    private const MIME_MAP = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'zip'  => 'application/zip',
    ];

    /**
     * @return array{success: bool, path?: string, error?: string, size?: int, ext?: string}
     */
    public static function store(array $file, string $subDir): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed.'];
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            return ['success' => false, 'error' => 'File exceeds the maximum allowed size.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_UPLOAD_TYPES, true)) {
            return ['success' => false, 'error' => 'File type not allowed.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::MIME_MAP[$ext]) || $detectedMime !== self::MIME_MAP[$ext]) {
            return ['success' => false, 'error' => 'File content does not match its extension.'];
        }

        $targetDir = STORAGE_PATH . '/' . trim($subDir, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = $targetDir . '/' . $randomName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return ['success' => false, 'error' => 'Could not save the uploaded file.'];
        }

        return [
            'success' => true,
            'path'    => trim($subDir, '/') . '/' . $randomName,
            'size'    => $file['size'],
            'ext'     => $ext,
        ];
    }
}
