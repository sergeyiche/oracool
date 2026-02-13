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
        private LoggerInterface $logger,
        private string $globalKnowledgeUserId = '858361483'
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
            
            $similar = $this->findSimilarAcrossKnowledgeScopes(
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

    private function findSimilarAcrossKnowledgeScopes(
        array $vector,
        string $userId,
        float $threshold,
        int $limit
    ): array {
        $allRows = [];
        foreach ($this->getSearchUserIds($userId) as $searchUserId) {
            $rows = $this->vectorSearch->findSimilar(
                vector: $vector,
                userId: $searchUserId,
                threshold: $threshold,
                limit: $limit
            );

            foreach ($rows as $row) {
                $row['matched_user_id'] = $searchUserId;
                $allRows[] = $row;
            }
        }

        if (empty($allRows)) {
            return [];
        }

        $deduped = [];
        foreach ($allRows as $row) {
            $key = sha1(trim((string) ($row['text'] ?? '')));
            if (!isset($deduped[$key]) || (float) $row['similarity'] > (float) $deduped[$key]['similarity']) {
                $deduped[$key] = $row;
            }
        }

        $merged = array_values($deduped);
        usort($merged, fn(array $a, array $b) => (float) $b['similarity'] <=> (float) $a['similarity']);

        return array_slice($merged, 0, $limit);
    }

    private function getSearchUserIds(string $userId): array
    {
        $ids = [$userId];
        if ($this->globalKnowledgeUserId !== '' && $this->globalKnowledgeUserId !== $userId) {
            $ids[] = $this->globalKnowledgeUserId;
        }

        return array_values(array_unique($ids));
    }
}
