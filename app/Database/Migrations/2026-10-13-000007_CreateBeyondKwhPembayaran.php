<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateBeyondKwhPembayaran extends Migration {
    public function up() {
        if (!$this->db->tableExists("beyond_kwh_pembayaran")) {
            $this->forge->addField([
                "id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"auto_increment"=>true],
                "rekomendasi_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "temuan_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>false],
                "spkpo_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "penagihan_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "user_id" => ["type"=>"INT","constraint"=>11,"unsigned"=>true,"null"=>true],
                "tanggal_pembayaran" => ["type"=>"DATE","null"=>false],
                "nomor_spkpo" => ["type"=>"VARCHAR","constraint"=>100,"null"=>true],
                "nomor_invoice" => ["type"=>"VARCHAR","constraint"=>100,"null"=>true],
                "nomor_bapp" => ["type"=>"VARCHAR","constraint"=>100,"null"=>true],
                "nomor_bast" => ["type"=>"VARCHAR","constraint"=>100,"null"=>true],
                "nominal" => ["type"=>"DECIMAL","constraint"=>"18,2","null"=>true,"default"=>0],
                "keterangan" => ["type"=>"TEXT","null"=>true],
                "created_at" => ["type"=>"DATETIME","null"=>true],
                "updated_at" => ["type"=>"DATETIME","null"=>true],
            ]);
            $this->forge->addKey("id", true);
            $this->forge->addKey("temuan_id");
            $this->forge->addKey("spkpo_id");
            $this->forge->addKey("penagihan_id");
            $this->forge->createTable("beyond_kwh_pembayaran", true);
        }
    }
    public function down() {
        if ($this->db->tableExists("beyond_kwh_pembayaran")) $this->forge->dropTable("beyond_kwh_pembayaran", true);
    }
}
