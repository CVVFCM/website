<?php

declare(strict_types=1);

namespace App\AI\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

use function Symfony\Component\Clock\now;

/**
 * Gives Forgie the current date and time (Europe/Paris) so it can reason about
 * "aujourd'hui", "ce week-end", past vs upcoming, etc.
 */
#[AsTool('current_datetime', 'Donne la date et l\'heure actuelles en France (Europe/Paris), avec le jour de la semaine.')]
final readonly class CurrentDateTimeTool
{
    /**
     * @return array{datetime: string, jour: string, fuseau: string}
     */
    public function __invoke(): array
    {
        $now = now()->setTimezone(new \DateTimeZone('Europe/Paris'));

        return [
            'datetime' => $now->format('Y-m-d H:i:s'),
            'jour' => $now->format('l'),
            'fuseau' => 'Europe/Paris',
        ];
    }
}
