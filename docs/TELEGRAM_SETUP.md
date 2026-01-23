# 🤖 Telegram Bot Setup Guide

**Дата:** 2026-01-12  
**Статус:** ✅ Handler и команды готовы!

---

## 📋 Обзор

Telegram Bot реализован как "цифровой двойник" с поддержкой:
- ✅ Webhook для приема сообщений
- ✅ RAG (Retrieval-Augmented Generation) для умных ответов
- ✅ Система обратной связи (approve/correct/delete)
- ✅ Команды управления профилем и базой знаний
- ✅ Три режима работы (silent/active/aggressive)

---

## 🚀 Быстрый старт

### 1. Создайте бота через @BotFather

```
1. Откройте Telegram, найдите @BotFather
2. Отправьте /newbot
3. Следуйте инструкциям
4. Сохраните токен бота
```

### 2. Настройте .env

```bash
# В /www/oracool/.env
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_WEBHOOK_SECRET=random_secret_xxxxxxxxxx
BOT_OWNER_TELEGRAM_ID=123456789  # Ваш Telegram ID
```

**Как узнать свой Telegram ID:**
- Напишите @userinfobot
- Или используйте @getmyid_bot

### 3. Установите webhook

```bash
# Из контейнера
docker compose exec app php bin/console telegram:webhook:setup https://your-domain.com/webhook/telegram

# Или через make
make shell
php bin/console telegram:webhook:setup https://your-domain.com/webhook/telegram
```

### 4. Проверьте webhook

```bash
docker compose exec app php bin/console telegram:webhook:setup --info
```

---

## 🎛️ Команды Telegram

### Для пользователей

| Команда | Описание |
|---------|----------|
| `/start` | Начало работы с ботом |
| `/help` | Список всех команд |
| `/status` | Текущий статус профиля |
| `/mode [silent\|active\|aggressive]` | Изменить режим работы |
| `/stats` | Статистика базы знаний |

### Режимы работы

**Silent (тихий):**
- Бот не отвечает автоматически
- Только наблюдает и учится
- Подходит для начального обучения

**Active (активный):**
- Отвечает на релевантные сообщения (score >= threshold)
- Сбалансированный режим
- Рекомендуется для владельца

**Aggressive (агрессивный):**
- Отвечает на все сообщения
- Даже с низкой релевантностью
- Для максимальной активности

---

## 💡 Обратная связь

После каждого ответа бота появляются кнопки:

**✅ Одобрить**
- Добавляет ответ в базу знаний
- Помогает боту учиться
- Повышает качество будущих ответов

**✏️ Исправить**
- Запрашивает правильный вариант
- Сохраняет оба варианта (до/после)
- Улучшает стиль общения

**🗑 Удалить**
- Удаляет неудачный ответ
- Помечает как негативный пример
- Предотвращает повторение ошибок

---

## 🛠️ Консольные команды

### Telegram

```bash
# Установить webhook
php bin/console telegram:webhook:setup https://your-domain.com/webhook/telegram

# Показать информацию о webhook
php bin/console telegram:webhook:setup --info

# Удалить webhook
php bin/console telegram:webhook:setup --delete
```

### Профили

```bash
# Создать профиль
php bin/console profile:create 123456789 --mode=active --style=casual

# Обновить профиль
php bin/console profile:update 123456789 --mode=aggressive
php bin/console profile:update 123456789 --threshold=0.8
php bin/console profile:update 123456789 --add-interest="программирование"
php bin/console profile:update 123456789 --add-example="Привет! Как дела?"
```

### База знаний

```bash
# Импорт из файла
php bin/console knowledge:import knowledge.txt 123456789

# Импорт JSON
php bin/console knowledge:import data.json 123456789 --format=json

# Статистика пользователя
php bin/console knowledge:stats 123456789

# Глобальная статистика
php bin/console knowledge:stats
```

---

## 📝 Форматы файлов для импорта

### TXT (по умолчанию)

```
Первая запись знаний.
Может быть несколько строк.

Вторая запись знаний.
Разделяются пустой строкой.

Третья запись.
```

### JSON

```json
[
  "Первая запись",
  "Вторая запись",
  {"text": "Третья запись с метаданными"}
]
```

### CSV

```csv
text
"Первая запись"
"Вторая запись"
"Третья запись"
```

---

