<?php

namespace App\Core\Domain\Conversation;

class Conversation
{
    private string $id;
    private string $userId;
    private string $chatId;
    private ?string $title;
    private ConversationStatus $status;
    private ?string $contextSummary;
    private int $messageCount;
    private ?\DateTimeInterface $lastMessageAt;
    private \DateTimeInterface $createdAt;
    private \DateTimeInterface $updatedAt;

    public function __construct(
        string $id,
        string $userId,
        string $chatId,
        ?string $title = null,
        ?ConversationStatus $status = null,
        ?string $contextSummary = null,
        int $messageCount = 0,
        ?\DateTimeInterface $lastMessageAt = null,
        ?\DateTimeInterface $createdAt = null,
        ?\DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->chatId = $chatId;
        $this->title = $title;
        $this->status = $status ?? ConversationStatus::ACTIVE;
        $this->contextSummary = $contextSummary;
        $this->messageCount = $messageCount;
        $this->lastMessageAt = $lastMessageAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public static function create(
        string $userId,
        string $chatId,
        ?string $title = null
    ): self {
        return new self(
            id: self::generateId(),
            userId: $userId,
            chatId: $chatId,
            title: $title
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function getStatus(): ConversationStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === ConversationStatus::ACTIVE;
    }

    public function archive(): void
    {
        $this->status = ConversationStatus::ARCHIVED;
        $this->touch();
    }

    public function delete(): void
    {
        $this->status = ConversationStatus::DELETED;
        $this->touch();
    }

    public function getContextSummary(): ?string
    {
        return $this->contextSummary;
    }

    public function updateContextSummary(string $summary): void
    {
        $this->contextSummary = $summary;
        $this->touch();
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function incrementMessageCount(): void
    {
        $this->messageCount++;
        $this->lastMessageAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function getLastMessageAt(): ?\DateTimeInterface
    {
        return $this->lastMessageAt;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function generateId(): string
    {
        // Используем UUID v4
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
            'user_id' => $this->userId,
            'chat_id' => $this->chatId,
            'title' => $this->title,
            'status' => $this->status->value,
            'context_summary' => $this->contextSummary,
            'message_count' => $this->messageCount,
            'last_message_at' => $this->lastMessageAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
