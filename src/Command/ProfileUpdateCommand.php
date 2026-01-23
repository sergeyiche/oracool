<?php

namespace App\Command;

use App\Core\Port\UserProfileRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'profile:update',
    description: 'Update user profile settings',
)]
class ProfileUpdateCommand extends Command
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID')
            ->addOption('mode', 'm', InputOption::VALUE_REQUIRED, 'Bot mode')
            ->addOption('style', 's', InputOption::VALUE_REQUIRED, 'Communication style')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Relevance threshold')
            ->addOption('add-interest', null, InputOption::VALUE_REQUIRED, 'Add interest')
            ->addOption('add-example', null, InputOption::VALUE_REQUIRED, 'Add example response');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getArgument('user-id');

        try {
            $profile = $this->profileRepository->findByUserId($userId);
            
            if (!$profile) {
                $io->error("Profile for user {$userId} not found!");
                return Command::FAILURE;
            }

            // Обновляем параметры
            if ($mode = $input->getOption('mode')) {
                $profile->updateBotMode($mode);
                $io->text("✓ Bot mode updated to: {$mode}");
            }

            if ($style = $input->getOption('style')) {
                $profile->updateCommunicationStyle($style);
                $io->text("✓ Communication style updated to: {$style}");
            }

            if ($threshold = $input->getOption('threshold')) {
                $profile->updateRelevanceThreshold((float) $threshold);
                $io->text("✓ Relevance threshold updated to: {$threshold}");
            }

            if ($interest = $input->getOption('add-interest')) {
                $profile->addInterest($interest);
                $io->text("✓ Interest added: {$interest}");
            }

            if ($example = $input->getOption('add-example')) {
                $profile->addExampleResponse($example);
                $io->text("✓ Example response added");
            }

            $this->profileRepository->save($profile);

            $io->success('Profile updated successfully!');
            
            // Показываем текущее состояние
            $io->definitionList(
                ['User ID' => $profile->getUserId()],
                ['Bot Mode' => $profile->getBotMode()],
                ['Communication Style' => $profile->getCommunicationStyle()],
                ['Relevance Threshold' => $profile->getRelevanceThreshold()],
                ['Interests' => implode(', ', $profile->getKeyInterests())],
                ['Example Responses' => count($profile->getExampleResponses())],
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to update profile: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
