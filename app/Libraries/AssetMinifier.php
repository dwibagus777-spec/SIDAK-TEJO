<?php

namespace App\Libraries;

class AssetMinifier
{
    /**
     * Menggabungkan dan meminimalkan berkas CSS
     * 
     * @param array $files Jalur relatif berkas CSS dari folder FCPATH (public/)
     * @return string URL dari berkas CSS yang digabungkan
     */
    public static function css(array $files): string
    {
        $hash = self::generateHash($files);
        $outputFilename = "combined-{$hash}.css";
        $outputPath = FCPATH . 'dist/css/' . $outputFilename;
        $outputUrl = base_url("dist/css/{$outputFilename}");

        if (!file_exists($outputPath)) {
            self::ensureDirectoryExists(dirname($outputPath));
            self::cleanupOldFiles(FCPATH . 'dist/css/', 'combined-*.css');

            $content = '';
            foreach ($files as $file) {
                $filePath = FCPATH . $file;
                if (file_exists($filePath)) {
                    $cssContent = file_get_contents($filePath);
                    
                    // KOREKSI JALUR RELATIF:
                    // Jika berkas adalah FontAwesome, ubah path font agar mengarah ke folder asalnya di plugins/
                    if (strpos($file, 'fontawesome-free') !== false) {
                        $cssContent = str_replace('../webfonts/', '../../plugins/fontawesome-free/webfonts/', $cssContent);
                    }
                    // Jika berkas adalah Leaflet CSS, ubah path images agar mengarah ke folder asalnya di plugins/
                    if (strpos($file, 'leaflet.css') !== false) {
                        $cssContent = str_replace('images/', '../../plugins/images/', $cssContent);
                    }

                    // Minifikasi sederhana untuk CSS
                    $cssContent = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $cssContent); // Hapus komentar
                    $cssContent = str_replace(["\r\n", "\r", "\n", "\t"], '', $cssContent); // Hapus newlines & tabs
                    $cssContent = preg_replace('/ {2,}/', ' ', $cssContent); // Hapus spasi berlebih
                    $content .= "/* Source: {$file} */\n" . $cssContent . "\n";
                }
            }
            file_put_contents($outputPath, $content);
        }

        return $outputUrl;
    }

    /**
     * Menggabungkan dan meminimalkan berkas JS
     * 
     * @param array $files Jalur relatif berkas JS dari folder FCPATH (public/)
     * @return string URL dari berkas JS yang digabungkan
     */
    public static function js(array $files): string
    {
        $hash = self::generateHash($files);
        $outputFilename = "combined-{$hash}.js";
        $outputPath = FCPATH . 'dist/js/' . $outputFilename;
        $outputUrl = base_url("dist/js/{$outputFilename}");

        if (!file_exists($outputPath)) {
            self::ensureDirectoryExists(dirname($outputPath));
            self::cleanupOldFiles(FCPATH . 'dist/js/', 'combined-*.js');

            $content = "/* SIDAK TEJO Combined JS */\n";
            // Temporarily disable AMD/CommonJS module loading to force standard browser global registration
            $content .= "var _origDefine = window.define; var _origModule = window.module; var _origExports = window.exports;\n";
            $content .= "window.define = undefined; window.module = undefined; window.exports = undefined;\n";

            foreach ($files as $file) {
                $filePath = FCPATH . $file;
                if (file_exists($filePath)) {
                    $jsContent = file_get_contents($filePath);
                    $content .= "\n/* Source: {$file} */\n" . $jsContent . "\n";
                }
            }

            // Restore module loaders after execution
            $content .= "\nwindow.define = _origDefine; window.module = _origModule; window.exports = _origExports;\n";

            file_put_contents($outputPath, $content);
        }

        return $outputUrl;
    }

    /**
     * Membuat hash unik berdasarkan nama berkas, waktu modifikasi terakhir (mtime), dan modifikasi compiler (cache-buster)
     */
    private static function generateHash(array $files): string
    {
        $hashString = 'v4_' . filemtime(__FILE__) . '_';
        foreach ($files as $file) {
            $filePath = FCPATH . $file;
            if (file_exists($filePath)) {
                $hashString .= $file . filemtime($filePath);
            }
        }
        return md5($hashString);
    }

    /**
     * Memastikan folder tujuan ada
     */
    private static function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Menghapus berkas gabungan lama agar tidak menumpuk di server
     */
    private static function cleanupOldFiles(string $dir, string $pattern): void
    {
        if (is_dir($dir)) {
            $files = glob($dir . $pattern);
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