## 🏗️ Архитектура

### Компоненты

```
TelegramWebhookController
    ↓
ProcessTelegramMessage (Use Case)
    ↓
├─ CheckRelevance → VectorSearch (RAG)
└─ GenerateResponse → LLM + Context
    ↓
TelegramBotService → Telegram API
```

### Поток обработки сообщения

1. **Webhook получает update** от Telegram
2. **TelegramMessageMapper** извлекает данные
3. **ProcessTelegramMessage** оркестрирует процесс:
   - Получает/создает профиль пользователя
   - Проверяет режим бота (silent/active/aggressive)
   - **CheckRelevance** векторизует сообщение и ищет в knowledge base
   - Если релевантно → **GenerateResponse** с RAG
4. **TelegramBotService** отправляет ответ с кнопками feedback

### Обработка Feedback

```
Пользователь нажимает кнопку
    ↓
callback_query → handleCallbackQuery()
    ↓
├─ approve: сохранить в knowledge base
├─ correct: запросить исправленный вариант
└─ delete: удалить ответ
```

---

## ⚙️ Настройка профиля

### Параметры профиля

**Communication Style:**
- `formal` - официальный стиль (temperature 0.3)
- `casual` - непринужденный (temperature 0.7)
- `balanced` - сбалансированный (temperature 0.5)
- `creative` - творческий (temperature 0.9)
- `technical` - технический (temperature 0.2)

**Response Length:**
- `short` - краткие ответы (1-2 предложения, 150 токенов)
- `medium` - средние (2-4 предложения, 300 токенов)
- `long` - развернутые (500 токенов)

**Relevance Threshold:**
- `0.5` - низкий порог (больше ответов, но менее точных)
- `0.7` - средний (рекомендуется)
- `0.9` - высокий (только очень релевантные)

### Пример настройки

```bash
# Создать профиль для себя
php bin/console profile:create 123456789 \
  --mode=active \
  --style=casual \
  --threshold=0.7

# Добавить интересы
php bin/console profile:update 123456789 \
  --add-interest="Python" \
  --add-interest="AI" \
  --add-interest="DevOps"

# Добавить примеры ответов
php bin/console profile:update 123456789 \
  --add-example="Привет! Чем могу помочь?" \
  --add-example="Интересный вопрос, дай подумать..."
```

---

## 🔒 Безопасность

### Webhook Secret Token

Telegram отправляет заголовок `X-Telegram-Bot-Api-Secret-Token` с каждым запросом:

```php
// В контроллере
if ($secretToken !== $this->webhookSecret) {
    return new JsonResponse(['error' => 'Unauthorized'], 401);
}
```

### Рекомендации

1. **Используйте HTTPS** для webhook URL
2. **Генерируйте случайный secret** при установке:
   ```bash
   openssl rand -hex 16
   ```
3. **Ограничьте доступ** к webhook endpoint на уровне Nginx/firewall
4. **Логируйте подозрительные запросы**

---

## 🧪 Тестирование

### Ручное тестирование

```bash
# Отправьте сообщение боту в Telegram
# Проверьте логи
docker compose logs -f app

# Проверьте webhook info
docker compose exec app php bin/console telegram:webhook:setup --info
```

### Проверка обработки

```bash
# Создать тестовый профиль
docker compose exec app php bin/console profile:create 999999999 --mode=active

# Посмотреть статистику
docker compose exec app php bin/console knowledge:stats 999999999
```

### Тестирование команд

```bash
# В Telegram отправьте:
/start
/help
/status
/mode active
/stats
```

---

## 📊 Мониторинг

### Логи

```bash
# Все логи приложения
docker compose logs -f app

# Только Telegram webhook
docker compose logs -f app | grep "Telegram"

# Ошибки
docker compose logs -f app | grep "ERROR"
```

### Метрики

Смотрите в логах:
- `processing_time_ms` - время обработки
- `relevance_score` - оценка релевантности
- `context_entries_used` - использовано контекста
- `matches_found` - найдено совпадений

---

## 🐛 Troubleshooting

### Бот не отвечает

**1. Проверьте webhook:**
```bash
php bin/console telegram:webhook:setup --info
```

**2. Проверьте режим:**
```bash
php bin/console knowledge:stats YOUR_TELEGRAM_ID
```

