<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создаёт таблицу conversations для поддержки множественных диалогов
 */
final class Version20260123000001_CreateConversationsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создаёт таблицу conversations и обновляет messages для многопользовательского режима';
    }

    public function up(Schema $schema): void
    {
        // 1. Создаём таблицу conversations
        $this->addSql('
            CREATE TABLE conversations (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id VARCHAR(255) NOT NULL,
                chat_id BIGINT NOT NULL,
                title VARCHAR(255),
                status VARCHAR(20) DEFAULT \'active\' CHECK (status IN (\'active\', \'archived\', \'deleted\')),
                context_summary TEXT,
                message_count INTEGER DEFAULT 0,
                last_message_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ DEFAULT NOW(),
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )
        ');

        // Partial unique index - только один активный conversation на (user_id, chat_id)
        $this->addSql('
            CREATE UNIQUE INDEX idx_conversations_user_chat_active 
            ON conversations(user_id, chat_id) 
            WHERE status = \'active\'
        ');

        // Другие индексы для conversations
        $this->addSql('CREATE INDEX idx_conversations_user_status ON conversations(user_id, status)');
        $this->addSql('CREATE INDEX idx_conversations_last_message ON conversations(last_message_at DESC)');
        $this->addSql('CREATE INDEX idx_conversations_chat ON conversations(chat_id)');

        // 2. Создаём временную таблицу для миграции старых messages
        $this->addSql('ALTER TABLE messages RENAME TO messages_old');

        // 3. Создаём новую таблицу messages с правильной структурой
        $this->addSql('
            CREATE TABLE messages (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                conversation_id UUID REFERENCES conversations(id) ON DELETE CASCADE,
                
                -- Идентификация сообщения
                external_message_id BIGINT,
                direction VARCHAR(10) NOT NULL CHECK (direction IN (\'incoming\', \'outgoing\')),
                
                -- Контент
                content_type VARCHAR(50) DEFAULT \'text\',
                content TEXT NOT NULL,
                
                -- RAG метаданные (для outgoing сообщений)
                relevance_score FLOAT,
                context_entries_used INTEGER,
                processing_time_ms INTEGER,
                
                -- Метаданные
                metadata JSONB DEFAULT \'{}\',
                created_at TIMESTAMPTZ DEFAULT NOW()
            )
        ');

        // Индексы для messages
        $this->addSql('CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_messages_external ON messages(external_message_id)');
        $this->addSql('CREATE INDEX idx_messages_direction ON messages(direction)');

        // 4. Мигрируем данные из старой таблицы
        // Создаём conversation для каждой уникальной пары (user_id, chat_id)
        $this->addSql('
            INSERT INTO conversations (user_id, chat_id, message_count, last_message_at, created_at)
            SELECT 
                user_id,
                chat_id,
                COUNT(*) as message_count,
                MAX(created_at) as last_message_at,
                MIN(created_at) as created_at
            FROM messages_old
            GROUP BY user_id, chat_id
        ');

        // Мигрируем сообщения (считаем их incoming, так как не знаем direction)
        $this->addSql('
            INSERT INTO messages (
                conversation_id,
                external_message_id,
                direction,
                content,
                metadata,
                created_at
            )
            SELECT 
                c.id as conversation_id,
                m.message_id as external_message_id,
                \'incoming\' as direction,
                COALESCE(m.text, \'\') as content,
                m.metadata,
                m.created_at
            FROM messages_old m
            INNER JOIN conversations c ON c.user_id = m.user_id AND c.chat_id = m.chat_id
            WHERE m.text IS NOT NULL AND m.text != \'\'
        ');

        // 5. Удаляем старую таблицу
        $this->addSql('DROP TABLE messages_old');
    }

    public function down(Schema $schema): void
    {
        // Откат миграции: восстанавливаем старую структуру
        
        // 1. Создаём старую таблицу messages
        $this->addSql('
            CREATE TABLE messages_old (
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

        // 2. Копируем данные обратно
        $this->addSql('
            INSERT INTO messages_old (user_id, chat_id, message_id, text, metadata, created_at)
            SELECT 
                c.user_id,
                c.chat_id,
                m.external_message_id as message_id,
                m.content as text,
                m.metadata,
                m.created_at
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
            WHERE m.external_message_id IS NOT NULL
        ');

        // 3. Удаляем новые таблицы
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE conversations');

        // 4. Переименовываем старую таблицу обратно
        $this->addSql('ALTER TABLE messages_old RENAME TO messages');
    }
}
