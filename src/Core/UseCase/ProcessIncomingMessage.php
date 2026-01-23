<?php

namespace App\Core\UseCase;

use App\Core\Domain\Message\IncomingMessage;
use App\Core\Port\MessageQueue\MessageQueueInterface;
use App\Core\Port\Repository\ConversationRepositoryInterface;

class ProcessIncomingMessage
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepo,
        private MessageQueueInterface $messageQueue,
        private IntentRecognizer $intentRecognizer
    ) {}
    
    public function executeAsync(IncomingMessage $message): void
    {
        // 1. Сохраняем сырое сообщение (для аналитики и дебага)
        $this->conversationRepo->saveRawMessage($message);
        
        // 2. Определяем тип сообщения и намерение
        $intent = $this->intentRecognizer->recognize($message);
        
        // 3. Классифицируем и отправляем в соответствующую очередь
        $queueName = $this->determineQueue($intent);
        
        $this->messageQueue->publish('message_processing', [
            'message_id' => $message->getId()->toString(),
            'intent' => $intent->toArray(),
            'priority' => $intent->isUrgent() ? 'high' : 'normal'
        ], $queueName);
    }
    
    private function determineQueue(Intent $intent): string
    {
        return match($intent->getType()) {
            IntentType::URGENT_HELP => 'urgent_processing',
            IntentType::DAILY_CHECKIN => 'scheduled_processing',
            IntentType::LIFE_OPTIMIZATION => 'ai_processing',
            default => 'default_processing'
        };
    }
}