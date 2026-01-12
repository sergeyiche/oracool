// src/Adapter/Telegram/TelegramWebhookController.php
namespace App\Adapter\Telegram;

use App\Core\UseCase\ProcessIncomingMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TelegramWebhookController
{
    public function __construct(
        private ProcessIncomingMessage $processMessage,
        private TelegramMessageMapper $messageMapper
    ) {}
    
    #[Route('/webhook/telegram/{botId}', methods: ['POST'])]
    public function handleWebhook(Request $request, string $botId): JsonResponse
    {
        // 1. Валидация подписи (Telegram Secret Token)
        if (!$this->validateTelegramSecret($request, $botId)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        // 2. Маппинг Telegram-формата в доменную модель
        $update = json_decode($request->getContent(), true);
        $incomingMessage = $this->messageMapper->mapToDomain($update, $botId);
        
        try {
            // 3. Асинхронная обработка (через очередь)
            $this->processMessage->executeAsync($incomingMessage);
            
            // 4. Немедленный ответ Telegram (чтобы не было timeout)
            return new JsonResponse(['ok' => true]);
            
        } catch (\Throwable $e) {
            // Логирование ошибки
            return new JsonResponse(['ok' => false, 'error' => 'Processing error'], 500);
        }
    }
}