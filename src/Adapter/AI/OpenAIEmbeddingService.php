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
            
        } catch (\Throwable $e) {
            $message = $this->buildEmbeddingErrorMessage($e);
            $this->logger->error('OpenAI embedding failed', [
                'error' => $message,
                'raw_error' => $e->getMessage(),
                'text_length' => strlen($text),
                'model' => $this->model
            ]);
            throw new \RuntimeException('Failed to generate embedding: ' . $message, 0, $e);
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
            
        } catch (\Throwable $e) {
            $message = $this->buildEmbeddingErrorMessage($e);
            $this->logger->error('OpenAI batch embedding failed', [
                'error' => $message,
                'raw_error' => $e->getMessage(),
                'batch_size' => count($texts),
                'model' => $this->model
            ]);
            throw new \RuntimeException('Failed to generate embeddings: ' . $message, 0, $e);
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

    private function buildEmbeddingErrorMessage(\Throwable $error): string
    {
        $raw = $error->getMessage();
        $isResponseParsingTypeError =
            $error instanceof \TypeError &&
            str_contains($raw, 'OpenAI\\Responses\\Embeddings\\CreateResponse::from()');

        if ($isResponseParsingTypeError) {
            return 'OpenAI Embeddings returned an unexpected response format. ' .
                'Most common causes: invalid OPENAI_API_KEY, missing API billing/permissions, or invalid OPENAI_EMBEDDING_MODEL.';
        }

        return $raw;
    }
}
