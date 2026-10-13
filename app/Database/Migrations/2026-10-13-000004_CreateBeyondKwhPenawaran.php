<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateBeyondKwhPenawaran extends Migration {
    public function up() {
        if (!$this->db->tableExists("beyond_kwh_penawaran")) {
            $this->forge->addField([
                "id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"auto_increment"=>true],
                "rekomendasi_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "temuan_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>false],
                "asset_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "user_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "tanggal_penawaran" => ["type"=>"DATE","null"=>false],
                "nomor_penawaran" => ["type"=>"VARCHAR","constraint"=>100,"null"=>true],
                "uraian" => ["type"=>"TEXT","null"=>true],
                "nominal" => ["type"=>"DECIMAL","constraint"=>"18,2","null"=>true,"default"=>0],
                "created_at" => ["type"=>"DATETIME","null"=>true],
                "updated_at" => ["type"=>"DATETIME","null"=>true],
            ]);
            $this->forge->addKey("id", true);
            $this->forge->addKey("temuan_id");
            $this->forge->createTable("beyond_kwh_penawaran", true);
        }
    }
    public function down() {
        if ($this->db->tableExists("beyond_kwh_penawaran")) $this->forge->dropTable("beyond_kwh_penawaran", true);
    }
}
