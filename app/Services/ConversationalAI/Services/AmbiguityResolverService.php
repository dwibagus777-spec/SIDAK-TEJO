<?php

namespace App\Services\ConversationalAI\Services;

class AmbiguityResolverService
{
    /**
     * Required parameter slots per Intent
     */
    protected array $intentSlots = [
        'DELETE_TEMUAN' => [
            'required' => ['temuan_id', 'nomor_temuan'],
            'prompt'   => 'Temuan dengan nomor berapa atau di lokasi mana yang ingin Anda hapus?'
        ],
        'FILTER_TEMUAN_ULP' => [
            'required' => ['ulp_name', 'ulp_id'],
            'prompt'   => 'Untuk ULP mana yang ingin Anda tampilkan data temuannya?'
        ],
        'UPDATE_STATUS' => [
            'required' => ['temuan_id', 'new_status'],
            'prompt'   => 'Mohon sebutkan nomor temuan dan status baru (Proses/Selesai) yang diinginkan.'
        ]
    ];

    public function checkAmbiguity(string $intent, array $extractedParams = []): array
    {
        if (!isset($this->intentSlots[$intent])) {
            return [
                'is_ambiguous'         => false,
                'clarification_prompt' => null,
                'missing_slots'        => []
            ];
        }

        $rule = $this->intentSlots[$intent];
        $missing = [];

        foreach ($rule['required'] as $slot) {
            if (empty($extractedParams[$slot])) {
                $missing[] = $slot;
            }
        }

        if (!empty($missing) && count($missing) === count($rule['required'])) {
            return [
                'is_ambiguous'         => true,
                'clarification_prompt' => $rule['prompt'],
                'missing_slots'        => $missing
            ];
        }

        return [
            'is_ambiguous'         => false,
            'clarification_prompt' => null,
            'missing_slots'        => []
        ];
    }
}
