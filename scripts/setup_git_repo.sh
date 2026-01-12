#!/bin/bash
# Скрипт для настройки Git репозитория

# Инициализация Git репозитория
git init

# Добавление remote репозитория
git remote add origin https://github.com/sergeyiche/oracool.git

# Проверка remote
echo "Remote репозитории:"
git remote -v

# Добавление всех файлов
git add .

# Первый коммит
git commit -m "Initial commit: Oracle AI Agent Platform

- Организована структура проекта по Symfony стандартам
- Добавлена документация (Oracle.md, Agents.md, ANALYSIS_AND_PLAN.md)
- Настроена базовая архитектура платформы для ИИ-агентов
- Подготовлены доменные модели и use cases
- Добавлены конфигурационные файлы и миграции БД"

# Установка ветки main (если нужно)
git branch -M main

echo ""
echo "✅ Git репозиторий настроен!"
echo ""
echo "Для отправки в GitHub выполните:"
echo "  git push -u origin main"
echo ""
echo "Если репозиторий на GitHub пустой, возможно потребуется:"
echo "  git push -u origin main --force"
