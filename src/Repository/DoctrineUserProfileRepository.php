<?php

namespace App\Repository;

use App\Core\Domain\User\UserProfile;
use App\Core\Port\UserProfileRepositoryInterface;
use Doctrine\DBAL\Connection;

/**
 * Doctrine-реализация репозитория профилей пользователей
 */
class DoctrineUserProfileRepository implements UserProfileRepositoryInterface
{
    public function __construct(
        private Connection $connection
    ) {}

    public function findById(string $id): ?UserProfile
    {
        $sql = 'SELECT * FROM user_profiles WHERE id = :id';
        $data = $this->connection->fetchAssociative($sql, ['id' => $id]);

        return $data ? $this->hydrate($data) : null;
    }

    public function findByUserId(string $userId): ?UserProfile
    {
        $sql = 'SELECT * FROM user_profiles WHERE user_id = :user_id';
        $data = $this->connection->fetchAssociative($sql, ['user_id' => $userId]);

        return $data ? $this->hydrate($data) : null;
    }

    public function save(UserProfile $profile): void
    {
        $existing = $this->findById($profile->getId());
        
        $data = [
            'id' => $profile->getId(),
            'user_id' => $profile->getUserId(),
            'communication_style' => $profile->getCommunicationStyle(),
            'response_length' => $profile->getResponseLength(),
            'use_emojis' => $profile->useEmojis(),
            'key_interests' => json_encode($profile->getKeyInterests()),
            'example_responses' => json_encode($profile->getExampleResponses()),
            'relevance_threshold' => $profile->getRelevanceThreshold(),
            'bot_mode' => $profile->getBotMode(),
            'embedding_provider' => $profile->getEmbeddingProvider(),
            'embedding_model' => $profile->getEmbeddingModel(),
            'embedding_dimension' => $profile->getEmbeddingDimension(),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        if ($existing) {
            // UPDATE
            $this->connection->update('user_profiles', $data, ['id' => $profile->getId()]);
        } else {
            // INSERT
            $data['created_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->connection->insert('user_profiles', $data);
        }
    }

    public function delete(string $id): void
    {
        $this->connection->delete('user_profiles', ['id' => $id]);
    }

    public function findAll(): array
    {
        $sql = 'SELECT * FROM user_profiles ORDER BY created_at DESC';
        $results = $this->connection->fetchAllAssociative($sql);

        return array_map(fn($data) => $this->hydrate($data), $results);
    }

    /**
     * Гидрация данных из БД в доменную модель
     */
    private function hydrate(array $data): UserProfile
    {
        return new UserProfile(
            id: $data['id'],
            userId: $data['user_id'],
            communicationStyle: $data['communication_style'] ?? 'balanced',
            responseLength: $data['response_length'] ?? 'medium',
            useEmojis: (bool)($data['use_emojis'] ?? true),
            keyInterests: json_decode($data['key_interests'] ?? '[]', true),
            exampleResponses: json_decode($data['example_responses'] ?? '[]', true),
            relevanceThreshold: (float)($data['relevance_threshold'] ?? 0.7),
            botMode: $data['bot_mode'] ?? 'silent',
            embeddingProvider: $data['embedding_provider'] ?? null,
            embeddingModel: $data['embedding_model'] ?? null,
            embeddingDimension: $data['embedding_dimension'] ?? null,
            createdAt: isset($data['created_at']) 
                ? new \DateTimeImmutable($data['created_at']) 
                : null,
            updatedAt: isset($data['updated_at']) 
                ? new \DateTimeImmutable($data['updated_at']) 
                : null
        );
    }
}
