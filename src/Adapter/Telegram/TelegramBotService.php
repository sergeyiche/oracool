<?php

namespace App\Adapter\Telegram;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Сервис для взаимодействия с Telegram Bot API
 */
class TelegramBotService
{
    private string $apiUrl;

    public function __construct(
        private Client $httpClient,
        private LoggerInterface $logger,
        private string $botToken
    ) {
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}";
    }

    /**
     * Отправка текстового сообщения
     */
    public function sendMessage(
        int $chatId,
        string $text,
        ?int $replyToMessageId = null,
        ?array $replyMarkup = null
    ): array {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyToMessageId) {
            $data['reply_to_message_id'] = $replyToMessageId;
        }

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            return $this->makeRequest('sendMessage', $data);
        } catch (\RuntimeException $e) {
            $shouldRetryWithoutReply =
                $replyToMessageId !== null &&
                str_contains($e->getMessage(), 'message to be replied not found');

            if (!$shouldRetryWithoutReply) {
                throw $e;
            }

            $this->logger->warning('Retrying Telegram sendMessage without reply_to_message_id', [
                'chat_id' => $chatId,
                'reply_to_message_id' => $replyToMessageId,
            ]);

            unset($data['reply_to_message_id']);
            return $this->makeRequest('sendMessage', $data);
        }
    }

    /**
     * Редактирование сообщения
     */
    public function editMessage(
        int $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null
    ): array {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->makeRequest('editMessageText', $data);
    }

    /**
     * Удаление сообщения
     */
    public function deleteMessage(int $chatId, int $messageId): array
    {
        return $this->makeRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Отправка действия (печатает...)
     */
    public function sendChatAction(int $chatId, string $action = 'typing'): array
    {
        return $this->makeRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action, // typing, upload_photo, record_video, etc.
        ]);
    }

    /**
     * Установка webhook
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $data = ['url' => $url];
        
        if ($secretToken) {
            $data['secret_token'] = $secretToken;
        }

        return $this->makeRequest('setWebhook', $data);
    }

    /**
     * Удаление webhook
     */
    public function deleteWebhook(): array
    {
        return $this->makeRequest('deleteWebhook');
    }

    /**
     * Получение информации о webhook
     */
    public function getWebhookInfo(): array
    {
        return $this->makeRequest('getWebhookInfo');
    }

    /**
     * Получение информации о боте
     */
    public function getMe(): array
    {
        return $this->makeRequest('getMe');
    }

    /**
     * Ответ на callback query
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): array {
        return $this->makeRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    /**
     * Создание inline keyboard
     */
    public function createInlineKeyboard(array $buttons): array
    {
        return [
            'inline_keyboard' => $buttons
        ];
    }

    /**
     * Создание reply keyboard
     */
    public function createReplyKeyboard(
        array $buttons,
        bool $resize = true,
        bool $oneTime = false
    ): array {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resize,
            'one_time_keyboard' => $oneTime,
        ];
    }

    /**
     * Базовый метод для выполнения запросов к API
     */
    private function makeRequest(string $method, array $data = []): array
    {
        $url = "{$this->apiUrl}/{$method}";

        try {
            $this->logger->debug("Telegram API request: {$method}", [
                'data' => $data
            ]);

            $response = $this->httpClient->post($url, [
                'json' => $data,
                'timeout' => 10,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!is_array($body) || !isset($body['ok']) || !$body['ok']) {
                throw new \RuntimeException(
                    "Telegram API error: " . ($body['description'] ?? 'Unknown error')
                );
            }

            $this->logger->debug("Telegram API response: {$method}", [
                'result' => $body['result'] ?? null
            ]);

            // Ensure we always return an array
            $result = $body['result'] ?? [];
            return is_array($result) ? $result : [];

        } catch (\Exception $e) {
            $this->logger->error("Telegram API request failed: {$method}", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw new \RuntimeException(
                "Failed to call Telegram API method '{$method}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Форматирование текста для HTML режима
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Создание кнопок для feedback
     */
    public function createFeedbackButtons(string $responseId): array
    {
        return $this->createInlineKeyboard([
            [
                ['text' => '✅ Одобрить', 'callback_data' => "approve:{$responseId}"],
                ['text' => '✏️ Исправить', 'callback_data' => "correct:{$responseId}"],
            ],
            [
                ['text' => '🗑 Удалить', 'callback_data' => "delete:{$responseId}"],
            ]
        ]);
    }
}
