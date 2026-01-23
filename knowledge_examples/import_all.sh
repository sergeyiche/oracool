#!/bin/bash

# Скрипт для импорта всей базы знаний философского бота-консультанта
# Использование: ./import_all.sh YOUR_TELEGRAM_ID

set -e  # Остановить при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Проверка аргументов
if [ -z "$1" ]; then
    echo -e "${RED}❌ Ошибка: не указан Telegram ID${NC}"
    echo -e "${YELLOW}Использование: $0 YOUR_TELEGRAM_ID${NC}"
    echo ""
    echo "Пример:"
    echo "  $0 858361483"
    exit 1
fi

TELEGRAM_ID=$1

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  🧠 Импорт базы знаний философского бота-консультанта     ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Telegram ID: ${TELEGRAM_ID}${NC}"
echo ""

# Функция для импорта файла
import_file() {
    local file=$1
    local description=$2
    
    echo -e "${BLUE}📥 Импорт: ${description}${NC}"
    echo -e "${YELLOW}   Файл: ${file}${NC}"
    
    if [ ! -f "$file" ]; then
        echo -e "${RED}   ❌ Файл не найден: ${file}${NC}"
        return 1
    fi
    
    docker exec oracool-app php bin/console knowledge:import "$file" "$TELEGRAM_ID"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}   ✅ Успешно импортировано${NC}"
    else
        echo -e "${RED}   ❌ Ошибка импорта${NC}"
        return 1
    fi
    echo ""
}

# Проверка что Docker контейнер запущен
echo -e "${YELLOW}🔍 Проверка Docker контейнера...${NC}"
if ! docker ps | grep -q oracool-app; then
    echo -e "${RED}❌ Контейнер oracool-app не запущен${NC}"
    echo -e "${YELLOW}Запустите: docker compose up -d${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Контейнер работает${NC}"
echo ""

# Импорт базы знаний
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}1/4: Стоическая философия${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
import_file "knowledge_examples/stoicism/core_principles.txt" "Основные стоические принципы и практики"

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}2/4: Юнгианская психология${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
import_file "knowledge_examples/jungian/shadow_and_archetypes.txt" "Юнгианские концепции (Тень, архетипы, индивидуация)"

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}3/4: Экзистенциальная терапия${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
import_file "knowledge_examples/existential/meaning_and_crisis.txt" "Экзистенциальная терапия (смысл, тревога, свобода)"

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}4/4: Личный стиль консультирования${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
import_file "knowledge_examples/personal/consulting_style.txt" "Личный стиль и подход к консультированию"

# Статистика
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}🎉 Импорт завершён!${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}📊 Статистика базы знаний:${NC}"
echo ""
docker exec oracool-app php bin/console knowledge:stats "$TELEGRAM_ID"

echo ""
echo -e "${GREEN}✨ Ваш философский бот-консультант готов к работе!${NC}"
echo ""
echo -e "${YELLOW}📝 Следующие шаги:${NC}"
echo "   1. Откройте бота в Telegram"
echo "   2. Отправьте /start"
echo "   3. Задайте вопрос, например:"
echo "      - Как найти смысл жизни?"
echo "      - Что делать с постоянной тревогой?"
echo "      - Как принять себя?"
echo ""
echo -e "${YELLOW}📚 Документация:${NC}"
echo "   - Подробное руководство: docs/BOT_TRAINING_GUIDE.md"
echo "   - Примеры знаний: knowledge_examples/README.md"
echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  Удачи в консультировании! 🏛️🧠✨                          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
