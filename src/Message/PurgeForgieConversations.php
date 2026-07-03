<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Scheduled hourly: drops Forgie conversations inactive for more than 24h.
 */
final readonly class PurgeForgieConversations
{
}
