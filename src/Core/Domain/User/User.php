// src/Core/Domain/User/User.php
namespace App\Core\Domain\User;

class User
{
    private UserId $id;
    private string $telegramId;
    private Profile $profile;
    private PhilosophicalProfile $philosophicalProfile;
    private array $journals = []; // Коллекция журналов
    private \DateTimeImmutable $createdAt;
    
    public function __construct(
        UserId $id,
        string $telegramId,
        string $username
    ) {
        $this->id = $id;
        $this->telegramId = $telegramId;
        $this->profile = new Profile($username);
        $this->philosophicalProfile = new PhilosophicalProfile();
        $this->createdAt = new \DateTimeImmutable();
    }
    
    // Фасадные методы для работы с философским профилем
    public function recordReflection(string $topic, string $insight): void
    {
        $reflection = new Reflection(
            topic: $topic,
            insight: $insight,
            kairos: new Kairos(
                moment: new \DateTimeImmutable(),
                opportunityType: 'reflection',
                significance: 'medium'
            )
        );
        
        $this->philosophicalProfile->addReflection($reflection);
        $this->profile->updateWisdomScore(5); // +5 к мудрости
    }
    
    public function faceDilemma(Dilemma $dilemma): PhilosophicalGuidance
    {
        // Анализ дилеммы через призму стоицизма
        $stoicAnalysis = StoicPrinciple::analyzeDilemma($dilemma);
        
        return new PhilosophicalGuidance(
            principle: $stoicAnalysis->getPrinciple(),
            reflectionQuestions: $stoicAnalysis->generateQuestions(),
            historicalParable: $this->getRelevantParable($dilemma)
        );
    }
}