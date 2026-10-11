<?php

namespace App\Controllers;

class StorageDiag extends BaseController
{
    public function index()
    {
        // Simple token check to prevent public exposure
        $token = $this->request->getGet('token');
        if ($token !== 'sidak-diag-2026') {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        $candidates = [
            '1: FCPATH/../../sidak_storage/foto/' => rtrim(dirname(dirname(rtrim(FCPATH, '/\\'))), '/\\') . '/sidak_storage/foto/',
            '2: ROOTPATH/../sidak_storage/foto/' => rtrim(dirname(rtrim(ROOTPATH, '/\\')), '/\\') . '/sidak_storage/foto/',
            '3: /home/u532206332/sidak_storage/foto/' => '/home/u532206332/sidak_storage/foto/',
            '4: /home/u532206332/domains/sidaktejo.site/sidak_storage/foto/' => '/home/u532206332/domains/sidaktejo.site/sidak_storage/foto/',
        ];

        $results = [];
        foreach ($candidates as $label => $path) {
            $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $results[] = [
                'candidate' => $label,
                'resolved_path' => $path,
                'is_dir' => $exists,
                'is_writable' => $writable,
                'selected' => false,
            ];
        }

        // Simulate the selection logic
        $selected = null;
        foreach ($results as $i => &$r) {
            if ($r['is_dir'] && $r['is_writable']) {
                $r['selected'] = true;
                $selected = $r;
                break;
            }
        }

        // Environment info
        $env = [
            'FCPATH' => FCPATH,
            'ROOTPATH' => ROOTPATH,
            'WRITEPATH' => WRITEPATH,
            'APPPATH' => APPPATH,
            'SIDAK_STORAGE_PATH (current)' => defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : 'NOT DEFINED',
            'PHP_SAPI' => PHP_SAPI,
            'current_user' => get_current_user(),
        ];

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode([
                'candidates' => $results,
                'selected' => $selected ? $selected['resolved_path'] : 'NONE (fallback will be used)',
                'environment' => $env,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}