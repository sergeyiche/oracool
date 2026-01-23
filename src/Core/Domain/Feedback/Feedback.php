<?php

namespace App\Core\Domain\Feedback;

/**
 * Обратная связь от пользователя о сгенерированном ответе
 */
class Feedback
{
    public function __construct(
        private string $id,
        private string $userId,
        private string $messageId,
        private ?string $responseId,
        private FeedbackType $type,
        private ?string $originalResponse = null,
        private ?string $correctedResponse = null,
        private ?string $notes = null,
        private ?\DateTimeImmutable $createdAt = null
    ) {
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public static function approve(
        string $id,
        string $userId,
        string $messageId,
        string $responseId,
        string $response
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            messageId: $messageId,
            responseId: $responseId,
            type: FeedbackType::APPROVE,
            originalResponse: $response
        );
    }

    public static function correct(
        string $id,
        string $userId,
        string $messageId,
        string $responseId,
        string $originalResponse,
        string $correctedResponse,
        ?string $notes = null
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            messageId: $messageId,
            responseId: $responseId,
            type: FeedbackType::CORRECT,
            originalResponse: $originalResponse,
            correctedResponse: $correctedResponse,
            notes: $notes
        );
    }

    public static function delete(
        string $id,
        string $userId,
        string $messageId,
        string $responseId,
        string $response,
        ?string $reason = null
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            messageId: $messageId,
            responseId: $responseId,
            type: FeedbackType::DELETE,
            originalResponse: $response,
            notes: $reason
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

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getResponseId(): ?string
    {
        return $this->responseId;
    }

    public function getType(): FeedbackType
    {
        return $this->type;
    }

    public function getOriginalResponse(): ?string
    {
        return $this->originalResponse;
    }

    public function getCorrectedResponse(): ?string
    {
        return $this->correctedResponse;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Query methods
    public function isApproval(): bool
    {
        return $this->type === FeedbackType::APPROVE;
    }

    public function isCorrection(): bool
    {
        return $this->type === FeedbackType::CORRECT;
    }

    public function isDeletion(): bool
    {
        return $this->type === FeedbackType::DELETE;
    }

    public function hasCorrection(): bool
    {
        return $this->correctedResponse !== null;
    }

    /**
     * Получить текст для добавления в базу знаний
     */
    public function getTextForKnowledgeBase(): ?string
    {
        return match($this->type) {
            FeedbackType::APPROVE => $this->originalResponse,
            FeedbackType::CORRECT => $this->correctedResponse,
            FeedbackType::DELETE => null,
        };
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'message_id' => $this->messageId,
            'response_id' => $this->responseId,
            'type' => $this->type->value,
            'original_response' => $this->originalResponse,
            'corrected_response' => $this->correctedResponse,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
