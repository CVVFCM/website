<?php

declare(strict_types=1);

namespace App\Calendar;

use Psr\Clock\ClockInterface;

/**
 * Hand-rolled RFC 5545 iCalendar builder for a single event.
 *
 * Event dates arrive as "Y-m-d\TH:i:s" wall-clock strings in Europe/Paris (the
 * Sulu templateData convention); a midnight time means "no time set", so an
 * event whose begin time is 00:00 and whose end is absent or also 00:00 is
 * emitted as an all-day VEVENT (DTEND exclusive, per the RFC). Timed events
 * carry a TZID and a static Europe/Paris VTIMEZONE. The UID is derived from
 * the Sulu page uuid so a re-downloaded event UPDATES the calendar entry
 * instead of duplicating it.
 */
final readonly class IcsBuilder
{
    private const string TIMEZONE = 'Europe/Paris';
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s';

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string      $uuid        the Sulu page uuid, used as the stable UID
     * @param string      $beginDate   "Y-m-d\TH:i:s" Europe/Paris wall clock
     * @param string|null $endDate     same format, null when the event has no end
     * @param string      $url         absolute URL of the event page
     * @param string|null $description plain text (already stripped of HTML)
     */
    public function build(
        string $uuid,
        string $title,
        string $beginDate,
        ?string $endDate,
        string $url,
        ?string $location = null,
        ?string $description = null,
    ): string {
        $begin = $this->parseDate($beginDate);
        $end = null !== $endDate && '' !== $endDate ? $this->parseDate($endDate) : null;

        $allDay = '00:00' === $begin->format('H:i') && (null === $end || '00:00' === $end->format('H:i'));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CVVFCM//Site web//FR',
            'CALSCALE:GREGORIAN',
        ];

        if (!$allDay) {
            $lines = [...$lines, ...$this->timezoneLines()];
        }

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = \sprintf('UID:%s@cvvfcm.fr', $uuid);
        $lines[] = 'DTSTAMP:'.$this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines = [...$lines, ...$this->dateLines($allDay, $begin, $end)];
        $lines[] = 'SUMMARY:'.$this->escape($title);
        if (null !== $location) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }
        if (null !== $description) {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }
        $lines[] = 'URL:'.$url;
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
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
