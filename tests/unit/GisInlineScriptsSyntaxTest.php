<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Validates that all inline <script> blocks in app/Views/gis/index.php are free of SyntaxErrors
 */
class GisInlineScriptsSyntaxTest extends CIUnitTestCase
{
    public function testGisInlineScriptsHaveNoSyntaxErrors(): void
    {
        $filePath = APPPATH . 'Views/gis/index.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertNotEmpty($content);

        // Ensure no duplicate or dangling .catch blocks in bindPointerSafeTap
        $this->assertStringNotContainsString("}, 'SUBMIT_CONNECTION');\n        .catch", $content);
        $this->assertStringNotContainsString("}, 'SUBMIT_CONNECTION');\r\n        .catch", $content);

        // Run node-based script syntax checker
        $cmd = 'node tests/check_inline_scripts.js 2>&1';
        exec($cmd, $output, $returnCode);

        $outStr = implode("\n", $output);
        $this->assertSame(0, $returnCode, "JavaScript syntax check failed:\n" . $outStr);
        $this->assertStringContainsString('OK (PASSED PARSE CHECK)', $outStr);
        $this->assertStringContainsString('errors: 0', $outStr);
    }
}
