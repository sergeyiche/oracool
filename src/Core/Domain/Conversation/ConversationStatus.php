<?php

namespace App\Core\Domain\Conversation;

enum ConversationStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case DELETED = 'deleted';
}
