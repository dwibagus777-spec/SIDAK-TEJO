<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class PublicExposureHardeningTest extends CIUnitTestCase
{
    public function testWebRootDeniesDiagnosticAndBackupArtifacts(): void
    {
        $htaccess = file_get_contents(ROOTPATH . 'public/.htaccess');

        $this->assertNotFalse($htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);
        preg_match_all('/<FilesMatch\s+"([^"]+)">([\s\S]*?)<\/FilesMatch>/i', $htaccess, $blocks, PREG_SET_ORDER);
        $deniedPatterns = [];

        foreach ($blocks as $block) {
            if (preg_match('/^\s*Require\s+all\s+denied\s*$/mi', $block[2])) {
                $deniedPatterns[] = $block[1];
            }
        }

        foreach ([
            'agent_compare.php',
            'check.php',
            'compare_agent_response.php',
            'compare_live.php',
            'db_auth_audit.php',
            'full_audit.php',
            'get_dashboard_html.php',
            'test-cloudinary.php',
            'test_ajax2.php',
            'test_autoload.php',
            'test_cache.php',
            'test_login_submit.php',
            '.env',
            '.env.staging',
            'sidaktejo.sql',
            'foto.zip',
            'error.log',
            'database.sql.gz',
        ] as $filename) {
            $isDenied = false;
            foreach ($deniedPatterns as $pattern) {
                if (preg_match('~' . $pattern . '~', $filename) === 1) {
                    $isDenied = true;
                    break;
                }
            }

            $this->assertTrue($isDenied, "Public web root must deny {$filename}.");
        }
    }

    public function testStoredPhotoRequestsAreRoutedBeforeStaticFileHandling(): void
    {
        $htaccess = file_get_contents(ROOTPATH . 'public/.htaccess');
        $denySessions = 'RewriteRule ^ci_sessions(?:/|$) - [F,NC,L]';
        $routeMediaDirectory = 'RewriteRule ^(?:foto|uploads)/?$ index.php/foto [L,NC,QSA]';
        $rewrite = 'RewriteRule ^(?:foto|uploads)/(.+)$ index.php/foto/$1 [L,NC,QSA]';
        $genericStaticCheck = 'RewriteCond %{REQUEST_FILENAME} !-f';

        $this->assertNotFalse($htaccess);
        $this->assertStringContainsString($routeMediaDirectory, $htaccess);
        $this->assertStringContainsString($rewrite, $htaccess);
        $this->assertStringContainsString($denySessions, $htaccess);
        $this->assertLessThan(strpos($htaccess, $genericStaticCheck), strpos($htaccess, $rewrite));
    }

    public function testPhotoRouteRequiresAuthenticationAndDisablesSharedCaching(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $controller = file_get_contents(APPPATH . 'Controllers/PhotoController.php');

        $this->assertNotFalse($routes);
        $this->assertNotFalse($controller);
        $this->assertStringContainsString(
            "\$routes->get('foto/(:any)', 'PhotoController::show/\$1', ['filter' => 'auth']);",
            $routes
        );
        $this->assertStringContainsString("->setHeader('Cache-Control', 'private, no-store, max-age=0')", $controller);
        $this->assertStringContainsString("->setHeader('Pragma', 'no-cache')", $controller);
        $this->assertStringNotContainsString("->setHeader('Cache-Control', 'public", $controller);
    }

    public function testDangerousMaintenanceRoutesAreRemovedOrRestrictedToAdmins(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertNotFalse($routes);

        foreach ([
            'master-assets/auto-migrate',
            'master-assets/auto-deploy',
            'auto-deploy',
            'master-assets/debug-json',
            'master-assets/b2-atomic-commit',
        ] as $removedRoute) {
            $this->assertStringNotContainsString("'{$removedRoute}'", $routes);
        }

        foreach ([
            'network-intelligence/audit',
            'fault-intelligence/audit',
            'master-assets/reconciliation-baseline',
            'master-assets/transline-audit',
            'master-assets/b22-production-lock',
            'fault-ingestion/audit',
            'fault-ingestion/forensic-reconciliation',
            'fault-intelligence/migrate',
            'fault-ingestion/migrate',
            'fault-dispatch/audit',
            'fault-dispatch/forensic-reconciliation',
            'fault-dispatch/migrate',
            'mobile-sync/audit',
            'mobile-sync/forensic-reconciliation',
            'mobile-sync/migrate',
            'remediation/audit',
            'remediation/forensic-reconciliation',
            'remediation/migrate',
        ] as $restrictedRoute) {
            $this->assertMatchesRegularExpression(
                '/\$routes->(?:get|match)\([^;\r\n]*[\'"]' . preg_quote($restrictedRoute, '/') . '[\'"][^;\r\n]*filter[\'"]\s*=>\s*[\'"]role:administrator,admin,admin_pusat[\'"]/',
                $routes,
                "{$restrictedRoute} must require an administrator role."
            );
        }
    }

    public function testAuditAndDeployTokenChecksHaveNoSourceFallback(): void
    {
        $controllerPaths = [
            APPPATH . 'Controllers/FaultIntelligenceController.php',
            APPPATH . 'Controllers/FaultIngestionController.php',
            APPPATH . 'Controllers/NetworkIntelligenceController.php',
            APPPATH . 'Controllers/FaultDispatchController.php',
            APPPATH . 'Controllers/MobileSyncController.php',
            APPPATH . 'Controllers/RemediationController.php',
        ];

        foreach ($controllerPaths as $path) {
            $controller = file_get_contents($path);
            $this->assertNotFalse($controller);
            $this->assertDoesNotMatchRegularExpression(
                '/\$(?:auditKey|deployMasterKey)\s*=\s*getenv\([^;\r\n]*\?:/',
                $controller,
                basename($path) . ' must not provide source-code token defaults.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$auditKey\s*=\s*[\'"][^\'"]+[\'"]/',
                $controller,
                basename($path) . ' must not hardcode an audit token.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$masterKey\s*=\s*[\'"][^\'"]+[\'"]/',
                $controller,
                basename($path) . ' must not hardcode a deployment token.'
            );
            if (in_array(basename($path), ['FaultIntelligenceController.php', 'FaultIngestionController.php'], true)) {
                $this->assertStringContainsString(
                    "if (\$masterKey === '' || empty(\$providedMaster)",
                    $controller,
                    basename($path) . ' must reject deployment when its token is unset.'
                );
            }
        }

        $migrateController = file_get_contents(APPPATH . 'Controllers/MigrateController.php');
        $this->assertNotFalse($migrateController);
        $this->assertDoesNotMatchRegularExpression('/\$validKeys\s*=\s*\[\s*[\'"]/', $migrateController);
        $this->assertStringNotContainsString("env('AUDIT_SECRET_KEY',", $migrateController);
        $this->assertStringNotContainsString("env('SIDAK_AUDIT_TOKEN',", $migrateController);
    }
}
