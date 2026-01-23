<?php

namespace App\Repository;

use App\Core\Domain\KnowledgeBase\KnowledgeBaseEntry;
use App\Core\Port\KnowledgeBaseRepositoryInterface;
use Doctrine\DBAL\Connection;

/**
 * Doctrine-реализация репозитория базы знаний
 */
class DoctrineKnowledgeBaseRepository implements KnowledgeBaseRepositoryInterface
{
    public function __construct(
        private Connection $connection
    ) {}

    public function findById(string $id): ?KnowledgeBaseEntry
    {
        $sql = 'SELECT * FROM knowledge_base WHERE id = :id';
        $data = $this->connection->fetchAssociative($sql, ['id' => $id]);

        return $data ? $this->hydrate($data) : null;
    }

    public function findByUserId(string $userId): array
    {
        $sql = 'SELECT * FROM knowledge_base WHERE user_id = :user_id ORDER BY created_at DESC';
        $results = $this->connection->fetchAllAssociative($sql, ['user_id' => $userId]);

        return array_map(fn($data) => $this->hydrate($data), $results);
    }

    public function save(KnowledgeBaseEntry $entry): void
    {
        $existing = $this->findById($entry->getId());
        
        $data = [
            'id' => $entry->getId(),
            'user_id' => $entry->getUserId(),
            'text' => $entry->getText(),
            'embedding' => $entry->hasEmbedding() 
                ? json_encode($entry->getEmbedding()) 
                : null,
            'embedding_model' => $entry->getEmbeddingModel(),
            'source' => $entry->getSource(),
            'source_id' => $entry->getSourceId(),
            'tags' => json_encode($entry->getTags()),
            'metadata' => json_encode($entry->getMetadata()),
        ];

        if ($existing) {
            // UPDATE
            $this->connection->update('knowledge_base', $data, ['id' => $entry->getId()]);
        } else {
            // INSERT
            $data['created_at'] = $entry->getCreatedAt()->format('Y-m-d H:i:s');
            $this->connection->insert('knowledge_base', $data);
        }
    }

    public function delete(string $id): void
    {
        $this->connection->delete('knowledge_base', ['id' => $id]);
    }

    public function bulkImport(array $entries): void
    {
        $this->connection->beginTransaction();

        try {
            foreach ($entries as $entry) {
                if ($entry instanceof KnowledgeBaseEntry) {
                    $this->save($entry);
                }
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function countByUserId(string $userId): int
    {
        $sql = 'SELECT COUNT(*) FROM knowledge_base WHERE user_id = :user_id';
        return (int) $this->connection->fetchOne($sql, ['user_id' => $userId]);
    }

    /**
     * Поиск по тексту (полнотекстовый поиск)
     */
    public function searchByText(string $userId, string $query, int $limit = 10): array
    {
        $sql = "
            SELECT * FROM knowledge_base 
            WHERE user_id = :user_id 
            AND text ILIKE :query
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $results = $this->connection->fetchAllAssociative($sql, [
            'user_id' => $userId,
            'query' => "%$query%",
            'limit' => $limit
        ]);

        return array_map(fn($data) => $this->hydrate($data), $results);
    }

    /**
     * Поиск по источнику
     */
    public function findBySource(string $userId, string $source, ?string $sourceId = null): array
    {
        $sql = 'SELECT * FROM knowledge_base WHERE user_id = :user_id AND source = :source';
        $params = ['user_id' => $userId, 'source' => $source];

        if ($sourceId !== null) {
            $sql .= ' AND source_id = :source_id';
            $params['source_id'] = $sourceId;
        }

        $sql .= ' ORDER BY created_at DESC';
        
        $results = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(fn($data) => $this->hydrate($data), $results);
    }

    /**
     * Поиск по тегам
     */
    public function findByTags(string $userId, array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        // Используем JSONB оператор @> для поиска по тегам
        $sql = "
            SELECT * FROM knowledge_base 
            WHERE user_id = :user_id 
            AND tags::jsonb @> :tags::jsonb
            ORDER BY created_at DESC
        ";

        $results = $this->connection->fetchAllAssociative($sql, [
            'user_id' => $userId,
            'tags' => json_encode($tags)
        ]);

        return array_map(fn($data) => $this->hydrate($data), $results);
    }

    /**
     * Гидрация данных из БД в доменную модель
     */
    private function hydrate(array $data): KnowledgeBaseEntry
    {
        $embedding = null;
        if (!empty($data['embedding'])) {
            $embedding = is_string($data['embedding']) 
                ? json_decode($data['embedding'], true)
                : $data['embedding'];
        }

        return new KnowledgeBaseEntry(
            id: $data['id'],
            userId: $data['user_id'],
            text: $data['text'],
            embedding: $embedding,
            embeddingModel: $data['embedding_model'] ?? null,
            source: $data['source'] ?? 'manual',
            sourceId: $data['source_id'] ?? null,
            tags: json_decode($data['tags'] ?? '[]', true),
            metadata: json_decode($data['metadata'] ?? '{}', true),
            createdAt: isset($data['created_at']) 
                ? new \DateTimeImmutable($data['created_at']) 
                : null
        );
    }
}
