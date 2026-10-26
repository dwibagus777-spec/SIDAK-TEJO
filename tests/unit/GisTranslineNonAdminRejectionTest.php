<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\GisController;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;

class GisTranslineNonAdminRejectionTest extends CIUnitTestCase
{
    public function testNonAdminIsRejectedFromDisconnectTopology(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 99;
        $_SESSION['username'] = 'field_officer';
        $_SESSION['user_role'] = 'PETUGAS_LAPANGAN';

        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiDisconnectTopology();
        $this->assertEquals(403, $response->getStatusCode());
        
        $body = json_decode($response->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('Akses ditolak', $body['message']);
    }

    public function testNonAdminIsRejectedFromUpdateConductor(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 99;
        $_SESSION['username'] = 'field_officer';
        $_SESSION['user_role'] = 'PETUGAS_LAPANGAN';

        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiUpdateConductorSpecification();
        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('Akses ditolak', $body['message']);
    }

    public function testNonAdminIsRejectedFromConnectTopology(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 99;
        $_SESSION['username'] = 'field_officer';
        $_SESSION['user_role'] = 'PETUGAS_LAPANGAN';

        $controller = new GisController();
        $controller->initController(
            service('request'),
            service('response'),
            service('logger')
        );

        $response = $controller->apiConnectTopology();
        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('Akses ditolak', $body['message']);
    }
}
