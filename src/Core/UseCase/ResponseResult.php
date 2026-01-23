<?php

namespace App\Core\UseCase;

/**
 * Результат генерации ответа
 */
readonly class ResponseResult
{
    public function __construct(
        public string $response,
        public float $relevanceScore,
        public int $contextEntriesUsed,
        public float $processingTimeMs,
        public string $embeddingModel,
        public string $llmModel
    ) {}

    public function hasHighConfidence(): bool
    {
        return $this->relevanceScore >= 0.8;
    }

    public function toArray(): array
    {
        return [
            'response' => $this->response,
            'relevance_score' => $this->relevanceScore,
            'context_entries_used' => $this->contextEntriesUsed,
            'processing_time_ms' => $this->processingTimeMs,
            'embedding_model' => $this->embeddingModel,
            'llm_model' => $this->llmModel,
            'high_confidence' => $this->hasHighConfidence(),
        ];
    }
}
