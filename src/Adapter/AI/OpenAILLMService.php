<?php

namespace App\Adapter\AI;

use App\Core\Port\LLMServiceInterface;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

class OpenAILLMService implements LLMServiceInterface
{
    public function __construct(
        private Client $openAIClient,
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
            $response = $this->openAIClient->chat()->create(array_merge([
                'model' => $this->model,
                'messages' => $messages
            ], $options));
            
            return $response->choices[0]->message->content;
            
        } catch (\Exception $e) {
            $this->logger->error('OpenAI LLM generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'message_count' => count($messages)
            ]);
            throw new \RuntimeException('Failed to generate response: ' . $e->getMessage(), 0, $e);
        }
    }
    
    public function getModelName(): string
    {
        return "openai:{$this->model}";
    }
}
