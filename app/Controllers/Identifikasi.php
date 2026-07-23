<?php

namespace App\Controllers;

use App\Repositories\PenyulangRepository;
use App\Repositories\TemuanRepository;
use App\Repositories\UlpRepository;

class Identifikasi extends BaseController
{
    private PenyulangRepository $penyulangRepository;
    private TemuanRepository $temuanRepository;
    private UlpRepository $ulpRepository;

    public function __construct()
    {
        $this->penyulangRepository = new PenyulangRepository();
        $this->temuanRepository = new TemuanRepository();
        $this->ulpRepository = new UlpRepository();
    }

    public function index()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = [];
        }

        return view('identifikasi/index', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    public function analisis()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $rules = [
            'penyulang_id'     => 'required|is_not_unique[penyulang.id]',
            'potensi_gangguan' => 'required|in_list[DGR,OCR,OCRDGR]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->to(site_url('identifikasi'))->with('error', 'Silakan pilih Penyulang dan Jenis Gangguan.');
        }

        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $potensiGangguan = $this->request->getPost('potensi_gangguan');

        $penyulang = $this->penyulangRepository->find($penyulangId);
        if (!$penyulang) {
            return redirect()->to(site_url('identifikasi'))->with('error', 'Penyulang tidak ditemukan.');
        }

        // Batasi ULP
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null && (int)$userUlpId !== (int)$penyulang['ulp_id']) {
            return redirect()->to(site_url('identifikasi'))->with('error', 'Anda tidak memiliki hak akses untuk menganalisis penyulang ini.');
        }

        $scoping = get_user_role_scoping();

        // Query temuan pencocokan penyulang & potensi_gangguan
        $temuanList = $this->temuanRepository->getIdentifikasiGangguan($penyulangId, $potensiGangguan, $scoping['jenis_temuan']);
        
        // Ranking section penyebab gangguan
        $sectionRanking = $this->temuanRepository->getRankingSectionsForIdentifikasi($penyulangId, $potensiGangguan, $scoping['jenis_temuan']);

        // Siapkan data grafik section (Chart.js)
        $chartLabels = [];
        $chartValues = [];
        foreach ($sectionRanking as $rank) {
            $chartLabels[] = $rank['nama_section'];
            $chartValues[] = (int)$rank['total_temuan'];
        }

        // Log audit
        log_activity('IDENTIFIKASI_GANGGUAN', 'Analisis gangguan penyulang: ' . $penyulang['nama_penyulang'] . ' - Gangguan: ' . $potensiGangguan);

        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));
        
        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp((int)$penyulang['ulp_id']);
        }

        return view('identifikasi/index', [
            'ulps'            => $ulps,
            'penyulangs'      => $penyulangs,
            'selectedUlp'     => $penyulang['ulp_id'],
            'selectedPenyulang' => $penyulangId,
            'selectedGangguan'  => $potensiGangguan,
            'penyulangName'   => $penyulang['nama_penyulang'],
            'temuanList'      => $temuanList,
            'sectionRanking'  => $sectionRanking,
            'chartLabels'     => json_encode($chartLabels),
            'chartValues'     => json_encode($chartValues),
            'isAnalyzed'      => true,
            'isRestricted'    => $isRestricted
        ]);
    }

    public function exportPdf()
    {
        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $potensiGangguan = $this->request->getPost('potensi_gangguan');

        $penyulang = $this->penyulangRepository->find($penyulangId);
        if (!$penyulang) return "Penyulang tidak ditemukan.";

        $temuanList = $this->temuanRepository->getIdentifikasiGangguan($penyulangId, $potensiGangguan);
        $sectionRanking = $this->temuanRepository->getRankingSectionsForIdentifikasi($penyulangId, $potensiGangguan);

        log_activity('EXPORT_IDENTIFIKASI_PDF', 'Mencetak PDF analisis gangguan penyulang: ' . $penyulang['nama_penyulang']);

        return view('identifikasi/print_pdf', [
            'penyulang' => $penyulang,
            'potensiGangguan' => $potensiGangguan,
            'temuanList' => $temuanList,
            'sectionRanking' => $sectionRanking
        ]);
    }

    public function exportCsv()
    {
        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $potensiGangguan = $this->request->getPost('potensi_gangguan');

        $penyulang = $this->penyulangRepository->find($penyulangId);
        if (!$penyulang) return "Penyulang tidak ditemukan.";

        $temuanList = $this->temuanRepository->getIdentifikasiGangguan($penyulangId, $potensiGangguan);

        log_activity('EXPORT_IDENTIFIKASI_CSV', 'Mengekspor CSV analisis gangguan penyulang: ' . $penyulang['nama_penyulang']);

        $filename = 'Analisis_Gangguan_' . str_replace(' ', '_', $penyulang['nama_penyulang']) . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Nomor Temuan', 'Tanggal', 'Section', 'Jenis', 'Pelaksana', 'Prioritas', 'Konduktor', 'Material', 'Detail Kerusakan', 'Alamat', 'Status']);

        foreach ($temuanList as $row) {
            fputcsv($output, [
                $row['nomor_temuan'],
                date('d-m-Y', strtotime($row['tanggal_temuan'])),
                $row['nama_section'],
                $row['jenis_temuan'],
                $row['pelaksana'],
                $row['prioritas'],
                $row['konduktor'],
                $row['material'],
                $row['detail_temuan'],
                $row['alamat'],
                $row['status']
            ]);
        }

        fclose($output);
        exit;
    }

    public function exportExcel()
    {
        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $potensiGangguan = $this->request->getPost('potensi_gangguan');

        $penyulang = $this->penyulangRepository->find($penyulangId);
        if (!$penyulang) return "Penyulang tidak ditemukan.";

        $temuanList = $this->temuanRepository->getIdentifikasiGangguan($penyulangId, $potensiGangguan);

        log_activity('EXPORT_IDENTIFIKASI_EXCEL', 'Mengekspor Excel analisis gangguan penyulang: ' . $penyulang['nama_penyulang']);

        $filename = 'Analisis_Gangguan_' . str_replace(' ', '_', $penyulang['nama_penyulang']) . '_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        $headers = ['Nomor Temuan', 'Tanggal', 'Section', 'Jenis', 'Pelaksana', 'Prioritas', 'Konduktor', 'Material', 'Detail Kerusakan', 'Alamat', 'Status'];
        fwrite($output, implode("\t", $headers) . "\n");

        foreach ($temuanList as $row) {
            $line = [
                $row['nomor_temuan'],
                date('d-m-Y', strtotime($row['tanggal_temuan'])),
                $row['nama_section'],
                $row['jenis_temuan'],
                $row['pelaksana'],
                $row['prioritas'],
                $row['konduktor'],
                str_replace(["\r", "\n", "\t"], " ", $row['material']),
                str_replace(["\r", "\n", "\t"], " ", $row['detail_temuan']),
                str_replace(["\r", "\n", "\t"], " ", $row['alamat']),
                $row['status']
            ];
            fwrite($output, implode("\t", $line) . "\n");
        }

        fclose($output);
        exit;
    }

    public function exportPpt()
    {
        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $potensiGangguan = $this->request->getPost('potensi_gangguan');

        $penyulang = $this->penyulangRepository->find($penyulangId);
        if (!$penyulang) return "Penyulang tidak ditemukan.";

        $temuanList = $this->temuanRepository->getIdentifikasiGangguan($penyulangId, $potensiGangguan);
        $sectionRanking = $this->temuanRepository->getRankingSectionsForIdentifikasi($penyulangId, $potensiGangguan);

        log_activity('EXPORT_IDENTIFIKASI_PPTX', 'Mengekspor PPTX analisis gangguan penyulang: ' . $penyulang['nama_penyulang']);

        $objPHPPowerPoint = new \PhpOffice\PhpPresentation\PhpPresentation();
        $objPHPPowerPoint->getLayout()->setDocumentLayout(\PhpOffice\PhpPresentation\DocumentLayout::LAYOUT_SCREEN_16X9);
        $objPHPPowerPoint->getDocumentProperties()
            ->setCreator('SIDAK TEJO')
            ->setTitle('Laporan Analisis Gangguan ' . $penyulang['nama_penyulang']);

        $objPHPPowerPoint->removeSlideByIndex(0);

        // --- SLIDE 1: COVER ---
        $currentSlide = $objPHPPowerPoint->createSlide();
        $shape = $currentSlide->createRichTextShape()
            ->setHeight(140)
            ->setWidth(900)
            ->setOffsetX(30)
            ->setOffsetY(150);
        $shape->createParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
        $textRun1 = $shape->createTextRun("PT PLN (Persero)\n");
        $textRun1->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF555555'));

        $shape->createParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
        $textRun2 = $shape->createTextRun('LAPORAN ANALISIS POTENSI GANGGUAN PENYULANG');
        $textRun2->getFont()->setBold(true)->setSize(22)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF0284C7'));

        $shape->createParagraph()->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
        $textRun3 = $shape->createTextRun("\nPenyulang: " . $penyulang['nama_penyulang'] . " | Gangguan: " . $potensiGangguan . "\nTanggal Analisis: " . date('d-m-Y'));
        $textRun3->getFont()->setSize(13)->setItalic(true);

        // --- SLIDE 2: PERINGKAT SECTION PEMICU ---
        $slide2 = $objPHPPowerPoint->createSlide();
        $titleShape = $slide2->createRichTextShape()
            ->setHeight(50)
            ->setWidth(900)
            ->setOffsetX(30)
            ->setOffsetY(20);
        $textRun = $titleShape->createTextRun('Peringkat Section Pemicu Gangguan');
        $textRun->getFont()->setBold(true)->setSize(24)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF0284C7'));

        $rankingShape = $slide2->createRichTextShape()
            ->setHeight(400)
            ->setWidth(850)
            ->setOffsetX(30)
            ->setOffsetY(90);
        
        $rankingText = "Kemungkinan Section penyebab gangguan (dari tertinggi ke terendah):\n\n";
        $no = 1;
        foreach ($sectionRanking as $rank) {
            $rankingText .= $no++ . ". Section " . $rank['nama_section'] . " - " . $rank['total_temuan'] . " temuan pemicu\n";
        }
        if (empty($sectionRanking)) {
            $rankingText .= "Tidak ada data section pemicu.";
        }
        $textRunRank = $rankingShape->createTextRun($rankingText);
        $textRunRank->getFont()->setSize(14)->setBold(true);

        // --- SLIDE 3+: RINCIAN TEMUAN (1 Temuan per Slide) ---
        foreach ($temuanList as $row) {
            $slide = $objPHPPowerPoint->createSlide();
            
            // Header Slide
            $titleShape = $slide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(900)
                ->setOffsetX(30)
                ->setOffsetY(20);
            $textRun = $titleShape->createTextRun('Temuan: ' . $row['nomor_temuan']);
            $textRun->getFont()->setBold(true)->setSize(22)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF0284C7'));

            // Left Column: Text Info
            $leftShape = $slide->createRichTextShape()
                ->setHeight(360)
                ->setWidth(450)
                ->setOffsetX(30)
                ->setOffsetY(80);
            
            $infoText = "Penyulang: " . $row['nama_penyulang'] . "\n"
                      . "Section: " . $row['nama_section'] . "\n"
                      . "Tanggal Temuan: " . date('d-m-Y', strtotime($row['tanggal_temuan'])) . "\n"
                      . "Prioritas: " . $row['prioritas'] . "\n"
                      . "Konduktor: " . $row['konduktor'] . "\n"
                      . "Alamat: " . $row['alamat'] . "\n\n"
                      . "Detail Kerusakan:\n" . $row['detail_temuan'] . "\n\n"
                      . "Material Dibutuhkan:\n" . $row['material'];

            $textRunInfo = $leftShape->createTextRun($infoText);
            $textRunInfo->getFont()->setSize(11);

            // Right Column: Image
            $photos = json_decode($row['foto'], true) ?: [];
            if (!empty($photos)) {
                $imagePath = FCPATH . $row['foto_path'] . $photos[0];
                if (file_exists($imagePath)) {
                    $shape = $slide->createDrawingShape();
                    $shape->setName('Foto Temuan')
                          ->setPath($imagePath)
                          ->setHeight(300)
                          ->setOffsetX(510)
                          ->setOffsetY(90);
                }
            } else {
                $noPhotoShape = $slide->createRichTextShape()
                    ->setHeight(50)
                    ->setWidth(300)
                    ->setOffsetX(510)
                    ->setOffsetY(200);
                $textRunNoPhoto = $noPhotoShape->createTextRun('(Tidak ada foto temuan)');
                $textRunNoPhoto->getFont()->setSize(14)->setItalic(true)->setColor(new \PhpOffice\PhpPresentation\Style\Color('FF888888'));
            }
        }

        if (ob_get_length()) ob_end_clean();
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment;filename="Analisis_Gangguan_' . str_replace(' ', '_', $penyulang['nama_penyulang']) . '_' . date('YmdHis') . '.pptx"');
        header('Cache-Control: max-age=0');

        $oWriterPPTX = \PhpOffice\PhpPresentation\IOFactory::createWriter($objPHPPowerPoint, 'PowerPoint2007');
        $oWriterPPTX->save('php://output');
        exit;
    }
}
