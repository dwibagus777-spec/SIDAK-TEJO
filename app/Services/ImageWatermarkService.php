<?php

namespace App\Services;

class ImageWatermarkService
{
    /**
     * Fix EXIF Image Orientation from Mobile Camera Uploads
     */
    public function fixExifOrientation(string $filePath): void
    {
        if (!function_exists('exif_read_data') || !file_exists($filePath)) {
            return;
        }

        $exif = @exif_read_data($filePath);
        if (empty($exif['Orientation'])) {
            return;
        }

        $image = @imagecreatefromjpeg($filePath);
        if (!$image) return;

        $ort = $exif['Orientation'];
        $rotated = match ($ort) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null
        };

        if ($rotated) {
            imagejpeg($rotated, $filePath, 85);
            imagedestroy($rotated);
        }
        imagedestroy($image);
    }

    /**
     * Generate Multi-Resolution Images: thumb/, medium/, & original/
     */
    public function generateResolutions(string $originalPath): void
    {
        if (!file_exists($originalPath)) return;

        $dir = dirname($originalPath);
        $fileName = basename($originalPath);

        $thumbDir  = $dir . '/thumb/';
        $mediumDir = $dir . '/medium/';

        if (!is_dir($thumbDir))  @mkdir($thumbDir, 0777, true);
        if (!is_dir($mediumDir)) @mkdir($mediumDir, 0777, true);

        // Create Thumbnail (Width 300px)
        $this->resizeImage($originalPath, $thumbDir . $fileName, 300, 75);

        // Create Medium (Width 800px)
        $this->resizeImage($originalPath, $mediumDir . $fileName, 800, 85);
    }

    private function resizeImage(string $src, string $dst, int $targetWidth, int $quality = 80): void
    {
        $info = @getimagesize($src);
        if (!$info) return;

        $width  = $info[0];
        $height = $info[1];
        if ($width <= 0 || $height <= 0) return;

        $ratio     = $targetWidth / $width;
        $newWidth  = $targetWidth;
        $newHeight = (int)round($height * $ratio);

        $srcImg = match($info['mime']) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($src),
            'image/png'               => @imagecreatefrompng($src),
            'image/webp'              => @imagecreatefromwebp($src),
            default                   => null
        };

        if (!$srcImg) return;

        $dstImg = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match($info['mime']) {
            'image/jpeg', 'image/jpg' => imagejpeg($dstImg, $dst, $quality),
            'image/png'               => imagepng($dstImg, $dst, 8),
            'image/webp'              => imagewebp($dstImg, $dst, $quality),
            default                   => imagejpeg($dstImg, $dst, $quality)
        };

        imagedestroy($srcImg);
        imagedestroy($dstImg);
    }

    /**
     * Apply Watermark Metadata Overlay on Uploaded Image via PHP GD
     */
    public function applyWatermark(string $fullPath, array $metadata): bool
    {
        if (!file_exists($fullPath)) {
            return false;
        }

        // Fix EXIF orientation first
        $this->fixExifOrientation($fullPath);

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

        $darkOverlay = imagecolorallocatealpha($srcImage, 15, 23, 42, 35);
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

        // Generate Thumb & Medium resolutions
        $this->generateResolutions($fullPath);

        return $result;
    }
}
