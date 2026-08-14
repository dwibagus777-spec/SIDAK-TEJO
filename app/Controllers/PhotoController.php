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
                    ->setHeader('Cache-Control', 'public, max-age=86400')
                    ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT')
                    ->setBody(file_get_contents($path));
            }
        }

        return $this->response->setStatusCode(404);
    }
}
