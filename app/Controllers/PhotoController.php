<?php

namespace App\Controllers;

class PhotoController extends BaseController
{
    public function show(string $filename = '')
    {
        $cleanName = basename(trim(rawurldecode($filename)));

        if (
            $cleanName === '' ||
            $cleanName === '.' ||
            $cleanName === '..' ||
            str_contains($cleanName, "\0")
        ) {
            return $this->response->setStatusCode(404);
        }

        $candidatePaths = [
            SIDAK_STORAGE_PATH . $cleanName,
            WRITEPATH . 'uploads/foto/' . $cleanName,
            FCPATH . 'foto/' . $cleanName,
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
