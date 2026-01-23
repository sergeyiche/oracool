<?php

namespace App\Adapter\Database;

use App\Core\Port\VectorSearchInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class PostgreSQLVectorSearchService implements VectorSearchInterface
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Находит похожие записи по вектору используя косинусную близость
     */
    public function findSimilar(
        array $vector, 
        string $userId, 
        float $threshold = 0.7, 
        int $limit = 5
    ): array {
        try {
            // Преобразуем вектор в JSON для PostgreSQL
            $vectorJson = json_encode($vector);
            
            // SQL запрос с косинусной близостью
            // Используем JSONB для хранения, поэтому считаем схожесть вручную
            $sql = "
                WITH similarities AS (
                    SELECT 
                        id,
                        text,
                        embedding,
                        source,
                        source_id,
                        tags,
                        metadata,
                        created_at,
                        calculate_cosine_similarity(embedding::jsonb, :vector::jsonb) as similarity
                    FROM knowledge_base
                    WHERE user_id = :user_id
                        AND embedding IS NOT NULL
                )
                SELECT *
                FROM similarities
                WHERE similarity >= :threshold
                ORDER BY similarity DESC
                LIMIT :limit
            ";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue('vector', $vectorJson);
            $stmt->bindValue('user_id', $userId);
            $stmt->bindValue('threshold', $threshold);
            $stmt->bindValue('limit', $limit);
            
            $result = $stmt->executeQuery();
            $rows = $result->fetchAllAssociative();
            
            // Преобразуем JSONB поля обратно в массивы
            return array_map(function($row) {
                return [
                    'id' => $row['id'],
                    'text' => $row['text'],
                    'embedding' => json_decode($row['embedding'], true),
                    'source' => $row['source'],
                    'source_id' => $row['source_id'],
                    'tags' => json_decode($row['tags'], true),
                    'metadata' => json_decode($row['metadata'], true),
                    'created_at' => $row['created_at'],
                    'similarity' => (float)$row['similarity']
                ];
            }, $rows);
            
        } catch (\Exception $e) {
            $this->logger->error('Vector search failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'threshold' => $threshold
            ]);
            throw new \RuntimeException('Failed to search similar vectors: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Добавляет вектор в базу знаний
     */
    public function addVector(
        array $vector, 
        string $userId, 
        string $text, 
        array $metadata = []
    ): string {
        try {
            $id = $this->generateUuid();
            $vectorJson = json_encode($vector);
            $source = $metadata['source'] ?? 'manual';
            $sourceId = $metadata['source_id'] ?? null;
            $tags = json_encode($metadata['tags'] ?? []);
            $embeddingModel = $metadata['embedding_model'] ?? null;
            
            unset($metadata['source'], $metadata['source_id'], $metadata['tags'], $metadata['embedding_model']);
            $metadataJson = json_encode($metadata);
            
            $sql = "
                INSERT INTO knowledge_base (
                    id, user_id, text, embedding, embedding_model, 
                    source, source_id, tags, metadata, created_at
                ) VALUES (
                    :id, :user_id, :text, :embedding::jsonb, :embedding_model,
                    :source, :source_id, :tags::jsonb, :metadata::jsonb, NOW()
                )
            ";
            
            $this->connection->executeStatement($sql, [
                'id' => $id,
                'user_id' => $userId,
                'text' => $text,
                'embedding' => $vectorJson,
                'embedding_model' => $embeddingModel,
                'source' => $source,
                'source_id' => $sourceId,
                'tags' => $tags,
                'metadata' => $metadataJson
            ]);
            
            return $id;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to add vector', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'text_length' => strlen($text)
            ]);
            throw new \RuntimeException('Failed to add vector: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Генерирует UUID v4
     */
    private function generateUuid(): string
    {
        $result = $this->connection->executeQuery('SELECT gen_random_uuid() as uuid');
        return $result->fetchOne();
    }
}
