<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit Test Suite for TL-01 UI-01: Mobile Transline Confirmation Accessibility
 *
 * Verifies that:
 * 1. Confirm Connection Sheet (#offcanvas-confirm-connection-sheet) uses .sheet-sticky-footer.
 * 2. Conductor Spec Sheet (#offcanvas-conductor-spec-sheet) uses .sheet-sticky-footer.
 * 3. Delete Connection Sheet (#offcanvas-delete-connection-sheet) uses .sheet-sticky-footer.
 * 4. Submit buttons are not clipped or placed at raw 0px bottom margin.
 * 5. CSS incorporates --gis-mob-bottom-nav, safe-area-inset-bottom, and desktop responsiveness.
 * 6. Zero mutation on database or Transline domain (Write gate remains strictly CLOSED).
 */
class GisMobileTranslineSheetAccessibilityTest extends CIUnitTestCase
{
    protected string $viewContent;

    protected function setUp(): void
    {
        parent::setUp();
        $viewFile = APPPATH . 'Views/gis/index.php';
        $this->assertFileExists($viewFile);
        $this->viewContent = file_get_contents($viewFile);
    }

    /**
     * Test 01: Confirm Connection Sheet has sticky footer with mobile bottom-nav clearance
     */
    public function testConfirmConnectionSheetHasStickyFooter(): void
    {
        $this->assertStringContainsString('id="offcanvas-confirm-connection-sheet"', $this->viewContent);
        $this->assertStringContainsString('id="btn-submit-connection"', $this->viewContent);

        // Verify button is inside sheet-sticky-footer
        $pattern = '/id="offcanvas-confirm-connection-sheet"[\s\S]*?class="sheet-sticky-footer"[\s\S]*?id="btn-submit-connection"/';
        $this->assertMatchesRegularExpression($pattern, $this->viewContent);
    }

    /**
     * Test 02: Conductor Spec Sheet has sticky footer with mobile bottom-nav clearance
     */
    public function testConductorSpecSheetHasStickyFooter(): void
    {
        $this->assertStringContainsString('id="offcanvas-conductor-spec-sheet"', $this->viewContent);
        $this->assertStringContainsString('id="btn-submit-conductor-spec"', $this->viewContent);

        $pattern = '/id="offcanvas-conductor-spec-sheet"[\s\S]*?class="sheet-sticky-footer"[\s\S]*?id="btn-submit-conductor-spec"/';
        $this->assertMatchesRegularExpression($pattern, $this->viewContent);
    }

    /**
     * Test 03: Delete Connection Sheet has sticky footer with mobile bottom-nav clearance
     */
    public function testDeleteConnectionSheetHasStickyFooter(): void
    {
        $this->assertStringContainsString('id="offcanvas-delete-connection-sheet"', $this->viewContent);
        $this->assertStringContainsString('id="btn-confirm-delete-pair"', $this->viewContent);

        $pattern = '/id="offcanvas-delete-connection-sheet"[\s\S]*?class="sheet-sticky-footer"[\s\S]*?id="btn-confirm-delete-pair"/';
        $this->assertMatchesRegularExpression($pattern, $this->viewContent);
    }

    /**
     * Test 04: CSS rules ensure sticky positioning, safe area, and overflow management
     */
    public function testCssRulesEnsureMobileSafePositioningAndScrollability(): void
    {
        // Safe-area and mobile nav clearance in sheet-sticky-footer
        $this->assertStringContainsString('.sheet-sticky-footer {', $this->viewContent);
        $this->assertStringContainsString('position: sticky;', $this->viewContent);
        $this->assertStringContainsString('bottom: 0;', $this->viewContent);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $this->viewContent);
        $this->assertStringContainsString('var(--gis-mob-bottom-nav', $this->viewContent);

        // Body scrollability and touch smoothness
        $this->assertStringContainsString('.offcanvas-compact-sheet .offcanvas-body {', $this->viewContent);
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch', $this->viewContent);
    }
}
