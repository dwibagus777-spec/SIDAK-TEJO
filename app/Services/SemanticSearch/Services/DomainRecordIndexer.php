<?php

namespace App\Services\SemanticSearch\Services;

use App\Services\SemanticSearch\Contracts\EmbeddingProviderInterface;
use App\Services\SemanticSearch\Contracts\VectorStoreInterface;
use Config\Database;

class DomainRecordIndexer
{
    protected EmbeddingProviderInterface $embeddingProvider;
    protected VectorStoreInterface $vectorStore;

    public function __construct(
        EmbeddingProviderInterface $embeddingProvider,
        VectorStoreInterface $vectorStore
    ) {
        $this->embeddingProvider = $embeddingProvider;
        $this->vectorStore        = $vectorStore;
    }

    public function indexTemuan(array $temuan): bool
    {
        $id = $temuan['id'] ?? null;
        if (!$id) {
            return false;
        }

        $textToEmbed = "Temuan {$temuan['nomor_temuan']}. Jenis: {$temuan['jenis_temuan']}. Detail: {$temuan['detail_temuan']}. "
            . "Lokasi: {$temuan['alamat']}. Status: {$temuan['status']}. Prioritas: {$temuan['prioritas']}.";

        $vector = $this->embeddingProvider->embedQuery($textToEmbed);
        $payload = [
            'type'         => 'TEMUAN',
            'id'           => $id,
            'nomor_temuan' => $temuan['nomor_temuan'] ?? '',
            'jenis_temuan' => $temuan['jenis_temuan'] ?? '',
            'detail'       => $temuan['detail_temuan'] ?? '',
            'status'       => $temuan['status'] ?? '',
            'prioritas'    => $temuan['prioritas'] ?? '',
            'ulp_id'       => $temuan['ulp_id'] ?? null
        ];

        return $this->vectorStore->upsert('domain_records', "temuan_{$id}", $vector, $payload);
    }

    public function indexPenyulang(array $penyulang): bool
    {
        $id = $penyulang['id'] ?? null;
        if (!$id) {
            return false;
        }

        $textToEmbed = "Penyulang {$penyulang['nama_penyulang']} (Kode: {$penyulang['kode_penyulang']}). Status: {$penyulang['status']}.";
        $vector = $this->embeddingProvider->embedQuery($textToEmbed);
        $payload = [
            'type'      => 'PENYULANG',
            'id'        => $id,
            'name'      => $penyulang['nama_penyulang'],
            'ulp_id'    => $penyulang['ulp_id'] ?? null
        ];

        return $this->vectorStore->upsert('domain_records', "penyulang_{$id}", $vector, $payload);
    }
}
