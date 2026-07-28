<?php

namespace App\Services\ConversationalAI\Services;

class TextNormalizationService
{
    /**
     * Common Indonesian shorthand dictionary
     */
    protected array $shorthandMap = [
        'sdg'  => 'sedang',
        'yg'   => 'yang',
        'tlg'  => 'tolong',
        'brp'  => 'berapa',
        'dgn'  => 'dengan',
        'utk'  => 'untuk',
        'bs'   => 'bisa',
        'blm'  => 'belum',
        'tdk'  => 'tidak',
        'gak'  => 'tidak',
        'nggak' => 'tidak',
        'klo'  => 'kalau',
        'sy'   => 'saya',
        'u/'   => 'untuk',
        'lrg'  => 'lorong',
        'sm'   => 'sama'
    ];

    /**
     * Technical PLN Jargon dictionary
     */
    protected array $plnJargonMap = [
        'har'   => 'pemeliharaan',
        'gardu' => 'gardu distribusi',
        'pdkb'  => 'pekerjaan dalam keadaan bertegangan',
        'ulp'   => 'unit layanan pelanggan',
        'up3'   => 'unit pelaksana pelayanan pelanggan',
        'row'   => 'right of way perantingan pohon',
        'ph'    => 'perabasan pohon'
    ];

    /**
     * Javanese dialect to Indonesian dictionary
     */
    protected array $javaneseMap = [
        'piye'    => 'bagaimana',
        'prive'   => 'bagaimana',
        'ono'     => 'ada',
        'sing'    => 'yang',
        'piro'    => 'berapa',
        'durung'  => 'belum',
        'wis'     => 'sudah',
        'marang'  => 'ke',
        'nang'    => 'di',
        'karo'    => 'dengan',
        'opo'     => 'apa',
        'saiki'   => 'sekarang',
        'golek'   => 'cari',
        'goleki'  => 'carikan',
        'isok'    => 'bisa'
    ];

    public function normalize(string $text): array
    {
        $rawText = trim($text);
        if (empty($rawText)) {
            return ['normalized' => '', 'tokens' => [], 'detected_language' => 'id'];
        }

        // Lowercase & clean special punctuation
        $cleanText = strtolower($rawText);
        $cleanText = preg_replace('/[^\w\s-]/u', ' ', $cleanText);

        $words = preg_split('/\s+/', $cleanText);
        $normalizedWords = [];
        $hasJavanese = false;

        foreach ($words as $word) {
            // Check Javanese dictionary
            if (isset($this->javaneseMap[$word])) {
                $normalizedWords[] = $this->javaneseMap[$word];
                $hasJavanese = true;
                continue;
            }

            // Check PLN Jargon dictionary
            if (isset($this->plnJargonMap[$word])) {
                $normalizedWords[] = $this->plnJargonMap[$word];
                continue;
            }

            // Check Shorthand dictionary
            if (isset($this->shorthandMap[$word])) {
                $normalizedWords[] = $this->shorthandMap[$word];
                continue;
            }

            // Typo auto-correction for common domain terms
            $corrected = $this->correctDomainTypo($word);
            $normalizedWords[] = $corrected;
        }

        $normalizedString = implode(' ', $normalizedWords);

        return [
            'original'          => $rawText,
            'normalized'        => $normalizedString,
            'tokens'            => $normalizedWords,
            'detected_language' => $hasJavanese ? 'jv' : 'id'
        ];
    }

    private function correctDomainTypo(string $word): string
    {
        $domainTerms = ['temuan', 'penyulang', 'section', 'inspeksi', 'eviden', 'sidoarjo', 'konstruksi', 'hotspot'];
        
        foreach ($domainTerms as $term) {
            if (levenshtein($word, $term) === 1 && strlen($word) > 4) {
                return $term;
            }
        }

        return $word;
    }
}
