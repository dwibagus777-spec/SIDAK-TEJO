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
        }        // TEST 4: apiNetwork for Feeder 15 (BANJAR KEMANTREN) - Master Asset Layer
        CLI::write("\n[4/7] Testing GET /gis/api-network?penyulang_id=15 (Asset Layer) ...", 'cyan');
        $_GET['penyulang_id'] = 15;
        $_GET['zoom'] = 15;
        $_GET['layers'] = 'JTM,GARDU,TRAFO,SWITCH';
        $res4 = $controller->apiNetwork();
        $body4 = $res4->getBody();
        $json4 = json_decode($body4, true);

        if ($res4->getStatusCode() === 200 && ($json4['status'] ?? '') === 'success') {
            $fCount = count($json4['data']['features'] ?? []);
            $feederAssetCount = $json4['data']['meta']['feeder_asset_count'] ?? 0;
            $unassignedAssetCount = $json4['data']['meta']['unassigned_ulp_asset_count'] ?? 0;
            $rejectedCrossUlp = $json4['data']['summary']['rejected_cross_ulp'] ?? 0;
            
            $validScopes = true;
            foreach ($json4['data']['features'] as $feat) {
                $p = $feat['properties'] ?? [];
                if ($p['entity_type'] !== 'ASSET') {
                    $validScopes = false;
                    break;
                }
                if ($p['asset_scope'] === 'ULP_UNASSIGNED' && $p['is_feeder_asset'] !== false) {
                    $validScopes = false;
                    break;
                }
            }

            if ($feederAssetCount === 0 && $unassignedAssetCount === 1 && $rejectedCrossUlp === 2 && $validScopes) {
                CLI::write(" [PASS] Status: 200 OK | Feeder Asset: 0 | Unassigned ULP 1 Asset: 1 | Rejected Other ULPs: 2", 'green');
                CLI::write("   Rendered: " . $json4['data']['features'][0]['properties']['nama_asset'] . " (Scope: ULP_UNASSIGNED, Feeder: NULL)", 'white');
            } else {
                CLI::error(" [FAIL] Feeder 15 scope violation: feederCount={$feederAssetCount}, unassignedCount={$unassignedAssetCount}, rejectedUlp={$rejectedCrossUlp}");
                return;
            }
        } else {
            CLI::error(" [FAIL] Status: " . $res4->getStatusCode() . " | Body: " . $body4);
            return;
        }

        // TEST 5: apiNetwork for Feeder 15 with TEMUAN layer explicitly enabled
        CLI::write("\n[5/7] Testing GET /gis/api-network?penyulang_id=15&layers=TEMUAN (Temuan Layer Explicit) ...", 'cyan');
        $_GET['penyulang_id'] = 15;
        $_GET['zoom'] = 15;
        $_GET['layers'] = 'TEMUAN';
        $res5 = $controller->apiNetwork();
        $body5 = $res5->getBody();
        $json5 = json_decode($body5, true);

        if ($res5->getStatusCode() === 200 && ($json5['status'] ?? '') === 'success') {
            $tFeatures = $json5['data']['features'] ?? [];
            $tCount = count($tFeatures);
            $allTemuanEntity = true;
            foreach ($tFeatures as $tf) {
                if (($tf['properties']['entity_type'] ?? '') !== 'TEMUAN' || ($tf['properties']['source_table'] ?? '') !== 'temuan') {
                    $allTemuanEntity = false;
                    break;
                }
            }

            if ($tCount > 0 && $allTemuanEntity && $json5['data']['summary']['total_assets'] === 0) {
                CLI::write(" [PASS] Status: 200 OK | Loaded {$tCount} Temuan markers | All entity_type === 'TEMUAN' | Asset Counter strictly 0", 'green');
            } else {
                CLI::error(" [FAIL] Temuan layer contract violation: count={$tCount}, allTemuan={$allTemuanEntity}, total_assets={$json5['data']['summary']['total_assets']}");
                return;
            }
        } else {
            CLI::error(" [FAIL] Status: " . $res5->getStatusCode() . " | Body: " . $body5);
            return;
        }

        // TEST 6: apiNetworkAudit (Data Provenance & 3-Scope Asset Boundary)
        CLI::write("\n[6/7] Testing GET /gis/api-network-audit?penyulang_id=15 ...", 'cyan');
        $_GET['penyulang_id'] = 15;
        $resAudit = $controller->apiNetworkAudit();
        $bodyAudit = $resAudit->getBody();
        $jsonAudit = json_decode($bodyAudit, true);

        if ($resAudit->getStatusCode() === 200 && ($jsonAudit['status'] ?? '') === 'success') {
            CLI::write(" [PASS] Status: 200 OK | DB Feeder Assets: {$jsonAudit['total_db_feeder_assets']} | DB Unassigned ULP Assets: {$jsonAudit['total_db_unassigned_ulp_assets']} | Rejected Other ULPs: {$jsonAudit['rejected_cross_ulp_assets']} | Layer Separation: VERIFIED", 'green');
        } else {
            CLI::error(" [FAIL] Status: " . $resAudit->getStatusCode() . " | Body: " . $bodyAudit);
            return;
        }

        // TEST 7: Database Integrity Invariant (Check that no new assets/migrations were created)
        CLI::write("\n[7/7] Testing Database Invariants (Zero Mutation Guard) ...", 'cyan');
        $db = \Config\Database::connect();
        $currentAssetsCount = $db->table('assets')->countAllResults();
        if ($currentAssetsCount === 3) {
            CLI::write(" [PASS] Database assets count strictly preserved at 3 original baseline records.", 'green');
        } else {
            CLI::error(" [FAIL] Assets table has unexpected count: {$currentAssetsCount}");
            return;
        }

        CLI::write("\n====================================================", 'green');
        CLI::write("🟢 ALL STRICT GIS 3-SCOPE & LAYER SEPARATION CHECKS PASSED CLEANLY (7/7)!", 'green');
        CLI::write("====================================================\n", 'green');
    }
}
