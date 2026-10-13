<?php
namespace App\Models;
use CodeIgniter\Model;
class BeyondKwhProbingModel extends Model {
    protected $table = "beyond_kwh_probing";
    protected $primaryKey = "id";
    protected $returnType = "array";
    protected $useTimestamps = true;
    protected $allowedFields = ["rekomendasi_id","temuan_id","inspeksi_kubikel_id","asset_id","user_id","tanggal_probing","uraian"];
}
