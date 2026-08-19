<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Border;

$objPHPPowerPoint = new PhpPresentation();

// Slide 1: Enterprise Cover Slide
$coverSlide = $objPHPPowerPoint->getActiveSlide();

// 1. Large Teal Left Container Box
$bgShape = $coverSlide->createRichTextShape()
    ->setHeight(360)
    ->setWidth(560)
    ->setOffsetX(0)
    ->setOffsetY(60);
$bgShape->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->setStartColor(new Color('FF009BAA')); // PLN Corporate Teal

$pTitle = $bgShape->getActiveParagraph();
$pTitle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$r1 = $bgShape->createTextRun("LAPORAN TEMUAN\nEMERGENCY\n\n");
$r1->getFont()->setBold(true)->setSize(36)->setColor(new Color('FFFFFFFF'));

$r2 = $bgShape->createTextRun("UP3 SIDOARJO\nULP SIDOARJO KOTA");
$r2->getFont()->setBold(true)->setSize(24)->setColor(new Color('FFFFFFFF'));

// Save output
$tempFile = __DIR__ . '/test_styled_output.pptx';
$oWriter = IOFactory::createWriter($objPHPPowerPoint, 'PowerPoint2007');
$oWriter->save($tempFile);

echo "STYLED_PPT_SUCCESS: " . filesize($tempFile) . " bytes\n";
