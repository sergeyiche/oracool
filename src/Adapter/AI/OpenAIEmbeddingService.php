<?php

namespace App\Adapter\AI;

use App\Core\Port\EmbeddingServiceInterface;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

class OpenAIEmbeddingService implements EmbeddingServiceInterface
{
    public function __construct(
        private Client $openAIClient,
        private LoggerInterface $logger,
        private string $model,
        private int $dimension
    ) {}
    
    public function embed(string $text): array
    {
        try {
            $response = $this->openAIClient->embeddings()->create([
                'model' => $this->model,
                'input' => $text
            ]);
            
            return $response->embeddings[0]->embedding;
            
        } catch (\Exception $e) {
            $this->logger->error('OpenAI embedding failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
                'model' => $this->model
            ]);
            throw new \RuntimeException('Failed to generate embedding: ' . $e->getMessage(), 0, $e);
        }
    }
    
    public function embedBatch(array $texts): array
    {
        try {
            $response = $this->openAIClient->embeddings()->create([
                'model' => $this->model,
                'input' => $texts
            ]);
            
            return array_map(
                fn($embedding) => $embedding->embedding,
                $response->embeddings
            );
            
        } catch (\Exception $e) {
            $this->logger->error('OpenAI batch embedding failed', [
                'error' => $e->getMessage(),
                'batch_size' => count($texts),
                'model' => $this->model
            ]);
            throw new \RuntimeException('Failed to generate embeddings: ' . $e->getMessage(), 0, $e);
        }
    }
    
    public function getDimension(): int
    {
        return $this->dimension;
    }
    
    public function getModelName(): string
    {
        return "openai:{$this->model}";
    }
}
