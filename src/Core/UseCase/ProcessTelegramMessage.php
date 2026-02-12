<?php

namespace App\Core\UseCase;

use App\Core\Domain\User\UserProfile;
use App\Core\Domain\Conversation\Message;
use App\Core\Port\UserProfileRepositoryInterface;
use App\Core\Port\KnowledgeBaseRepositoryInterface;
use App\Core\Port\Repository\ConversationRepositoryInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Use Case: Обработка входящего сообщения из Telegram
 * Оркестрирует: Conversation Management -> CheckRelevance -> GenerateResponse
 */
class ProcessTelegramMessage
{
    public function __construct(
        private CheckRelevance $checkRelevance,
        private GenerateResponse $generateResponse,
        private UserProfileRepositoryInterface $profileRepository,
        private KnowledgeBaseRepositoryInterface $knowledgeRepository,
        private ConversationRepositoryInterface $conversationRepository,
        private Connection $connection,
        private LoggerInterface $logger,
        private string $botOwnerTelegramId,
        private string $defaultKnowledgeSourceUserId = '858361483'
    ) {}

    /**
     * Обрабатывает входящее сообщение
     * 
     * @return ProcessingResult
     */
    public function execute(
        string $text,
        int $telegramUserId,
        int $chatId,
        ?int $messageId = null
    ): ProcessingResult {
        $startTime = microtime(true);

        try {
            // 1. Получаем или создаем профиль пользователя
            $profile = $this->getOrCreateProfile((string)$telegramUserId);

            // 2. Получаем или создаём conversation
            $conversation = $this->conversationRepository->getOrCreateConversation(
                userId: (string)$telegramUserId,
                chatId: (string)$chatId
            );

            $this->logger->info('Processing Telegram message', [
                'user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'conversation_id' => $conversation->getId(),
                'message_id' => $messageId,
                'bot_mode' => $profile->getBotMode(),
                'text_length' => strlen($text)
            ]);

            // 3. Сохраняем incoming сообщение
            $incomingMessage = Message::incoming(
                conversationId: $conversation->getId(),
                content: $text,
                externalMessageId: $messageId
            );
            $this->conversationRepository->saveMessage($incomingMessage);

            // 4. Проверяем режим бота
            if (!$profile->isActive()) {
                $this->logger->debug('Bot is in silent mode, skipping');
                
                return new ProcessingResult(
                    shouldRespond: false,
                    reason: 'Bot is in silent mode',
                    profile: $profile
                );
            }

            // 5. Проверяем релевантность сообщения
            $relevanceResult = $this->checkRelevance->execute(
                message: $text,
                userId: $profile->getUserId(),
                threshold: $profile->getRelevanceThreshold()
            );

            $this->logger->info('Relevance check completed', [
                'is_relevant' => $relevanceResult->isRelevant,
                'score' => $relevanceResult->score,
                'matches' => $relevanceResult->matchesFound
            ]);

            // 6. Проверяем нужно ли отвечать
            if (!$relevanceResult->isRelevant && $profile->getBotMode() !== 'aggressive') {
                $this->logger->debug('Message not relevant and bot not in aggressive mode');
                
                return new ProcessingResult(
                    shouldRespond: false,
                    reason: 'Message not relevant',
                    profile: $profile,
                    relevanceScore: $relevanceResult->score,
                    matchesFound: $relevanceResult->matchesFound
                );
            }

            // 7. Загружаем историю диалога из БД
            $conversationHistory = $this->loadConversationHistory($conversation->getId());

            // 8. Генерируем ответ с учётом истории
            $responseResult = $this->generateResponse->execute(
                message: $text,
                profile: $profile,
                conversationHistory: $conversationHistory
            );

            // 9. Сохраняем outgoing сообщение с метаданными
            $outgoingMessage = Message::outgoing(
                conversationId: $conversation->getId(),
                content: $responseResult->response,
                relevanceScore: $responseResult->relevanceScore,
                contextEntriesUsed: $responseResult->contextEntriesUsed,
                processingTimeMs: (int)$responseResult->processingTimeMs
            );
            $this->conversationRepository->saveMessage($outgoingMessage);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Message processed successfully', [
                'conversation_id' => $conversation->getId(),
                'message_count' => $conversation->getMessageCount() + 2, // incoming + outgoing
                'response_length' => strlen($responseResult->response),
                'history_messages' => count($conversationHistory),
                'total_duration_ms' => $duration
            ]);

