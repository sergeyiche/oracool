<?php

namespace App\Command;

use App\Core\Port\Repository\ConversationRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'conversation:show',
    description: 'Просмотр диалога'
)]
class ConversationShowCommand extends Command
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepo
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('conversation_id', InputArgument::REQUIRED, 'ID диалога')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Количество последних сообщений', null)
            ->setHelp('Показывает содержимое диалога');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conversationId = $input->getArgument('conversation_id');
        $limit = $input->getOption('limit');

        try {
            // Получаем conversation
            $conversation = $this->conversationRepo->findById($conversationId);

            if (!$conversation) {
                $io->error('Диалог не найден: ' . $conversationId);
                return Command::FAILURE;
            }

            // Информация о диалоге
            $io->title('💬 Диалог ' . substr($conversationId, 0, 8) . '...');

            $io->definitionList(
                ['User ID' => $conversation->getUserId()],
                ['Chat ID' => $conversation->getChatId()],
                ['Заголовок' => $conversation->getTitle() ?: '-'],
                ['Статус' => $conversation->getStatus()->value],
                ['Сообщений' => $conversation->getMessageCount()],
                ['Создан' => $conversation->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Обновлён' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s')]
            );

            // Получаем сообщения
            if ($limit) {
                $messages = $this->conversationRepo->getRecentMessages($conversationId, (int)$limit);
                $io->section(sprintf('Последние %d сообщений', $limit));
            } else {
                $messages = $this->conversationRepo->getAllMessages($conversationId);
                $io->section('Все сообщения');
            }

            if (empty($messages)) {
                $io->warning('Сообщения не найдены');
                return Command::SUCCESS;
            }

            // Отображаем сообщения
            foreach ($messages as $message) {
                $icon = $message->isIncoming() ? '👤' : '🤖';
                $role = $message->isIncoming() ? 'USER' : 'BOT';
                $timestamp = $message->getCreatedAt()->format('H:i:s');

                $io->writeln('');
                $io->writeln(sprintf(
                    '<fg=cyan>%s [%s] %s</>',
                    $icon,
                    $timestamp,
                    $role
                ));

                // Контент
                $content = wordwrap($message->getContent(), 80);
                $io->writeln($content);

                // Метаданные для BOT сообщений
                if ($message->isOutgoing() && $message->getRelevanceScore()) {
                    $io->writeln(sprintf(
                        '<fg=gray>  └─ Релевантность: %.1f%%, Записей: %d, Время: %dms</>',
                        $message->getRelevanceScore() * 100,
                        $message->getContextEntriesUsed() ?? 0,
                        $message->getProcessingTimeMs() ?? 0
                    ));
                }
            }

            $io->writeln('');
            $io->success(sprintf('Показано сообщений: %d', count($messages)));

            // Статистика
            if (count($messages) > 1) {
                $io->section('📊 Статистика');

                $incomingCount = count(array_filter($messages, fn($m) => $m->isIncoming()));
                $outgoingCount = count(array_filter($messages, fn($m) => $m->isOutgoing()));

                $avgRelevance = null;
                $avgProcessingTime = null;

                $outgoingMessages = array_filter($messages, fn($m) => $m->isOutgoing());
                if (!empty($outgoingMessages)) {
                    $relevanceScores = array_filter(
                        array_map(fn($m) => $m->getRelevanceScore(), $outgoingMessages)
                    );
                    if (!empty($relevanceScores)) {
                        $avgRelevance = array_sum($relevanceScores) / count($relevanceScores);
                    }

                    $processingTimes = array_filter(
                        array_map(fn($m) => $m->getProcessingTimeMs(), $outgoingMessages)
                    );
                    if (!empty($processingTimes)) {
                        $avgProcessingTime = array_sum($processingTimes) / count($processingTimes);
                    }
                }

                $io->definitionList(
                    ['Сообщений от пользователя' => $incomingCount],
                    ['Ответов бота' => $outgoingCount],
                    ['Средняя релевантность' => $avgRelevance ? sprintf('%.1f%%', $avgRelevance * 100) : '-'],
                    ['Среднее время ответа' => $avgProcessingTime ? sprintf('%.0f ms', $avgProcessingTime) : '-']
                );
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
