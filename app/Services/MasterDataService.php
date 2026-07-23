<?php

namespace App\Services;

use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;

class MasterDataService
{
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private SectionRepository $sectionRepository;

    public function __construct()
    {
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository = new SectionRepository();
    }

    // --- ULP Operations ---
    
    public function createUlp(array $data): bool
    {
        $res = $this->ulpRepository->insert($data);
        if ($res) {
            log_activity('CREATE_ULP', 'Menambahkan ULP baru: ' . $data['nama_ulp']);
            return true;
        }
        return false;
    }

    public function updateUlp(int $id, array $data): bool
    {
        $res = $this->ulpRepository->update($id, $data);
        if ($res) {
            log_activity('UPDATE_ULP', 'Mengubah data ULP ID: ' . $id);
            return true;
        }
        return false;
    }

    public function deleteUlp(int $id): bool
    {
        $ulp = $this->ulpRepository->find($id);
        if ($ulp) {
            $res = $this->ulpRepository->delete($id);
            log_activity('DELETE_ULP', 'Menghapus ULP: ' . $ulp['nama_ulp']);
            return $res;
        }
        return false;
    }

    // --- Penyulang Operations ---

    public function createPenyulang(array $data): bool
    {
        $res = $this->penyulangRepository->insert($data);
        if ($res) {
            log_activity('CREATE_PENYULANG', 'Menambahkan Penyulang baru: ' . $data['nama_penyulang']);
            return true;
        }
        return false;
    }

    public function updatePenyulang(int $id, array $data): bool
    {
        $res = $this->penyulangRepository->update($id, $data);
        if ($res) {
            log_activity('UPDATE_PENYULANG', 'Mengubah data Penyulang ID: ' . $id);
            return true;
        }
        return false;
    }

    public function deletePenyulang(int $id): bool
    {
        $penyulang = $this->penyulangRepository->find($id);
        if ($penyulang) {
            $res = $this->penyulangRepository->delete($id);
            log_activity('DELETE_PENYULANG', 'Menghapus Penyulang: ' . $penyulang['nama_penyulang']);
            return $res;
        }
        return false;
    }

    // --- Section Operations ---

    public function createSection(array $data): bool
    {
        $res = $this->sectionRepository->insert($data);
        if ($res) {
            log_activity('CREATE_SECTION', 'Menambahkan Section baru: ' . $data['nama_section']);
            return true;
        }
        return false;
    }

    public function updateSection(int $id, array $data): bool
    {
        $res = $this->sectionRepository->update($id, $data);
        if ($res) {
            log_activity('UPDATE_SECTION', 'Mengubah data Section ID: ' . $id);
            return true;
        }
        return false;
    }

    public function deleteSection(int $id): bool
    {
        $section = $this->sectionRepository->find($id);
        if ($section) {
            $res = $this->sectionRepository->delete($id);
            log_activity('DELETE_SECTION', 'Menghapus Section: ' . $section['nama_section']);
            return $res;
        }
        return false;
    }
}
