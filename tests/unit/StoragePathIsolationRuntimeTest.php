<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class StoragePathIsolationRuntimeTest extends CIUnitTestCase
{
    private string $sandbox;
    private string $projectRoot;
    private string $constantsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sidak_storage_isolation_' . bin2hex(random_bytes(8));
        $this->projectRoot = $this->sandbox . DIRECTORY_SEPARATOR . 'project';
        $this->constantsPath = $this->sandbox . DIRECTORY_SEPARATOR . 'Constants.php';

        foreach ([
            $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto',
            $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto',
            $this->sandbox . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . 'sidaktejo.site' . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto',
            $this->projectRoot . DIRECTORY_SEPARATOR . 'public',
            $this->projectRoot . DIRECTORY_SEPARATOR . 'app',
        ] as $directory) {
            mkdir($directory, 0775, true);
        }

        $source = file_get_contents(APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Constants.php');
        if ($source === false) {
            $this->fail('Unable to read Constants.php for isolated runtime tests.');
        }

        $sandboxPath = str_replace('\\', '/', $this->sandbox);
        $source = str_replace(
            [
                "'/home/u532206332/domains/sidaktejo.site/sidak_storage",
                "'/home/u532206332/sidak_storage_staging",
                "'/home/u532206332/sidak_storage",
            ],
            [
                "'" . $sandboxPath . "/domains/sidaktejo.site/sidak_storage",
                "'" . $sandboxPath . "/sidak_storage_staging",
                "'" . $sandboxPath . "/sidak_storage",
            ],
            $source
        );

        if (file_put_contents($this->constantsPath, $source) === false) {
            $this->fail('Unable to prepare isolated Constants.php fixture.');
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->sandbox);
        parent::tearDown();
    }

    public function testAutomaticStorageResolutionIsIsolatedByEnvironment(): void
    {
        $staging = $this->runResolver('staging');
        $production = $this->runResolver('production');
        $development = $this->runResolver('development');

        $this->assertSame(0, $staging['exit']);
        $this->assertSame(realpath($this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto') . DIRECTORY_SEPARATOR, $staging['path']);
        $this->assertSame(0, $production['exit']);
        $this->assertSame(realpath($this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto') . DIRECTORY_SEPARATOR, $production['path']);
        $this->assertSame(0, $development['exit']);
        $this->assertStringContainsString('sidak-tejo-development-storage', $development['path']);
        $this->assertNotSame($staging['path'], $production['path']);
        $this->assertNotSame($development['path'], $production['path']);
    }

    public function testUnknownAndEmptyEnvironmentValuesFailClosed(): void
    {
        foreach (['prodution', ''] as $environment) {
            $result = $this->runResolver($environment, null, 'production');

            $this->assertNotSame(0, $result['exit']);
            $this->assertStringContainsString('CI_ENVIRONMENT', $result['output']);
            $this->assertStringNotContainsString($this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto', $result['output']);
        }
    }

    public function testExplicitPathCannotCrossEnvironmentBoundaries(): void
    {
        $productionPath = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto';
        $stagingPath = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto';

        $stagingUsingProduction = $this->runResolver('staging', $productionPath);
        $productionUsingStaging = $this->runResolver('production', $stagingPath);

        $this->assertNotSame(0, $stagingUsingProduction['exit']);
        $this->assertNotSame(0, $productionUsingStaging['exit']);
    }

    public function testExplicitMissingPathAndPublicDocumentPathAreRejected(): void
    {
        $missingPath = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'missing';
        $publicPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'public';

        $missing = $this->runResolver('production', $missingPath);
        $public = $this->runResolver('production', $publicPath);

        $this->assertNotSame(0, $missing['exit']);
        $this->assertNotSame(0, $public['exit']);
        $this->assertStringContainsString('outside the application and document roots', $public['output']);
    }

    public function testProductionFailsExplicitlyInsteadOfUsingAnUnverifiedFallback(): void
    {
        $this->removeDirectory($this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto');
        $this->removeDirectory($this->sandbox . DIRECTORY_SEPARATOR . 'domains');

        $result = $this->runResolver('production');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('not configured to an existing writable private directory', $result['output']);
        $this->assertStringNotContainsString($this->projectRoot . DIRECTORY_SEPARATOR . 'public', $result['output']);
    }

    public function testCanonicalResolutionRejectsTraversalAndEnvironmentCrossingSymlinks(): void
    {
        $stagingPath = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto';
        $traversalPath = $stagingPath . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto';
        $traversal = $this->runResolver('staging', $traversalPath);

        $this->assertNotSame(0, $traversal['exit']);

        $symlinkPath = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto-link';
        if (!@symlink($this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto', $symlinkPath)) {
            $this->markTestSkipped('The current OS/account cannot create directory symlinks.');
        }

        $symlink = $this->runResolver('staging', $symlinkPath);
        $this->assertNotSame(0, $symlink['exit']);
    }

    public function testExplicitNonWritableDirectoryIsRejectedWhenFilesystemCanRepresentIt(): void
    {
        $path = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto';
        chmod($path, 0555);
        clearstatcache(true, $path);

        if (is_writable($path)) {
            $this->markTestSkipped('The current OS/account still reports chmod-restricted directories as writable.');
        }

        $result = $this->runResolver('production', $path);
        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('existing writable directory', $result['output']);
    }

    private function runResolver(string $environment, ?string $explicitPath = null, ?string $frameworkEnvironment = null): array
    {
        $scriptPath = $this->sandbox . DIRECTORY_SEPARATOR . 'run-resolver-' . bin2hex(random_bytes(6)) . '.php';
        $projectRoot = str_replace('\\', '/', $this->projectRoot);
        $constantsPath = str_replace('\\', '/', $this->constantsPath);
        $setup = "putenv('CI_ENVIRONMENT=" . addslashes($environment) . "');\n";

        if ($explicitPath !== null) {
            $setup .= "putenv('SIDAK_STORAGE_PATH=" . addslashes(str_replace('\\', '/', $explicitPath)) . "');\n";
        } else {
            $setup .= "putenv('SIDAK_STORAGE_PATH');\n";
        }

        $script = "<?php\n" . $setup
            . "define('ROOTPATH', " . var_export($projectRoot . '/', true) . ");\n"
            . "define('FCPATH', " . var_export($projectRoot . '/public/', true) . ");\n"
            . "define('APPPATH', " . var_export($projectRoot . '/app/', true) . ");\n"
            . "define('ENVIRONMENT', " . var_export($frameworkEnvironment ?? $environment, true) . ");\n"
            . "try { require " . var_export($constantsPath, true) . "; echo SIDAK_STORAGE_PATH; }"
            . " catch (Throwable \$exception) { fwrite(STDERR, \$exception->getMessage()); exit(2); }\n";

        if (file_put_contents($scriptPath, $script) === false) {
            $this->fail('Unable to prepare isolated PHP subprocess script.');
        }

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $scriptPath],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            $this->fail('Unable to start isolated PHP subprocess.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        @unlink($scriptPath);

        return [
            'exit' => $exit,
            'output' => trim((string) $stdout . (string) $stderr),
            'path' => $exit === 0 ? trim((string) $stdout) : null,
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (is_link($directory) || is_file($directory)) {
            @unlink($directory);
            return;
        }

        if (!is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $this->removeDirectory($directory . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($directory);
    }
}
