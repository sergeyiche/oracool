<?php

namespace App\Core\UseCase;

use App\Core\Domain\User\UserProfile;
use App\Core\Port\UserProfileRepositoryInterface;
use App\Core\Port\KnowledgeBaseRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Use Case: Обработка входящего сообщения из Telegram
 * Оркестрирует CheckRelevance -> GenerateResponse
 */
class ProcessTelegramMessage
{
    public function __construct(
        private CheckRelevance $checkRelevance,
        private GenerateResponse $generateResponse,
        private UserProfileRepositoryInterface $profileRepository,
        private KnowledgeBaseRepositoryInterface $knowledgeRepository,
        private LoggerInterface $logger,
        private string $botOwnerTelegramId
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
        ?int $messageId = null,
        array $conversationHistory = []
    ): ProcessingResult {
        $startTime = microtime(true);

        try {
            // 1. Получаем или создаем профиль пользователя
            $profile = $this->getOrCreateProfile((string)$telegramUserId);

            $this->logger->info('Processing Telegram message', [
                'user_id' => $telegramUserId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'bot_mode' => $profile->getBotMode(),
                'text_length' => strlen($text)
            ]);

            // 2. Проверяем режим бота
            if (!$profile->isActive()) {
                $this->logger->debug('Bot is in silent mode, skipping');
                
                return new ProcessingResult(
                    shouldRespond: false,
                    reason: 'Bot is in silent mode',
                    profile: $profile
                );
            }

            // 3. Проверяем релевантность сообщения
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

            // 4. Проверяем нужно ли отвечать
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

            // 5. Генерируем ответ
            $responseResult = $this->generateResponse->execute(
                message: $text,
                profile: $profile,
                conversationHistory: $conversationHistory
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Message processed successfully', [
                'response_length' => strlen($responseResult->response),
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
                'chat_id' => $chatId
            ]);

            throw new \RuntimeException(
                'Failed to process message: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Получает или создает профиль пользователя
     */
    private function getOrCreateProfile(string $userId): UserProfile
    {
        $profile = $this->profileRepository->findByUserId($userId);

        if (!$profile) {
            $this->logger->info('Creating new user profile', ['user_id' => $userId]);
            
            $profile = UserProfile::createDefault(
                id: $this->generateUuid(),
                userId: $userId
            );

            // Если это владелец бота, создаем активный профиль
            if ($userId === $this->botOwnerTelegramId) {
                $profile->updateBotMode('active');
                $this->logger->info('Owner profile created with active mode');
            }

            $this->profileRepository->save($profile);
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
}
