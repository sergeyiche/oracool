<?php

namespace App\Core\Domain\User;

/**
 * Профиль пользователя "цифрового двойника"
 * Содержит настройки стиля общения и поведения бота
 */
class UserProfile
{
    public function __construct(
        private string $id,
        private string $userId,
        private string $communicationStyle = 'balanced',
        private string $responseLength = 'medium',
        private bool $useEmojis = true,
        private array $keyInterests = [],
        private array $exampleResponses = [],
        private float $relevanceThreshold = 0.7,
        private string $botMode = 'silent',
        private ?string $embeddingProvider = 'ollama',
        private ?string $embeddingModel = null,
        private ?int $embeddingDimension = 768,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public static function createDefault(string $id, string $userId): self
    {
        return new self($id, $userId);
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCommunicationStyle(): string
    {
        return $this->communicationStyle;
    }

    public function getResponseLength(): string
    {
        return $this->responseLength;
    }

    public function useEmojis(): bool
    {
        return $this->useEmojis;
    }

    public function getKeyInterests(): array
    {
        return $this->keyInterests;
    }

    public function getExampleResponses(): array
    {
        return $this->exampleResponses;
    }

    public function getRelevanceThreshold(): float
    {
        return $this->relevanceThreshold;
    }

    public function getBotMode(): string
    {
        return $this->botMode;
    }

    public function getEmbeddingProvider(): ?string
    {
        return $this->embeddingProvider;
    }

    public function getEmbeddingModel(): ?string
    {
        return $this->embeddingModel;
    }

    public function getEmbeddingDimension(): ?int
    {
        return $this->embeddingDimension;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters для обновления профиля
    public function updateCommunicationStyle(string $style): void
    {
        $this->communicationStyle = $style;
        $this->touch();
    }

    public function updateBotMode(string $mode): void
    {
        if (!in_array($mode, ['silent', 'active', 'aggressive'])) {
            throw new \InvalidArgumentException("Invalid bot mode: $mode");
        }
        $this->botMode = $mode;
        $this->touch();
    }

    public function updateRelevanceThreshold(float $threshold): void
    {
        if ($threshold < 0 || $threshold > 1) {
            throw new \InvalidArgumentException("Threshold must be between 0 and 1");
        }
        $this->relevanceThreshold = $threshold;
        $this->touch();
    }

    public function addInterest(string $interest): void
    {
        if (!in_array($interest, $this->keyInterests)) {
            $this->keyInterests[] = $interest;
            $this->touch();
        }
    }

    public function addExampleResponse(string $example): void
    {
        $this->exampleResponses[] = $example;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->botMode !== 'silent';
    }

    public function shouldRespond(float $relevanceScore): bool
    {
        return $relevanceScore >= $this->relevanceThreshold;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'communication_style' => $this->communicationStyle,
            'response_length' => $this->responseLength,
            'use_emojis' => $this->useEmojis,
            'key_interests' => $this->keyInterests,
            'example_responses' => $this->exampleResponses,
            'relevance_threshold' => $this->relevanceThreshold,
            'bot_mode' => $this->botMode,
            'embedding_provider' => $this->embeddingProvider,
            'embedding_model' => $this->embeddingModel,
            'embedding_dimension' => $this->embeddingDimension,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
