<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Миграция для создания таблиц базы знаний и профилей пользователей
 */
final class Version20240116000001_CreateKnowledgeBaseAndProfiles extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создает таблицы для базы знаний, профилей пользователей и обратной связи с поддержкой разных размерностей векторов';
    }

    public function up(Schema $schema): void
    {
        // Установка расширения pgvector (если еще не установлено)
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        // Таблица профилей пользователей (без FK к users, так как users таблица не нужна для MVP)
        $this->addSql('
            CREATE TABLE user_profiles (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL UNIQUE,
                communication_style VARCHAR(50) DEFAULT \'balanced\',
                response_length VARCHAR(20) DEFAULT \'medium\',
                use_emojis BOOLEAN DEFAULT true,
                key_interests JSONB DEFAULT \'[]\',
                example_responses JSONB DEFAULT \'[]\',
                relevance_threshold FLOAT DEFAULT 0.7,
                bot_mode VARCHAR(20) DEFAULT \'silent\',
                embedding_provider VARCHAR(50) DEFAULT \'ollama\',
                embedding_model VARCHAR(100),
                embedding_dimension INTEGER DEFAULT 768,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )
        ');

        // Таблица базы знаний
        // Используем JSONB для гибкости хранения векторов разной размерности
        $this->addSql('
            CREATE TABLE knowledge_base (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL,
                text TEXT NOT NULL,
                embedding JSONB,
                embedding_model VARCHAR(100),
                source VARCHAR(100),
                source_id VARCHAR(255),
                tags JSONB DEFAULT \'[]\',
                metadata JSONB DEFAULT \'{}\',
                created_at TIMESTAMPTZ DEFAULT NOW()
            )
        ');

        // Таблица обратной связи
        $this->addSql('
            CREATE TABLE feedback (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL,
                message_id UUID,
                response_id UUID,
                feedback_type VARCHAR(20) NOT NULL CHECK (feedback_type IN (\'approve\', \'correct\', \'delete\')),
                original_response TEXT,
                corrected_response TEXT,
                notes TEXT,
                created_at TIMESTAMPTZ DEFAULT NOW()
            )
        ');

        // Таблица режимов работы бота
        $this->addSql('
            CREATE TABLE bot_modes (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL,
                chat_id VARCHAR(255) NOT NULL,
                messenger_type VARCHAR(20) NOT NULL,
                mode VARCHAR(20) NOT NULL CHECK (mode IN (\'silent\', \'active\', \'aggressive\')),
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW(),
                UNIQUE(user_id, chat_id, messenger_type)
            )
        ');

        // Таблица сообщений
        $this->addSql('
            CREATE TABLE messages (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL,
                chat_id BIGINT NOT NULL,
                message_id BIGINT NOT NULL,
                text TEXT,
                metadata JSONB DEFAULT \'{}\',
                created_at TIMESTAMPTZ DEFAULT NOW(),
                UNIQUE(chat_id, message_id)
            )
        ');

        // Таблица белого списка чатов
        $this->addSql('
            CREATE TABLE whitelisted_chats (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL,
                chat_id VARCHAR(255) NOT NULL,
                messenger_type VARCHAR(20) NOT NULL,
                chat_title VARCHAR(255),
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                UNIQUE(user_id, chat_id, messenger_type)
            )
        ');

        // Индексы для производительности
        $this->addSql('CREATE INDEX idx_knowledge_base_user ON knowledge_base(user_id)');
        $this->addSql('CREATE INDEX idx_knowledge_base_created ON knowledge_base(created_at DESC)');
        $this->addSql('CREATE INDEX idx_knowledge_base_source ON knowledge_base(source, source_id)');
        $this->addSql('CREATE INDEX idx_knowledge_base_model ON knowledge_base(embedding_model)');
        $this->addSql('CREATE INDEX idx_feedback_user_created ON feedback(user_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_feedback_type ON feedback(feedback_type)');
        $this->addSql('CREATE INDEX idx_bot_modes_user_chat ON bot_modes(user_id, chat_id, messenger_type)');
        $this->addSql('CREATE INDEX idx_whitelisted_chats_user ON whitelisted_chats(user_id, is_active)');
        
        // Индекс для JSONB поиска по эмбеддингам (для небольших объемов данных)
        $this->addSql('CREATE INDEX idx_knowledge_base_embedding ON knowledge_base USING gin(embedding)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS whitelisted_chats');
        $this->addSql('DROP TABLE IF EXISTS bot_modes');
        $this->addSql('DROP TABLE IF EXISTS feedback');
        $this->addSql('DROP TABLE IF EXISTS knowledge_base');
        $this->addSql('DROP TABLE IF EXISTS user_profiles');
    }
}
