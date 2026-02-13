<?php

namespace App\Core\UseCase;

use App\Core\Domain\User\UserProfile;

/**
 * Результат обработки Telegram сообщения
 */
readonly class ProcessingResult
{
    public function __construct(
        public bool $shouldRespond,
        public string $reason,
        public UserProfile $profile,
        public ?string $response = null,
        public ?float $relevanceScore = null,
        public ?int $matchesFound = null,
        public ?int $contextEntriesUsed = null,
        public ?float $processingTimeMs = null,
        public ?string $responseMessageId = null
    ) {}

    public function hasResponse(): bool
    {
        return $this->response !== null && $this->response !== '';
    }

    public function toArray(): array
    {
        return [
            'should_respond' => $this->shouldRespond,
            'reason' => $this->reason,
            'has_response' => $this->hasResponse(),
            'response_length' => $this->response ? strlen($this->response) : 0,
            'relevance_score' => $this->relevanceScore,
            'matches_found' => $this->matchesFound,
            'context_entries_used' => $this->contextEntriesUsed,
            'processing_time_ms' => $this->processingTimeMs,
            'bot_mode' => $this->profile->getBotMode(),
            'response_message_id' => $this->responseMessageId,
        ];
    }
}
