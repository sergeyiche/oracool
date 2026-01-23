<?php

namespace App\Adapter\AI;

use App\Core\Port\LLMServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class OllamaLLMService implements LLMServiceInterface
{
    public function __construct(
        private Client $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
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
            $response = $this->httpClient->post("{$this->baseUrl}/api/chat", [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false
                ], $options),
                'timeout' => 180 // Увеличено для длинных промптов с RAG
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['message']['content'])) {
                throw new \RuntimeException('Invalid response from Ollama: missing message content');
            }
            
            return $data['message']['content'];
            
        } catch (GuzzleException $e) {
            $this->logger->error('Ollama LLM generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'message_count' => count($messages)
            ]);
            throw new \RuntimeException('Failed to generate response: ' . $e->getMessage(), 0, $e);
        }
    }
    
    public function getModelName(): string
    {
        return "ollama:{$this->model}";
    }
}
