<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class DatabaseEnvironmentConfigurationTest extends CIUnitTestCase
{
    public function testCodeIgniterDottedDatabaseEnvironmentValuesConfigureDefaultConnection(): void
    {
        $environmentValues = [
            'database.default.hostname' => 'staging-db.invalid',
            'database.default.username' => 'staging_test_user',
            'database.default.password' => 'test-only-password',
            'database.default.database' => 'sidaktejo_staging_test',
            'database.default.port' => '3307',
        ];
        $previousEnvironment = [];
        $previousEnvSuperglobals = [];
        $previousServerSuperglobals = [];

        foreach ($environmentValues as $name => $value) {
            $previousEnvironment[$name] = getenv($name);
            $previousEnvSuperglobals[$name] = [
                'present' => array_key_exists($name, $_ENV),
                'value' => $_ENV[$name] ?? null,
            ];
            $previousServerSuperglobals[$name] = [
                'present' => array_key_exists($name, $_SERVER),
                'value' => $_SERVER[$name] ?? null,
            ];
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        try {
            $database = new Database();

            $this->assertSame('staging-db.invalid', $database->default['hostname']);
            $this->assertSame('staging_test_user', $database->default['username']);
            $this->assertSame('test-only-password', $database->default['password']);
            $this->assertSame('sidaktejo_staging_test', $database->default['database']);
            $this->assertSame(3307, $database->default['port']);
        } finally {
            foreach ($previousEnvironment as $name => $value) {
                if ($value === false) {
                    putenv($name);
                } else {
                    putenv($name . '=' . $value);
                }

                foreach ([
                    '_ENV' => $previousEnvSuperglobals[$name],
                    '_SERVER' => $previousServerSuperglobals[$name],
                ] as $superglobal => $previous) {
                    if ($previous['present']) {
                        $GLOBALS[$superglobal][$name] = $previous['value'];
                    } else {
                        unset($GLOBALS[$superglobal][$name]);
                    }
                }
            }
        }
    }
}
