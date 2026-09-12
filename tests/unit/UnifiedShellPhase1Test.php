<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit Test Suite for SIDAK TEJO UI/UX REVAMP - Phase 1 Unified Shell & Navigation
 *
 * Verifies:
 * 1. Single Mobile Bottom Dock (#sidak-mobile-dock) - exactly ONE instance, zero legacy dock.
 * 2. 5-slot Mobile Dock Architecture with center elevated FAB and safe-area insets.
 * 3. Offcanvas More Navigation Sheet (#offcanvasMoreMenu) with modular shortcuts.
 * 4. Desktop AI Command Bar (#global-search-wrapper) with live input and Ctrl/Cmd+K shortcuts.
 * 5. Design System CSS Tokens (--sidak-*) in custom_modern.css.
 * 6. Header Elements Integrity: collapse toggle, announcement ticker, AI command bar, user info, favorites, utilities.
 * 7. Navigation Categories & Role Gates Integrity: all 5 categories and check_role calls preserved.
 * 8. Zero Topology / DB Engine Mutation: zero calls to Transline completion services.
 */
class UnifiedShellPhase1Test extends CIUnitTestCase
{
    protected string $layoutContent;
    protected string $cssContent;

    protected function setUp(): void
    {
        parent::setUp();
        $layoutFile = APPPATH . 'Views/layouts/admin.php';
        $this->assertFileExists($layoutFile);
        $this->layoutContent = file_get_contents($layoutFile);

        $cssFile = FCPATH . 'dist/css/custom_modern.css';
        $this->assertFileExists($cssFile);
        $this->cssContent = file_get_contents($cssFile);
    }

    /**
     * Test 1: Single Mobile Bottom Dock exists, redundant legacy dock removed
     */
    public function testSingleMobileBottomDockExists(): void
    {
        $matches = [];
        preg_match_all('/id=["\']sidak-mobile-dock["\']/', $this->layoutContent, $matches);
        $this->assertCount(1, $matches[0], 'Exactly ONE #sidak-mobile-dock must exist in the layout DOM.');

        $oldNavMatches = [];
        preg_match_all('/class=["\']mobile-bottom-nav d-lg-none["\']/', $this->layoutContent, $oldNavMatches);
        $this->assertCount(0, $oldNavMatches[0], 'Redundant legacy .mobile-bottom-nav d-lg-none must not exist in layout.');
    }

    /**
     * Test 2: Mobile Dock contains 5 logical slots (Home, GIS, Tugas, Center FAB, More)
     */
    public function testMobileDockFiveSlotsAndFab(): void
    {
        $this->assertStringContainsString('class="sidak-dock-item', $this->layoutContent);
        $this->assertStringContainsString('class="sidak-dock-fab"', $this->layoutContent);
        $this->assertStringContainsString('data-bs-toggle="offcanvas"', $this->layoutContent);
        $this->assertStringContainsString('data-bs-target="#offcanvasMoreMenu"', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'dashboard\')', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'gis\')', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'planning\')', $this->layoutContent);
    }

    /**
     * Test 3: Offcanvas More Menu Sheet completeness
     */
    public function testOffcanvasMoreMenuCompleteness(): void
    {
        $this->assertStringContainsString('id="offcanvasMoreMenu"', $this->layoutContent);
        $this->assertStringContainsString('sidak-more-tile', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'temuan\')', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'work-orders\')', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'ai-copilot\')', $this->layoutContent);
        $this->assertStringContainsString('site_url(\'logout\')', $this->layoutContent);
    }

    /**
     * Test 4: AI Command Bar components & keyboard shortcut bindings
     */
    public function testAiCommandBarComponentsAndShortcuts(): void
    {
        $this->assertStringContainsString('id="global-search-wrapper"', $this->layoutContent);
        $this->assertStringContainsString('id="global-search-input"', $this->layoutContent);
        $this->assertStringContainsString('id="global-search-dropdown"', $this->layoutContent);
        $this->assertStringContainsString('⌘K', $this->layoutContent);
        $this->assertStringContainsString('sidak-cmd-input', $this->layoutContent);

        $this->assertStringContainsString("e.key.toLowerCase() === 'k'", $this->layoutContent);
        $this->assertStringContainsString("e.key === 'Escape'", $this->layoutContent);
    }

    /**
     * Test 5: Modern Design System CSS Tokens and Classes
     */
    public function testDesignSystemTokensPresent(): void
    {
        $requiredTokens = [
            '--sidak-bg',
            '--sidak-surface',
            '--sidak-surface-elevated',
            '--sidak-text',
            '--sidak-primary',
            '--sidak-primary-light',
            '--sidak-border',
            '--sidak-radius-pill',
            '.sidak-cmd-wrapper',
            '.sidak-cmd-bar',
            '.sidak-cmd-input',
            '.sidak-cmd-kbd',
            '.sidak-mobile-dock',
            '.sidak-dock-inner',
            '.sidak-dock-item',
            '.sidak-dock-fab',
            '.sidak-fab-circle',
            '.sidak-more-tile',
            'env(safe-area-inset-bottom)',
        ];

        foreach ($requiredTokens as $token) {
            $this->assertStringContainsString($token, $this->cssContent, "Token or class {$token} must exist in custom_modern.css");
        }
    }

    /**
     * Test 6: Top Navbar Header Controls & Quick Utilities
     */
    public function testPreservationOfHeaderControls(): void
    {
        $headerControls = [
            'id="btn-collapse-sidebar"',
            'id="running-announcement-text"',
            'id="global-search-wrapper"',
            'id="global-search-input"',
            'id="btn-qr-scan-header"',
            'id="favMenuToggle"',
            'id="fav-menu-list"',
            'site_url(\'backup-database\')',
            'site_url(\'change-password\')',
            'site_url(\'logout\')',
        ];

        foreach ($headerControls as $ctrl) {
            $this->assertStringContainsString($ctrl, $this->layoutContent, "Header control {$ctrl} must be preserved.");
        }
    }

    /**
     * Test 7: Sidebar Category Accordions & Security Gates
     */
    public function testPreservationOfSidebarCategoriesAndRoleGates(): void
    {
        $categories = [
            'CORE & ANALYTICS',
            'OPERASIONAL & LAPANGAN',
            'AI & INTELLIGENCE',
            'DATA & MASTER REFERENSI',
            'SYSTEM & LAPORAN',
        ];

        foreach ($categories as $cat) {
            $this->assertStringContainsString($cat, $this->layoutContent, "Category {$cat} must exist in sidebar.");
        }

        $roleCheckMatches = [];
        preg_match_all('/check_role\(/', $this->layoutContent, $roleCheckMatches);
        $this->assertGreaterThanOrEqual(5, count($roleCheckMatches[0]), 'Role security gates check_role() must be maintained throughout sidebar.');
    }

    /**
     * Test 8: Zero Mutation on Topology / DB Engine
     */
    public function testZeroWriteEngineUntouched(): void
    {
        $this->assertStringNotContainsString('MultiFeederCompletionOrchestrator', $this->layoutContent);
        $this->assertStringNotContainsString('TranslineCompletionService', $this->layoutContent);
        $this->assertStringNotContainsString('TranslineProposalService', $this->layoutContent);
    }
}