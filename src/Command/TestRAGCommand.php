<?php

namespace App\Command;

use App\Core\Port\EmbeddingServiceInterface;
use App\Core\Port\VectorSearchInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:rag',
    description: 'Тестирование RAG pipeline (векторный поиск + embedding)'
)]
class TestRAGCommand extends Command
{
    public function __construct(
        private EmbeddingServiceInterface $embeddingService,
        private VectorSearchInterface $vectorSearch
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user_id', InputArgument::REQUIRED, 'Telegram User ID')
            ->addArgument('query', InputArgument::OPTIONAL, 'Тестовый запрос', 'Как найти смысл жизни?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user_id');
        $query = $input->getArgument('query');

        $io->title('Тестирование RAG Pipeline');
        
        $io->section('Параметры теста');
        $io->table(
            ['Параметр', 'Значение'],
            [
                ['User ID', $userId],
                ['Запрос', $query],
                ['Threshold', '0.65'],
                ['Limit', '5']
            ]
        );

        try {
            // 1. Генерируем embedding
            $io->section('Шаг 1: Генерация embedding');
            $startTime = microtime(true);
            $vector = $this->embeddingService->embed($query);
            $embeddingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $io->success(sprintf(
                'Embedding сгенерирован за %sms (размерность: %d)',
                $embeddingTime,
                count($vector)
            ));
            
            // 2. Векторный поиск
            $io->section('Шаг 2: Векторный поиск');
            $startTime = microtime(true);
            $results = $this->vectorSearch->findSimilar(
                vector: $vector,
                userId: $userId,
                threshold: 0.65,
                limit: 5
            );
            $searchTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $io->success(sprintf(
                'Поиск выполнен за %sms, найдено записей: %d',
                $searchTime,
                count($results)
            ));
            
            // 3. Результаты
            if (empty($results)) {
                $io->warning('НЕ НАЙДЕНО РЕЛЕВАНТНЫХ ЗАПИСЕЙ!');
                $io->note([
                    'Возможные причины:',
                    '1. База знаний пуста',
                    '2. Embeddings не сгенерированы',
                    '3. Threshold слишком высокий (попробуйте 0.5)',
                    '4. Запрос не релевантен базе знаний'
                ]);
                return Command::FAILURE;
            }
            
            $io->section('Шаг 3: Найденные записи');
            
            $tableRows = [];
            foreach ($results as $i => $result) {
                $similarity = round($result['similarity'] * 100, 1);
                $text = mb_substr($result['text'], 0, 100);
                if (mb_strlen($result['text']) > 100) {
                    $text .= '...';
                }
                
                $tableRows[] = [
                    $i + 1,
                    $similarity . '%',
                    $text
                ];
            }
            
            $io->table(
                ['#', 'Релевантность', 'Текст'],
                $tableRows
            );
            
            // 4. Форматированный контекст (как будет передан в LLM)
            $io->section('Шаг 4: Контекст для LLM');
            $contextText = '';
            foreach ($results as $i => $entry) {
                $similarity = round($entry['similarity'] * 100, 1);
                $contextText .= sprintf(
                    "[%d] (релевантность: %s%%) %s\n\n",
                    $i + 1,
                    $similarity,
                    $entry['text']
                );
            }
            
            $io->writeln('<info>' . $contextText . '</info>');
            
            $io->success('RAG Pipeline работает корректно! ✅');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Ошибка при выполнении теста: ' . $e->getMessage());
            $io->block($e->getTraceAsString(), 'TRACE', 'fg=red', ' ', true);
            
            return Command::FAILURE;
        }
    }
}
