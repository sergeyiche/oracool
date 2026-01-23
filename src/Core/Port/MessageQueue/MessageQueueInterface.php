<?php

namespace App\Core\Port\MessageQueue;

/**
 * Интерфейс очереди сообщений (заглушка для MVP)
 */
interface MessageQueueInterface
{
    public function publish(string $exchange, array $message, string $routingKey): void;
}
