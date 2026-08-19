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
            'shift'            => $this->request->getPost('shift'),
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

        $total = count($data);
        $selesai = 0;
        $emergency = 0;
        foreach ($data as $r) {
            if (strtoupper($r['status']) === 'SELESAI') $selesai++;
            if (strtoupper($r['prioritas']) === 'EMERGENCY') $emergency++;
        }

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="utf-8"><style>';
        echo 'body { font-family: Calibri, sans-serif; font-size: 11pt; }';
        echo '.title { font-size: 16pt; font-weight: bold; color: #0284c7; }';
        echo '.sub-title { font-size: 10pt; color: #475569; }';
        echo 'table { border-collapse: collapse; width: 100%; }';
        echo 'th { background-color: #0284c7; color: #ffffff; font-weight: bold; border: 1px solid #000000; text-align: center; padding: 6px; }';
        echo 'td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: middle; }';
        echo '.pri-EMERGENCY { background-color: #fee2e2; color: #dc2626; font-weight: bold; }';
        echo '.pri-HIGH { background-color: #fff7ed; color: #c2410c; font-weight: bold; }';
        echo '.pri-MEDIUM { background-color: #eff6ff; color: #1d4ed8; }';
        echo '.pri-LOW { background-color: #f0fdf4; color: #16a34a; }';
        echo '.st-SELESAI { background-color: #dcfce7; color: #15803d; font-weight: bold; }';
        echo '.summary-row { background-color: #f1f5f9; font-weight: bold; }';
        echo '</style></head><body>';

        echo '<table>';
        echo '<tr><td colspan="19" class="title">PT PLN (PERSERO) &mdash; LAPORAN INSPEKSI TEMUAN (SIDAK TEJO)</td></tr>';
        echo '<tr><td colspan="19" class="sub-title">Tanggal Ekspor: ' . date('d-m-Y H:i:s') . ' WIB | User: ' . esc($session->get('user_name')) . ' | Total Data: ' . $total . ' (Selesai: ' . $selesai . ', Emergency: ' . $emergency . ')</td></tr>';
        echo '<tr><td colspan="19"></td></tr>';

        echo '<thead><tr>';
        $headers = [
            'No', 'Nomor Temuan', 'ULP', 'Penyulang', 'Section', 'Jenis Temuan', 'Pelaksana', 
            'Prioritas', 'Potensi Gangguan', 'Konduktor', 'NOGA', 'Material', 
            'Detail Temuan', 'Alamat', 'Latitude', 'Longitude', 'Tanggal Temuan', 
            'Status', 'Tanggal Selesai'
        ];
        foreach ($headers as $h) {
            echo '<th>' . $h . '</th>';
        }
        echo '</tr></thead>';

        echo '<tbody>';
        $no = 1;
        foreach ($data as $row) {
            $priClass = 'pri-' . strtoupper($row['prioritas']);
            $stClass = strtoupper($row['status']) === 'SELESAI' ? 'st-SELESAI' : '';
            echo '<tr>';
            echo '<td align="center">' . $no++ . '</td>';
            echo '<td>' . esc($row['nomor_temuan']) . '</td>';
            echo '<td>' . esc($row['nama_ulp']) . '</td>';
            echo '<td>' . esc($row['nama_penyulang']) . '</td>';
            echo '<td>' . esc($row['nama_section']) . '</td>';
            echo '<td>' . esc($row['jenis_temuan']) . '</td>';
            echo '<td>' . esc($row['pelaksana']) . '</td>';
            echo '<td class="' . $priClass . '" align="center">' . esc($row['prioritas']) . '</td>';
            echo '<td>' . esc($row['potensi_gangguan']) . '</td>';
            echo '<td>' . esc($row['konduktor']) . '</td>';
            echo '<td align="center">' . esc($row['noga'] ?: '-') . '</td>';
            echo '<td>' . esc($row['material']) . '</td>';
            echo '<td>' . esc($row['detail_temuan']) . '</td>';
            echo '<td>' . esc($row['alamat']) . '</td>';
            echo '<td>' . esc($row['latitude']) . '</td>';
            echo '<td>' . esc($row['longitude']) . '</td>';
            echo '<td align="center">' . date('d-m-Y', strtotime($row['tanggal_temuan'])) . '</td>';
            echo '<td class="' . $stClass . '" align="center">' . esc($row['status']) . '</td>';
            echo '<td align="center">' . ($row['tanggal_selesai'] ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-') . '</td>';
            echo '</tr>';
        }

        echo '<tr class="summary-row">';
        echo '<td colspan="7" align="right">TOTAL RINGKASAN:</td>';
        echo '<td align="center">Emergency: ' . $emergency . '</td>';
        echo '<td colspan="9"></td>';
        echo '<td align="center">Selesai: ' . $selesai . ' / ' . $total . '</td>';
        echo '<td></td>';
        echo '</tr>';

        echo '</tbody></table></body></html>';
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

    private function resolveLocalPhotoPath(?string $photoName): ?string
    {
        if (empty($photoName)) return null;
        $cleanName = basename(rawurldecode(trim($photoName)));
        if (empty($cleanName) || $cleanName === '.' || $cleanName === '..') return null;

        $persistentDir = defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : WRITEPATH . 'uploads/foto/';
        $candidatePaths = [
            $persistentDir . $cleanName,
            WRITEPATH . 'uploads/foto/' . $cleanName,
            WRITEPATH . 'uploads/' . $cleanName,
            FCPATH . 'foto/' . $cleanName,
            FCPATH . 'uploads/' . $cleanName,
            FCPATH . 'public/uploads/' . $cleanName,
            FCPATH . 'public/foto/' . $cleanName,
        ];

        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path) && filesize($path) > 0) {
                return $path;
            }
        }
        return null;
    }

    private function getTemuanPhotoPaths(array $row): array
    {
        $paths = [];
        
        // 1. Direct fields: foto_sebelum, foto_proses, foto_sesudah
        foreach (['foto_sebelum', 'foto_proses', 'foto_sesudah'] as $field) {
            if (!empty($row[$field])) {
                $resolved = $this->resolveLocalPhotoPath((string)$row[$field]);
                if ($resolved && !in_array($resolved, $paths)) {
                    $paths[] = $resolved;
                }
            }
        }

        // 2. Parsed JSON or comma-separated field: 'foto'
        if (count($paths) < 2 && !empty($row['foto'])) {
            $fotoRaw = trim((string)$row['foto']);
            $photosList = [];
            if (str_starts_with($fotoRaw, '[') && str_ends_with($fotoRaw, ']')) {
                $decoded = json_decode($fotoRaw, true);
                if (is_array($decoded)) {
                    $photosList = $decoded;
                }
            } else {
                $photosList = explode(',', $fotoRaw);
            }

            foreach ($photosList as $pItem) {
                $resolved = $this->resolveLocalPhotoPath((string)$pItem);
                if ($resolved && !in_array($resolved, $paths)) {
                    $paths[] = $resolved;
                    if (count($paths) >= 2) break;
                }
            }
        }

        return array_slice($paths, 0, 2);
    }

    public function pptx()
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

        log_activity('EXPORT_PPTX_REPORT', 'Mengekspor laporan temuan ke PowerPoint (.pptx).');

        $objPHPPowerPoint = new \PhpOffice\PhpPresentation\PhpPresentation();
        
        // ── SLIDE 1: COVER SLIDE (Exact Visual Replica of Master Reference Slide 1) ──
        $coverSlide = $objPHPPowerPoint->getActiveSlide();

        // 1. Corporate Teal Main Hero Box (Cover Card)
        $heroCard = $coverSlide->createRichTextShape()
            ->setHeight(370)
            ->setWidth(560)
            ->setOffsetX(0)
            ->setOffsetY(60);
        $heroCard->getFill()
            ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
            ->setStartColor(new \PhpOffice\PhpPresentation\Style\Color('FF009BAA')); // PLN Corporate Teal

        $pCover = $heroCard->getActiveParagraph();
        $pCover->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_LEFT);

        $runCoverTitle = $heroCard->createTextRun("\n LAPORAN TEMUAN\n EMERGENCY\n\n");
        $runCoverTitle->getFont()->setBold(true)->setSize(34)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FFFFFFFF'));

        $ulpNameText = 'UP3 SIDOARJO';
        if (!empty($filters['ulp_id'])) {
            $ulpObj = $this->ulpRepository->find($filters['ulp_id']);
            if ($ulpObj) {
                $ulpNameText .= "\n" . strtoupper($ulpObj['nama_ulp']);
            }
        } else {
            $ulpNameText .= "\nULP SIDOARJO KOTA";
        }

        $runCoverSub = $heroCard->createTextRun(" " . $ulpNameText);
        $runCoverSub->getFont()->setBold(true)->setSize(22)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FFFFFFFF'));

        // Top Right Corporate PLN Logo
        $plnLogoPath = FCPATH . 'dist/img/logo_pln.png';
        if (!file_exists($plnLogoPath)) {
            $plnLogoPath = FCPATH . 'assets/img/logo_sidak.png';
        }

        if (file_exists($plnLogoPath)) {
            $logoShape = new \PhpOffice\PhpPresentation\Shape\Drawing\File();
            $logoShape->setName('PLN Logo')
                ->setPath($plnLogoPath)
                ->setHeight(75)
                ->setOffsetX(760)
                ->setOffsetY(40);
            $coverSlide->addShape($logoShape);
        }

        // Bottom Left ISO SMAP Text / Badge
        $isoShape = $coverSlide->createRichTextShape()
            ->setHeight(40)
            ->setWidth(480)
            ->setOffsetX(40)
            ->setOffsetY(475);
        $isoText = $isoShape->createTextRun("ISO 37001 Sistem Manajemen Anti Penyuapan (SMAP)");
        $isoText->getFont()->setSize(10)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF64748B'));

        // Bottom Right Decorative Footer Bar & Link
        $coverFooterBar = $coverSlide->createRichTextShape()
            ->setHeight(30)
            ->setWidth(380)
            ->setOffsetX(480)
            ->setOffsetY(475);
        $coverFooterBar->getFill()
            ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
            ->setStartColor(new \PhpOffice\PhpPresentation\Style\Color('FF009BAA'));

        $webShape = $coverSlide->createRichTextShape()
            ->setHeight(30)
            ->setWidth(180)
            ->setOffsetX(760)
            ->setOffsetY(480);
        $webText = $webShape->createTextRun("www.pln.co.id");
        $webText->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF008080'));

        // ── SLIDES 2 TO N: ITEM SLIDES (Exact Visual Replica of Master Reference Slide 2) ──
        foreach ($data as $row) {
            $slide = $objPHPPowerPoint->createSlide();

            // Header Title Line
            $headerShape = $slide->createRichTextShape()
                ->setHeight(45)
                ->setWidth(740)
                ->setOffsetX(40)
                ->setOffsetY(20);
            
            $headerTitleStr = "LIST TO EMERGENCY " . strtoupper($row['nama_ulp'] ?? 'SIDOARJO KOTA') . " P . " . strtoupper($row['nama_penyulang'] ?? 'SURABAYA');
            $headerRun = $headerShape->createTextRun($headerTitleStr);
            $headerRun->getFont()->setBold(true)->setSize(17)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF0F172A'));

            // Multi-Color 4-Strip Header Accent Line (Teal, Cyan, Yellow, Dark Blue)
            $colors = ['FF008080', 'FF00A3B4', 'FFFCBF49', 'FF003637'];
            $startPos = 40;
            foreach ($colors as $c) {
                $strip = $slide->createRichTextShape()
                    ->setHeight(4)
                    ->setWidth(60)
                    ->setOffsetX($startPos)
                    ->setOffsetY(60);
                $strip->getFill()
                    ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
                    ->setStartColor(new \PhpOffice\PhpPresentation\Style\Color($c));
                $startPos += 62;
            }

            // Top Right Corporate PLN Logo on item slide
            if (file_exists($plnLogoPath)) {
                $itemLogo = new \PhpOffice\PhpPresentation\Shape\Drawing\File();
                $itemLogo->setName('PLN Logo')
                    ->setPath($plnLogoPath)
                    ->setHeight(48)
                    ->setOffsetX(800)
                    ->setOffsetY(18);
                $slide->addShape($itemLogo);
            }

            // Resolve physical photo filepaths from disk
            $photoPaths = $this->getTemuanPhotoPaths($row);

            if (count($photoPaths) >= 2) {
                // Photo 1 (Left Frame)
                $drawing1 = new \PhpOffice\PhpPresentation\Shape\Drawing\File();
                $drawing1->setName('Foto 1')
                    ->setPath($photoPaths[0])
                    ->setWidth(195)
                    ->setHeight(320)
                    ->setOffsetX(40)
                    ->setOffsetY(85);
                $slide->addShape($drawing1);

                // Photo 2 (Right Frame)
                $drawing2 = new \PhpOffice\PhpPresentation\Shape\Drawing\File();
                $drawing2->setName('Foto 2')
                    ->setPath($photoPaths[1])
                    ->setWidth(195)
                    ->setHeight(320)
                    ->setOffsetX(245)
                    ->setOffsetY(85);
                $slide->addShape($drawing2);
            } elseif (count($photoPaths) === 1) {
                // Single Photo Centered on Left Half
                $drawingSingle = new \PhpOffice\PhpPresentation\Shape\Drawing\File();
                $drawingSingle->setName('Foto Temuan')
                    ->setPath($photoPaths[0])
                    ->setWidth(250)
                    ->setHeight(320)
                    ->setOffsetX(110)
                    ->setOffsetY(85);
                $slide->addShape($drawingSingle);
            } else {
                // Fallback Placeholder Box when photo is absent
                $noImgShape = $slide->createRichTextShape()
                    ->setHeight(320)
                    ->setWidth(400)
                    ->setOffsetX(40)
                    ->setOffsetY(85);
                $noImgShape->getFill()
                    ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
                    ->setStartColor(new \PhpOffice\PhpPresentation\Style\Color('FFF1F5F9'));
                $noImgShape->getActiveParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
                $noImgRun = $noImgShape->createTextRun("\n\n\n\n[ Dokumentasi Foto Lapangan ]");
                $noImgRun->getFont()->setSize(12)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF64748B'));
            }

            // Description & Metadata Box (Right Half)
            $descShape = $slide->createRichTextShape()
                ->setHeight(330)
                ->setWidth(460)
                ->setOffsetX(465)
                ->setOffsetY(85);
            
            $p = $descShape->getActiveParagraph();
            $p->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_LEFT);

            // Primary Detail Text
            $runDetail = $descShape->createTextRun(esc($row['detail_temuan']) . "\n\n");
            $runDetail->getFont()->setBold(true)->setSize(18)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF000000'));

            // Metadata Block
            $runMeta = $descShape->createTextRun(
                "Nomor Temuan: " . esc($row['nomor_temuan']) . "\n" .
                "Jenis Temuan: " . esc($row['jenis_temuan']) . " | Prioritas: " . esc($row['prioritas']) . "\n" .
                "Pelaksana: " . esc($row['pelaksana']) . "\n" .
                "Tanggal: " . date('d-m-Y', strtotime($row['tanggal_temuan'])) . " | Status: " . esc($row['status'])
            );
            $runMeta->getFont()->setSize(11)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF334155'));

            // Bottom Right Decorative Footer Strip
            $itemFooterBar = $slide->createRichTextShape()
                ->setHeight(25)
                ->setWidth(320)
                ->setOffsetX(480)
                ->setOffsetY(485);
            $itemFooterBar->getFill()
                ->setFillType(\PhpOffice\PhpPresentation\Style\Fill::FILL_SOLID)
                ->setStartColor(new \PhpOffice\PhpPresentation\Style\Color('FF00A3B4'));

            $itemWebShape = $slide->createRichTextShape()
                ->setHeight(30)
                ->setWidth(180)
                ->setOffsetX(760)
                ->setOffsetY(485);
            $itemWebRun = $itemWebShape->createTextRun("www.pln.co.id");
            $itemWebRun->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF008080'));
        }

        // Save cleanly to temp file in WRITEPATH to avoid HTTP binary stream header corruption
        $tempFilename = 'Laporan_Sidak_Tejo_' . date('Ymd_His') . '_' . uniqid() . '.pptx';
        $tempPath = WRITEPATH . 'uploads/' . $tempFilename;

        $oWriter = \PhpOffice\PhpPresentation\IOFactory::createWriter($objPHPPowerPoint, 'PowerPoint2007');
        $oWriter->save($tempPath);

        while (ob_get_level()) {
            ob_end_clean();
        }

        return $this->response->download($tempPath, null)->setFileName('Laporan_Sidak_Tejo_' . date('Ymd_His') . '.pptx');
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
                
                $photos = json_decode((string)($item['foto'] ?? ''), true) ?: [];
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
                
                $photos = json_decode((string)($item['foto'] ?? ''), true) ?: [];
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
                $candidatePaths = [
                    (defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : FCPATH . 'foto/') . $foto['nama_file'],
                    WRITEPATH . 'uploads/foto/' . $foto['nama_file'],
                    FCPATH . 'foto/' . $foto['nama_file'],
                ];
                $imagePath = null;
                foreach ($candidatePaths as $cPath) {
                    if (is_file($cPath)) {
                        $imagePath = $cPath;
                        break;
                    }
                }
                if ($imagePath) {
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
