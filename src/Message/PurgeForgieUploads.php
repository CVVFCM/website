<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Scheduled hourly: drops Forgie image uploads older than a few hours. They are only
 * needed during the turn they are sent on (model vision + optional email attachment).
 */
final readonly class PurgeForgieUploads
{
}
