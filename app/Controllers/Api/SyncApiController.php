<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class SyncApiController extends BaseController
{
    /**
     * POST /api/v1/sync/bulk-records
     * Processes offline JSON queue records from mobile app (Temuan, Tindak Lanjut, Status Updates)
     */
    public function bulkRecords(): ResponseInterface
    {
        try {
            $json = $this->request->getJSON(true) ?? [];
            $records = $json['records'] ?? [];

            if (empty($records)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Paket antrean transaksi kosong.'
                ]);
            }

            $db = Database::connect();
            $processed = [];

            foreach ($records as $item) {
                $queueId   = $item['queue_id'] ?? null;
                $tableName = $item['table_name'] ?? 'temuan';
                $action    = strtoupper($item['action'] ?? 'CREATE');
                $localId   = $item['local_id'] ?? null;
                $data      = $item['data'] ?? [];

                if (empty($data)) {
                    continue;
                }

                $serverId = null;
                $status   = 'SUCCESS';
                $errorMsg = null;

                $db->transStart();

                if ($tableName === 'temuan') {
                    if ($action === 'CREATE') {
                        // Generate unique nomor temuan if not provided
                        if (empty($data['nomor_temuan'])) {
                            $data['nomor_temuan'] = 'TMN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                        }
                        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
                        $data['updated_at'] = date('Y-m-d H:i:s');

                        $db->table('temuan')->insert($data);
                        $serverId = $db->insertID();
                    } elseif ($action === 'UPDATE' && !empty($data['id'])) {
                        $serverId = $data['id'];
                        unset($data['id']);
                        $data['updated_at'] = date('Y-m-d H:i:s');

                        $db->table('temuan')->where('id', $serverId)->update($data);
                    }
                } elseif ($tableName === 'tindak_lanjut') {
                    $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
                    $db->table('tindak_lanjut')->insert($data);
                    $serverId = $db->insertID();
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    $status   = 'FAILED';
                    $errorMsg = 'Gagal menyimpan transaksi ke database server.';
                }

                $processed[] = [
                    'queue_id'  => $queueId,
                    'local_id'  => $localId,
                    'server_id' => $serverId,
                    'status'    => $status,
                    'error'     => $errorMsg
                ];
            }

            return $this->response->setStatusCode(200)->setJSON([
                'success'   => true,
                'message'   => 'Paket sinkronisasi berhasil diproses.',
                'processed' => $processed
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'SyncApiController Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Terjadi kesalahan sistem saat pemrosesan sinkronisasi.',
                'detail'  => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/v1/sync/upload-photo
     * Receives compressed offline photos (max 1920px JPEG 75%) uploaded from mobile queue
     */
    public function uploadPhoto(): ResponseInterface
    {
        try {
            $serverId  = $this->request->getPost('server_id');
            $tableName = $this->request->getPost('table_name') ?? 'temuan';
            $file      = $this->request->getFile('photo');

            if (!$file || !$file->isValid()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Berkas foto tidak valid atau tidak ditemukan.'
                ]);
            }

            $uploadDir = FCPATH . 'foto/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $newName = 'offline_sync_' . date('Ymd_His') . '_' . $file->getRandomName();
            $file->move($uploadDir, $newName);

            if ($tableName === 'temuan' && !empty($serverId)) {
                $db = Database::connect();
                $row = $db->table('temuan')->where('id', $serverId)->get()->getRowArray();
                if ($row) {
                    $existingPhotos = [];
                    if (!empty($row['foto'])) {
                        $decoded = json_decode($row['foto'], true);
                        $existingPhotos = is_array($decoded) ? $decoded : explode(',', (string)($row['foto'] ?? ''));
                    }
                    $existingPhotos[] = $newName;

                    $db->table('temuan')
                        ->where('id', $serverId)
                        ->update([
                            'foto'       => json_encode(array_values(array_filter($existingPhotos))),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                }
            }

            return $this->response->setStatusCode(200)->setJSON([
                'success'   => true,
                'file_name' => $newName,
                'file_url'  => base_url('foto/' . $newName),
                'message'   => 'Foto offline berhasil disinkronisasi ke server.'
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'SyncApiController Upload Photo Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Gagal mengunggah foto ke server.',
                'detail'  => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/sync
     * Returns server metadata & master tables for mobile offline DB seed
     */
    public function getSyncMeta(): ResponseInterface
    {
        try {
            $db = Database::connect();
            $ulps       = $db->table('ulps')->get()->getResultArray();
            $penyulangs = $db->table('penyulang')->where('status', 'AKTIF')->get()->getResultArray();
            $sections   = $db->table('sections')->where('status', 'AKTIF')->get()->getResultArray();

            return $this->response->setStatusCode(200)->setJSON([
                'success'     => true,
                'server_time' => date('Y-m-d H:i:s'),
                'master'      => [
                    'ulps'       => $ulps,
                    'penyulangs' => $penyulangs,
                    'sections'   => $sections
                ],
                'message'     => 'Metadata master offline sync berhasil dimuat.'
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
