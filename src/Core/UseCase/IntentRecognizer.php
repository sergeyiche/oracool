<?php

namespace App\Core\UseCase;

/**
 * Распознаватель намерений сообщений (заглушка для MVP)
 */
class IntentRecognizer
{
    public function recognize($message): array
    {
        // Заглушка для MVP
        return ['type' => 'default'];
    }
}
