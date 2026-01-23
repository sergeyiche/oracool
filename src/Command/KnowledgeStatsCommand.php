<?php

namespace App\Command;

use App\Core\Port\KnowledgeBaseRepositoryInterface;
use App\Core\Port\UserProfileRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'knowledge:stats',
    description: 'Show knowledge base statistics',
)]
class KnowledgeStatsCommand extends Command
{
    public function __construct(
        private KnowledgeBaseRepositoryInterface $knowledgeRepository,
        private UserProfileRepositoryInterface $profileRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user-id', InputArgument::OPTIONAL, 'User ID (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('user-id');

        try {
            if ($userId) {
                return $this->showUserStats($io, $userId);
            } else {
                return $this->showGlobalStats($io);
            }
        } catch (\Exception $e) {
            $io->error('Failed to get stats: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showUserStats(SymfonyStyle $io, string $userId): int
    {
        $io->title("Knowledge Base Stats for User {$userId}");

        // Профиль
        $profile = $this->profileRepository->findByUserId($userId);
        if (!$profile) {
            $io->error("Profile not found for user {$userId}");
            return Command::FAILURE;
        }

        // Статистика
        $totalEntries = $this->knowledgeRepository->countByUserId($userId);
        $entries = $this->knowledgeRepository->findByUserId($userId);

        // Группировка по источникам
        $bySource = [];
        foreach ($entries as $entry) {
            $source = $entry->getSource() ?? 'unknown';
            $bySource[$source] = ($bySource[$source] ?? 0) + 1;
        }

        $io->section('Profile');
        $io->definitionList(
            ['Bot Mode' => $profile->getBotMode()],
            ['Communication Style' => $profile->getCommunicationStyle()],
            ['Relevance Threshold' => $profile->getRelevanceThreshold()],
            ['Embedding Provider' => $profile->getEmbeddingProvider() ?? 'Not set'],
            ['Embedding Model' => $profile->getEmbeddingModel() ?? 'Not set'],
            ['Interests' => count($profile->getKeyInterests())],
            ['Example Responses' => count($profile->getExampleResponses())],
        );

        $io->section('Knowledge Base');
        $io->definitionList(
            ['Total Entries' => $totalEntries],
            ['By Source' => ''],
        );

        foreach ($bySource as $source => $count) {
            $io->text("  - {$source}: {$count}");
        }

        if (!empty($entries)) {
            $latestEntry = $entries[0];
            $io->text("\nLatest Entry:");
            $io->text("  Created: " . $latestEntry->getCreatedAt()->format('Y-m-d H:i:s'));
            $io->text("  Source: " . ($latestEntry->getSource() ?? 'unknown'));
            $io->text("  Text: " . substr($latestEntry->getText(), 0, 100) . '...');
        }

        return Command::SUCCESS;
    }

    private function showGlobalStats(SymfonyStyle $io): int
    {
        $io->title('Global Knowledge Base Stats');

        // Все профили
        $profiles = $this->profileRepository->findAll();
        $totalProfiles = count($profiles);

        $io->definitionList(
            ['Total Profiles' => $totalProfiles],
        );

        if ($totalProfiles > 0) {
            $io->section('Profiles by Mode');
            $byMode = [];
            foreach ($profiles as $profile) {
                $mode = $profile->getBotMode();
                $byMode[$mode] = ($byMode[$mode] ?? 0) + 1;
            }

            foreach ($byMode as $mode => $count) {
                $io->text("  {$mode}: {$count}");
            }

            $io->section('Recent Profiles');
            $table = [];
            foreach (array_slice($profiles, 0, 5) as $profile) {
                $entriesCount = $this->knowledgeRepository->countByUserId($profile->getUserId());
                $table[] = [
                    $profile->getUserId(),
                    $profile->getBotMode(),
                    $entriesCount,
                ];
            }

            $io->table(['User ID', 'Mode', 'Entries'], $table);
        }

        return Command::SUCCESS;
    }
}
