<?php

namespace App\Core\Port\Repository;

use App\Core\Domain\Conversation\Conversation;
use App\Core\Domain\Conversation\Message;

interface ConversationRepositoryInterface
{
    /**
     * Находит или создаёт conversation для пользователя в чате
     */
    public function getOrCreateConversation(string $userId, string $chatId): Conversation;

    /**
     * Находит активный conversation
     */
    public function findActiveConversation(string $userId, string $chatId): ?Conversation;

    /**
     * Находит conversation по ID
     */
    public function findById(string $conversationId): ?Conversation;

    /**
     * Сохраняет conversation
     */
    public function saveConversation(Conversation $conversation): void;

    /**
     * Сохраняет сообщение
     */
    public function saveMessage(Message $message): void;

    /**
     * Получает последние N сообщений из диалога
     * 
     * @return Message[]
     */
    public function getRecentMessages(string $conversationId, int $limit = 10): array;

    /**
     * Получает все сообщения диалога
     * 
     * @return Message[]
     */
    public function getAllMessages(string $conversationId): array;

    /**
     * Получает все conversations пользователя
     * 
     * @return Conversation[]
     */
    public function getUserConversations(string $userId, ?string $status = null): array;

    /**
     * Архивирует текущий активный conversation и создаёт новый
     */
    public function clearConversation(string $userId, string $chatId): Conversation;

    /**
     * Удаляет conversation и все его сообщения
     */
    public function deleteConversation(string $conversationId): void;

    /**
     * Сохраняет сырое сообщение (для совместимости)
     * @deprecated Используйте saveMessage
     */
    public function saveRawMessage($message): void;
}
