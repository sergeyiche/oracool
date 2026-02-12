#!/bin/bash

# Скрипт для переключения между LLM моделями

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
SERVICES_FILE="$PROJECT_ROOT/config/services.yaml"

# Цвета
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  🤖 Переключение LLM модели                           ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo

if [ -z "$1" ]; then
    echo -e "${YELLOW}Использование:${NC}"
    echo "  $0 <модель>"
    echo
    echo -e "${YELLOW}Доступные модели:${NC}"
    echo "  1. qwen        - Qwen2.5:7b (локально, бесплатно)"
    echo "  2. llama       - Llama3.2 (локально, бесплатно)"
    echo "  3. mistral     - Mistral (локально, бесплатно)"
    echo "  4. openai      - OpenAI GPT-4o-mini (платно, отлично)"
    echo "  5. deepseek    - DeepSeek-V3 (ДЁШЕВО, отлично для русского)"
    echo
    echo -e "${YELLOW}Пример:${NC}"
    echo "  $0 qwen"
    exit 1
fi

MODEL="$1"

# Функция для обновления alias в services.yaml
update_service_alias() {
    local llm_alias="$1"
    local embedding_alias="$2"
    
    # Обновляем LLM alias
    sed -i "s|alias: 'llm_service\.[^']*'|alias: 'llm_service.${llm_alias}'|" "$SERVICES_FILE"
    
    # Обновляем Embedding alias
    sed -i "s|alias: 'embedding_service\.[^']*'|alias: 'embedding_service.${embedding_alias}'|" "$SERVICES_FILE"
}

case "$MODEL" in
    qwen)
        echo -e "${BLUE}📥 Переключение на Qwen2.5:7b...${NC}"
        
        # Проверяем что модель скачана
        if ! docker exec oracool-ollama ollama list | grep -q "qwen2.5:7b"; then
            echo -e "${YELLOW}⏳ Модель не найдена, скачиваю...${NC}"
            docker exec oracool-ollama ollama pull qwen2.5:7b
        fi
        
        sed -i 's/^EMBEDDING_PROVIDER=.*/EMBEDDING_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^LLM_PROVIDER=.*/LLM_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^OLLAMA_LLM_MODEL=.*/OLLAMA_LLM_MODEL=qwen2.5:7b/' "$ENV_FILE"
        
        # Обновляем services.yaml
        update_service_alias "ollama" "ollama"
        
        echo -e "${GREEN}✅ Переключено на Qwen2.5:7b${NC}"
        ;;
        
    llama)
        echo -e "${BLUE}📥 Переключение на Llama3.2...${NC}"
        
        if ! docker exec oracool-ollama ollama list | grep -q "llama3.2"; then
            echo -e "${YELLOW}⏳ Модель не найдена, скачиваю...${NC}"
            docker exec oracool-ollama ollama pull llama3.2
        fi
        
        sed -i 's/^EMBEDDING_PROVIDER=.*/EMBEDDING_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^LLM_PROVIDER=.*/LLM_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^OLLAMA_LLM_MODEL=.*/OLLAMA_LLM_MODEL=llama3.2/' "$ENV_FILE"
        
        # Обновляем services.yaml
        update_service_alias "ollama" "ollama"
        
        echo -e "${GREEN}✅ Переключено на Llama3.2${NC}"
        ;;
        
    mistral)
        echo -e "${BLUE}📥 Переключение на Mistral...${NC}"
        
        if ! docker exec oracool-ollama ollama list | grep -q "mistral"; then
            echo -e "${YELLOW}⏳ Модель не найдена, скачиваю...${NC}"
            docker exec oracool-ollama ollama pull mistral
        fi
        
        sed -i 's/^EMBEDDING_PROVIDER=.*/EMBEDDING_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^LLM_PROVIDER=.*/LLM_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^OLLAMA_LLM_MODEL=.*/OLLAMA_LLM_MODEL=mistral/' "$ENV_FILE"
        
        # Обновляем services.yaml
        update_service_alias "ollama" "ollama"
        
        echo -e "${GREEN}✅ Переключено на Mistral${NC}"
        ;;
        
    openai)
        echo -e "${BLUE}📥 Переключение на OpenAI GPT-4o-mini...${NC}"
        
        # Проверяем наличие API ключа
        if ! grep -q "^OPENAI_API_KEY=sk-" "$ENV_FILE"; then
            echo -e "${RED}❌ ОШИБКА: OPENAI_API_KEY не настроен в .env${NC}"
            echo -e "${YELLOW}Добавьте ваш API ключ:${NC}"
            echo "  sed -i 's/^OPENAI_API_KEY=.*/OPENAI_API_KEY=sk-your-key-here/' $ENV_FILE"
            exit 1
        fi
        
        sed -i 's/^EMBEDDING_PROVIDER=.*/EMBEDDING_PROVIDER=openai/' "$ENV_FILE"
        sed -i 's/^LLM_PROVIDER=.*/LLM_PROVIDER=openai/' "$ENV_FILE"
        
        # Обновляем services.yaml
        update_service_alias "openai" "openai"
        
        echo -e "${GREEN}✅ Переключено на OpenAI GPT-4o-mini${NC}"
        ;;
        
    deepseek)
        echo -e "${BLUE}📥 Переключение на DeepSeek-V3...${NC}"
        
        # Проверяем наличие API ключа
        if ! grep -q "^DEEPSEEK_API_KEY=sk-" "$ENV_FILE"; then
            echo -e "${RED}❌ ОШИБКА: DEEPSEEK_API_KEY не настроен в .env${NC}"
            echo -e "${YELLOW}Получите ключ на https://platform.deepseek.com${NC}"
            echo -e "${YELLOW}Добавьте в .env:${NC}"
            echo "  echo 'DEEPSEEK_API_KEY=sk-your-key-here' >> $ENV_FILE"
            exit 1
        fi
        
        sed -i 's/^EMBEDDING_PROVIDER=.*/EMBEDDING_PROVIDER=ollama/' "$ENV_FILE"
        sed -i 's/^LLM_PROVIDER=.*/LLM_PROVIDER=deepseek/' "$ENV_FILE"
        
        # Обновляем services.yaml
        update_service_alias "deepseek" "ollama"
        
        echo -e "${GREEN}✅ Переключено на DeepSeek-V3${NC}"
        echo -e "${YELLOW}💡 DeepSeek в ~10 раз дешевле OpenAI!${NC}"
        ;;
        
    *)
        echo -e "${RED}❌ Неизвестная модель: $MODEL${NC}"
        exit 1
        ;;
esac

echo
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${YELLOW}🔄 Перезапуск контейнеров...${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"

cd "$PROJECT_ROOT"
docker compose restart app messenger-consumer

echo
echo -e "${GREEN}✅ Готово!${NC}"
echo
echo -e "${YELLOW}📊 Текущие настройки:${NC}"
grep -E "^(EMBEDDING_PROVIDER|LLM_PROVIDER|OLLAMA_LLM_MODEL|OPENAI_LLM_MODEL)=" "$ENV_FILE" | sed 's/^/  /'
echo
echo -e "${YELLOW}🧪 Тестирование:${NC}"
echo "  docker exec oracool-app php bin/console test:response 858361483 \"Как найти смысл жизни?\""
echo
