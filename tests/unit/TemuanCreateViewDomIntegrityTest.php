<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * TemuanCreateViewDomIntegrityTest
 *
 * Verifies DOM integrity, grid hierarchy, and tag balancing for app/Views/temuan/create.php.
 * Ensures that neither #map03-location-assistant-card nor #row-vegetasi-container
 * traps subsequent form fields or breaks the Bootstrap grid (.col-lg-8 and .col-lg-4).
 *
 * @internal
 */
final class TemuanCreateViewDomIntegrityTest extends CIUnitTestCase
{
    private string $viewPath;
    private string $viewContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewPath = APPPATH . 'Views/temuan/create.php';
        $this->assertFileExists($this->viewPath, 'View file temuan/create.php must exist.');
        $this->viewContent = file_get_contents($this->viewPath);
    }

    /**
     * Test 1: All <div> tags within the view must be perfectly balanced (zero unclosed/unmatched divs).
     */
    public function testAllDivTagsAreBalanced(): void
    {
        $lines = explode("\n", $this->viewContent);
        $stack = [];

        foreach ($lines as $lineIndex => $line) {
            $lineNum = $lineIndex + 1;
            preg_match_all('/(<\/?div\b[^>]*>)/i', $line, $matches);
            foreach ($matches[1] as $tag) {
                if (strpos($tag, '</') === 0) {
                    $this->assertNotEmpty($stack, "Unmatched closing </div> found at line {$lineNum}: {$tag}");
                    array_pop($stack);
                } else {
                    $stack[] = ['line' => $lineNum, 'tag' => substr($tag, 0, 60)];
                }
            }
        }

        $unclosedCount = count($stack);
        $unclosedDesc = array_map(fn($item) => "line {$item['line']}: {$item['tag']}", $stack);
        $this->assertSame(0, $unclosedCount, "Found {$unclosedCount} unclosed <div> tags: " . implode(', ', $unclosedDesc));
    }

    /**
     * Test 2: #map03-location-assistant-card must be closed BEFORE #authoritative-asset-card.
     */
    public function testMap03CardClosesBeforeAuthoritativeAssetCard(): void
    {
        $map03Pos = strpos($this->viewContent, 'id="map03-location-assistant-card"');
        $this->assertNotFalse($map03Pos, '#map03-location-assistant-card must exist');

        $authoritativePos = strpos($this->viewContent, 'id="authoritative-asset-card"');
        $this->assertNotFalse($authoritativePos, '#authoritative-asset-card must exist');

        $segment = substr($this->viewContent, $map03Pos, $authoritativePos - $map03Pos);
        $opens = preg_match_all('/<div\b/i', $segment);
        $closes = preg_match_all('/<\/div>/i', $segment);

        $this->assertSame($opens, $closes, "#map03-location-assistant-card subtree must be fully closed before #authoritative-asset-card begins (Opens: {$opens}, Closes: {$closes})");
    }

    /**
     * Test 3: #row-vegetasi-container must be closed BEFORE #jtm-accessories-section.
     */
    public function testRowVegetasiContainerClosesBeforeJtmAccessoriesSection(): void
    {
        $rowVegPos = strpos($this->viewContent, 'id="row-vegetasi-container"');
        $this->assertNotFalse($rowVegPos, '#row-vegetasi-container must exist');

        $jtmAccPos = strpos($this->viewContent, 'id="jtm-accessories-section"');
        $this->assertNotFalse($jtmAccPos, '#jtm-accessories-section must exist');

        $segment = substr($this->viewContent, $rowVegPos, $jtmAccPos - $rowVegPos);
        $opens = preg_match_all('/<div\b/i', $segment);
        $closes = preg_match_all('/<\/div>/i', $segment);

        $this->assertSame($opens, $closes, "#row-vegetasi-container must be closed before #jtm-accessories-section begins (Opens: {$opens}, Closes: {$closes})");
    }

    /**
     * Test 4: .col-lg-8 must be closed BEFORE .col-lg-4 begins (siblings, NOT nested).
     */
    public function testColLg8AndColLg4AreStrictSiblings(): void
    {
        $col8Pos = strpos($this->viewContent, 'class="col-lg-8 col-12"');
        $this->assertNotFalse($col8Pos, 'col-lg-8 container must exist');

        $col4Pos = strpos($this->viewContent, 'class="col-lg-4 col-12"');
        $this->assertNotFalse($col4Pos, 'col-lg-4 container must exist');
        $this->assertGreaterThan($col8Pos, $col4Pos, 'col-lg-8 must appear before col-lg-4');

        $segment = substr($this->viewContent, $col8Pos, $col4Pos - $col8Pos);
        $opens = preg_match_all('/<div\b/i', $segment);
        $closes = preg_match_all('/<\/div>/i', $segment);

        $this->assertSame($opens, $closes, "All divs inside .col-lg-8 (including .col-lg-8 itself) must be closed before .col-lg-4 begins (Opens: {$opens}, Closes: {$closes})");
    }
}
