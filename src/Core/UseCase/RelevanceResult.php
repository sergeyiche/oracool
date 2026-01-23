<?php

namespace App\Core\UseCase;

/**
 * Результат проверки релевантности
 */
readonly class RelevanceResult
{
    public function __construct(
        public bool $isRelevant,
        public float $score,
        public int $matchesFound,
        public array $similarEntries,
        public float $processingTimeMs
    ) {}

    /**
     * Получить наиболее релевантную запись
     */
    public function getTopMatch(): ?array
    {
        return $this->similarEntries[0] ?? null;
    }

    /**
     * Получить тексты похожих записей для контекста
     */
    public function getSimilarTexts(int $limit = 3): array
    {
        return array_slice(
            array_map(fn($entry) => $entry['text'], $this->similarEntries),
            0,
            $limit
        );
    }

    public function toArray(): array
    {
        return [
            'is_relevant' => $this->isRelevant,
            'score' => $this->score,
            'matches_found' => $this->matchesFound,
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }
}
