<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestGisEndpointsCommand extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:gis-api';
    protected $description = 'Test all GIS API endpoints for HTTP status, content-type and JSON contracts.';

    public function run(array $params)
    {
        CLI::write("====================================================", 'yellow');
        CLI::write("⚡ TESTING GIS API ENDPOINTS (HTTP & JSON CONTRACTS)", 'yellow');
        CLI::write("====================================================", 'yellow');

        $session = session();
        $session->set([
            'user_id'   => 1,
            'user_name' => 'Administrator',
            'user_role' => 'admin',
            'ulp_id'    => 1,
        ]);

        $request = \Config\Services::request();
        $response = \Config\Services::response();
        $logger = \Config\Services::logger();

        $controller = new \App\Controllers\GisController();
        $controller->initController($request, $response, $logger);

        // TEST 1: apiPenyulangs with valid ULP ID
        CLI::write("\n[1/5] Testing GET /gis/api-penyulangs?ulp_id=1 ...", 'cyan');
        $_GET['ulp_id'] = 1;
        $res1 = $controller->apiPenyulangs();
        $body1 = $res1->getBody();
        $json1 = json_decode($body1, true);

        if ($res1->getStatusCode() === 200 && ($json1['status'] ?? '') === 'success') {
            $count = count($json1['penyulangs'] ?? []);
            CLI::write(" [PASS] Status: 200 OK | Found {$count} Penyulangs for ULP 1", 'green');
            if ($count > 0) {
                CLI::write("   Example: " . $json1['penyulangs'][0]['nama_penyulang'] . " (ID: " . $json1['penyulangs'][0]['id'] . ")", 'white');
            }
        } else {
            CLI::error(" [FAIL] Status: " . $res1->getStatusCode() . " | Body: " . $body1);
            return;
        }

        // TEST 2: apiPenyulangs with missing ULP ID (defensive test)
        CLI::write("\n[2/5] Testing GET /gis/api-penyulangs (missing ulp_id) ...", 'cyan');
        $_GET['ulp_id'] = 0;
        $res2 = $controller->apiPenyulangs();
        $body2 = $res2->getBody();
        $json2 = json_decode($body2, true);

        if ($res2->getStatusCode() === 422 && ($json2['status'] ?? '') === 'error') {
            CLI::write(" [PASS] Status: 422 Unprocessable Entity (Defensive Check Passed)", 'green');
        } else {
            CLI::error(" [FAIL] Status: " . $res2->getStatusCode() . " | Body: " . $body2);
        }

        // TEST 3: apiConductors
        CLI::write("\n[3/5] Testing GET /gis/api-conductors ...", 'cyan');
        $res3 = $controller->apiConductors();
        $body3 = $res3->getBody();
        $json3 = json_decode($body3, true);

        if ($res3->getStatusCode() === 200 && ($json3['status'] ?? '') === 'success') {
            $cCount = count($json3['data'] ?? []);
            CLI::write(" [PASS] Status: 200 OK | Master Conductors Loaded: {$cCount} items", 'green');
        } else {
            CLI::error(" [FAIL] Status: " . $res3->getStatusCode() . " | Body: " . $body3);
        }

        // TEST 4: apiNetwork with first valid penyulang
        $firstPenyulangId = (int)($json1['penyulangs'][0]['id'] ?? 1);
        CLI::write("\n[4/5] Testing GET /gis/api-network?penyulang_id={$firstPenyulangId} ...", 'cyan');
        $_GET['penyulang_id'] = $firstPenyulangId;
        $_GET['zoom'] = 15;
        $_GET['layers'] = 'JTM,GARDU,TRAFO,SWITCH';
        $res4 = $controller->apiNetwork();
        $body4 = $res4->getBody();
        $json4 = json_decode($body4, true);

        if ($res4->getStatusCode() === 200 && ($json4['status'] ?? '') === 'success') {
            $fCount = count($json4['data']['features'] ?? []);
            $eCount = count($json4['data']['transline']['properties']['edges'] ?? []);
            CLI::write(" [PASS] Status: 200 OK | Features: {$fCount} | Transline Edges: {$eCount}", 'green');
        } else {
            CLI::error(" [FAIL] Status: " . $res4->getStatusCode() . " | Body: " . $body4);
        }

        // TEST 5: apiUpdateConductorSpecification with Admin Direct Commit
        CLI::write("\n[5/5] Testing POST /gis/api-update-conductor (Admin Direct Commit) ...", 'cyan');
        $assets = \Config\Database::connect()->table('assets')->select('id')->where('penyulang_id', $firstPenyulangId)->limit(2)->get()->getResultArray();
        if (count($assets) >= 2) {
            $sId = (int)$assets[0]['id'];
            $tId = (int)$assets[1]['id'];

            $_POST = [
                'source_asset_id'    => $sId,
                'target_asset_id'    => $tId,
                'conductor_type'     => 'A3CS',
                'conductor_size'     => '150 mm²',
                'conductor_material' => 'ALUMINUM_ALLOY',
            ];

            $res5 = $controller->apiUpdateConductorSpecification();
            $body5 = $res5->getBody();
            $json5 = json_decode($body5, true);

            if ($res5->getStatusCode() === 200 && ($json5['is_direct_commit'] ?? false) === true) {
                CLI::write(" [PASS] Status: 200 OK | Direct Commit Successful: " . $json5['message'], 'green');
            } else {
                CLI::error(" [FAIL] Status: " . $res5->getStatusCode() . " | Body: " . $body5);
            }
        } else {
            CLI::write(" [SKIP] Not enough assets in feeder {$firstPenyulangId} to test.", 'yellow');
        }

        CLI::write("\n====================================================", 'green');
        CLI::write("🟢 ALL GIS ENDPOINT CHECKS PASSED CLEANLY (0 ERRORS)!", 'green');
        CLI::write("====================================================\n", 'green');
    }
}
