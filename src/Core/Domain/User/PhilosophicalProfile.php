// src/Core/Domain/User/PhilosophicalProfile.php
namespace App\Core\Domain\User;

class PhilosophicalProfile
{
    private array $virtues = [
        'wisdom' => 0,
        'courage' => 0,
        'justice' => 0,
        'temperance' => 0
    ];
    
    private array $cognitiveDistortions = [];
    private array $philosophicalPreferences = [];
    private array $reflections = [];
    private \DateTimeImmutable $lastPhilosophicalAssessment;
    
    public function assessCognitiveDistortions(string $userInput): array
    {
        // Детекция когнитивных искажений в тексте пользователя
        $distortions = [
            'black_and_white' => $this->detectBlackWhiteThinking($userInput),
            'catastrophizing' => $this->detectCatastrophizing($userInput),
            'overgeneralization' => $this->detectOvergeneralization($userInput),
            'personalization' => $this->detectPersonalization($userInput)
        ];
        
        $this->cognitiveDistortions = array_merge(
            $this->cognitiveDistortions,
            array_filter($distortions)
        );
        
        return $distortions;
    }
    
    public function getStoicExercise(): StoicExercise
    {
        // Подбор стоического упражнения на основе профиля
        if ($this->virtues['courage'] < 30) {
            return StoicExercise::negativeVisualization();
        }
        
        if ($this->virtues['temperance'] < 30) {
            return StoicExercise::dichotomyOfControl();
        }
        
        return StoicExercise::morningMeditation();
    }
}