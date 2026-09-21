<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use App\Controllers\Temuan;

/**
 * CR-HOTFIX-02 Part A: Session Reconciliation & Auth Guard Unit Test
 *
 * Verifies that the session key mismatch ('logged_in' vs 'is_logged_in') is reconciled
 * and that all material/asset endpoints correctly allow authenticated requests without
 * triggering false "Sesi Berakhir" / 401 Unauthorized responses.
 *
 * @internal
 */
final class MaterialPickerAuthSessionTest extends CIUnitTestCase
{
    private Temuan $controller;
    protected $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = Services::session();
        $this->session->destroy();

        $request = Services::request();
        $request->setGlobal('get', []);
        $request->setGlobal('post', []);
        $request->setGlobal('request', []);

        $this->controller = new Temuan();
        $this->controller->initController($request, Services::response(), Services::logger());
    }

    /**
     * Test that an unauthenticated request to ajaxMaterialPicker returns 401 Unauthorized
     */
    public function testAjaxMaterialPickerUnauthenticatedReturns401(): void
    {
        $this->session->destroy();
        $_SESSION = [];

        $response = $this->controller->ajaxMaterialPicker();

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertEquals('ERROR', $body['status'] ?? null);
    }

    /**
     * Test that an authenticated request with 'logged_in' => true returns 200 (not 401)
     */
    public function testAjaxMaterialPickerWithLoggedInSessionReturnsNon401(): void
    {
        $this->session->set([
            'user_id'   => 1,
            'logged_in' => true,
            'user_role' => 'administrator',
        ]);

        $response = $this->controller->ajaxMaterialPicker();

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /**
     * Test that an authenticated request with 'is_logged_in' => true returns 200 (not 401)
     */
    public function testAjaxMaterialPickerWithIsLoggedInSessionReturnsNon401(): void
    {
        $this->session->set([
            'user_id'      => 1,
            'is_logged_in' => true,
            'user_role'    => 'administrator',
        ]);

        $response = $this->controller->ajaxMaterialPicker();

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /**
     * Test that ajaxAssetCoordinates endpoint returns 401 when unauthenticated
     */
    public function testAjaxAssetCoordinatesUnauthenticatedReturns401(): void
    {
        $this->session->destroy();
        $_SESSION = [];

        $response = $this->controller->ajaxAssetCoordinates();

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test that ajaxAssetCoordinates returns 400 when authenticated but asset_id is missing
     */
    public function testAjaxAssetCoordinatesMissingAssetIdReturns400(): void
    {
        $this->session->set([
            'user_id'   => 1,
            'logged_in' => true,
            'user_role' => 'administrator',
        ]);

        $response = $this->controller->ajaxAssetCoordinates();

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertEquals('INVALID_ASSET', $body['status'] ?? null);
    }

    /**
     * Test that ajaxJtmAccessories returns 401 when unauthenticated
     */
    public function testAjaxJtmAccessoriesUnauthenticatedReturns401(): void
    {
        $this->session->destroy();
        $_SESSION = [];

        $response = $this->controller->ajaxJtmAccessories();

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test that ajaxJtmAccessories returns 200 with catalog when authenticated
     */
    public function testAjaxJtmAccessoriesAuthenticatedReturnsCatalog(): void
    {
        $this->session->set([
            'user_id'   => 1,
            'logged_in' => true,
            'user_role' => 'administrator',
        ]);

        $response = $this->controller->ajaxJtmAccessories();

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertEquals('SUCCESS', $body['status'] ?? null);
        $this->assertIsArray($body['accessories'] ?? null);
        $this->assertNotEmpty($body['accessories']);
    }
}