Если `Bot Mode: silent` → измените на `active`:
```bash
php bin/console profile:update YOUR_TELEGRAM_ID --mode=active
```

**3. Проверьте логи:**
```bash
docker compose logs -f app | grep "Processing Telegram message"
```

### Webhook не устанавливается

**1. Проверьте URL доступен из интернета:**
```bash
curl -I https://your-domain.com/webhook/telegram
```

**2. Проверьте SSL сертификат:**
- Telegram требует валидный SSL
- Используйте Let's Encrypt

**3. Проверьте токен бота в .env**

### Низкая релевантность (всегда 0)

**1. База знаний пуста:**
```bash
php bin/console knowledge:stats YOUR_TELEGRAM_ID
```

Если `Total Entries: 0` → импортируйте данные:
```bash
php bin/console knowledge:import data.txt YOUR_TELEGRAM_ID
```

**2. Проблема с Embedding Service:**
```bash
# Проверьте Ollama
curl http://host.docker.internal:11434/api/tags

# Или переключитесь на OpenAI
# В .env:
EMBEDDING_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

---

## 📚 API Reference

### TelegramBotService Methods

```php
// Отправка сообщения
$telegramBot->sendMessage($chatId, $text, $replyToMessageId, $replyMarkup);

// Редактирование
$telegramBot->editMessage($chatId, $messageId, $text, $replyMarkup);

// Удаление
$telegramBot->deleteMessage($chatId, $messageId);

// Действие "печатает..."
$telegramBot->sendChatAction($chatId, 'typing');

// Inline клавиатура
$keyboard = $telegramBot->createInlineKeyboard([
    [['text' => 'Button 1', 'callback_data' => 'action1']],
    [['text' => 'Button 2', 'callback_data' => 'action2']]
]);
```

### ProcessTelegramMessage

```php
$result = $processMessage->execute(
    text: 'Входящее сообщение',
    telegramUserId: 123456789,
    chatId: 123456789,
    messageId: 42
);

if ($result->shouldRespond) {
    echo $result->response;
    echo "Relevance: {$result->relevanceScore}";
}
```

---

## 🎯 Best Practices

### 1. Обучение бота

**Начните с silent режима:**
```bash
php bin/console profile:create YOUR_ID --mode=silent
```

**Импортируйте исходные данные:**
```bash
# Ваши старые сообщения, заметки, статьи
php bin/console knowledge:import my_messages.txt YOUR_ID
```

**Постепенно переходите к active:**
```bash
php bin/console profile:update YOUR_ID --mode=active
```

### 2. Качество ответов

**Используйте feedback:**
- ✅ Одобряйте хорошие ответы
- ✏️ Исправляйте неточные
- 🗑 Удаляйте плохие

**Добавляйте примеры:**
```bash
php bin/console profile:update YOUR_ID \
  --add-example="Так я обычно отвечаю"
```

### 3. Настройка релевантности

**Экспериментируйте с порогом:**
```bash
# Больше ответов
php bin/console profile:update YOUR_ID --threshold=0.6

# Только точные
php bin/console profile:update YOUR_ID --threshold=0.85
```

---

## 📖 Дополнительная документация

- **MVP_PROGRESS.md** - Общий прогресс MVP
- **DOCKER_SETUP.md** - Docker инфраструктура
- **ANALYSIS_AND_PLAN.md** - Архитектурный план

---

**Готово к использованию!** 🚀

Теперь ваш бот может:
- ✅ Принимать сообщения через webhook
- ✅ Проверять релевантность через векторный поиск
- ✅ Генерировать умные ответы с RAG
- ✅ Учиться на обратной связи
- ✅ Управляться через консольные команды



---------------------

Done! Congratulations on your new bot. You will find it at t.me/Orac00lBot. You can now add a description, about section and profile picture for your bot, see /help for a list of commands. By the way, when you've finished creating your cool bot, ping our Bot Support if you want a better username for it. Just make sure the bot is fully operational before you do this.

Use this token to access the HTTP API:
8063685759:AAGq7T2jwKCXnQ8EIjthf57WZK0xkTMWkfI
Keep your token secure and store it safely, it can be used by anyone to control your bot.

For a description of the Bot API, see this page: https://core.telegram.org/bots/api@userinfobot 


----------------------

@sergey_i_che
Id: 858361483
First: Sergey
Last: Cherednichenko