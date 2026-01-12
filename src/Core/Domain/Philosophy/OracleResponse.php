// src/Core/Domain/Philosophy/Response/OracleResponse.php
namespace App\Core\Domain\Philosophy\Response;

readonly class OracleResponse
{
    public function __construct(
        public string $id,
        public string $type, // 'parable', 'question', 'principle', 'exercise'
        public string $content,
        public ?string $principle = null,
        public ?string $historicalReference = null,
        public ?string $cognitiveReframe = null,
        public array $followUpQuestions = [],
        public \DateTimeImmutable $createdAt
    ) {}
    
    public static function createParableResponse(
        string $story,
        string $moral,
        string $historicalFigure
    ): self {
        return new self(
            id: Uuid::uuid4(),
            type: 'parable',
            content: "📜 *Притча о {$historicalFigure}*\n\n{$story}\n\n💡 *Мудрость*: {$moral}",
            historicalReference: $historicalFigure,
            createdAt: new \DateTimeImmutable()
        );
    }
    
    public static function createSocraticQuestion(
        string $question,
        array $perspectives
    ): self {
        $content = "🤔 *Сократовский вопрос*:\n{$question}\n\n";
        $content .= "*Рассмотри с разных сторон*:\n";
        
        foreach ($perspectives as $i => $perspective) {
            $content .= ($i + 1) . ". {$perspective}\n";
        }
        
        return new self(
            id: Uuid::uuid4(),
            type: 'question',
            content: $content,
            followUpQuestions: ['Какой вариант резонирует с твоей добродетелью?'],
            createdAt: new \DateTimeImmutable()
        );
    }
}