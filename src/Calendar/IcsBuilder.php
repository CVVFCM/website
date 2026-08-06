<?php

declare(strict_types=1);

namespace App\Calendar;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Hand-rolled RFC 5545 iCalendar builder, for a single event ({@see build()})
 * or for a whole feed ({@see buildFeed()}).
 *
 * Event dates arrive as "Y-m-d\TH:i:s" wall-clock strings in Europe/Paris (the
 * Sulu templateData convention); a midnight time means "no time set", so an
 * event whose begin time is 00:00 and whose end is absent or also 00:00 is
 * emitted as an all-day VEVENT (DTEND exclusive, per the RFC). Timed events
 * carry a TZID and a static Europe/Paris VTIMEZONE, emitted once per calendar.
 * The UID is derived from the Sulu page uuid so a re-downloaded event UPDATES
 * the calendar entry instead of duplicating it.
 */
final readonly class IcsBuilder
{
    private const string TIMEZONE = 'Europe/Paris';
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s';

    public function __construct(
        private ClockInterface $clock,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * A one-event calendar, as served from an event page.
     *
     * @throws \InvalidArgumentException when a date cannot be parsed
     */
    public function build(CalendarEvent $event): string
    {
        $lines = $this->preamble();

        if (!$this->isAllDay($event)) {
            $lines = [...$lines, ...$this->timezoneLines()];
        }

        $lines = [...$lines, ...$this->eventLines($event), 'END:VCALENDAR'];

        return $this->render($lines);
    }

    /**
     * A single calendar holding every given event, as served from the events
     * list page. An event whose dates cannot be parsed is skipped rather than
     * failing the whole feed — one bad editorial value must not break the
     * subscription for everybody.
     *
     * @param list<CalendarEvent> $events
     * @param string|null         $calendarName display name suggested to the calendar client
     */
    public function buildFeed(array $events, ?string $calendarName = null): string
    {
        /** @var list<string> $eventLines */
        $eventLines = [];
        $hasTimedEvent = false;

        foreach ($events as $event) {
            try {
                $vevent = $this->eventLines($event);
                $hasTimedEvent = $hasTimedEvent || !$this->isAllDay($event);
            } catch (\InvalidArgumentException $exception) {
                $this->logger->warning('Skipping an event with unparsable dates in the ICS feed.', [
                    'uuid' => $event->uuid,
                    'begin_date' => $event->beginDate,
                    'end_date' => $event->endDate,
                    'exception' => $exception,
                ]);

                continue;
            }

            $eventLines = [...$eventLines, ...$vevent];
        }

        $lines = $this->preamble();
        if (null !== $calendarName && '' !== $calendarName) {
            $lines[] = 'X-WR-CALNAME:'.$this->escape($calendarName);
        }
        if ($hasTimedEvent) {
            $lines = [...$lines, ...$this->timezoneLines()];
        }

        $lines = [...$lines, ...$eventLines, 'END:VCALENDAR'];

        return $this->render($lines);
    }

    /**
     * @return list<string>
     */
    private function preamble(): array
    {
        return [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CVVFCM//Site web//FR',
            'CALSCALE:GREGORIAN',
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function render(array $lines): string
    {
        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * @return list<string>
     *
     * @throws \InvalidArgumentException when a date cannot be parsed
     */
    private function eventLines(CalendarEvent $event): array
    {
        $begin = $this->parseDate($event->beginDate);
        $end = $this->parseEndDate($event);

        $lines = [
            'BEGIN:VEVENT',
            \sprintf('UID:%s@cvvfcm.fr', $event->uuid),
            'DTSTAMP:'.$this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        ];
        $lines = [...$lines, ...$this->dateLines($this->allDay($begin, $end), $begin, $end)];
        $lines[] = 'SUMMARY:'.$this->escape($event->title);
        if (null !== $event->location) {
            $lines[] = 'LOCATION:'.$this->escape($event->location);
        }
        if (null !== $event->description) {
            $lines[] = 'DESCRIPTION:'.$this->escape($event->description);
        }
        $lines[] = 'URL:'.$event->url;
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function dateLines(bool $allDay, \DateTimeImmutable $begin, ?\DateTimeImmutable $end): array
    {
        if ($allDay) {
            // DTEND is exclusive: a single-day event ends the next morning.
            $exclusiveEnd = ($end ?? $begin)->modify('+1 day');

            return [
                'DTSTART;VALUE=DATE:'.$begin->format('Ymd'),
                'DTEND;VALUE=DATE:'.$exclusiveEnd->format('Ymd'),
            ];
        }

        $lines = ['DTSTART;TZID='.self::TIMEZONE.':'.$begin->format('Ymd\THis')];
        if (null !== $end && $end > $begin) {
            $lines[] = 'DTEND;TZID='.self::TIMEZONE.':'.$end->format('Ymd\THis');
        }

        return $lines;
    }

    /**
     * @throws \InvalidArgumentException when a date cannot be parsed
     */
    private function isAllDay(CalendarEvent $event): bool
    {
        return $this->allDay($this->parseDate($event->beginDate), $this->parseEndDate($event));
    }

    private function allDay(\DateTimeImmutable $begin, ?\DateTimeImmutable $end): bool
    {
        return '00:00' === $begin->format('H:i') && (null === $end || '00:00' === $end->format('H:i'));
    }

    /**
     * @throws \InvalidArgumentException when the date cannot be parsed
     */
    private function parseEndDate(CalendarEvent $event): ?\DateTimeImmutable
    {
        return null !== $event->endDate && '' !== $event->endDate ? $this->parseDate($event->endDate) : null;
    }

    /**
     * Static Europe/Paris VTIMEZONE: CEST (+02:00) from the last Sunday of
     * March, CET (+01:00) from the last Sunday of October.
     *
     * @return list<string>
     */
    private function timezoneLines(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:'.self::TIMEZONE,
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat(
            self::DATE_FORMAT,
            $date,
            new \DateTimeZone(self::TIMEZONE),
        );

        if (false === $parsed) {
            throw new \InvalidArgumentException(\sprintf('Invalid event date "%s", expected "%s".', $date, self::DATE_FORMAT));
        }

        return $parsed;
    }

    /**
     * RFC 5545 §3.3.11 TEXT escaping: backslash, semicolon, comma, and
     * newlines (as the literal sequence "\n").
     */
    private function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $text,
        );
    }

    /**
     * RFC 5545 §3.1 line folding: lines longer than 75 octets are split, each
     * continuation line starting with a single space (which counts towards its
     * own 75-octet budget). Folds on byte length without ever splitting inside
     * a multibyte UTF-8 character.
     */
    private function fold(string $line): string
    {
        if (\strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $current = '';
        $budget = 75;
        foreach (mb_str_split($line, 1, 'UTF-8') as $char) {
            if (\strlen($current) + \strlen($char) > $budget) {
                $folded .= $current."\r\n ";
                $current = '';
                $budget = 74;
            }
            $current .= $char;
        }

        return $folded.$current;
    }
}
