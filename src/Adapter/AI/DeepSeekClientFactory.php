<?php

namespace App\Adapter\AI;

use OpenAI;
use OpenAI\Client;

class DeepSeekClientFactory
{
    public static function create(string $apiKey, string $baseUrl): Client
    {
        return OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->make();
    }
}
