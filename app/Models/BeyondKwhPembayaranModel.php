<?php
namespace App\Models;
use CodeIgniter\Model;
class BeyondKwhPembayaranModel extends Model {
    protected $table = "beyond_kwh_pembayaran";
    protected $primaryKey = "id";
    protected $returnType = "array";
    protected $useTimestamps = true;
    protected $allowedFields = ["rekomendasi_id","temuan_id","spkpo_id","penagihan_id","user_id","tanggal_pembayaran","nomor_spkpo","nomor_invoice","nomor_bapp","nomor_bast","nominal","keterangan","created_at","updated_at"];
}
