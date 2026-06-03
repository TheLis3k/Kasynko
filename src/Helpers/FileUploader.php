<?php

namespace App\Helpers;

class FileUploader
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'application/pdf' => 'pdf',
    ];

    private int    $maxSize;
    private string $uploadDir;

    public function __construct(string $category = 'avatars')
    {
        $cfg             = require dirname(__DIR__, 2) . '/config/config.php';
        $this->maxSize   = $cfg['app']['max_upload'];
        $this->uploadDir = $cfg['app']['upload_dir'] . '/' . $category;

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0750, true);
        }
    }

    /**
     * Returns the relative path (relative to upload_dir root) on success.
     * Throws \RuntimeException on failure.
     */
    public function store(array $fileInput, int $ownerId): string
    {
        if ($fileInput['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error code: ' . $fileInput['error']);
        }

        if ($fileInput['size'] > $this->maxSize) {
            throw new \RuntimeException(t('validation.required', ['field' => 'file']) . ' (5 MB max)');
        }

        $mime = mime_content_type($fileInput['tmp_name']);
        if (!array_key_exists($mime, self::ALLOWED_MIME)) {
            throw new \RuntimeException('Niedozwolony typ pliku: ' . $mime);
        }

        $ext      = self::ALLOWED_MIME[$mime];
        $filename = $ownerId . '_' . uniqid() . '.' . $ext;
        $dest     = $this->uploadDir . '/' . $filename;

        if (!move_uploaded_file($fileInput['tmp_name'], $dest)) {
            throw new \RuntimeException('Nie udało się zapisać pliku.');
        }

        $category = basename($this->uploadDir);
        return $category . '/' . $filename;
    }

    public static function delete(string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }
        $cfg  = require dirname(__DIR__, 2) . '/config/config.php';
        $full = $cfg['app']['upload_dir'] . '/' . $relativePath;
        if (file_exists($full)) {
            unlink($full);
        }
    }
}
