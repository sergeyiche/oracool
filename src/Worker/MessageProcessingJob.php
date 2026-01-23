<?php

namespace App\Worker;

/**
 * Message объект для асинхронной обработки сообщений
 */
class MessageProcessingJob
{
    public function __construct(
        private string $messageId,
        private array $intent,
        private string $priority = 'normal'
    ) {}

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getIntent(): array
    {
        return $this->intent;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }
}
