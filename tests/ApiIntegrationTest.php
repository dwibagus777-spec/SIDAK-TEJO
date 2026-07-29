<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\IntegrationService;
use App\Repositories\IntegrationRepository;

class ApiIntegrationTest extends CIUnitTestCase
{
    private IntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IntegrationService();
    }

    public function testJwtGenerationAndValidation()
    {
        $user = [
            'id'   => 1,
            'nama' => 'Test User',
            'role' => 'administrator'
        ];

        $token = $this->service->generateJWT($user);
        $this->assertNotEmpty($token);

        $payload = $this->service->validateJWT($token);
        $this->assertNotNull($payload);
        $this->assertEquals(1, $payload['sub']);
        $this->assertEquals('administrator', $payload['role']);
    }

    public function testApiKeyGeneration()
    {
        $res = $this->service->generateApiKey(1, ['*'], 500);
        $this->assertArrayHasKey('api_key', $res);
        $this->assertArrayHasKey('secret', $res);
        $this->assertStringStartsWith('stj_', $res['api_key']);
    }

    public function testHealthCheckEngine()
    {
        $health = $this->service->healthCheck();
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('database', $health['checks']);
        $this->assertArrayHasKey('memory', $health['checks']);
    }

    public function testExportFormats()
    {
        $sample = ['name' => 'SIDAK TEJO', 'status' => 'ACTIVE'];

        $json = $this->service->exportData($sample, 'json');
        $this->assertJson($json);

        $xml = $this->service->exportData($sample, 'xml');
        $this->assertStringContainsString('<root>', $xml);

        $csv = $this->service->exportData($sample, 'csv');
        $this->assertNotEmpty($csv);
    }
}
