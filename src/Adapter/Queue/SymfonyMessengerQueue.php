<?php

namespace App\Adapter\Queue;

use App\Core\Port\MessageQueue\MessageQueueInterface;

/**
 * Адаптер для Symfony Messenger (заглушка для MVP)
 */
class SymfonyMessengerQueue implements MessageQueueInterface
{
    public function publish(string $exchange, array $message, string $routingKey): void
    {
        // Заглушка для MVP - просто логируем
    }
}
