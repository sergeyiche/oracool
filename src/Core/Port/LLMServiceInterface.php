<?php

namespace App\Core\Port;

interface LLMServiceInterface
{
    /**
     * Генерирует ответ на основе промпта
     * @param string $prompt Пользовательский промпт
     * @param array $context Дополнительный контекст
     * @param array $options Опции (temperature, max_tokens и т.д.)
     * @return string Сгенерированный ответ
     */
    public function generate(string $prompt, array $context = [], array $options = []): string;
    
    /**
     * Генерирует ответ с системным промптом
     * @param string $systemPrompt Системный промпт
     * @param string $userPrompt Пользовательский промпт
     * @param array $options Опции
     * @return string Сгенерированный ответ
     */
    public function generateWithSystemPrompt(
        string $systemPrompt, 
        string $userPrompt, 
        array $options = []
    ): string;
    
    /**
     * Генерирует ответ с историей сообщений
     * @param array $messages Массив сообщений [['role' => 'user|assistant', 'content' => '...']]
     * @param array $options Опции
     * @return string Сгенерированный ответ
     */
    public function generateWithHistory(array $messages, array $options = []): string;
    
    /**
     * Возвращает название модели/провайдера
     * @return string Например, "ollama:llama3.2"
     */
    public function getModelName(): string;
}
