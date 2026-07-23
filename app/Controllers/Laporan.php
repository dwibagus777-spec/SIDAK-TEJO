<?php

namespace App\Controllers;

use App\Repositories\TemuanRepository;
use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;

class Laporan extends BaseController
{
    private TemuanRepository $temuanRepository;
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private SectionRepository $sectionRepository;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository = new SectionRepository();
    }

    private function parseGarduName(string $detail): string
    {
        if (preg_match('/Gardu:\s*([^.\n]+)/i', $detail, $matches)) {
            return trim($matches[1]);
        }
        return 'Gardu';
    }

    // ==========================================
    // LAPORAN TEMUAN
    // ==========================================

    public function index()
    {
        return $this->temuan();
    }

    public function temuan()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        return view('laporan/index', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    private function getFiltersFromRequest(): array
    {
        return [
            'tanggal_awal'     => $this->request->getPost('tanggal_awal'),
            'tanggal_akhir'    => $this->request->getPost('tanggal_akhir'),
            'ulp_id'           => $this->request->getPost('ulp_id'),
            'penyulang_id'     => $this->request->getPost('penyulang_id'),
            'section_id'       => $this->request->getPost('section_id'),
            'pelaksana'        => $this->request->getPost('pelaksana'),
            'prioritas'        => $this->request->getPost('prioritas'),
            'jenis_temuan'     => $this->request->getPost('jenis_temuan'),
            'potensi_gangguan' => $this->request->getPost('potensi_gangguan'),
            'status'           => $this->request->getPost('status'),
        ];
    }

    public function preview()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $filters = $this->getFiltersFromRequest();
        $data = $this->temuanRepository->getFilteredTemuan($filters, $ulpIdFilter);

        log_activity('GENERATE_REPORT_PREVIEW', 'Membuka preview laporan temuan.');

        return view('laporan/preview', [
            'data' => $data,
            'filters' => $filters
        ]);
    }

    public function print()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $filters = $this->getFiltersFromRequest();
        $data = $this->temuanRepository->getFilteredTemuan($filters, $ulpIdFilter);

        log_activity('PRINT_REPORT', 'Mencetak laporan temuan.');

        return view('laporan/print', [
            'data' => $data,
            'filters' => $filters
        ]);
    }

    public function excel()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $filters = $this->getFiltersFromRequest();
        $data = $this->temuanRepository->getFilteredTemuan($filters, $ulpIdFilter);

        log_activity('EXPORT_EXCEL_REPORT', 'Mengekspor laporan temuan ke Excel.');

        $filename = 'Laporan_Sidak_Tejo_' . date('Ymd_His') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        
        $headers = [
            'Nomor Temuan', 'ULP', 'Penyulang', 'Section', 'Jenis Temuan', 'Pelaksana', 
            'Prioritas', 'Potensi Gangguan', 'Konduktor', 'NOGA', 'Material', 
            'Detail Temuan', 'Alamat', 'Latitude', 'Longitude', 'Tanggal Temuan', 
            'Status', 'Tanggal Selesai', 'Catatan Tindak Lanjut'
        ];
        fwrite($output, implode("\t", $headers) . "\n");

        foreach ($data as $row) {
            $line = [
                $row['nomor_temuan'],
                $row['nama_ulp'],
                $row['nama_penyulang'],
                $row['nama_section'],
                $row['jenis_temuan'],
                $row['pelaksana'],
                $row['prioritas'],
                $row['potensi_gangguan'],
                $row['konduktor'],
                $row['noga'] ?: '-',
                str_replace(["\r", "\n", "\t"], " ", $row['material']),
                str_replace(["\r", "\n", "\t"], " ", $row['detail_temuan']),
                str_replace(["\r", "\n", "\t"], " ", $row['alamat']),
                $row['latitude'],
                $row['longitude'],
                $row['tanggal_temuan'],
                $row['status'],
                $row['tanggal_selesai'] ?: '-',
                str_replace(["\r", "\n", "\t"], " ", $row['catatan_tindak_lanjut'] ?: '-')
            ];
            fwrite($output, implode("\t", $line) . "\n");
        }

        fclose($output);
        exit;
    }

    public function csv()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $filters = $this->getFiltersFromRequest();
        $data = $this->temuanRepository->getFilteredTemuan($filters, $ulpIdFilter);

        log_activity('EXPORT_CSV_REPORT', 'Mengekspor laporan temuan ke CSV.');

        $filename = 'Laporan_Sidak_Tejo_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        
        fputcsv($output, [
            'Nomor Temuan', 'ULP', 'Penyulang', 'Section', 'Jenis Temuan', 'Pelaksana', 
            'Prioritas', 'Potensi Gangguan', 'Konduktor', 'NOGA', 'Material', 
            'Detail Temuan', 'Alamat', 'Latitude', 'Longitude', 'Tanggal Temuan', 
            'Status', 'Tanggal Selesai', 'Catatan Tindak Lanjut'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['nomor_temuan'],
                $row['nama_ulp'],
                $row['nama_penyulang'],
                $row['nama_section'],
                $row['jenis_temuan'],
                $row['pelaksana'],
                $row['prioritas'],
                $row['potensi_gangguan'],
                $row['konduktor'],
                $row['noga'] ?: '-',
                $row['material'],
                $row['detail_temuan'],
                $row['alamat'],
                $row['latitude'],
                $row['longitude'],
                $row['tanggal_temuan'],
                $row['status'],
                $row['tanggal_selesai'] ?: '-',
                $row['catatan_tindak_lanjut'] ?: '-'
            ]);
        }

        fclose($output);
        exit;
    }

    // ==========================================
    // LAPORAN EVIDEN
    // ==========================================

    public function eviden()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        return view('laporan/eviden', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    public function ajaxEvidenData()
    {
        $idPenyulang = (int)$this->request->getPost('id_penyulang');
        $jenis = $this->request->getPost('jenis');
        $tglAwal = $this->request->getPost('tgl_awal');
        $tglAkhir = $this->request->getPost('tgl_akhir');

        $db = \Config\Database::connect();
        if ($jenis === 'KUBIKEL') {
            $builder = $db->table('tb_eviden_kubikel k');
            $builder->select('k.id_kubikel as id, k.nama_gardu, k.tgl_input, s.nama_section');
            $builder->join('sections s', 'k.id_section = s.id', 'left');
            $builder->where('k.id_penyulang', $idPenyulang);
            if (!empty($tglAwal)) $builder->where('k.tgl_input >=', $tglAwal);
            if (!empty($tglAkhir)) $builder->where('k.tgl_input <=', $tglAkhir);
            $builder->orderBy('k.tgl_input', 'DESC');
        } elseif ($jenis === 'NAMEPLATE') {
            $builder = $db->table('temuan t');
            $builder->select('t.id, t.detail_temuan, t.tanggal_temuan as tgl_input, s.nama_section');
            $builder->join('sections s', 't.section_id = s.id', 'left');
            $builder->where('t.pelaksana', 'HAR GARDU');
            $builder->groupStart()
                ->like('t.detail_temuan', 'nameplate')
                ->orLike('t.detail_temuan', 'nemplate')
            ->groupEnd();
            $builder->where('t.penyulang_id', $idPenyulang);
            $builder->where('t.deleted_at', null);
            if (!empty($tglAwal)) $builder->where('t.tanggal_temuan >=', $tglAwal);
            if (!empty($tglAkhir)) $builder->where('t.tanggal_temuan <=', $tglAkhir);
            $builder->orderBy('t.tanggal_temuan', 'DESC');
        } else {
            $builder = $db->table('tb_eviden_trafo t');
            $builder->select('t.id_trafo as id, t.nama_gardu, t.tgl_input, s.nama_section');
            $builder->join('sections s', 't.id_section = s.id', 'left');
            $builder->where('t.id_penyulang', $idPenyulang);
            if (!empty($tglAwal)) $builder->where('t.tgl_input >=', $tglAwal);
            if (!empty($tglAkhir)) $builder->where('t.tgl_input <=', $tglAkhir);
            $builder->orderBy('t.tgl_input', 'DESC');
        }

        $dataList = $builder->get()->getResultArray();
        
        if ($jenis === 'NAMEPLATE') {
            foreach ($dataList as &$item) {
                $item['nama_gardu'] = $this->parseGarduName($item['detail_temuan']);
            }
        }
        
        return view('laporan/ajax_eviden_data', ['dataList' => $dataList]);
    }

    public function exportEvidenPdf()
    {
        $jenis = $this->request->getPost('jenis_eviden');
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return "Pilih data gardu terlebih dahulu.";
        }

        $db = \Config\Database::connect();
        if ($jenis === 'KUBIKEL') {
            $builder = $db->table('tb_eviden_kubikel k');
            $builder->select('k.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 'k.id_penyulang = p.id', 'left');
            $builder->join('sections s', 'k.id_section = s.id', 'left');
            $builder->whereIn('k.id_kubikel', $selectedIds);
            $dataList = $builder->get()->getResultArray();
            
            $fotoModel = new \App\Models\FotoEvidenModel();
            foreach ($dataList as &$item) {
                $item['fotos'] = $fotoModel->where('id_parent', $item['id_kubikel'])->where('kategori', 'KUBIKEL')->findAll();
            }
        } elseif ($jenis === 'NAMEPLATE') {
            $builder = $db->table('temuan t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
            $builder->join('sections s', 't.section_id = s.id', 'left');
            $builder->whereIn('t.id', $selectedIds);
            $dataList = $builder->get()->getResultArray();
            
            foreach ($dataList as &$item) {
                $item['nama_gardu'] = $this->parseGarduName($item['detail_temuan']);
                $item['tgl_input'] = $item['tanggal_temuan'];
                $item['keterangan'] = $item['detail_temuan'];
                
                $photos = json_decode($item['foto'], true) ?: [];
                $item['fotos'] = [];
                foreach ($photos as $photo) {
                    $item['fotos'][] = [
                        'nama_file' => $photo,
                        'jenis_foto' => 'EVIDEN NAMEPLATE GARDU'
                    ];
                }
            }
        } else {
            $builder = $db->table('tb_eviden_trafo t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.id_penyulang = p.id', 'left');
            $builder->join('sections s', 't.id_section = s.id', 'left');
            $builder->whereIn('t.id_trafo', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            $fotoModel = new \App\Models\FotoEvidenModel();
            foreach ($dataList as &$item) {
                $item['fotos'] = $fotoModel->where('id_parent', $item['id_trafo'])->where('kategori', 'TRAFO')->findAll();
            }
        }

        log_activity('PRINT_EVIDEN_REPORT', 'Mencetak laporan PDF eviden ' . $jenis);

        return view('laporan/print_eviden', [
            'dataList' => $dataList,
            'jenis' => $jenis
        ]);
    }

    public function exportEvidenCsv()
    {
        $jenis = $this->request->getPost('jenis_eviden');
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return "Pilih data gardu terlebih dahulu.";
        }

        $db = \Config\Database::connect();
        if ($jenis === 'KUBIKEL') {
            $builder = $db->table('tb_eviden_kubikel k');
            $builder->select('k.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 'k.id_penyulang = p.id', 'left');
            $builder->join('sections s', 'k.id_section = s.id', 'left');
            $builder->whereIn('k.id_kubikel', $selectedIds);
            $dataList = $builder->get()->getResultArray();
        } elseif ($jenis === 'NAMEPLATE') {
            $builder = $db->table('temuan t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
            $builder->join('sections s', 't.section_id = s.id', 'left');
            $builder->whereIn('t.id', $selectedIds);
            $dataList = $builder->get()->getResultArray();
            foreach ($dataList as &$item) {
                $item['nama_gardu'] = $this->parseGarduName($item['detail_temuan']);
                $item['tgl_input'] = $item['tanggal_temuan'];
                $item['keterangan'] = $item['detail_temuan'];
            }
        } else {
            $builder = $db->table('tb_eviden_trafo t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.id_penyulang = p.id', 'left');
            $builder->join('sections s', 't.id_section = s.id', 'left');
            $builder->whereIn('t.id_trafo', $selectedIds);
            $dataList = $builder->get()->getResultArray();
        }

        log_activity('EXPORT_EVIDEN_CSV', 'Mengekspor laporan eviden ke CSV.');

        $filename = 'Laporan_Eviden_' . $jenis . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        
        if ($jenis === 'KUBIKEL') {
            fputcsv($output, ['No', 'Penyulang', 'Section', 'Nama Gardu', 'ID Pelanggan', 'Tanggal Input', 'Keterangan']);
            $no = 1;
            foreach ($dataList as $row) {
                fputcsv($output, [
                    $no++,
                    $row['nama_penyulang'],
                    $row['nama_section'],
                    $row['nama_gardu'],
                    $row['id_pel'],
                    $row['tgl_input'],
                    $row['keterangan']
                ]);
            }
        } elseif ($jenis === 'NAMEPLATE') {
            fputcsv($output, ['No', 'Penyulang', 'Section', 'Nama Gardu', 'Tanggal Input', 'Keterangan']);
            $no = 1;
            foreach ($dataList as $row) {
                fputcsv($output, [
                    $no++,
                    $row['nama_penyulang'],
                    $row['nama_section'],
                    $row['nama_gardu'],
                    $row['tgl_input'],
                    $row['keterangan']
                ]);
            }
        } else {
            fputcsv($output, ['No', 'Penyulang', 'Section', 'Nama Gardu', 'Tanggal Input', 'Keterangan']);
            $no = 1;
            foreach ($dataList as $row) {
                fputcsv($output, [
                    $no++,
                    $row['nama_penyulang'],
                    $row['nama_section'],
                    $row['nama_gardu'],
                    $row['tgl_input'],
                    $row['keterangan']
                ]);
            }
        }
        
        fclose($output);
        exit;
    }

    public function exportEvidenExcel()
    {
        return $this->exportEvidenCsv();
    }

    public function exportEvidenPpt()
    {
        $jenis = $this->request->getPost('jenis_eviden');
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return "Pilih data gardu terlebih dahulu.";
        }

        $db = \Config\Database::connect();
        if ($jenis === 'KUBIKEL') {
            $builder = $db->table('tb_eviden_kubikel k');
            $builder->select('k.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 'k.id_penyulang = p.id', 'left');
            $builder->join('sections s', 'k.id_section = s.id', 'left');
            $builder->whereIn('k.id_kubikel', $selectedIds);
            $dataList = $builder->get()->getResultArray();
            
            $fotoModel = new \App\Models\FotoEvidenModel();
            foreach ($dataList as &$item) {
                $item['fotos'] = $fotoModel->where('id_parent', $item['id_kubikel'])->where('kategori', 'KUBIKEL')->findAll();
            }
        } elseif ($jenis === 'NAMEPLATE') {
            $builder = $db->table('temuan t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
            $builder->join('sections s', 't.section_id = s.id', 'left');
            $builder->whereIn('t.id', $selectedIds);
            $dataList = $builder->get()->getResultArray();
            
            foreach ($dataList as &$item) {
                $item['nama_gardu'] = $this->parseGarduName($item['detail_temuan']);
                $item['tgl_input'] = $item['tanggal_temuan'];
                $item['keterangan'] = $item['detail_temuan'];
                
                $photos = json_decode($item['foto'], true) ?: [];
                $item['fotos'] = [];
                foreach ($photos as $photo) {
                    $item['fotos'][] = [
                        'nama_file' => $photo,
                        'jenis_foto' => 'EVIDEN NAMEPLATE GARDU'
                    ];
                }
            }
        } else {
            $builder = $db->table('tb_eviden_trafo t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.id_penyulang = p.id', 'left');
            $builder->join('sections s', 't.id_section = s.id', 'left');
            $builder->whereIn('t.id_trafo', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            $fotoModel = new \App\Models\FotoEvidenModel();
            foreach ($dataList as &$item) {
                $item['fotos'] = $fotoModel->where('id_parent', $item['id_trafo'])->where('kategori', 'TRAFO')->findAll();
            }
        }

        log_activity('EXPORT_EVIDEN_PPTX', 'Mengekspor laporan eviden ke PPTX.');

        // Instansiasi Presentasi PPTX
        $objPHPPowerPoint = new \PhpOffice\PhpPresentation\PhpPresentation();
        $objPHPPowerPoint->getLayout()->setDocumentLayout(\PhpOffice\PhpPresentation\DocumentLayout::LAYOUT_SCREEN_16X9);
        $objPHPPowerPoint->getDocumentProperties()
            ->setCreator('SIDAK TEJO')
            ->setTitle('Laporan Eviden ' . $jenis);

        $objPHPPowerPoint->removeSlideByIndex(0);

        // --- SLIDE JUDUL ---
        $currentSlide = $objPHPPowerPoint->createSlide();
        $shape = $currentSlide->createRichTextShape()
            ->setHeight(120)
            ->setWidth(900)
            ->setOffsetX(30)
            ->setOffsetY(180);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
        $textRun = $shape->createTextRun('PT PLN (PERSERO) UID JAWA TIMUR');
        $textRun->getFont()->setBold(true)->setSize(24);
        
        $shape->createParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
        $textRun2 = $shape->createTextRun('LAPORAN EVIDEN PEMELIHARAAN ' . $jenis);
        $textRun2->getFont()->setBold(true)->setSize(20)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF1F497D'));

        $shape->createParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
        $textRun3 = $shape->createTextRun('Tanggal Cetak: ' . date('d-m-Y'));
        $textRun3->getFont()->setSize(14)->setItalic(true);

        // --- SLIDE KONTEN (1 Slide per Gardu) ---
        foreach ($dataList as $row) {
            $slide = $objPHPPowerPoint->createSlide();
            
            // Title Gardu
            $titleShape = $slide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(900)
                ->setOffsetX(30)
                ->setOffsetY(20);
            $textRun = $titleShape->createTextRun('Gardu: ' . $row['nama_gardu']);
            $textRun->getFont()->setBold(true)->setSize(24)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF0057A0'));

            // Info Detail
            $infoShape = $slide->createRichTextShape()
                ->setHeight(80)
                ->setWidth(900)
                ->setOffsetX(30)
                ->setOffsetY(70);
            $textRun = $infoShape->createTextRun("Penyulang: " . $row['nama_penyulang'] . " | Section: " . $row['nama_section'] . " | Tanggal: " . date("d-m-Y", strtotime($row['tgl_input'])));
            $textRun->getFont()->setSize(12);
            $infoShape->createParagraph();
            $textRun = $infoShape->createTextRun("Keterangan: " . $row['keterangan']);
            $textRun->getFont()->setSize(12)->setItalic(true);

            // Tampilkan foto-foto (maksimal 4 foto per slide dengan grid 2x2)
            $fotos = array_slice($row['fotos'], 0, 4);
            $idx = 0;
            foreach ($fotos as $foto) {
                $imagePath = FCPATH . 'foto/' . $foto['nama_file'];
                if (file_exists($imagePath)) {
                    if ($idx === 0) { $x = 30; $y = 160; }
                    elseif ($idx === 1) { $x = 480; $y = 160; }
                    elseif ($idx === 2) { $x = 30; $y = 370; }
                    elseif ($idx === 3) { $x = 480; $y = 370; }

                    $shape = $slide->createDrawingShape();
                    $shape->setName($foto['jenis_foto'])
                          ->setPath($imagePath)
                          ->setHeight(170)
                          ->setOffsetX($x)
                          ->setOffsetY($y);

                    // Label nama foto
                    $labelShape = $slide->createRichTextShape()
                        ->setHeight(30)
                        ->setWidth(400)
                        ->setOffsetX($x)
                        ->setOffsetY($y - 25);
                    $textRun = $labelShape->createTextRun($foto['jenis_foto']);
                    $textRun->getFont()->setSize(10)->setBold(true);
                }
                $idx++;
            }
        }

        if (ob_get_length()) ob_end_clean();
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment;filename="Laporan_Eviden_' . $jenis . '_' . date('YmdHis') . '.pptx"');
        header('Cache-Control: max-age=0');

        $oWriterPPTX = \PhpOffice\PhpPresentation\IOFactory::createWriter($objPHPPowerPoint, 'PowerPoint2007');
        $oWriterPPTX->save('php://output');
        exit;
    }

    // ==========================================
    // LAPORAN MANAGEMENT TRAFO
    // ==========================================

    public function management()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        return view('laporan/management', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    public function ajaxManagementData()
    {
        $idPenyulang = (int)$this->request->getPost('id_penyulang');
        $tglAwal = $this->request->getPost('tgl_awal');
        $tglAkhir = $this->request->getPost('tgl_akhir');

        $db = \Config\Database::connect();
        $builder = $db->table('tb_management_trafo m');
        $builder->select('m.id_management as id, m.nama_gardu, m.tgl_input, s.nama_section');
        $builder->join('sections s', 'm.id_section = s.id', 'left');
        $builder->where('m.id_penyulang', $idPenyulang);
        if (!empty($tglAwal)) $builder->where('m.tgl_input >=', $tglAwal);
        if (!empty($tglAkhir)) $builder->where('m.tgl_input <=', $tglAkhir);
        $builder->orderBy('m.tgl_input', 'DESC');

        $dataList = $builder->get()->getResultArray();
        return view('laporan/ajax_management_data', ['dataList' => $dataList]);
    }

    public function exportManagementPdf()
    {
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return "Pilih data gardu terlebih dahulu.";
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tb_management_trafo m');
        $builder->select('m.*, p.nama_penyulang, s.nama_section');
        $builder->join('penyulang p', 'm.id_penyulang = p.id', 'left');
        $builder->join('sections s', 'm.id_section = s.id', 'left');
        $builder->whereIn('m.id_management', $selectedIds);
        $dataList = $builder->get()->getResultArray();

        log_activity('PRINT_MANAGEMENT_REPORT', 'Mencetak laporan PDF management trafo.');

        return view('laporan/print_management', [
            'dataList' => $dataList
        ]);
    }

    public function exportManagementCsv()
    {
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return "Pilih data gardu terlebih dahulu.";
        }

        $db = \Config\Database::connect();
        $builder = $db->table('tb_management_trafo m');
        $builder->select('m.*, p.nama_penyulang, s.nama_section');
        $builder->join('penyulang p', 'm.id_penyulang = p.id', 'left');
        $builder->join('sections s', 'm.id_section = s.id', 'left');
        $builder->whereIn('m.id_management', $selectedIds);
        $dataList = $builder->get()->getResultArray();

        log_activity('EXPORT_MANAGEMENT_CSV', 'Mengekspor laporan management trafo ke CSV.');

        $filename = 'Laporan_Management_Trafo_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        
        fputcsv($output, ['No', 'Penyulang', 'Section', 'Nama Gardu', 'Tanggal Input', 'Keterangan']);
        $no = 1;
        foreach ($dataList as $row) {
            fputcsv($output, [
                $no++,
                $row['nama_penyulang'],
                $row['nama_section'],
                $row['nama_gardu'],
                $row['tgl_input'],
                $row['keterangan']
            ]);
        }
        
        fclose($output);
        exit;
    }

    public function exportManagementExcel()
    {
        return $this->exportManagementCsv();
    }
}
