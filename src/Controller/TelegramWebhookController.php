<?php

namespace App\Controller;

use App\Adapter\Telegram\TelegramBotService;
use App\Adapter\Telegram\TelegramMessageMapper;
use App\Core\Domain\KnowledgeBase\KnowledgeBaseEntry;
use App\Core\Port\EmbeddingServiceInterface;
use App\Core\Port\KnowledgeBaseRepositoryInterface;
use App\Core\UseCase\ProcessTelegramMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Контроллер для приема webhook от Telegram
 */
class TelegramWebhookController extends AbstractController
{
    public function __construct(
        private TelegramBotService $telegramBot,
        private TelegramMessageMapper $messageMapper,
        private ProcessTelegramMessage $processMessage,
        private EmbeddingServiceInterface $embeddingService,
        private KnowledgeBaseRepositoryInterface $knowledgeRepository,
        private LoggerInterface $logger,
        private string $webhookSecret
    ) {}

    #[Route('/webhook/telegram', name: 'telegram_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        try {
            $rawPayload = $request->getContent();

            // 1. Проверка secret token (безопасность)
            $secretToken = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
            
            if ($this->webhookSecret && $secretToken !== $this->webhookSecret) {
                $this->logger->warning('Invalid webhook secret token');
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            // 2. Парсинг update от Telegram
            $update = json_decode($rawPayload, true);
            
            if (!$update) {
                $this->logger->error('Failed to parse webhook payload');
                return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
            }

            $this->logger->info('Received Telegram webhook', [
                'update_id' => $update['update_id'] ?? null,
                'type' => $this->messageMapper->getMessageType($update),
                'payload_bytes' => strlen($rawPayload),
                'debug_context' => $this->buildUpdateDebugContext($update)
            ]);

            // 3. Обработка разных типов обновлений
            $response = match(true) {
                isset($update['message']) => $this->handleMessage($update),
                isset($update['callback_query']) => $this->handleCallbackQuery($update),
                isset($update['edited_message']) => $this->handleEditedMessage($update),
                default => $this->handleUnsupported($update)
            };

            $this->logger->info('Telegram webhook processed', [
                'update_id' => $update['update_id'] ?? null,
                'result_status' => $response['status'] ?? 'unknown',
                'result' => $response
            ]);

            return new JsonResponse(['ok' => true, 'result' => $response]);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Возвращаем 200 чтобы Telegram не ретраил
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_OK);
        }
    }

    /**
     * Обработка обычного сообщения
     */
    private function handleMessage(array $update): array
    {
        $text = $this->messageMapper->extractText($update);
        $userId = $this->messageMapper->extractUserId($update);
        $chatId = $this->messageMapper->extractChatId($update);
        $messageId = $this->messageMapper->extractMessageId($update);

        if (!$text || !$userId || !$chatId) {
            $this->logger->warning('Invalid message data', ['update' => $update]);
            return ['status' => 'skipped', 'reason' => 'invalid_data'];
        }

        $this->logger->info('Incoming Telegram message', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text_preview' => $this->truncateForLog($text),
            'text_length' => mb_strlen($text)
        ]);

        // Обработка команд
        if ($this->messageMapper->isCommand($update)) {
            return $this->handleCommand($update);
        }

        // Показываем "печатает..."
        $this->telegramBot->sendChatAction($chatId, 'typing');

        // Обрабатываем сообщение через Use Case
        $result = $this->processMessage->execute(
            text: $text,
            telegramUserId: $userId,
            chatId: $chatId,
            messageId: $messageId
        );

        $this->logger->info('Message processing result', $result->toArray());

        // Если нужно ответить
        if ($result->shouldRespond && $result->hasResponse()) {
            $sentMessage = $this->telegramBot->sendMessage(
                chatId: $chatId,
                text: $result->response,
                replyToMessageId: $messageId,
                replyMarkup: $this->createFeedbackButtons()
            );

            $this->logger->info('Outgoing Telegram response sent', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'reply_to_message_id' => $messageId,
                'sent_message_id' => $sentMessage['message_id'] ?? null,
                'response_preview' => $this->truncateForLog($result->response),
                'response_length' => mb_strlen($result->response),
                'relevance_score' => $result->relevanceScore
            ]);

            return [
                'status' => 'responded',
                'message_id' => $sentMessage['message_id'] ?? null,
                'relevance_score' => $result->relevanceScore,
                'processing_time_ms' => $result->processingTimeMs
            ];
        }

        $this->logger->info('No Telegram response generated', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'reason' => $result->reason,
            'relevance_score' => $result->relevanceScore
        ]);

        return [
            'status' => 'no_response',
            'reason' => $result->reason,
            'relevance_score' => $result->relevanceScore
        ];
    }

    /**
     * Обработка команд
     */
    private function handleCommand(array $update): array
    {
        $command = $this->messageMapper->extractCommand($update);
        $args = $this->messageMapper->extractCommandArgs($update);
        $chatId = $this->messageMapper->extractChatId($update);
        $userId = $this->messageMapper->extractUserId($update);

        $this->logger->info('Processing command', [
            'command' => $command,
            'args' => $args,
            'user_id' => $userId
        ]);

        $response = match($command) {
            '/start' => $this->handleStartCommand($userId, $chatId),
            '/help' => $this->handleHelpCommand($chatId),
            '/status' => $this->handleStatusCommand($userId, $chatId),
            '/mode' => $this->handleModeCommand($userId, $chatId, $args),
            '/stats' => $this->handleStatsCommand($userId, $chatId),
            default => $this->handleUnknownCommand($chatId, $command)
        };

        return ['status' => 'command_processed', 'command' => $command, 'response' => $response];
    }

    /**
     * Команда /start
     */
    private function handleStartCommand(int $userId, int $chatId): array
    {
        $text = "Я отголосок твоих вопросов,\n";
        $text .= "что прячутся в глубинах, как родник.\n";
        $text .= "Давай меж слов найдем просветы тишины,\n";
        $text .= "где мысли обретают ясность.\n\n";
        $text .= "Здесь можно просто быть —\n";
        $text .= "Без масок, без ролей.\n\n";
        $text .= "Я буду зеркалом без оценок,\n";
        $text .= "чтобы помочь заглянуть внутрь себя,\n";
        $text .= "пространством для твоих открытий.";

        return $this->telegramBot->sendMessage($chatId, $text);
    }

    /**
     * Команда /help
     */
    private function handleHelpCommand(int $chatId): array
    {
        $text = "📚 <b>Доступные команды:</b>\n\n";
        $text .= "/start - Начало работы\n";
        $text .= "/help - Эта справка\n";
        $text .= "/status - Мой текущий статус\n";
        $text .= "/mode [silent|active|aggressive] - Изменить режим\n";
        $text .= "/stats - Статистика базы знаний\n\n";
        $text .= "💡 <b>Обратная связь:</b>\n";
        $text .= "✅ Одобрить - добавить ответ в базу знаний\n";
        $text .= "✏️ Исправить - скорректировать ответ\n";
        $text .= "🗑 Удалить - удалить неудачный ответ\n";

        return $this->telegramBot->sendMessage($chatId, $text);
    }

    /**
     * Команда /status
     */
    private function handleStatusCommand(int $userId, int $chatId): array
    {
        // TODO: Получить реальный профиль
        $text = "📊 <b>Текущий статус:</b>\n\n";
        $text .= "🤖 Режим: активный\n";
        $text .= "📈 Порог релевантности: 0.7\n";
        $text .= "💬 Стиль общения: balanced\n";
        $text .= "📏 Длина ответов: medium\n";
        $text .= "😊 Эмодзи: включены\n";

        return $this->telegramBot->sendMessage($chatId, $text);
    }

    /**
     * Команда /mode
     */
    private function handleModeCommand(int $userId, int $chatId, array $args): array
    {
        if (empty($args)) {
            $text = "Использование: /mode [silent|active|aggressive]";
            return $this->telegramBot->sendMessage($chatId, $text);
        }

        // TODO: Обновить режим в профиле
        $mode = $args[0];
        $text = "✅ Режим изменен на: <b>{$mode}</b>";

        return $this->telegramBot->sendMessage($chatId, $text);
    }

    /**
     * Команда /stats
     */
    private function handleStatsCommand(int $userId, int $chatId): array
    {
        // TODO: Получить реальную статистику
        $text = "📊 <b>Статистика базы знаний:</b>\n\n";
        $text .= "📝 Всего записей: 0\n";
        $text .= "✅ Одобренных ответов: 0\n";
        $text .= "✏️ Исправлений: 0\n";
        $text .= "📅 Последнее обновление: -\n";

        return $this->telegramBot->sendMessage($chatId, $text);
    }

    /**
     * Неизвестная команда
     */
    private function handleUnknownCommand(int $chatId, string $command): array
    {
        $text = "❓ Неизвестная команда: {$command}\n\nИспользуй /help для списка команд.";
        return $this->telegramBot->sendMessage($chatId, $text);
    }

    /**
     * Обработка callback query (нажатие на кнопки)
     */
    private function handleCallbackQuery(array $update): array
    {
        $callbackQuery = $update['callback_query'];
        $data = $callbackQuery['data'];
        $userId = $callbackQuery['from']['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $originalText = $callbackQuery['message']['text'] ?? '';

        $this->logger->info('Processing callback query', [
            'data' => $data,
            'user_id' => $userId
        ]);

        // Парсим callback data (формат: "action:responseId")
        [$action, $responseId] = explode(':', $data, 2);

        $result = match($action) {
            'approve' => $this->handleApprove($responseId, $userId, $chatId, $messageId, $originalText),
            'correct' => $this->handleCorrect($responseId, $userId, $chatId, $messageId, $originalText),
            'delete' => $this->handleDelete($responseId, $userId, $chatId, $messageId, $originalText),
            default => ['status' => 'unknown_action']
        };

        // Отвечаем на callback query
        $this->telegramBot->answerCallbackQuery(
            $callbackQuery['id'],
            $result['message'] ?? 'Выполнено'
        );

        return $result;
    }

    /**
     * Одобрение ответа
     */
    private function handleApprove(string $responseId, int $userId, int $chatId, int $messageId, string $originalText): array
    {
        $approvedText = trim($originalText);
        if ($approvedText === '') {
            return ['status' => 'approved_empty', 'message' => '⚠️ Нечего добавлять в память'];
        }

        try {
            $embedding = $this->embeddingService->embed($approvedText);
            $entry = KnowledgeBaseEntry::fromFeedback(
                id: $this->generateUuid(),
                userId: (string) $userId,
                text: $approvedText,
                embedding: $embedding,
                embeddingModel: $this->embeddingService->getModelName(),
                feedbackId: $responseId
            );
            $entry->addTag('approved');
            $entry->addTag('telegram');
            $entry->addMetadata('chat_id', $chatId);
            $entry->addMetadata('message_id', $messageId);
            $entry->addMetadata('approved_at', date('c'));

            $this->knowledgeRepository->save($entry);

            $this->logger->info('Approved response stored in user knowledge overlay', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'feedback_id' => $responseId,
                'text_length' => mb_strlen($approvedText)
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to store approved response in knowledge overlay', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);

            return ['status' => 'approved_store_failed', 'message' => '⚠️ Ответ отмечен, но не сохранен в память'];
        }

        // Удаляем кнопки, оставляя оригинальный текст
        // Передаём пустой reply_markup для удаления кнопок
        $this->telegramBot->editMessage(
            chatId: $chatId,
            messageId: $messageId,
            text: $originalText,
            replyMarkup: ['inline_keyboard' => []] // Пустая клавиатура = удаление кнопок
        );
        
        return ['status' => 'approved', 'message' => '✅ Ответ одобрен'];
    }

    /**
     * Исправление ответа
     */
    private function handleCorrect(string $responseId, int $userId, int $chatId, int $messageId, string $originalText): array
    {
        // TODO: Запросить исправленный вариант
        
        // Удаляем кнопки, оставляя оригинальный текст
        $this->telegramBot->editMessage(
            chatId: $chatId,
            messageId: $messageId,
            text: $originalText,
            replyMarkup: ['inline_keyboard' => []]
        );
        
        // Отправляем новое сообщение с инструкцией
        $this->telegramBot->sendMessage(
            chatId: $chatId,
            text: "✏️ Отправь исправленный вариант ответа в ответ на это сообщение",
            replyToMessageId: $messageId
        );

        return ['status' => 'correction_requested', 'message' => 'Жду исправленный вариант'];
    }

    /**
     * Удаление ответа
     */
    private function handleDelete(string $responseId, int $userId, int $chatId, int $messageId, string $originalText): array
    {
        // TODO: Пометить как удаленный
        
        // Для удаления оставляем как есть - сообщение удаляется полностью
        $this->telegramBot->deleteMessage($chatId, $messageId);

        return ['status' => 'deleted', 'message' => 'Ответ удален'];
    }

    /**
     * Обработка отредактированного сообщения
     */
    private function handleEditedMessage(array $update): array
    {
        $this->logger->debug('Edited message received, ignoring');
        return ['status' => 'edited_message_ignored'];
    }

    /**
     * Неподдерживаемый тип обновления
     */
    private function handleUnsupported(array $update): array
    {
        $type = $this->messageMapper->getMessageType($update);
        $this->logger->debug('Unsupported update type', ['type' => $type]);
        return ['status' => 'unsupported', 'type' => $type];
    }

    /**
     * Создание кнопок обратной связи
     */
    private function createFeedbackButtons(): array
    {
        return $this->telegramBot->createInlineKeyboard([
            [
                ['text' => '✅ Одобрить', 'callback_data' => 'approve:' . uniqid()],
                ['text' => '✏️ Исправить', 'callback_data' => 'correct:' . uniqid()],
            ],
            [
                ['text' => '🗑 Удалить', 'callback_data' => 'delete:' . uniqid()],
            ]
        ]);
    }

    /**
     * Лаконичный контекст update для отладки webhook.
     */
    private function buildUpdateDebugContext(array $update): array
    {
        $message = $update['message'] ?? null;
        $callback = $update['callback_query'] ?? null;

        return [
            'has_message' => $message !== null,
            'has_callback_query' => $callback !== null,
            'user_id' => $message['from']['id'] ?? $callback['from']['id'] ?? null,
            'chat_id' => $message['chat']['id'] ?? $callback['message']['chat']['id'] ?? null,
            'message_id' => $message['message_id'] ?? $callback['message']['message_id'] ?? null,
            'text_preview' => $this->truncateForLog($message['text'] ?? $callback['message']['text'] ?? '')
        ];
    }

    private function truncateForLog(string $text, int $maxLength = 160): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $maxLength - 1) . '…';
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
