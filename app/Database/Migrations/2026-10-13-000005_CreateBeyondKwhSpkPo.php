<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateBeyondKwhSpkPo extends Migration {
    public function up() {
        if (!$this->db->tableExists("beyond_kwh_spkpo")) {
            $this->forge->addField([
                "id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"auto_increment"=>true],
                "rekomendasi_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "temuan_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>false],
                "asset_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "user_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "penawaran_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "tanggal_spkpo" => ["type"=>"DATE","null"=>false],
                "tanggal_berlaku" => ["type"=>"DATE","null"=>true],
                "tanggal_selesai_pekerjaan" => ["type"=>"DATE","null"=>true],
                "nomor_spkpo" => ["type"=>"VARCHAR","constraint"=>100,"null"=>true],
                "uraian_jenis_perbaikan" => ["type"=>"TEXT","null"=>true],
                "nominal" => ["type"=>"DECIMAL","constraint"=>"18,2","null"=>true,"default"=>0],
                "created_at" => ["type"=>"DATETIME","null"=>true],
                "updated_at" => ["type"=>"DATETIME","null"=>true],
            ]);
            $this->forge->addKey("id", true);
            $this->forge->addKey("temuan_id");
            $this->forge->addKey("nomor_spkpo");
            $this->forge->createTable("beyond_kwh_spkpo", true);
        }
    }
    public function down() {
        if ($this->db->tableExists("beyond_kwh_spkpo")) $this->forge->dropTable("beyond_kwh_spkpo", true);
    }
}
