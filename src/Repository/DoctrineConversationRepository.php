<?php

namespace App\Repository;

use App\Core\Domain\Conversation\Conversation;
use App\Core\Domain\Conversation\ConversationStatus;
use App\Core\Domain\Conversation\Message;
use App\Core\Domain\Conversation\MessageDirection;
use App\Core\Port\Repository\ConversationRepositoryInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class DoctrineConversationRepository implements ConversationRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    public function getOrCreateConversation(string $userId, string $chatId): Conversation
    {
        $existing = $this->findActiveConversation($userId, $chatId);
        
        if ($existing) {
            return $existing;
        }

        // Создаём новый conversation
        $conversation = Conversation::create($userId, $chatId);
        $this->saveConversation($conversation);

        $this->logger->info('Created new conversation', [
            'conversation_id' => $conversation->getId(),
            'user_id' => $userId,
            'chat_id' => $chatId
        ]);

        return $conversation;
    }

    public function findActiveConversation(string $userId, string $chatId): ?Conversation
    {
        $sql = "
            SELECT * FROM conversations
            WHERE user_id = :user_id 
            AND chat_id = :chat_id 
            AND status = :status
            LIMIT 1
        ";

        $result = $this->connection->fetchAssociative($sql, [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'status' => ConversationStatus::ACTIVE->value
        ]);

        if (!$result) {
            return null;
        }

        return $this->hydrateConversation($result);
    }

    public function findById(string $conversationId): ?Conversation
    {
        $sql = "SELECT * FROM conversations WHERE id = :id";
        
        $result = $this->connection->fetchAssociative($sql, [
            'id' => $conversationId
        ]);

        if (!$result) {
            return null;
        }

        return $this->hydrateConversation($result);
    }

    public function saveConversation(Conversation $conversation): void
    {
        $sql = "
            INSERT INTO conversations (
                id, user_id, chat_id, title, status, context_summary,
                message_count, last_message_at, created_at, updated_at
            ) VALUES (
                :id, :user_id, :chat_id, :title, :status, :context_summary,
                :message_count, :last_message_at, :created_at, :updated_at
            )
            ON CONFLICT (id) DO UPDATE SET
                title = EXCLUDED.title,
                status = EXCLUDED.status,
                context_summary = EXCLUDED.context_summary,
                message_count = EXCLUDED.message_count,
                last_message_at = EXCLUDED.last_message_at,
                updated_at = EXCLUDED.updated_at
        ";

        $this->connection->executeStatement($sql, [
            'id' => $conversation->getId(),
            'user_id' => $conversation->getUserId(),
            'chat_id' => $conversation->getChatId(),
            'title' => $conversation->getTitle(),
            'status' => $conversation->getStatus()->value,
            'context_summary' => $conversation->getContextSummary(),
            'message_count' => $conversation->getMessageCount(),
            'last_message_at' => $conversation->getLastMessageAt()?->format('Y-m-d H:i:s'),
            'created_at' => $conversation->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function saveMessage(Message $message): void
    {
        $sql = "
            INSERT INTO messages (
                id, conversation_id, external_message_id, direction,
                content_type, content, relevance_score, context_entries_used,
                processing_time_ms, metadata, created_at
            ) VALUES (
                :id, :conversation_id, :external_message_id, :direction,
                :content_type, :content, :relevance_score, :context_entries_used,
                :processing_time_ms, :metadata, :created_at
            )
        ";

        $this->connection->executeStatement($sql, [
            'id' => $message->getId(),
            'conversation_id' => $message->getConversationId(),
            'external_message_id' => $message->getExternalMessageId(),
            'direction' => $message->getDirection()->value,
            'content_type' => $message->getContentType(),
            'content' => $message->getContent(),
            'relevance_score' => $message->getRelevanceScore(),
            'context_entries_used' => $message->getContextEntriesUsed(),
            'processing_time_ms' => $message->getProcessingTimeMs(),
            'metadata' => json_encode($message->getMetadata()),
            'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        // Обновляем счётчик в conversation
        $this->connection->executeStatement("
            UPDATE conversations 
            SET message_count = message_count + 1,
                last_message_at = :last_message_at,
                updated_at = NOW()
            WHERE id = :conversation_id
        ", [
            'conversation_id' => $message->getConversationId(),
            'last_message_at' => $message->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function getRecentMessages(string $conversationId, int $limit = 10): array
    {
        $sql = "
            SELECT * FROM messages
            WHERE conversation_id = :conversation_id
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $results = $this->connection->fetchAllAssociative($sql, [
            'conversation_id' => $conversationId,
            'limit' => $limit
        ]);

        // Переворачиваем массив чтобы старые сообщения были первыми
        $messages = array_reverse(array_map(
            fn($row) => $this->hydrateMessage($row),
            $results
        ));

        return $messages;
    }

    public function getAllMessages(string $conversationId): array
    {
        $sql = "
            SELECT * FROM messages
            WHERE conversation_id = :conversation_id
            ORDER BY created_at ASC
        ";

        $results = $this->connection->fetchAllAssociative($sql, [
            'conversation_id' => $conversationId
        ]);

        return array_map(
            fn($row) => $this->hydrateMessage($row),
            $results
        );
    }

    public function getUserConversations(string $userId, ?string $status = null): array
    {
        $sql = "
            SELECT * FROM conversations
            WHERE user_id = :user_id
        ";

        $params = ['user_id' => $userId];

        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY last_message_at DESC NULLS LAST, created_at DESC";

        $results = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(
            fn($row) => $this->hydrateConversation($row),
            $results
        );
    }

    public function clearConversation(string $userId, string $chatId): Conversation
    {
        // Архивируем текущий активный conversation
        $existing = $this->findActiveConversation($userId, $chatId);
        
        if ($existing) {
            $existing->archive();
            $this->saveConversation($existing);

            $this->logger->info('Archived conversation', [
                'conversation_id' => $existing->getId(),
                'user_id' => $userId,
                'chat_id' => $chatId
            ]);
        }

        // Создаём новый conversation
        return $this->getOrCreateConversation($userId, $chatId);
    }

    public function deleteConversation(string $conversationId): void
    {
        // Сначала удаляем сообщения (CASCADE должен сработать, но на всякий случай)
        $this->connection->executeStatement(
            "DELETE FROM messages WHERE conversation_id = :id",
            ['id' => $conversationId]
        );

        // Затем удаляем conversation
        $this->connection->executeStatement(
            "DELETE FROM conversations WHERE id = :id",
            ['id' => $conversationId]
        );

        $this->logger->info('Deleted conversation', [
            'conversation_id' => $conversationId
        ]);
    }

    public function saveRawMessage($message): void
    {
        // Deprecated, но оставляем для совместимости
        $this->logger->warning('saveRawMessage is deprecated, use saveMessage instead');
    }

    private function hydrateConversation(array $row): Conversation
    {
        return new Conversation(
            id: $row['id'],
            userId: $row['user_id'],
            chatId: (string)$row['chat_id'],
            title: $row['title'],
            status: ConversationStatus::from($row['status']),
            contextSummary: $row['context_summary'],
            messageCount: (int)$row['message_count'],
            lastMessageAt: $row['last_message_at'] ? new \DateTimeImmutable($row['last_message_at']) : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at'])
        );
    }

    private function hydrateMessage(array $row): Message
    {
        return new Message(
            id: $row['id'],
            conversationId: $row['conversation_id'],
            direction: MessageDirection::from($row['direction']),
            content: $row['content'],
            externalMessageId: $row['external_message_id'] ? (int)$row['external_message_id'] : null,
            contentType: $row['content_type'] ?? 'text',
            relevanceScore: $row['relevance_score'] ? (float)$row['relevance_score'] : null,
            contextEntriesUsed: $row['context_entries_used'] ? (int)$row['context_entries_used'] : null,
            processingTimeMs: $row['processing_time_ms'] ? (int)$row['processing_time_ms'] : null,
            metadata: json_decode($row['metadata'] ?? '{}', true),
            createdAt: new \DateTimeImmutable($row['created_at'])
        );
    }
}
