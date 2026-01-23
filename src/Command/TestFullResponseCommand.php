<?php

namespace App\Command;

use App\Core\Domain\User\UserProfile;
use App\Core\Port\UserProfileRepositoryInterface;
use App\Core\UseCase\GenerateResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:response',
    description: 'Тестирование полного пайплайна генерации ответа (с RAG + LLM)'
)]
class TestFullResponseCommand extends Command
{
    public function __construct(
        private GenerateResponse $generateResponse,
        private UserProfileRepositoryInterface $profileRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user_id', InputArgument::REQUIRED, 'Telegram User ID')
            ->addArgument('message', InputArgument::OPTIONAL, 'Тестовое сообщение', 'Как найти смысл жизни?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user_id');
        $message = $input->getArgument('message');

        $io->title('Тестирование полного пайплайна генерации ответа');
        
        $io->section('Параметры теста');
        $io->table(
            ['Параметр', 'Значение'],
            [
                ['User ID', $userId],
                ['Сообщение', $message]
            ]
        );

        try {
            // Получаем профиль
            $io->section('Шаг 1: Получение профиля');
            $profile = $this->profileRepository->findByUserId($userId);
            
            if (!$profile) {
                $io->error("Профиль не найден для User ID: $userId");
                return Command::FAILURE;
            }
            
            $io->success('Профиль загружен');
            $io->table(
                ['Свойство', 'Значение'],
                [
                    ['Режим', $profile->getBotMode()],
                    ['Стиль', $profile->getCommunicationStyle()],
                    ['Threshold', $profile->getRelevanceThreshold()]
                ]
            );

            // Генерируем ответ
            $io->section('Шаг 2: Генерация ответа (RAG + LLM)');
            $io->writeln('⏳ Генерация может занять 10-15 секунд...');
            
            $startTime = microtime(true);
            $result = $this->generateResponse->execute(
                message: $message,
                profile: $profile,
                conversationHistory: []
            );
            $totalTime = round(microtime(true) - $startTime, 2);
            
            $io->success('Ответ сгенерирован за ' . $totalTime . ' сек');
            
            // Результаты
            $io->section('Шаг 3: Результат');
            
            $io->table(
                ['Метрика', 'Значение'],
                [
                    ['Релевантность', round($result->relevanceScore * 100, 1) . '%'],
                    ['Использовано записей', $result->contextEntriesUsed],
                    ['Время обработки', $result->processingTimeMs . 'ms'],
                    ['Embedding Model', $result->embeddingModel],
                    ['LLM Model', $result->llmModel]
                ]
            );
            
            $io->section('Ответ бота:');
            $io->block($result->response, null, 'fg=cyan;bg=black', ' ', true);
            
            $io->success('✅ Полный пайплайн работает корректно!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Ошибка при выполнении теста: ' . $e->getMessage());
            $io->block($e->getTraceAsString(), 'TRACE', 'fg=red', ' ', true);
            
            return Command::FAILURE;
        }
    }
}
