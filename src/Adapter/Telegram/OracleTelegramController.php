// src/Adapter/Telegram/OracleTelegramController.php
namespace App\Adapter\Telegram;

use App\Core\UseCase\ProcessPhilosophicalQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OracleTelegramController
{
    #[Route('/oracle/telegram', name: 'oracle_webhook', methods: ['POST'])]
    public function handleOracleRequest(Request $request): JsonResponse
    {
        $update = json_decode($request->getContent(), true);
        
        // Проверяем, это команда или философский вопрос
        if (isset($update['message']['text'])) {
            $text = $update['message']['text'];
            
            if (str_starts_with($text, '/')) {
                return $this->handleCommand($update);
            }
            
            // Философский запрос
            return $this->handlePhilosophicalQuery($update);
        }
        
        return new JsonResponse(['status' => 'ignored']);
    }
    
    private function handlePhilosophicalQuery(array $update): JsonResponse
    {
        // Маппинг в доменную модель
        $message = $this->messageMapper->mapToDomain($update);
        
        // Обработка философского запроса
        $oracleResponse = $this->processPhilosophicalQuery->execute($message);
        
        // Форматирование ответа для Telegram
        $telegramResponse = $this->formatOracleResponse($oracleResponse);
        
        // Отправка (асинхронно)
        $this->telegramSender->sendMessage(
            $update['message']['chat']['id'],
            $telegramResponse
        );
        
        return new JsonResponse(['status' => 'processing']);
    }
    
    private function formatOracleResponse(OracleResponse $response): string
    {
        $formatted = $response->content . "\n\n";
        
        if ($response->principle) {
            $formatted .= "🏛 *Принцип*: {$response->principle}\n\n";
        }
        
        if ($response->cognitiveReframe) {
            $formatted .= "🔄 *Когнитивный рефрейминг*: {$response->cognitiveReframe}\n\n";
        }
        
        if (!empty($response->followUpQuestions)) {
            $formatted .= "💭 *Вопрос для рефлексии*:\n";
            foreach ($response->followUpQuestions as $question) {
                $formatted .= "• {$question}\n";
            }
        }
        
        $formatted .= "\n_«Познай самого себя» — Надпись в Дельфах_";
        
        return $formatted;
    }
}