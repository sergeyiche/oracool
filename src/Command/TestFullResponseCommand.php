<?php

namespace App\Command;

use App\Core\Domain\User\UserProfile;
use App\Core\Port\UserProfileRepositoryInterface;
use App\Core\Port\Repository\ConversationRepositoryInterface;
use App\Core\UseCase\ProcessTelegramMessage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:response',
    description: 'Тестирование полного пайплайна генерации ответа (с conversations + RAG + LLM)'
)]
class TestFullResponseCommand extends Command
{
    public function __construct(
        private ProcessTelegramMessage $processMessage,
        private UserProfileRepositoryInterface $profileRepository,
        private ConversationRepositoryInterface $conversationRepository,
        private Connection $connection
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
            // Получаем профиль (или сообщаем о создании)
            $io->section('Шаг 1: Проверка профиля');
            $profile = $this->profileRepository->findByUserId($userId);
            
            if (!$profile) {
                $io->warning("Профиль не найден. Будет создан автоматически с параметрами:");
                $io->table(
                    ['Параметр', 'Значение'],
                    [
                        ['Режим', 'active'],
                        ['Стиль', 'creative'],
                        ['Threshold', '0.65'],
                        ['База знаний', 'Автокопирование из 858361483']
                    ]
                );
            } else {
                $io->success('Профиль найден');
                $io->table(
                    ['Свойство', 'Значение'],
                    [
                        ['Режим', $profile->getBotMode()],
                        ['Стиль', $profile->getCommunicationStyle()],
                        ['Threshold', $profile->getRelevanceThreshold()]
                    ]
                );
            }

            // Обрабатываем сообщение (создаёт conversation + RAG + LLM)
            $io->section('Шаг 2: Обработка сообщения (Conversation + RAG + LLM)');
            $io->writeln('⏳ Генерация может занять 10-15 секунд...');
            
            $chatId = 999999999; // Тестовый chat_id
            
            $startTime = microtime(true);
            $result = $this->processMessage->execute(
                text: $message,
                telegramUserId: (int)$userId,
                chatId: $chatId,
                messageId: (int)(time())
            );
            $totalTime = round(microtime(true) - $startTime, 2);
            
            $io->success('Ответ сгенерирован за ' . $totalTime . ' сек');
            
            // Проверяем создание профиля
            if (!$profile) {
                $profile = $this->profileRepository->findByUserId($userId);
                if ($profile) {
                    $io->success('✅ Профиль автоматически создан!');
                    
                    // Проверяем базу знаний
                    $kbCount = $this->connection->executeQuery(
                        'SELECT COUNT(*) FROM knowledge_base WHERE user_id = ?',
                        [$userId]
                    )->fetchOne();
                    
                    $io->table(
                        ['Параметр', 'Значение'],
                        [
                            ['User ID', $profile->getUserId()],
                            ['Режим', $profile->getBotMode()],
                            ['Стиль', $profile->getCommunicationStyle()],
                            ['Threshold', $profile->getRelevanceThreshold()],
                            ['База знаний', $kbCount . ' записей']
                        ]
                    );
                }
            }
            
            // Результаты
            $io->section('Шаг 3: Результат');
            
            if (!$result->shouldRespond) {
                $io->warning('Бот не должен отвечать. Причина: ' . $result->reason);
                return Command::SUCCESS;
            }
            
            $io->table(
                ['Метрика', 'Значение'],
                [
                    ['Релевантность', round($result->relevanceScore * 100, 1) . '%'],
                    ['Использовано записей', $result->contextEntriesUsed],
                    ['Время обработки', $result->processingTimeMs . 'ms']
                ]
            );
            
            $io->section('Ответ бота:');
            $io->block($result->response, null, 'fg=cyan;bg=black', ' ', true);
            
            // Проверяем conversation
            $io->section('Шаг 4: Проверка Conversation');
            $conversation = $this->conversationRepository->findActiveConversation($userId, (string)$chatId);
            
            if ($conversation) {
                $io->success('✅ Conversation создан!');
                $io->table(
                    ['Свойство', 'Значение'],
                    [
                        ['Conversation ID', substr($conversation->getId(), 0, 8) . '...'],
                        ['Chat ID', $conversation->getChatId()],
                        ['Сообщений', $conversation->getMessageCount()],
                        ['Статус', $conversation->getStatus()->value]
                    ]
                );

                // Проверяем сообщения
                $messages = $this->conversationRepository->getAllMessages($conversation->getId());
                $io->writeln(sprintf('Сохранено сообщений: %d', count($messages)));
                $io->writeln('  • Incoming: ' . count(array_filter($messages, fn($m) => $m->isIncoming())));
                $io->writeln('  • Outgoing: ' . count(array_filter($messages, fn($m) => $m->isOutgoing())));
                
                $io->note(sprintf('Просмотр: php bin/console conversation:show %s', $conversation->getId()));
            } else {
                $io->warning('⚠️  Conversation не найден');
            }
            
            $io->success('✅ Полный пайплайн работает корректно!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Ошибка при выполнении теста: ' . $e->getMessage());
            $io->block($e->getTraceAsString(), 'TRACE', 'fg=red', ' ', true);
            
            return Command::FAILURE;
        }
    }
}
