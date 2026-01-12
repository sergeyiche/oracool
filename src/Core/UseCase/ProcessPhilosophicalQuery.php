// src/Core/UseCase/ProcessPhilosophicalQuery.php
namespace App\Core\UseCase;

use App\Core\Domain\Message\IncomingMessage;
use App\Core\Domain\Philosophy\Response\OracleResponse;
use App\Core\Port\AI\PhilosophyAIInterface;
use App\Core\Port\Repository\UserRepositoryInterface;

class ProcessPhilosophicalQuery
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PhilosophyAIInterface $philosophyAI,
        private CognitiveAnalyzer $cognitiveAnalyzer
    ) {}
    
    public function execute(IncomingMessage $message): OracleResponse
    {
        // 1. Находим или создаем пользователя
        $user = $this->userRepository->findByMessengerId(
            $message->getMessengerType(),
            $message->getUserId()
        ) ?? $this->createNewOracleStudent($message);
        
        // 2. Анализируем когнитивные искажения
        $distortions = $user->getPhilosophicalProfile()
            ->assessCognitiveDistortions($message->getContent()->text);
        
        // 3. Определяем тип запроса
        $queryType = $this->classifyPhilosophicalQuery($message);
        
        // 4. Генерируем ответ в стиле Оракула
        return match ($queryType) {
            QueryType::DILEMMA => $this->handleDilemma($user, $message, $distortions),
            QueryType::REFLECTION => $this->handleReflection($user, $message),
            QueryType::GUIDANCE => $this->provideGuidance($user, $message),
            default => $this->provideSocraticQuestion($user, $message)
        };
    }
    
    private function handleDilemma(User $user, IncomingMessage $message, array $distortions): OracleResponse
    {
        // Создаем дилемму
        $dilemma = new Dilemma(
            description: $message->getContent()->text,
            distortions: $distortions,
            kairos: new Kairos(
                moment: new \DateTimeImmutable(),
                opportunityType: 'decision',
                significance: 'high'
            )
        );
        
        // Получаем стоический анализ
        $stoicAnalysis = $this->philosophyAI->analyzeThroughStoicism($dilemma);
        
        // Генерируем притчу
        $parable = $this->philosophyAI->generateRelevantParable($dilemma);
        
        // Формируем сократический вопрос
        $socraticQuestion = $this->philosophyAI->generateSocraticQuestion($dilemma);
        
        return OracleResponse::createDilemmaResponse(
            stoicPrinciple: $stoicAnalysis->getPrinciple(),
            parable: $parable,
            socraticQuestion: $socraticQuestion,
            cognitiveReframe: $this->createCognitiveReframe($distortions)
        );
    }
}