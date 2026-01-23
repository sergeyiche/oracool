<?php

namespace App\Command;

use App\Adapter\Telegram\TelegramBotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'telegram:webhook:setup',
    description: 'Setup Telegram webhook URL',
)]
class TelegramWebhookSetupCommand extends Command
{
    public function __construct(
        private TelegramBotService $telegramBot,
        private string $webhookSecret
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::OPTIONAL, 'Webhook URL (e.g. https://your-domain.com/webhook/telegram)')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete webhook instead of setting')
            ->addOption('info', 'i', InputOption::VALUE_NONE, 'Show webhook info');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Показать информацию
            if ($input->getOption('info')) {
                return $this->showWebhookInfo($io);
            }

            // Удалить webhook
            if ($input->getOption('delete')) {
                return $this->deleteWebhook($io);
            }

            // Установить webhook
            $url = $input->getArgument('url');
            if (!$url) {
                $io->error('URL is required when not using --info or --delete options');
                return Command::FAILURE;
            }
            return $this->setupWebhook($io, $url);

        } catch (\Exception $e) {
            $io->error('Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function setupWebhook(SymfonyStyle $io, string $url): int
    {
        $io->title('Setting up Telegram Webhook');
        $io->text("URL: {$url}");
        $io->text("Secret: {$this->webhookSecret}");

        $result = $this->telegramBot->setWebhook($url, $this->webhookSecret);

        $io->success('Webhook setup completed!');
        $io->text(json_encode($result, JSON_PRETTY_PRINT));

        // Показать информацию
        $this->showWebhookInfo($io);

        return Command::SUCCESS;
    }

    private function deleteWebhook(SymfonyStyle $io): int
    {
        $io->title('Deleting Telegram Webhook');

        $result = $this->telegramBot->deleteWebhook();

        $io->success('Webhook deleted!');
        $io->text(json_encode($result, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function showWebhookInfo(SymfonyStyle $io): int
    {
        $io->title('Telegram Webhook Info');

        $info = $this->telegramBot->getWebhookInfo();

        $io->definitionList(
            ['URL' => $info['url'] ?? 'Not set'],
            ['Has custom certificate' => $info['has_custom_certificate'] ? 'Yes' : 'No'],
            ['Pending updates' => $info['pending_update_count'] ?? 0],
            ['Last error date' => isset($info['last_error_date']) ? date('Y-m-d H:i:s', $info['last_error_date']) : 'None'],
            ['Last error message' => $info['last_error_message'] ?? 'None'],
            ['Max connections' => $info['max_connections'] ?? 40],
        );

        return Command::SUCCESS;
    }
}
