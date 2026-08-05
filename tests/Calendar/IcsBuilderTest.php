<?php

declare(strict_types=1);

namespace App\Tests\Calendar;

use App\Calendar\IcsBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class IcsBuilderTest extends TestCase
{
    private const string UUID = '0198c6a2-1111-7222-8333-444455556666';

    public function testItBuildsAValidCalendarSkeletonWithCrlfLineEndings(): void
    {
        $ics = $this->build();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("VERSION:2.0\r\n", $ics);
        $this->assertStringContainsString("PRODID:-//CVVFCM//Site web//FR\r\n", $ics);
        $this->assertStringContainsString("CALSCALE:GREGORIAN\r\n", $ics);
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        $this->assertStringContainsString("END:VEVENT\r\n", $ics);

        // Every line break is a CRLF — no lone LF or CR anywhere.
        $this->assertSame(substr_count($ics, "\r\n"), substr_count($ics, "\n"));
        $this->assertSame(substr_count($ics, "\r\n"), substr_count($ics, "\r"));
    }

    public function testTheUidIsTheUuidOnTheClubDomain(): void
    {
        $this->assertStringContainsString(
            'UID:'.self::UUID."@cvvfcm.fr\r\n",
            $this->build(),
        );
    }

    public function testTheDtstampIsTheFrozenClockInUtc(): void
    {
        $ics = (new IcsBuilder(new MockClock('2026-08-05 14:30:00', 'Europe/Paris')))->build(
            uuid: self::UUID,
            title: 'Test',
            beginDate: '2027-06-12T10:00:00',
            endDate: null,
            url: 'https://cvvfcm.fr/evenements/test',
        );

        // 14:30 Paris summer time is 12:30 UTC.
        $this->assertStringContainsString("DTSTAMP:20260805T123000Z\r\n", $ics);
    }

    public function testAnAllDaySingleDayEventGetsAnExclusiveDtendOfTheNextDay(): void
    {
        $ics = $this->build(beginDate: '2027-04-17T00:00:00', endDate: null);

        $this->assertStringContainsString("DTSTART;VALUE=DATE:20270417\r\n", $ics);
        $this->assertStringContainsString("DTEND;VALUE=DATE:20270418\r\n", $ics);
        $this->assertStringNotContainsString('VTIMEZONE', $ics);
    }

    public function testAnAllDayMultiDayEventGetsAnExclusiveDtendAfterItsLastDay(): void
    {
        $ics = $this->build(beginDate: '2027-04-17T00:00:00', endDate: '2027-04-19T00:00:00');

        $this->assertStringContainsString("DTSTART;VALUE=DATE:20270417\r\n", $ics);
        $this->assertStringContainsString("DTEND;VALUE=DATE:20270420\r\n", $ics);
    }

    public function testATimedEventWithAnEndGetsTzidDatesAndAVtimezone(): void
    {
        $ics = $this->build(beginDate: '2027-06-12T10:00:00', endDate: '2027-06-13T18:00:00');

        $this->assertStringContainsString("DTSTART;TZID=Europe/Paris:20270612T100000\r\n", $ics);
        $this->assertStringContainsString("DTEND;TZID=Europe/Paris:20270613T180000\r\n", $ics);
        $this->assertStringContainsString("BEGIN:VTIMEZONE\r\n", $ics);
        $this->assertStringContainsString("TZID:Europe/Paris\r\n", $ics);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', $ics);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', $ics);
    }

    public function testATimedEventWithoutAnEndHasNoDtend(): void
    {
        $ics = $this->build(beginDate: '2027-06-12T10:00:00', endDate: null);

        $this->assertStringContainsString("DTSTART;TZID=Europe/Paris:20270612T100000\r\n", $ics);
        $this->assertStringNotContainsString('DTEND', $ics);
    }

    public function testItEscapesTextPerRfc5545(): void
    {
        $ics = $this->build(
            title: "Régate; du club, étape 1\nsur deux lignes",
            location: 'Lac, des; Vieilles Forges',
        );

        $unfolded = $this->unfold($ics);
        $this->assertStringContainsString('SUMMARY:Régate\\; du club\\, étape 1\\nsur deux lignes', $unfolded);
        $this->assertStringContainsString('LOCATION:Lac\\, des\\; Vieilles Forges', $unfolded);
    }

    public function testItFoldsLongLinesAt75OctetsWithoutBreakingUtf8(): void
    {
        // Accented characters make the octet length diverge from the character
        // count, exercising the multibyte-aware folding.
        $title = str_repeat('Régate très épique été ', 10);
        $ics = $this->build(title: $title);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, \strlen($line), \sprintf('Line exceeds 75 octets: "%s"', $line));
        }

        // The folded content unfolds back to the escaped original, and the
        // whole document is still valid UTF-8 (no character split in half).
        $this->assertStringContainsString('SUMMARY:'.trim($title), $this->unfold($ics));
        $this->assertTrue(mb_check_encoding($ics, 'UTF-8'));
    }

    public function testOptionalFieldsAreOmittedWhenAbsent(): void
    {
        $ics = $this->build(location: null, description: null);

        $this->assertStringNotContainsString('LOCATION', $ics);
        $this->assertStringNotContainsString('DESCRIPTION', $ics);
    }

    public function testItIncludesSummaryDescriptionAndUrl(): void
    {
        $ics = $this->build();

        $unfolded = $this->unfold($ics);
        $this->assertStringContainsString('SUMMARY:Régate du club', $unfolded);
        $this->assertStringContainsString('DESCRIPTION:Une belle régate.', $unfolded);
        $this->assertStringContainsString('URL:https://cvvfcm.fr/evenements/regate-du-club', $unfolded);
    }

    private function build(
        string $title = 'Régate du club',
        string $beginDate = '2027-06-12T10:00:00',
        ?string $endDate = '2027-06-13T18:00:00',
        ?string $location = 'Lac des Vieilles Forges',
        ?string $description = 'Une belle régate.',
    ): string {
        $builder = new IcsBuilder(new MockClock('2026-08-05 12:00:00', 'UTC'));

        return $builder->build(
            uuid: self::UUID,
            title: $title,
            beginDate: $beginDate,
            endDate: $endDate,
            url: 'https://cvvfcm.fr/evenements/regate-du-club',
            location: $location,
            description: $description,
        );
    }

    private function unfold(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }
}
