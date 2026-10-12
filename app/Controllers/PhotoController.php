<?php

namespace App\Controllers;

class PhotoController extends BaseController
{
    public function show(string $filename = '')
    {
        $relativePath = ltrim(rawurldecode($filename), '/');
        $relativePath = str_replace('\\', '/', $relativePath);

        $parts = array_filter(
            explode('/', $relativePath),
            static fn ($part) =>
                $part !== '' &&
                $part !== '.' &&
                $part !== '..' &&
                !str_contains($part, "\0")
        );

        $relativePath = implode('/', $parts);

        if ($relativePath === '') {
            return $this->response->setStatusCode(404);
        }

        // Authorization: check if user has access to this photo
        // FAIL-CLOSED: deny access if helper unavailable or throws
        $hasAccess = false;
        if (function_exists('check_photo_access')) {
            try {
                $hasAccess = check_photo_access($relativePath);
            } catch (\Throwable $e) {
                log_message('error', '[PhotoController] check_photo_access error: ' . $e->getMessage());
                $hasAccess = false;
            }
        } else {
            log_message('error', '[PhotoController] check_photo_access helper not available - denying access');
            $hasAccess = false;
        }

        if (!$hasAccess) {
            log_message('warning', '[PhotoController] Akses ditolak ke foto: ' . $relativePath . ' oleh user: ' . (session()->get('user_id') ?? 'unknown'));
            return $this->response->setStatusCode(403)->setBody('Forbidden: Access denied to this photo');
        }

        $baseStorageDir = defined('SIDAK_STORAGE_PATH') ? rtrim(SIDAK_STORAGE_PATH, '/\\') . '/' : WRITEPATH . 'uploads/foto/';

        $candidatePaths = [
            $baseStorageDir . $relativePath,
            WRITEPATH . 'uploads/foto/' . $relativePath,
            FCPATH . 'foto/' . $relativePath,
            FCPATH . 'uploads/' . $relativePath,
        ];

        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                $mime = mime_content_type($path) ?: 'image/jpeg';
                $mtime = filemtime($path) ?: time();

                return $this->response
                    ->setHeader('Content-Type', $mime)
                    ->setHeader('Cache-Control', 'private, no-store, max-age=0')
                    ->setHeader('Pragma', 'no-cache')
                    ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT')
                    ->setBody(file_get_contents($path));
            }
        }

        return $this->response->setStatusCode(404);
    }
}