// src/Worker/MessageProcessingWorker.php
namespace App\Worker;

use App\Core\UseCase\HandleMessageIntent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MessageProcessingWorker
{
    public function __construct(
        private HandleMessageIntent $handleIntent,
        private ConversationRepositoryInterface $conversationRepo
    ) {}
    
    public function __invoke(MessageProcessingJob $job): void
    {
        // 1. Получаем сообщение из БД
        $message = $this->conversationRepo->findMessage($job->getMessageId());
        
        // 2. Обрабатываем в зависимости от намерения
        $response = $this->handleIntent->execute($message, $job->getIntent());
        
        // 3. Сохраняем ответ
        $this->conversationRepo->saveResponse($response);
        
        // 4. Отправляем в мессенджер (асинхронно)
        $this->dispatchToMessenger($response);
    }
}