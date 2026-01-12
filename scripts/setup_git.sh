#!/bin/bash
# Упрощенный скрипт для настройки Git репозитория

# Переход в корневую директорию проекта
cd "$(dirname "$0")/.." || exit 1

echo "🔧 Настройка Git репозитория..."
echo ""

# Инициализация Git репозитория
if [ -d .git ]; then
    echo "⚠️  Git репозиторий уже инициализирован"
else
    git init
    echo "✅ Git репозиторий инициализирован"
fi

# Проверка существования remote
if git remote get-url origin >/dev/null 2>&1; then
    echo "⚠️  Remote 'origin' уже настроен"
    CURRENT_REMOTE=$(git remote get-url origin)
    echo "   Текущий: $CURRENT_REMOTE"
    read -p "Обновить на https://github.com/sergeyiche/oracool.git? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git remote set-url origin https://github.com/sergeyiche/oracool.git
        echo "✅ Remote обновлен"
    fi
else
    git remote add origin https://github.com/sergeyiche/oracool.git
    echo "✅ Remote добавлен"
fi

# Показать remote
echo ""
echo "Remote репозитории:"
git remote -v
echo ""

# Добавление всех файлов
echo "📦 Добавление файлов..."
git add .

# Проверка статуса
echo ""
echo "Статус репозитория:"
git status --short
echo ""

# Первый коммит
read -p "Создать первый коммит? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    git commit -m "Initial commit: Oracle AI Agent Platform

- Организована структура проекта по Symfony стандартам
- Добавлена документация (Oracle.md, Agents.md, ANALYSIS_AND_PLAN.md)
- Настроена базовая архитектура платформы для ИИ-агентов
- Подготовлены доменные модели и use cases
- Добавлены конфигурационные файлы и миграции БД"
    
    echo "✅ Коммит создан"
    
    # Установка ветки main
    git branch -M main 2>/dev/null
    
    echo ""
    echo "✅ Git репозиторий настроен!"
    echo ""
    echo "Для отправки в GitHub выполните:"
    echo "  git push -u origin main"
    echo ""
    echo "Если репозиторий на GitHub пустой, возможно потребуется:"
    echo "  git push -u origin main --force"
else
    echo "⚠️  Коммит не создан. Выполните вручную:"
    echo "  git commit -m 'Initial commit'"
fi
