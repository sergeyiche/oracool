<?php

namespace App\Core\Domain\KnowledgeBase;

/**
 * Запись в базе знаний с векторизованным представлением
 */
class KnowledgeBaseEntry
{
    public function __construct(
        private string $id,
        private string $userId,
        private string $text,
        private ?array $embedding = null,
        private ?string $embeddingModel = null,
        private ?string $source = 'manual',
        private ?string $sourceId = null,
        private array $tags = [],
        private array $metadata = [],
        private ?\DateTimeImmutable $createdAt = null
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public static function create(
        string $id,
        string $userId,
        string $text,
        ?array $embedding = null,
        ?string $embeddingModel = null
    ): self {
        return new self($id, $userId, $text, $embedding, $embeddingModel);
    }

    public static function fromMessage(
        string $id,
        string $userId,
        string $text,
        array $embedding,
        string $embeddingModel,
        string $messageId
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            text: $text,
            embedding: $embedding,
            embeddingModel: $embeddingModel,
            source: 'message',
            sourceId: $messageId
        );
    }

    public static function fromFeedback(
        string $id,
        string $userId,
        string $text,
        array $embedding,
        string $embeddingModel,
        string $feedbackId
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            text: $text,
            embedding: $embedding,
            embeddingModel: $embeddingModel,
            source: 'feedback',
            sourceId: $feedbackId
        );
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

    public function getText(): string
    {
        return $this->text;
    }

    public function getEmbedding(): ?array
    {
        return $this->embedding;
    }

    public function getEmbeddingModel(): ?string
    {
        return $this->embeddingModel;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Methods
    public function setEmbedding(array $embedding, string $model): void
    {
        $this->embedding = $embedding;
        $this->embeddingModel = $model;
    }

    public function hasEmbedding(): bool
    {
        return $this->embedding !== null && !empty($this->embedding);
    }

    public function addTag(string $tag): void
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'text' => $this->text,
            'embedding' => $this->embedding,
            'embedding_model' => $this->embeddingModel,
            'source' => $this->source,
            'source_id' => $this->sourceId,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
