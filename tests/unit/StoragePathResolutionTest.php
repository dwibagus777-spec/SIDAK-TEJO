<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class StoragePathResolutionTest extends CIUnitTestCase
{
    private string $sandbox;
    private string $projectRoot;
    private string $constantsPath;
    private string $productionStorage;
    private string $stagingStorage;
    private string $domainStagingStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sidak_storage_resolution_' . bin2hex(random_bytes(8));
        $this->projectRoot = $this->sandbox . DIRECTORY_SEPARATOR . 'project';
        $this->constantsPath = $this->sandbox . DIRECTORY_SEPARATOR . 'Constants.php';
        $this->productionStorage = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto';
        $this->stagingStorage = $this->sandbox . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto';
        $this->domainStagingStorage = $this->sandbox . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . 'sidaktejo.site' . DIRECTORY_SEPARATOR . 'sidak_storage_staging' . DIRECTORY_SEPARATOR . 'foto';

        foreach ([
            $this->productionStorage,
            $this->stagingStorage,
            $this->sandbox . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . 'sidaktejo.site' . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto',
            $this->domainStagingStorage,
            $this->projectRoot . DIRECTORY_SEPARATOR . 'public',
            $this->projectRoot . DIRECTORY_SEPARATOR . 'app',
        ] as $directory) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                $this->fail('Unable to create isolated storage fixture.');
            }
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

    public function testAutomaticResolutionUsesOnlyFixturePaths(): void
    {
        $staging = $this->runResolver('staging');
        $production = $this->runResolver('production');

        $this->assertSame(0, $staging['exit']);
        $this->assertSame(realpath($this->stagingStorage) . DIRECTORY_SEPARATOR, $staging['path']);
        $this->assertSame(0, $production['exit']);
        $this->assertSame(realpath($this->productionStorage) . DIRECTORY_SEPARATOR, $production['path']);
        $this->assertNotSame($staging['path'], $production['path']);
    }

    public function testUnknownAndEmptyEnvironmentValuesFailClosed(): void
    {
        foreach (['stagng', ''] as $environment) {
            $result = $this->runResolver($environment, null, 'production');

            $this->assertNotSame(0, $result['exit']);
            $this->assertStringContainsString('CI_ENVIRONMENT', $result['output']);
            $this->assertStringNotContainsString($this->productionStorage, $result['output']);
        }
    }

    public function testExplicitPathsCannotCrossEnvironmentBoundaries(): void
    {
        $stagingUsingProduction = $this->runResolver('staging', $this->productionStorage);
        $productionUsingStaging = $this->runResolver('production', $this->stagingStorage);

        $this->assertNotSame(0, $stagingUsingProduction['exit']);
        $this->assertNotSame(0, $productionUsingStaging['exit']);
    }

    public function testConfirmedHostingerStagingStoragePathIsAllowed(): void
    {
        $staging = $this->runResolver('staging', $this->domainStagingStorage);

        $this->assertSame(0, $staging['exit']);
        $this->assertSame(realpath($this->domainStagingStorage) . DIRECTORY_SEPARATOR, $staging['path']);
        $this->assertNotSame(realpath($this->productionStorage) . DIRECTORY_SEPARATOR, $staging['path']);
    }

    public function testMissingPathDocumentRootAndTraversalAreRejected(): void
    {
        $missing = $this->runResolver('staging', $this->sandbox . DIRECTORY_SEPARATOR . 'missing');
        $public = $this->runResolver('staging', $this->projectRoot . DIRECTORY_SEPARATOR . 'public');
        $traversal = $this->runResolver(
            'staging',
            $this->stagingStorage . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto'
        );

        $this->assertNotSame(0, $missing['exit']);
        $this->assertNotSame(0, $public['exit']);
        $this->assertNotSame(0, $traversal['exit']);
        $this->assertStringContainsString('outside the application and document roots', $public['output']);
    }

    public function testMissingProductionStorageDoesNotFallBack(): void
    {
        $this->removeDirectory($this->productionStorage);
        $this->removeDirectory(
            $this->sandbox . DIRECTORY_SEPARATOR . 'domains' . DIRECTORY_SEPARATOR . 'sidaktejo.site' . DIRECTORY_SEPARATOR . 'sidak_storage' . DIRECTORY_SEPARATOR . 'foto'
        );

        $result = $this->runResolver('production');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('not configured to an existing writable private directory', $result['output']);
        $this->assertStringNotContainsString($this->projectRoot . DIRECTORY_SEPARATOR . 'public', $result['output']);
    }

    public function testSymlinkCannotCrossEnvironmentBoundary(): void
    {
        $symlinkPath = dirname($this->stagingStorage) . DIRECTORY_SEPARATOR . 'foto-link';
        if (!@symlink($this->productionStorage, $symlinkPath)) {
            $this->markTestSkipped('The current OS/account cannot create directory symlinks in the temporary fixture.');
        }

        $result = $this->runResolver('staging', $symlinkPath);
        $this->assertNotSame(0, $result['exit']);
    }

    public function testUnknownEnvironmentGuardIsPresent(): void
    {
        $source = file_get_contents($this->constantsPath);

        $this->assertIsString($source);
        $this->assertStringContainsString("['development', 'testing', 'staging', 'production']", $source);
        $this->assertStringContainsString('Unsupported CI_ENVIRONMENT', $source);
    }

    private function runResolver(string $environment, ?string $explicitPath = null, ?string $frameworkEnvironment = null): array
    {
        $scriptPath = $this->sandbox . DIRECTORY_SEPARATOR . 'run-resolver-' . bin2hex(random_bytes(6)) . '.php';
        $projectRoot = str_replace('\\', '/', $this->projectRoot);
        $constantsPath = str_replace('\\', '/', $this->constantsPath);
        $script = "<?php\n"
            . "putenv('CI_ENVIRONMENT=" . addslashes($environment) . "');\n"
            . ($explicitPath === null
                ? "putenv('SIDAK_STORAGE_PATH');\n"
                : "putenv('SIDAK_STORAGE_PATH=" . addslashes(str_replace('\\', '/', $explicitPath)) . "');\n")
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
