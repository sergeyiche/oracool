<?php

namespace App\Core\UseCase;

use App\Core\Port\EmbeddingServiceInterface;
use App\Core\Port\VectorSearchInterface;
use Psr\Log\LoggerInterface;

/**
 * Use Case: Проверка релевантности входящего сообщения
 * Определяет, нужно ли боту отвечать на это сообщение
 */
class CheckRelevance
{
    public function __construct(
        private EmbeddingServiceInterface $embeddingService,
        private VectorSearchInterface $vectorSearch,
        private LoggerInterface $logger
    ) {}

    /**
     * Проверяет релевантность сообщения для пользователя
     * 
     * @param string $message Текст сообщения
     * @param string $userId UUID пользователя
     * @param float $threshold Порог релевантности (0.0-1.0)
     * @return RelevanceResult
     */
    public function execute(
        string $message, 
        string $userId, 
        float $threshold = 0.7
    ): RelevanceResult {
        $startTime = microtime(true);

        try {
            // 1. Векторизуем входящее сообщение
            $this->logger->debug('Vectorizing message', [
                'message_length' => strlen($message),
                'user_id' => $userId
            ]);
            
            $vector = $this->embeddingService->embed($message);
            
            // 2. Ищем похожие записи в базе знаний
            $this->logger->debug('Searching similar entries', [
                'threshold' => $threshold,
                'vector_dimension' => count($vector)
            ]);
            
            $similar = $this->vectorSearch->findSimilar(
                vector: $vector,
                userId: $userId,
                threshold: $threshold,
                limit: 5
            );

            // 3. Анализируем результаты
            $isRelevant = !empty($similar);
            $maxSimilarity = !empty($similar) ? $similar[0]['similarity'] : 0.0;
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Relevance check completed', [
                'is_relevant' => $isRelevant,
                'max_similarity' => $maxSimilarity,
                'matches_found' => count($similar),
                'duration_ms' => $duration
            ]);

            return new RelevanceResult(
                isRelevant: $isRelevant,
                score: $maxSimilarity,
                matchesFound: count($similar),
                similarEntries: $similar,
                processingTimeMs: $duration
            );

        } catch (\Exception $e) {
            $this->logger->error('Relevance check failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            throw new \RuntimeException(
                'Failed to check relevance: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Быстрая проверка без деталей
     */
    public function isRelevant(
        string $message,
        string $userId,
        float $threshold = 0.7
    ): bool {
        $result = $this->execute($message, $userId, $threshold);
        return $result->isRelevant;
    }
}
