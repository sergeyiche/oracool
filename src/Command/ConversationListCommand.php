<?php

namespace App\Command;

use App\Core\Port\Repository\ConversationRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'conversation:list',
    description: 'Список диалогов пользователя'
)]
class ConversationListCommand extends Command
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepo
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user_id', InputArgument::REQUIRED, 'Telegram user ID')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Фильтр по статусу (active/archived/deleted)', null)
            ->setHelp('Показывает список всех диалогов пользователя');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user_id');
        $status = $input->getOption('status');

        $io->title('📚 Диалоги пользователя ' . $userId);

        try {
            // Получаем диалоги
            $conversations = $this->conversationRepo->getUserConversations($userId, $status);

            if (empty($conversations)) {
                $io->warning('Диалоги не найдены');
                return Command::SUCCESS;
            }

            // Создаём таблицу
            $table = new Table($output);
            $table->setHeaders([
                'ID',
                'Chat ID',
                'Заголовок',
                'Статус',
                'Сообщений',
                'Последнее сообщение',
                'Создан'
            ]);

            foreach ($conversations as $conversation) {
                $table->addRow([
                    substr($conversation->getId(), 0, 8) . '...',
                    $conversation->getChatId(),
                    $conversation->getTitle() ?: '-',
                    $this->formatStatus($conversation->getStatus()->value),
                    $conversation->getMessageCount(),
                    $conversation->getLastMessageAt()?->format('Y-m-d H:i') ?: '-',
                    $conversation->getCreatedAt()->format('Y-m-d H:i')
                ]);
            }

            $table->render();

            $io->success(sprintf('Найдено диалогов: %d', count($conversations)));

            // Подсказки
            $io->section('💡 Команды');
            $io->text([
                'Просмотр диалога:',
                '  php bin/console conversation:show CONVERSATION_ID',
                '',
                'Очистить историю:',
                '  php bin/console conversation:clear ' . $userId . ' CHAT_ID',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function formatStatus(string $status): string
    {
        return match($status) {
            'active' => '✅ active',
            'archived' => '📦 archived',
            'deleted' => '🗑️  deleted',
            default => $status
        };
    }
}
