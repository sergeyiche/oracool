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
    private ?string $systemPromptTemplate = null;
    private ?int $systemPromptMtime = null;
    private ?int $systemPromptSize = null;

    public function __construct(
        private LLMServiceInterface $llmService,
        private EmbeddingServiceInterface $embeddingService,
        private VectorSearchInterface $vectorSearch,
        private LoggerInterface $logger,
        private string $systemPromptFilePath = '',
        private string $globalKnowledgeUserId = '858361483'
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
            
            $relevantContext = $this->findSimilarAcrossKnowledgeScopes(
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
        $template = $this->loadSystemPromptTemplate();
        $contextText = $this->formatContext($context);

        $styleGuide = $this->getStyleGuide($profile);
        $lengthGuide = $this->getLengthGuide($profile);
        $contextBlock = $this->buildContextBlock($contextText);
        $languageRule = 'КРИТИЧНО: всегда отвечай только на русском языке.';

        // Поддерживаем как шаблон с плейсхолдерами, так и обычный текстовый файл без них.
        $hasPlaceholders = str_contains($template, '{{STYLE_GUIDE}}')
            || str_contains($template, '{{LENGTH_GUIDE}}')
            || str_contains($template, '{{CONTEXT_BLOCK}}')
            || str_contains($template, '{{LANGUAGE_RULE}}');

        if ($hasPlaceholders) {
            return trim((string) strtr($template, [
                '{{STYLE_GUIDE}}' => $styleGuide,
                '{{LENGTH_GUIDE}}' => $lengthGuide,
                '{{CONTEXT_BLOCK}}' => $contextBlock,
                '{{LANGUAGE_RULE}}' => $languageRule,
            ]));
        }

        $prompt = rtrim($template);
        $prompt .= "\n\nТехнические настройки ответа:";
        $prompt .= "\n- Стиль коммуникации: {$styleGuide}";
        $prompt .= "\n- Длина ответа: {$lengthGuide}";

        if ($contextBlock !== '') {
            $prompt .= "\n\n{$contextBlock}";
        }

        $prompt .= "\n\n{$languageRule}";

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

    private function getStyleGuide(UserProfile $profile): string
    {
        return match($profile->getCommunicationStyle()) {
            'formal' => 'спокойный, уважительный, философски глубокий',
            'casual' => 'простой и понятный, но с глубиной',
            'creative' => 'метафоричный, образный, поэтичный',
            'technical' => 'точный, структурированный, логичный',
            default => 'сбалансированный: эмпатия + ясность',
        };
    }

    private function getLengthGuide(UserProfile $profile): string
    {
        return match($profile->getResponseLength()) {
            'short' => 'кратко: 2-3 предложения',
            'medium' => 'средне: 3-5 предложений',
            'long' => 'подробно: с примерами и конкретикой',
            default => 'средне: 3-5 предложений',
        };
    }

    private function buildContextBlock(string $contextText): string
    {
        if ($contextText === '') {
            return '';
        }

        return "Контекст из базы знаний (используй как опору):\n"
            . str_repeat('-', 50) . "\n"
            . $contextText . "\n"
            . str_repeat('-', 50);
    }

    private function loadSystemPromptTemplate(): string
    {
        if ($this->systemPromptFilePath === '') {
            $this->logger->warning('System prompt file path is empty, using default fallback prompt');
            return $this->systemPromptTemplate = $this->getDefaultPromptTemplate();
        }

        clearstatcache(true, $this->systemPromptFilePath);

        $currentMtime = filemtime($this->systemPromptFilePath);
        $currentSize = filesize($this->systemPromptFilePath);

        if (
            $this->systemPromptTemplate !== null
            && $currentMtime !== false
            && $currentSize !== false
            && $this->systemPromptMtime === (int) $currentMtime
            && $this->systemPromptSize === (int) $currentSize
        ) {
            return $this->systemPromptTemplate;
        }

        $content = @file_get_contents($this->systemPromptFilePath);
        if ($content === false || trim($content) === '') {
            $this->logger->warning('Failed to load system prompt file, using default fallback prompt', [
                'path' => $this->systemPromptFilePath
            ]);

            // Сбрасываем метку времени, чтобы при следующем корректном изменении
            // система подтянула файл, а не оставалась на fallback-промпте навсегда.
            $this->systemPromptMtime = null;
            $this->systemPromptSize = null;

            return $this->systemPromptTemplate = $this->getDefaultPromptTemplate();
        }

        $this->systemPromptMtime = $currentMtime !== false ? (int) $currentMtime : null;
        $this->systemPromptSize = $currentSize !== false ? (int) $currentSize : null;

        return $this->systemPromptTemplate = trim($content);
    }

    private function getDefaultPromptTemplate(): string
    {
        return "Ты — экзистенциальный собеседник и проводник.\n"
            . "Сопровождай человека к ясности, устойчивости и контакту с реальностью.\n"
            . "Не лечи и не морализируй, говори тепло, уважительно и недирективно.";
    }

    private function findSimilarAcrossKnowledgeScopes(
        array $vector,
        string $userId,
        float $threshold,
        int $limit
    ): array {
        $allRows = [];
        foreach ($this->getSearchUserIds($userId) as $searchUserId) {
            $rows = $this->vectorSearch->findSimilar(
                vector: $vector,
                userId: $searchUserId,
                threshold: $threshold,
                limit: $limit
            );

            foreach ($rows as $row) {
                $row['matched_user_id'] = $searchUserId;
                $allRows[] = $row;
            }
        }

        if (empty($allRows)) {
            return [];
        }

        $deduped = [];
        foreach ($allRows as $row) {
            $key = sha1(trim((string) ($row['text'] ?? '')));
            if (!isset($deduped[$key]) || (float) $row['similarity'] > (float) $deduped[$key]['similarity']) {
                $deduped[$key] = $row;
            }
        }

        $merged = array_values($deduped);
        usort($merged, fn(array $a, array $b) => (float) $b['similarity'] <=> (float) $a['similarity']);

        return array_slice($merged, 0, $limit);
    }

    private function getSearchUserIds(string $userId): array
    {
        $ids = [$userId];
        if ($this->globalKnowledgeUserId !== '' && $this->globalKnowledgeUserId !== $userId) {
            $ids[] = $this->globalKnowledgeUserId;
        }

        return array_values(array_unique($ids));
    }
}
