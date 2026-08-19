<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Alignment;

$objPHPPowerPoint = new PhpPresentation();
$currentSlide = $objPHPPowerPoint->getActiveSlide();

$shape = $currentSlide->createRichTextShape()
  ->setHeight(100)
  ->setWidth(600)
  ->setOffsetX(100)
  ->setOffsetY(100);
$shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$textRun = $shape->createTextRun('LAPORAN TEMUAN EMERGENCY');
$textRun->getFont()->setBold(true)->setSize(28)->setColor(new Color('FF008080'));

echo "PPT_ENGINE_READY\n";
