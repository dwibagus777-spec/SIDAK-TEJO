<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Alignment;

$objPHPPowerPoint = new PhpPresentation();

// Slide 1: Cover
$coverSlide = $objPHPPowerPoint->getActiveSlide();

$titleShape = $coverSlide->createRichTextShape()
    ->setHeight(180)
    ->setWidth(680)
    ->setOffsetX(40)
    ->setOffsetY(140);
$titleShape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$textRun1 = $titleShape->createTextRun("LAPORAN TEMUAN EMERGENCY\n");
$textRun1->getFont()->setBold(true)->setSize(32)->setColor(new Color('FF008080'));

$textRun2 = $titleShape->createTextRun("UP3 SIDOARJO\nULP SIDOARJO KOTA");
$textRun2->getFont()->setBold(true)->setSize(24)->setColor(new Color('FF003637'));

// Slide 2: Item Slide
$itemSlide = $objPHPPowerPoint->createSlide();
$headerShape = $itemSlide->createRichTextShape()
    ->setHeight(60)
    ->setWidth(720)
    ->setOffsetX(40)
    ->setOffsetY(25);
$headerRun = $headerShape->createTextRun("LIST TO EMERGENCY ULP SIDOARJO KOTA P . SURABAYA");
$headerRun->getFont()->setBold(true)->setSize(16)->setColor(new Color('FF0F172A'));

$descShape = $itemSlide->createRichTextShape()
    ->setHeight(330)
    ->setWidth(420)
    ->setOffsetX(495)
    ->setOffsetY(100);
$runDetail = $descShape->createTextRun("Hotspot joint konduktor phasa T 115°C di TM11 PT. Tunas baru lampung.Zona 2. P. Surabaya\n\n");
$runDetail->getFont()->setBold(true)->setSize(16)->setColor(new Color('FF000000'));

$tempFile = __DIR__ . '/test_output.pptx';
$oWriter = IOFactory::createWriter($objPHPPowerPoint, 'PowerPoint2007');
$oWriter->save($tempFile);

echo "TEST_PPTX_GENERATE_SUCCESS: " . filesize($tempFile) . " bytes\n";
