<?php

namespace App\Adapter\AI;

use App\Core\Port\EmbeddingServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class OllamaEmbeddingService implements EmbeddingServiceInterface
{
    public function __construct(
        private Client $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $model,
        private int $dimension
    ) {}
    
    public function embed(string $text): array
    {
        try {
            $response = $this->httpClient->post("{$this->baseUrl}/api/embeddings", [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $text
                ],
                'timeout' => 30
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['embedding'])) {
                throw new \RuntimeException('Invalid response from Ollama: missing embedding');
            }
            
            return $data['embedding'];
            
        } catch (GuzzleException $e) {
            $this->logger->error('Ollama embedding failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
                'model' => $this->model
            ]);
            throw new \RuntimeException('Failed to generate embedding: ' . $e->getMessage(), 0, $e);
        }
    }
    
    public function embedBatch(array $texts): array
    {
        // Ollama не поддерживает батч-запросы, поэтому делаем последовательно
        $embeddings = [];
        foreach ($texts as $text) {
            $embeddings[] = $this->embed($text);
        }
        return $embeddings;
    }
    
    public function getDimension(): int
    {
        return $this->dimension;
    }
    
    public function getModelName(): string
    {
        return "ollama:{$this->model}";
    }
}
