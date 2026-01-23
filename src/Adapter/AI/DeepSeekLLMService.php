<?php

namespace App\Adapter\AI;

use App\Core\Port\LLMServiceInterface;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

/**
 * DeepSeek LLM Service - использует DeepSeek API (совместимый с OpenAI)
 * 
 * Преимущества:
 * - В ~10 раз дешевле OpenAI ($0.14/$0.28 за 1M токенов)
 * - Отличная поддержка русского языка
 * - Высокое качество (конкурирует с GPT-4)
 */
class DeepSeekLLMService implements LLMServiceInterface
{
    public function __construct(
        private Client $deepSeekClient,
        private LoggerInterface $logger,
        private string $model
    ) {}
    
    public function generate(string $prompt, array $context = [], array $options = []): string
    {
        $messages = [];
        
        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => implode("\n", $context)
            ];
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];
        
        return $this->generateWithHistory($messages, $options);
    }
    
    public function generateWithSystemPrompt(
        string $systemPrompt, 
        string $userPrompt, 
        array $options = []
    ): string {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];
        
        return $this->generateWithHistory($messages, $options);
    }
    
    public function generateWithHistory(array $messages, array $options = []): string
    {
        try {
            $this->logger->debug('DeepSeek LLM request', [
                'model' => $this->model,
                'message_count' => count($messages)
            ]);
            
            $response = $this->deepSeekClient->chat()->create(array_merge([
                'model' => $this->model,
                'messages' => $messages
            ], $options));
            
            $content = $response->choices[0]->message->content;
            
            $this->logger->info('DeepSeek LLM response', [
                'model' => $this->model,
                'response_length' => strlen($content),
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens ?? 0,
                    'completion_tokens' => $response->usage->completionTokens ?? 0,
                    'total_tokens' => $response->usage->totalTokens ?? 0,
                ]
            ]);
            
            return $content;
            
        } catch (\Exception $e) {
            $this->logger->error('DeepSeek LLM generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'message_count' => count($messages)
            ]);
            throw new \RuntimeException('Failed to generate response: ' . $e->getMessage(), 0, $e);
        }
    }
    
    public function getModelName(): string
    {
        return "deepseek:{$this->model}";
    }
}