            return new ProcessingResult(
                shouldRespond: true,
                reason: 'Message processed successfully',
                profile: $profile,
                response: $responseResult->response,
                relevanceScore: $relevanceResult->score,
                matchesFound: $relevanceResult->matchesFound,
                contextEntriesUsed: $responseResult->contextEntriesUsed,
                processingTimeMs: $duration
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to process Telegram message', [
                'error' => $e->getMessage(),
                'user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                'Failed to process message: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Загружает историю диалога в формате для LLM
     */
    private function loadConversationHistory(string $conversationId, int $limit = 10): array
    {
        $messages = $this->conversationRepository->getRecentMessages($conversationId, $limit);

        // Преобразуем в формат для LLM
        return array_map(
            fn(Message $message) => $message->toLLMFormat(),
            $messages
        );
    }

    /**
     * Получает или создает профиль пользователя
     */
    private function getOrCreateProfile(string $userId): UserProfile
    {
        $profile = $this->profileRepository->findByUserId($userId);

        if (!$profile) {
            $this->logger->info('Auto-creating new user profile', ['user_id' => $userId]);
            
            // Создаём профиль
            $profile = UserProfile::createDefault(
                id: $this->generateUuid(),
                userId: $userId
            );

            // Настраиваем параметры по умолчанию для нового пользователя
            $profile->updateBotMode('active');
            $profile->updateCommunicationStyle('creative');
            $profile->updateRelevanceThreshold(0.65);

            $this->profileRepository->save($profile);

            $this->logger->info('New profile created', [
                'user_id' => $userId,
                'mode' => 'active',
                'style' => 'creative',
                'threshold' => 0.65
            ]);

            // Автоматически копируем базу знаний
            try {
                $copiedCount = $this->copyKnowledgeBase($this->defaultKnowledgeSourceUserId, $userId);
                
                if ($copiedCount > 0) {
                    $this->logger->info('Knowledge base auto-copied', [
                        'from_user' => $this->defaultKnowledgeSourceUserId,
                        'to_user' => $userId,
                        'entries_copied' => $copiedCount
                    ]);
                } else {
                    $this->logger->warning('No knowledge base entries to copy', [
                        'source_user' => $this->defaultKnowledgeSourceUserId
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to copy knowledge base for new user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                // Не падаем, профиль уже создан
            }
        }

        return $profile;
    }

    /**
     * Проверяет является ли пользователь владельцем бота
     */
    public function isOwner(int $telegramUserId): bool
    {
        return (string)$telegramUserId === $this->botOwnerTelegramId;
    }

    private function generateUuid(): string
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

    /**
     * Копирует базу знаний от одного пользователя другому
     * 
     * @param string $fromUserId ID пользователя-источника
     * @param string $toUserId ID пользователя-получателя
     * @return int Количество скопированных записей
     */
    private function copyKnowledgeBase(string $fromUserId, string $toUserId): int
    {
        $sql = "
            INSERT INTO knowledge_base (id, user_id, text, embedding, embedding_model, source, created_at)
            SELECT 
                gen_random_uuid(),
                :to_user_id,
                text,
                embedding,
                embedding_model,
                source,
                NOW()
            FROM knowledge_base
            WHERE user_id = :from_user_id
        ";

        $result = $this->connection->executeStatement($sql, [
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId
        ]);

        return $result;
    }
}
