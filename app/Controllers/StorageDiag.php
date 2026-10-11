<?php

namespace App\Controllers;

class StorageDiag extends BaseController
{
    public function index()
    {
        // Use the same session/auth logic as RoleFilter
        $session = session();

        if (!$session->get('logged_in')) {
            return $this->response->setStatusCode(401)->setBody('Unauthorized: Login required');
        }

        $role = strtolower((string)$session->get('user_role'));
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat'])) {
            return $this->response->setStatusCode(403)->setBody('Forbidden: Admin access required');
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

        // Simulate the selection logic (matches Constants.php)
        $selected = null;
        foreach ($results as $i => &$r) {
            if ($r['is_dir'] && $r['is_writable']) {
                $r['selected'] = true;
                $selected = $r;
                break;
            }
        }

        // Only expose essential diagnostic info - no server environment details
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode([
                'candidates' => $results,
                'selected_path' => $selected ? $selected['resolved_path'] : 'NONE (fallback will be used)',
                'sidak_storage_path_constant' => defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : 'NOT DEFINED',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}