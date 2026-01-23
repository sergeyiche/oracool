<?php

namespace App\Adapter\Telegram;

/**
 * Маппер для преобразования Telegram сообщений
 */
class TelegramMessageMapper
{
    /**
     * Извлекает текст из обновления Telegram
     */
    public function extractText(array $update): ?string
    {
        // Из обычного сообщения
        if (isset($update['message']['text'])) {
            return trim($update['message']['text']);
        }

        // Из отредактированного сообщения
        if (isset($update['edited_message']['text'])) {
            return trim($update['edited_message']['text']);
        }

        // Из callback query
        if (isset($update['callback_query']['data'])) {
            return trim($update['callback_query']['data']);
        }

        return null;
    }

    /**
     * Извлекает ID пользователя
     */
    public function extractUserId(array $update): ?int
    {
        if (isset($update['message']['from']['id'])) {
            return $update['message']['from']['id'];
        }

        if (isset($update['edited_message']['from']['id'])) {
            return $update['edited_message']['from']['id'];
        }

        if (isset($update['callback_query']['from']['id'])) {
            return $update['callback_query']['from']['id'];
        }

        return null;
    }

    /**
     * Извлекает ID чата
     */
    public function extractChatId(array $update): ?int
    {
        if (isset($update['message']['chat']['id'])) {
            return $update['message']['chat']['id'];
        }

        if (isset($update['edited_message']['chat']['id'])) {
            return $update['edited_message']['chat']['id'];
        }

        if (isset($update['callback_query']['message']['chat']['id'])) {
            return $update['callback_query']['message']['chat']['id'];
        }

        return null;
    }

    /**
     * Извлекает ID сообщения
     */
    public function extractMessageId(array $update): ?int
    {
        if (isset($update['message']['message_id'])) {
            return $update['message']['message_id'];
        }

        if (isset($update['edited_message']['message_id'])) {
            return $update['edited_message']['message_id'];
        }

        if (isset($update['callback_query']['message']['message_id'])) {
            return $update['callback_query']['message']['message_id'];
        }

        return null;
    }

    /**
     * Извлекает username пользователя
     */
    public function extractUsername(array $update): ?string
    {
        $from = null;

        if (isset($update['message']['from'])) {
            $from = $update['message']['from'];
        } elseif (isset($update['callback_query']['from'])) {
            $from = $update['callback_query']['from'];
        }

        if (!$from) {
            return null;
        }

        return $from['username'] 
            ?? $from['first_name'] 
            ?? $from['id'] 
            ?? null;
    }

    /**
     * Определяет тип сообщения
     */
    public function getMessageType(array $update): string
    {
        if (isset($update['message'])) {
            return 'message';
        }

        if (isset($update['edited_message'])) {
            return 'edited_message';
        }

        if (isset($update['callback_query'])) {
            return 'callback_query';
        }

        if (isset($update['inline_query'])) {
            return 'inline_query';
        }

        return 'unknown';
    }

    /**
     * Проверяет, является ли сообщение командой
     */
    public function isCommand(array $update): bool
    {
        $text = $this->extractText($update);
        return $text && str_starts_with($text, '/');
    }

    /**
     * Извлекает команду
     */
    public function extractCommand(array $update): ?string
    {
        if (!$this->isCommand($update)) {
            return null;
        }

        $text = $this->extractText($update);
        $parts = explode(' ', $text);
        $command = $parts[0];

        // Убираем @ бота если есть
        if (str_contains($command, '@')) {
            $command = explode('@', $command)[0];
        }

        return $command;
    }

    /**
     * Извлекает аргументы команды
     */
    public function extractCommandArgs(array $update): array
    {
        if (!$this->isCommand($update)) {
            return [];
        }

        $text = $this->extractText($update);
        $parts = explode(' ', $text);
        array_shift($parts); // Убираем саму команду

        return array_filter($parts);
    }

    /**
     * Проверяет, является ли сообщение ответом на другое сообщение
     */
    public function isReply(array $update): bool
    {
        return isset($update['message']['reply_to_message']);
    }

    /**
     * Извлекает ID сообщения, на которое отвечают
     */
    public function extractReplyToMessageId(array $update): ?int
    {
        if (!$this->isReply($update)) {
            return null;
        }

        return $update['message']['reply_to_message']['message_id'] ?? null;
    }

    /**
     * Преобразует обновление в массив для логирования
     */
    public function toLoggableArray(array $update): array
    {
        return [
            'type' => $this->getMessageType($update),
            'chat_id' => $this->extractChatId($update),
            'user_id' => $this->extractUserId($update),
            'username' => $this->extractUsername($update),
            'message_id' => $this->extractMessageId($update),
            'text' => $this->extractText($update),
            'is_command' => $this->isCommand($update),
            'command' => $this->extractCommand($update),
            'is_reply' => $this->isReply($update),
        ];
    }
}
