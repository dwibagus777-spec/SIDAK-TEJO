<?php
namespace App\Models;
use CodeIgniter\Model;
class BeyondKwhPenagihanModel extends Model {
    protected $table = "beyond_kwh_penagihan";
    protected $primaryKey = "id";
    protected $returnType = "array";
    protected $useTimestamps = true;
    protected $allowedFields = ["rekomendasi_id","temuan_id","spkpo_id","user_id","tanggal_penagihan","nomor_spkpo","nomor_bapp","nomor_bast","nominal","keterangan","created_at","updated_at"];
}
