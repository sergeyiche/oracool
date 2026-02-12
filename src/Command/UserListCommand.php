<?php

namespace App\Command;

use App\Core\Port\UserProfileRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:list',
    description: 'Список всех пользователей с их настройками'
)]
class UserListCommand extends Command
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepo,
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', 'm', InputOption::VALUE_OPTIONAL, 'Фильтр по режиму (active/passive/always_respond)', null)
            ->addOption('style', 's', InputOption::VALUE_OPTIONAL, 'Фильтр по стилю (formal/casual/creative/technical)', null)
            ->setHelp('Показывает список всех пользователей бота с их настройками профилей');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $modeFilter = $input->getOption('mode');
        $styleFilter = $input->getOption('style');

        $io->title('👥 Список пользователей бота');

        try {
            // Получаем всех пользователей
            $profiles = $this->profileRepo->findAll();

            if (empty($profiles)) {
                $io->warning('Пользователи не найдены');
                return Command::SUCCESS;
            }

            // Фильтрация
            if ($modeFilter || $styleFilter) {
                $profiles = array_filter($profiles, function($profile) use ($modeFilter, $styleFilter) {
                    $modeMatch = !$modeFilter || $profile->getBotMode() === $modeFilter;
                    $styleMatch = !$styleFilter || $profile->getCommunicationStyle() === $styleFilter;
                    return $modeMatch && $styleMatch;
                });
            }

            if (empty($profiles)) {
                $io->warning('Пользователи с указанными фильтрами не найдены');
                return Command::SUCCESS;
            }

            // Создаём таблицу
            $table = new Table($output);
            $table->setHeaders([
                'User ID',
                'Режим',
                'Стиль',
                'Длина ответа',
                'Порог',
                'Интересы',
                'База знаний',
                'Создан'
            ]);

            $stats = [
                'active' => 0,
                'passive' => 0,
                'always_respond' => 0,
                'total_kb_entries' => 0
            ];

            // Получаем количество записей в БЗ для всех пользователей одним запросом
            $kbCounts = $this->getKnowledgeBaseCounts();

            foreach ($profiles as $profile) {
                $userId = $profile->getUserId();
                $kbCount = $kbCounts[$userId] ?? 0;
                
                $table->addRow([
                    $userId,
                    $this->formatMode($profile->getBotMode()),
                    $profile->getCommunicationStyle(),
                    $profile->getResponseLength(),
                    $profile->getRelevanceThreshold(),
                    count($profile->getKeyInterests() ?? []),
                    $kbCount > 0 ? $kbCount : '-',
                    $profile->getCreatedAt()->format('Y-m-d H:i')
                ]);

                // Статистика
                $mode = $profile->getBotMode();
                if (isset($stats[$mode])) {
                    $stats[$mode]++;
                }
                $stats['total_kb_entries'] += $kbCount;
            }

            $table->render();

            // Итоги
            $io->newLine();
            $io->section('📊 Статистика');
            
            $statsTable = new Table($output);
            $statsTable->setHeaders(['Метрика', 'Значение']);
            $statsTable->addRows([
                ['Всего пользователей', count($profiles)],
                ['Режим: active', $stats['active']],
                ['Режим: passive', $stats['passive']],
                ['Режим: always_respond', $stats['always_respond']],
                ['Всего записей в БЗ', $stats['total_kb_entries']],
            ]);
            $statsTable->render();

            // Подсказки
            $io->newLine();
            $io->section('💡 Полезные команды');
            $io->text([
                'Просмотр статистики пользователя:',
                '  docker exec oracool-app php bin/console knowledge:stats USER_ID',
                '',
                'Обновить профиль:',
                '  docker exec oracool-app php bin/console profile:update USER_ID --mode=active',
                '',
                'Диалоги пользователя:',
                '  docker exec oracool-app php bin/console conversation:list USER_ID',
                '',
                'Фильтры:',
                '  docker exec oracool-app php bin/console user:list --mode=active',
                '  docker exec oracool-app php bin/console user:list --style=creative',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ошибка: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Получает количество записей в базе знаний для каждого пользователя
     */
    private function getKnowledgeBaseCounts(): array
    {
        $sql = 'SELECT user_id, COUNT(*) as count 
                FROM knowledge_base 
                GROUP BY user_id';
        
        $results = $this->connection->fetchAllAssociative($sql);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['user_id']] = (int)$row['count'];
        }
        
        return $counts;
    }

    private function formatMode(string $mode): string
    {
        return match($mode) {
            'active' => '✅ active',
            'passive' => '👁️  passive',
            'always_respond' => '💬 always',
            default => $mode
        };
    }
}
