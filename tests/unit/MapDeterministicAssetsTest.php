<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Config\Database;
use App\Services\GisTranslineService;

class MapDeterministicAssetsTest extends TestCase
{
    public function testGisTranslineServicePredictionMethod(): void
    {
        $db = Database::connect();
        
        // Ensure tables exist in SQLite test memory
        if (!$db->tableExists('assets')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'null' => true],
                'section_id' => ['type' => 'INT', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        if (!$db->tableExists('gis_translines')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id' => ['type' => 'INT', 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'null' => true],
                'source_asset_id' => ['type' => 'INT'],
                'target_asset_id' => ['type' => 'INT'],
                'is_active' => ['type' => 'INT', 'default' => 1],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        $service = new GisTranslineService($db);
        $res = $service->predictTranslineSection(1);

        $this->assertIsArray($res);
        $this->assertSame('success', $res['status']);
        $this->assertArrayHasKey('total_assets', $res);
        $this->assertArrayHasKey('unresolved_count', $res);
        $this->assertArrayHasKey('predicted_count', $res);
        $this->assertArrayHasKey('predictions', $res);
    }
}
