-- 1. Таблица пользователей (агрегат User)
CREATE TABLE users (
    id UUID PRIMARY KEY,
    telegram_id BIGINT UNIQUE,
    username VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    language_code VARCHAR(10),
    timezone VARCHAR(50),
    joy_score INTEGER DEFAULT 0, -- интегральный показатель "радости"
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. Таблица диалогов (агрегат Conversation)
CREATE TABLE conversations (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id),
    messenger_type VARCHAR(20) NOT NULL,
    bot_id VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    context JSONB DEFAULT '{}', -- текущий контекст диалога
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, messenger_type, bot_id)
);

-- 3. Таблица сообщений (Value Objects внутри Conversation)
CREATE TABLE messages (
    id UUID PRIMARY KEY,
    conversation_id UUID REFERENCES conversations(id),
    external_message_id VARCHAR(255), -- ID сообщения в мессенджере
    direction VARCHAR(10) CHECK (direction IN ('incoming', 'outgoing')),
    content_type VARCHAR(50) DEFAULT 'text',
    content TEXT,
    attachments JSONB DEFAULT '[]',
    intent VARCHAR(100), -- распознанное намерение
    sentiment_score FLOAT, -- анализ тональности
    processing_time_ms INTEGER,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 4. Таблица для обучения ИИ (разметка диалогов)
CREATE TABLE training_data (
    id UUID PRIMARY KEY,
    message_id UUID REFERENCES messages(id),
    user_feedback INTEGER CHECK (user_feedback BETWEEN 1 AND 5),
    corrected_intent VARCHAR(100),
    corrected_response TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    annotated_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Индексы для производительности
CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at DESC);
CREATE INDEX idx_conversations_user ON conversations(user_id);
CREATE INDEX idx_messages_intent ON messages(intent) WHERE intent IS NOT NULL;