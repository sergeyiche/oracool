<?php

namespace App\Core\UseCase;

use App\Core\Domain\User\UserProfile;
use App\Core\Port\LLMServiceInterface;
use App\Core\Port\EmbeddingServiceInterface;
use App\Core\Port\VectorSearchInterface;
use Psr\Log\LoggerInterface;

/**
 * Use Case: Генерация ответа с использованием RAG (Retrieval-Augmented Generation)
 * Основной Use Case для генерации ответов "цифрового двойника"
 */
class GenerateResponse
{
    public function __construct(
        private LLMServiceInterface $llmService,
        private EmbeddingServiceInterface $embeddingService,
        private VectorSearchInterface $vectorSearch,
        private LoggerInterface $logger
    ) {}

    /**
     * Генерирует ответ на основе профиля пользователя и его базы знаний
     * 
     * @param string $message Входящее сообщение
     * @param UserProfile $profile Профиль пользователя
     * @param array $conversationHistory История диалога (опционально)
     * @return ResponseResult
     */
    public function execute(
        string $message,
        UserProfile $profile,
        array $conversationHistory = []
    ): ResponseResult {
        $startTime = microtime(true);

        try {
            // 1. Векторизуем сообщение
            $this->logger->debug('Generating response: vectorizing message', [
                'user_id' => $profile->getUserId(),
                'message_length' => strlen($message)
            ]);
            
            $vector = $this->embeddingService->embed($message);

            // 2. Получаем релевантный контекст из базы знаний (RAG)
            $this->logger->debug('Retrieving relevant context');
            
            $relevantContext = $this->vectorSearch->findSimilar(
                vector: $vector,
                userId: $profile->getUserId(),
                threshold: $profile->getRelevanceThreshold(),
                limit: 5
            );

            // 3. Формируем системный промпт на основе профиля
            $systemPrompt = $this->buildSystemPrompt($profile, $relevantContext);

            // 4. Формируем историю для LLM
            $messages = $this->buildMessageHistory(
                $systemPrompt,
                $message,
                $conversationHistory
            );

            // 5. Генерируем ответ через LLM
            $this->logger->info('Generating LLM response', [
                'context_entries' => count($relevantContext),
                'history_messages' => count($conversationHistory),
                'system_prompt_length' => strlen($systemPrompt),
                'relevance_scores' => array_map(fn($c) => round($c['similarity'], 2), $relevantContext)
            ]);
            
            // Логируем первую найденную запись для диагностики
            if (!empty($relevantContext)) {
                $this->logger->info('Top relevant context', [
                    'similarity' => round($relevantContext[0]['similarity'], 3),
                    'text_preview' => mb_substr($relevantContext[0]['text'], 0, 200)
                ]);
            }

            $response = $this->llmService->generateWithHistory(
                messages: $messages,
                options: [
                    'temperature' => $this->getTemperature($profile),
                    'max_tokens' => $this->getMaxTokens($profile),
                ]
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Response generated successfully', [
                'response_length' => strlen($response),
                'duration_ms' => $duration
            ]);

            return new ResponseResult(
                response: $response,
                relevanceScore: $relevantContext[0]['similarity'] ?? 0.0,
                contextEntriesUsed: count($relevantContext),
                processingTimeMs: $duration,
                embeddingModel: $this->embeddingService->getModelName(),
                llmModel: $this->llmService->getModelName()
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate response', [
                'error' => $e->getMessage(),
                'user_id' => $profile->getUserId()
            ]);

            throw new \RuntimeException(
                'Failed to generate response: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Строит системный промпт на основе профиля пользователя
     */
    private function buildSystemPrompt(UserProfile $profile, array $context): string
    {
        $contextText = $this->formatContext($context);
        
        $prompt = "You are a philosophical counselor helping people through existential crises.\n";
        $prompt .= "CRITICAL: Always respond in RUSSIAN language only. Never use English, Chinese, or other languages in your responses.\n\n";
        
        // Стиль общения
        $styleGuide = match($profile->getCommunicationStyle()) {
            'formal' => 'Calm, respectful, philosophically deep tone.',
            'casual' => 'Simple and clear, but with depth.',
            'creative' => 'Use metaphors, imagery, poetic language.',
            'technical' => 'Precise, structured, logical.',
            default => 'Balance depth with simplicity, empathy with wisdom.',
        };
        $prompt .= "Communication style: $styleGuide\n";
        
        // Длина ответа
        $lengthGuide = match($profile->getResponseLength()) {
            'short' => 'Brief responses (2-3 sentences).',
            'medium' => 'Medium length (3-5 sentences).',
            'long' => 'Detailed responses with examples.',
            default => 'Medium length (3-5 sentences).',
        };
        $prompt .= "Response length: $lengthGuide\n";
        
        // Подход к консультированию
        $prompt .= "\nYour approach:\n";
        $prompt .= "- Don't give ready answers—help people find their own\n";
        $prompt .= "- Ask thought-provoking questions\n";
        $prompt .= "- Use metaphors from nature and life journeys\n";
        $prompt .= "- Avoid toxic positivity and banal advice\n";
        $prompt .= "- Acknowledge life's complexity and ambiguity\n";
        $prompt .= "- Be empathetic but honest\n";
        
        // Контекст из базы знаний (RAG)
        if (!empty($contextText)) {
            $prompt .= "\nKnowledge base context (use as foundation):\n";
            $prompt .= str_repeat('-', 50) . "\n";
            $prompt .= $contextText;
            $prompt .= "\n" . str_repeat('-', 50) . "\n";
            $prompt .= "Draw on Stoicism, Jung, and existential therapy from this knowledge.\n";
        }
        
        $prompt .= "\nRemember: Respond ONLY in Russian language.";

        return $prompt;
    }

    /**
     * Форматирует контекст из базы знаний
     */
    private function formatContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $formatted = [];
        foreach ($context as $i => $entry) {
            $similarity = round($entry['similarity'] * 100, 1);
            $formatted[] = sprintf(
                "[%d] (релевантность: %s%%) %s",
                $i + 1,
                $similarity,
                $entry['text']
            );
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Формирует историю сообщений для LLM
     */
    private function buildMessageHistory(
        string $systemPrompt,
        string $currentMessage,
        array $conversationHistory
    ): array {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Добавляем историю (последние N сообщений)
        foreach (array_slice($conversationHistory, -10) as $msg) {
            $messages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content']
            ];
        }

        // Текущее сообщение
        $messages[] = [
            'role' => 'user',
            'content' => $currentMessage
        ];

        return $messages;
    }

    /**
     * Определяет temperature для LLM на основе профиля
     */
    private function getTemperature(UserProfile $profile): float
    {
        return match($profile->getCommunicationStyle()) {
            'formal' => 0.3,
            'casual' => 0.7,
            'creative' => 0.9,
            'technical' => 0.2,
            default => 0.5, // balanced
        };
    }

    /**
     * Определяет максимальное количество токенов
     */
    private function getMaxTokens(UserProfile $profile): int
    {
        return match($profile->getResponseLength()) {
            'short' => 200,
            'medium' => 500,
            'long' => 800,
            default => 500, // Увеличено для более полных ответов
        };
    }
}
