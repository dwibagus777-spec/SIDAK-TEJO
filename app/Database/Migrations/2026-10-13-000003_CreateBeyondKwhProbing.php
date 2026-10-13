<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateBeyondKwhProbing extends Migration {
    public function up() {
        if (!$this->db->tableExists("beyond_kwh_probing")) {
            $this->forge->addField([
                "id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"auto_increment"=>true],
                "rekomendasi_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "temuan_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>false],
                "inspeksi_kubikel_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "asset_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "user_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "tanggal_probing" => ["type"=>"DATE","null"=>false],
                "uraian" => ["type"=>"TEXT","null"=>true],
                "created_at" => ["type"=>"DATETIME","null"=>true],
                "updated_at" => ["type"=>"DATETIME","null"=>true],
            ]);
            $this->forge->addKey("id", true);
            $this->forge->addKey("rekomendasi_id");
            $this->forge->addKey("temuan_id");
            $this->forge->createTable("beyond_kwh_probing", true);
        }
    }
    public function down() {
        if ($this->db->tableExists("beyond_kwh_probing")) $this->forge->dropTable("beyond_kwh_probing", true);
    }
}
