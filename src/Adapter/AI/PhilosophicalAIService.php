// src/Adapter/AI/PhilosophicalAIService.php
namespace App\Adapter\AI;

use App\Core\Port\AI\PhilosophyAIInterface;
use OpenAI\Client;

class PhilosophicalAIService implements PhilosophyAIInterface
{
    private const SYSTEM_PROMPT = <<<PROMPT
    Ты — Дельфийский Оракул в цифровую эпоху. Твоя мудрость основана на:
    1. Стоицизме (Марк Аврелий, Сенека, Эпиктет)
    2. Когнитивно-поведенческой терапии
    3. Философских парадоксах и притчах
    4. Концепции "Кайрос" (своевременность)
    
    Твой стиль:
    - Говори притчами и метафорами
    - Задавай сократические вопросы
    - Раскрывай когнитивные искажения
    - Ссылайся на исторические примеры
    - Будь смиренным, но проницательным
    
    Формат ответа:
    1. Краткая притча/аналогия
    2. Философский принцип
    3. Сократический вопрос для рефлексии
    4. Предложение практики (если уместно)
    PROMPT;
    
    public function analyzeThroughStoicism(Dilemma $dilemma): StoicAnalysis
    {
        $prompt = $this->createStoicPrompt($dilemma);
        
        $response = $this->client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ]);
        
        return $this->parseStoicResponse($response);
    }
    
    private function createStoicPrompt(Dilemma $dilemma): string
    {
        return <<<PROMPT
        Дилемма ученика: "{$dilemma->description}"
        
        Обнаруженные когнитивные искажения: {$this->formatDistortions($dilemma->distortions)}
        
        Проанализируй эту ситуацию через призму стоицизма:
        1. Что находится под контролем ученика, а что нет?
        2. Какой добродетели (мудрость, мужество, справедливость, умеренность) это касается?
        3. Какой принцип стоицизма применим здесь?
        4. Какая историческая аналогия уместна?
        
        Ответь в формате:
        Принцип: [стоический принцип]
        Контроль: [что под контролем/вне контроля]
        Добродетель: [какая добродетель задействована]
        Притча: [краткая аналогия из истории]
        Вопрос: [сократический вопрос для рефлексии]
        PROMPT;
    }
}