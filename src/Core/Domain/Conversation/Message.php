<?php

namespace App\Core\Domain\Conversation;

class Message
{
    private string $id;
    private string $conversationId;
    private ?int $externalMessageId;
    private MessageDirection $direction;
    private string $contentType;
    private string $content;
    private ?float $relevanceScore;
    private ?int $contextEntriesUsed;
    private ?int $processingTimeMs;
    private array $metadata;
    private \DateTimeInterface $createdAt;

    public function __construct(
        string $id,
        string $conversationId,
        MessageDirection $direction,
        string $content,
        ?int $externalMessageId = null,
        string $contentType = 'text',
        ?float $relevanceScore = null,
        ?int $contextEntriesUsed = null,
        ?int $processingTimeMs = null,
        array $metadata = [],
        ?\DateTimeInterface $createdAt = null
    ) {
        $this->id = $id;
        $this->conversationId = $conversationId;
        $this->externalMessageId = $externalMessageId;
        $this->direction = $direction;
        $this->contentType = $contentType;
        $this->content = $content;
        $this->relevanceScore = $relevanceScore;
        $this->contextEntriesUsed = $contextEntriesUsed;
        $this->processingTimeMs = $processingTimeMs;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public static function incoming(
        string $conversationId,
        string $content,
        ?int $externalMessageId = null,
        array $metadata = []
    ): self {
        return new self(
            id: self::generateId(),
            conversationId: $conversationId,
            direction: MessageDirection::INCOMING,
            content: $content,
            externalMessageId: $externalMessageId,
            metadata: $metadata
        );
    }

    public static function outgoing(
        string $conversationId,
        string $content,
        ?float $relevanceScore = null,
        ?int $contextEntriesUsed = null,
        ?int $processingTimeMs = null,
        array $metadata = []
    ): self {
        return new self(
            id: self::generateId(),
            conversationId: $conversationId,
            direction: MessageDirection::OUTGOING,
            content: $content,
            relevanceScore: $relevanceScore,
            contextEntriesUsed: $contextEntriesUsed,
            processingTimeMs: $processingTimeMs,
            metadata: $metadata
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getConversationId(): string
    {
        return $this->conversationId;
    }

    public function getExternalMessageId(): ?int
    {
        return $this->externalMessageId;
    }

    public function getDirection(): MessageDirection
    {
        return $this->direction;
    }

    public function isIncoming(): bool
    {
        return $this->direction === MessageDirection::INCOMING;
    }

    public function isOutgoing(): bool
    {
        return $this->direction === MessageDirection::OUTGOING;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRelevanceScore(): ?float
    {
        return $this->relevanceScore;
    }

    public function getContextEntriesUsed(): ?int
    {
        return $this->contextEntriesUsed;
    }

    public function getProcessingTimeMs(): ?int
    {
        return $this->processingTimeMs;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Форматирует сообщение для передачи в LLM
     */
    public function toLLMFormat(): array
    {
        return [
            'role' => $this->direction === MessageDirection::INCOMING ? 'user' : 'assistant',
            'content' => $this->content
        ];
    }

    private static function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversationId,
            'external_message_id' => $this->externalMessageId,
            'direction' => $this->direction->value,
            'content_type' => $this->contentType,
            'content' => $this->content,
            'relevance_score' => $this->relevanceScore,
            'context_entries_used' => $this->contextEntriesUsed,
            'processing_time_ms' => $this->processingTimeMs,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
