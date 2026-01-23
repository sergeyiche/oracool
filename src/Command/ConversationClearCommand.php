<?php

namespace App\Command;

use App\Core\Port\Repository\ConversationRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'conversation:clear',
    description: 'Очистить историю диалога (начать новый)'
)]
class ConversationClearCommand extends Command
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
            ->addArgument('chat_id', InputArgument::REQUIRED, 'Telegram chat ID')
            ->setHelp('Архивирует текущий диалог и создаёт новый (пустую историю)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user_id');
        $chatId = $input->getArgument('chat_id');

        $io->title('🗑️  Очистка истории диалога');

        try {
            // Проверяем существует ли активный диалог
            $existing = $this->conversationRepo->findActiveConversation($userId, $chatId);

            if (!$existing) {
                $io->warning('Активный диалог не найден');
                return Command::SUCCESS;
            }

            $io->definitionList(
                ['User ID' => $userId],
                ['Chat ID' => $chatId],
                ['Conversation ID' => $existing->getId()],
                ['Сообщений' => $existing->getMessageCount()]
            );

            // Подтверждение
            if (!$io->confirm('Архивировать текущий диалог и начать новый?', false)) {
                $io->info('Отменено');
                return Command::SUCCESS;
            }

            // Очищаем (архивируем старый, создаём новый)
            $newConversation = $this->conversationRepo->clearConversation($userId, $chatId);

            $io->success([
                sprintf('✅ Старый диалог (%s) архивирован', substr($existing->getId(), 0, 8)),
                sprintf('✅ Создан новый диалог (%s)', substr($newConversation->getId(), 0, 8)),
                '✅ История очищена'
            ]);

            $io->note('Следующее сообщение начнёт диалог с чистого листа');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
