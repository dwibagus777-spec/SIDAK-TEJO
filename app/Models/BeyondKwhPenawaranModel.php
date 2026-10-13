<?php
namespace App\Models;
use CodeIgniter\Model;
class BeyondKwhPenawaranModel extends Model {
    protected $table = "beyond_kwh_penawaran";
    protected $primaryKey = "id";
    protected $returnType = "array";
    protected $useTimestamps = true;
    protected $allowedFields = ["rekomendasi_id","temuan_id","asset_id","user_id","tanggal_penawaran","nomor_penawaran","uraian","nominal","created_at","updated_at"];
}
