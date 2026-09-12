<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class GisHighZoomVisibilityTest extends CIUnitTestCase
{
    public function testGisMapMaxZoomConfiguration()
    {
        $viewPath = APPPATH . 'Views/gis/index.php';
        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        // 1. Assert L.map has maxZoom: 22
        $this->assertMatchesRegularExpression(
            '/L\.map\([\'"]gisMap[\'"],\s*\{[^}]*maxZoom:\s*22/s',
            $content,
            'L.map must specify maxZoom: 22'
        );

        // 2. Assert L.tileLayer has maxNativeZoom: 19 and maxZoom: 22
        $this->assertMatchesRegularExpression(
            '/L\.tileLayer\(.*?maxNativeZoom:\s*19.*?maxZoom:\s*22/s',
            $content,
            'L.tileLayer must specify maxNativeZoom: 19 and maxZoom: 22'
        );

        // 3. Assert GIS-01 Canvas Renderer is preserved
        $this->assertStringContainsString('gisCanvasRenderer = L.canvas', $content);
        $this->assertStringContainsString('translinePolylineLayer', $content);

        // 4. Assert Marker Cluster Cutoff is preserved at zoom 16
        $this->assertStringContainsString('disableClusteringAtZoom: 16', $content);

        // 5. Assert GIS-02 Icon Resolver is preserved
        $this->assertStringContainsString('function resolveAssetIcon(props, visual)', $content);
        $this->assertStringContainsString('GARDU_GTT2_DIST', $content);
        $this->assertStringContainsString('tm1.png', $content);

        // 6. Assert No Zoom Event listeners that reload network
        $this->assertDoesNotMatchRegularExpression(
            '/map\.on\([\'"](zoom|zoomstart|zoomend|move|moveend)[\'"],\s*.*loadGisNetwork/i',
            $content,
            'Map zoom/move events must never trigger loadGisNetwork'
        );
    }
}
