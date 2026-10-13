<?php
namespace App\Models;
use CodeIgniter\Model;
class BeyondKwhSpkPoModel extends Model {
    protected $table = "beyond_kwh_spkpo";
    protected $primaryKey = "id";
    protected $returnType = "array";
    protected $useTimestamps = true;
    protected $allowedFields = ["rekomendasi_id","temuan_id","asset_id","user_id","penawaran_id","tanggal_spkpo","tanggal_berlaku","tanggal_selesai_pekerjaan","nomor_spkpo","uraian_jenis_perbaikan","nominal","created_at","updated_at"];
}
