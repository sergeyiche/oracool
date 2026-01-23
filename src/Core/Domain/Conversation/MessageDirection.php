<?php

namespace App\Core\Domain\Conversation;

enum MessageDirection: string
{
    case INCOMING = 'incoming';
    case OUTGOING = 'outgoing';
}
