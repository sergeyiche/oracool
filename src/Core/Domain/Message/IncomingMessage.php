// src/Core/Domain/Message/IncomingMessage.php
namespace App\Core\Domain\Message;

class IncomingMessage
{
    public function __construct(
        private MessageId $id,
        private MessengerType $messenger, // "telegram", "whatsapp", etc
        private string $botId, // Идентификатор конкретного бота
        private UserId $userId,
        private MessageContent $content,
        private MessageContext $context,
        private \DateTimeImmutable $receivedAt
    ) {}
    
    // Value Objects
    public readonly class MessageContent {
        public function __construct(
            public string $text,
            public ?array $attachments = null, // фото, документы
            public ?Location $location = null,
            public ?string $replyToMessageId = null
        ) {}
    }
    
    public readonly class MessageContext {
        public function __construct(
            public array $userProfile,
            public ?string $sessionId,
            public string $language,
            public array $metadata = []
        ) {}
    }
}