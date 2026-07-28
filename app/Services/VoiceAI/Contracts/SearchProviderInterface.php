<?php

namespace App\Services\VoiceAI\Contracts;

interface SearchProviderInterface
{
    /**
     * Search system domain data (Temuan, Eviden, Penyulang, Section, ULP)
     *
     * @param string $query Natural language query
     * @param array $filters Entity/Scoping filters (ulp_id, status, prioritas)
     * @param int $limit Max results
     * @return array Array of formatted search results
     */
    public function searchSystemData(string $query, array $filters = [], int $limit = 5): array;

    /**
     * Search knowledge base / domain SOP / guidelines
     *
     * @param string $query Natural language query
     * @return array Array of knowledge snippet matches
     */
    public function searchKnowledgeBase(string $query): array;

    /**
     * Get search provider name
     */
    public function getProviderName(): string;
}
