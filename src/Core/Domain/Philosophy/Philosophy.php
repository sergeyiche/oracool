// src/Core/Domain/Philosophy/ValueObjects.php
namespace App\Core\Domain\Philosophy;

// Древнегреческие понятия как Value Objects
readonly class Arete
{
    public function __construct(
        public int $excellence,      // Совершенство (0-100)
        public int $virtue,          // Добродетель (0-100)
        public int $potential        // Потенциал (0-100)
    ) {}
    
    public function calculateScore(): int
    {
        return (int) (($this->excellence + $this->virtue + $this->potential) / 3);
    }
}

readonly class Logos
{
    public function __construct(
        public string $principle,    // Принцип ("Причина и следствие")
        public string $reasoning,    // Логическое обоснование
        public array $examples = []  // Примеры из истории/мифологии
    ) {}
}

readonly class Kairos
{
    public function __construct(
        public \DateTimeImmutable $moment,
        public string $opportunityType, // 'reflection', 'decision', 'crisis'
        public string $significance     // Важность момента
    ) {}
    
    public function isOpportune(): bool
    {
        // Логика определения "своевременности"
        return $this->significance === 'high';
    }
}