<?php

namespace App\Command;

use App\Core\Domain\KnowledgeBase\KnowledgeBaseEntry;
use App\Core\Port\EmbeddingServiceInterface;
use App\Core\Port\KnowledgeBaseRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'knowledge:import',
    description: 'Import knowledge base from file',
)]
class KnowledgeImportCommand extends Command
{
    public function __construct(
        private KnowledgeBaseRepositoryInterface $knowledgeRepository,
        private EmbeddingServiceInterface $embeddingService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to file (txt, json, csv)')
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'File format (txt|json|csv)', 'txt')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch size for embeddings', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        $userId = $input->getArgument('user-id');
        $format = $input->getOption('format');
        $batchSize = (int) $input->getOption('batch-size');

        if (!file_exists($file)) {
            $io->error("File not found: {$file}");
            return Command::FAILURE;
        }

        try {
            $io->title("Importing Knowledge Base");
            $io->text("File: {$file}");
            $io->text("User ID: {$userId}");
            $io->text("Format: {$format}");

            // Загружаем данные
            $texts = $this->loadTexts($file, $format);
            $totalTexts = count($texts);

            if ($totalTexts === 0) {
                $io->warning('No texts found in file');
                return Command::SUCCESS;
            }

            $io->text("Found {$totalTexts} entries");
            $io->newLine();

            // Создаем progress bar
            $progressBar = new ProgressBar($output, $totalTexts);
            $progressBar->setFormat('verbose');
            $progressBar->start();

            $entries = [];
            $processed = 0;

            // Обрабатываем батчами
            foreach (array_chunk($texts, $batchSize) as $batch) {
                // Векторизуем батч
                $embeddings = $this->embeddingService->embedBatch($batch);

                // Создаем entries
                foreach ($batch as $i => $text) {
                    $entry = KnowledgeBaseEntry::create(
                        id: $this->generateUuid(),
                        userId: $userId,
                        text: $text,
                        embedding: $embeddings[$i] ?? null,
                        embeddingModel: $this->embeddingService->getModelName()
                    );

                    $entry->addTag('imported');
                    $entry->addMetadata('source_file', basename($file));
                    $entry->addMetadata('imported_at', date('Y-m-d H:i:s'));

                    $entries[] = $entry;
                    $processed++;
                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            $io->newLine(2);

            // Сохраняем в БД
            $io->text('Saving to database...');
            $this->knowledgeRepository->bulkImport($entries);

            $io->success("Successfully imported {$processed} entries!");
            $io->definitionList(
                ['Total Entries' => $processed],
                ['Embedding Model' => $this->embeddingService->getModelName()],
                ['Embedding Dimension' => $this->embeddingService->getDimension()],
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function loadTexts(string $file, string $format): array
    {
        return match($format) {
            'txt' => $this->loadFromTxt($file),
            'json' => $this->loadFromJson($file),
            'csv' => $this->loadFromCsv($file),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    private function loadFromTxt(string $file): array
    {
        $content = file_get_contents($file);
        
        // Разделяем по двойным переносам строк
        $texts = preg_split('/\n\s*\n/', $content);
        
        return array_filter(
            array_map('trim', $texts),
            fn($text) => !empty($text)
        );
    }

    private function loadFromJson(string $file): array
    {
        $data = json_decode(file_get_contents($file), true);
        
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON format');
        }

        // Ожидаем массив строк или массив объектов с полем 'text'
        return array_map(
            fn($item) => is_string($item) ? $item : ($item['text'] ?? ''),
            $data
        );
    }

    private function loadFromCsv(string $file): array
    {
        $texts = [];
        $handle = fopen($file, 'r');
        
        // Пропускаем заголовок если есть
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty($row[0])) {
                $texts[] = $row[0];
            }
        }
        
        fclose($handle);
        return $texts;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
