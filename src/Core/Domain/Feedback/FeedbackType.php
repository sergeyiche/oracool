<?php

namespace App\Core\Domain\Feedback;

/**
 * Типы обратной связи
 */
enum FeedbackType: string
{
    case APPROVE = 'approve';  // Одобрить ответ
    case CORRECT = 'correct';  // Исправить ответ
    case DELETE = 'delete';    // Удалить ответ
}
