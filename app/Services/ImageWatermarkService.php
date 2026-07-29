<?php

namespace App\Services;

class ImageWatermarkService
{
    /**
     * Apply Watermark Metadata Overlay on Uploaded Image via PHP GD
     */
    public function applyWatermark(string $fullPath, array $metadata): bool
    {
        if (!file_exists($fullPath)) {
            return false;
        }

        $imageInfo = @getimagesize($fullPath);
        if (!$imageInfo) {
            return false;
        }

        $mime = $imageInfo['mime'];
        $srcImage = match($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($fullPath),
            'image/png'               => @imagecreatefrompng($fullPath),
            'image/webp'              => @imagecreatefromwebp($fullPath),
            default                   => null
        };

        if (!$srcImage) {
            return false;
        }

        $width  = imagesx($srcImage);
        $height = imagesy($srcImage);

        // Watermark Banner Background at Bottom
        $bannerHeight = max(110, (int)($height * 0.16));
        $bannerY = $height - $bannerHeight;

        $darkOverlay = imagecolorallocatealpha($srcImage, 15, 23, 42, 35); // Dark semi-transparent slate
        imagefilledrectangle($srcImage, 0, $bannerY, $width, $height, $darkOverlay);

        // Colors
        $yellow = imagecolorallocate($srcImage, 245, 158, 11);
        $white  = imagecolorallocate($srcImage, 255, 255, 255);
        $cyan   = imagecolorallocate($srcImage, 6, 182, 212);

        // Lines of Watermark Metadata
        $lines = [
            "⚡ SIDAK TEJO ENTERPRISE WATERMARK ⚡",
            "UP3: " . ($metadata['up3'] ?? 'UP3 SIDOARJO') . " | ULP: " . ($metadata['ulp'] ?? 'Sidoarjo') . " | Penyulang: " . ($metadata['penyulang'] ?? '-'),
            "Koordinat: Lat " . ($metadata['lat'] ?? '-') . ", Lng " . ($metadata['lng'] ?? '-'),
            "Waktu: " . date('Y-m-d H:i:s') . " WIB | User: " . ($metadata['user'] ?? 'User') . " | Status: " . ($metadata['status'] ?? 'TEMUAN'),
            "Nomor: " . ($metadata['nomor'] ?? 'STJ-2026') . " | Dev: " . ($metadata['device'] ?? 'Web/Browser')
        ];

        $fontSize = max(2, min(5, (int)($width / 240)));
        $lineY = $bannerY + 10;

        foreach ($lines as $idx => $line) {
            $color = ($idx === 0) ? $yellow : (($idx === 2) ? $cyan : $white);
            imagestring($srcImage, $fontSize, 15, $lineY, $line, $color);
            $lineY += ($fontSize * 4) + 2;
        }

        // Save Watermarked Image with 85% Quality Compression
        $result = match($mime) {
            'image/jpeg', 'image/jpg' => imagejpeg($srcImage, $fullPath, 85),
            'image/png'               => imagepng($srcImage, $fullPath, 8),
            'image/webp'              => imagewebp($srcImage, $fullPath, 85),
            default                   => imagejpeg($srcImage, $fullPath, 85)
        };

        imagedestroy($srcImage);
        return $result;
    }
}
