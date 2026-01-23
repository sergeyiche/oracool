<?php

namespace App\Command;

use App\Core\Domain\User\UserProfile;
use App\Core\Port\UserProfileRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'profile:create',
    description: 'Create a new user profile',
)]
class ProfileCreateCommand extends Command
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID (Telegram ID)')
            ->addOption('mode', 'm', InputOption::VALUE_REQUIRED, 'Bot mode (silent|active|aggressive)', 'silent')
            ->addOption('style', 's', InputOption::VALUE_REQUIRED, 'Communication style', 'balanced')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Relevance threshold (0.0-1.0)', '0.7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getArgument('user-id');
        $mode = $input->getOption('mode');
        $style = $input->getOption('style');
        $threshold = (float) $input->getOption('threshold');

        try {
            // Проверяем существование
            $existing = $this->profileRepository->findByUserId($userId);
            if ($existing) {
                $io->error("Profile for user {$userId} already exists!");
                return Command::FAILURE;
            }

            // Создаем профиль
            $profile = UserProfile::createDefault($this->generateUuid(), $userId);
            $profile->updateBotMode($mode);
            $profile->updateCommunicationStyle($style);
            $profile->updateRelevanceThreshold($threshold);

            $this->profileRepository->save($profile);

            $io->success("Profile created successfully!");
            $io->definitionList(
                ['Profile ID' => $profile->getId()],
                ['User ID' => $profile->getUserId()],
                ['Bot Mode' => $profile->getBotMode()],
                ['Communication Style' => $profile->getCommunicationStyle()],
                ['Relevance Threshold' => $profile->getRelevanceThreshold()],
                ['Use Emojis' => $profile->useEmojis() ? 'Yes' : 'No'],
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to create profile: ' . $e->getMessage());
            return Command::FAILURE;
        }
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
