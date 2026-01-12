# PowerShell скрипт для настройки Git репозитория

# Проверка наличия Git
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Git не установлен в системе!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Установите Git с https://git-scm.com/download/win" -ForegroundColor Yellow
    Write-Host "Или используйте Git Bash для выполнения scripts/setup_git_repo.sh" -ForegroundColor Yellow
    exit 1
}

# Переход в директорию проекта
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "🔧 Настройка Git репозитория..." -ForegroundColor Cyan

# Инициализация Git репозитория
if (Test-Path .git) {
    Write-Host "⚠️  Git репозиторий уже инициализирован" -ForegroundColor Yellow
} else {
    git init
    Write-Host "✅ Git репозиторий инициализирован" -ForegroundColor Green
}

# Проверка существования remote
$remoteExists = git remote get-url origin 2>$null
if ($remoteExists) {
    Write-Host "⚠️  Remote 'origin' уже настроен: $remoteExists" -ForegroundColor Yellow
    $update = Read-Host "Обновить на https://github.com/sergeyiche/oracool.git? (y/n)"
    if ($update -eq "y" -or $update -eq "Y") {
        git remote set-url origin https://github.com/sergeyiche/oracool.git
        Write-Host "✅ Remote обновлен" -ForegroundColor Green
    }
} else {
    git remote add origin https://github.com/sergeyiche/oracool.git
    Write-Host "✅ Remote добавлен" -ForegroundColor Green
}

# Показать remote
Write-Host ""
Write-Host "Remote репозитории:" -ForegroundColor Cyan
git remote -v

# Добавление всех файлов
Write-Host ""
Write-Host "📦 Добавление файлов..." -ForegroundColor Cyan
git add .

# Проверка статуса
Write-Host ""
Write-Host "Статус репозитория:" -ForegroundColor Cyan
git status --short

# Первый коммит
Write-Host ""
$commit = Read-Host "Создать первый коммит? (y/n)"
if ($commit -eq "y" -or $commit -eq "Y") {
    git commit -m "Initial commit: Oracle AI Agent Platform

- Организована структура проекта по Symfony стандартам
- Добавлена документация (Oracle.md, Agents.md, ANALYSIS_AND_PLAN.md)
- Настроена базовая архитектура платформы для ИИ-агентов
- Подготовлены доменные модели и use cases
- Добавлены конфигурационные файлы и миграции БД"
    
    Write-Host "✅ Коммит создан" -ForegroundColor Green
    
    # Установка ветки main
    git branch -M main 2>$null
    
    Write-Host ""
    Write-Host "✅ Git репозиторий настроен!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Для отправки в GitHub выполните:" -ForegroundColor Yellow
    Write-Host "  git push -u origin main" -ForegroundColor White
    Write-Host ""
    Write-Host "Если репозиторий на GitHub пустой, возможно потребуется:" -ForegroundColor Yellow
    Write-Host "  git push -u origin main --force" -ForegroundColor White
} else {
    Write-Host "⚠️  Коммит не создан. Выполните вручную:" -ForegroundColor Yellow
    Write-Host "  git commit -m 'Initial commit'" -ForegroundColor White
}
