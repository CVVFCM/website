<?php

declare(strict_types=1);

namespace App\Calendar;

/**
 * A single event to serialise into an iCalendar VEVENT.
 *
 * Dates are the raw "Y-m-d\TH:i:s" Europe/Paris wall-clock strings stored in
 * the Sulu templateData — no conversion happens here, {@see IcsBuilder} owns
 * the parsing and the timezone rules.
 */
final readonly class CalendarEvent
{
    /**
     * @param string      $uuid        the Sulu page uuid, used as the stable UID
     * @param string      $beginDate   "Y-m-d\TH:i:s" Europe/Paris wall clock
     * @param string|null $endDate     same format, null when the event has no end
     * @param string      $url         absolute URL of the event page
     * @param string|null $description plain text (already stripped of HTML)
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $beginDate,
        public ?string $endDate,
        public string $url,
        public ?string $location = null,
        public ?string $description = null,
    ) {
    }
}
