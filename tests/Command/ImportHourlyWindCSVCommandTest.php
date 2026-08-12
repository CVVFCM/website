<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ImportHourlyWindCSVCommand;
use App\Repository\LiveWeatherRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

final class ImportHourlyWindCSVCommandTest extends KernelTestCase
{
    // 2024-09-09 08:00, 09:00 and 10:00 UTC. Far from any other fixture in the test database.
    private const int FIRST_HOUR = 1725868800;
    // 2024-09-11 08:00 UTC. The suite shares one database with no rollback between tests, so each
    // test reads its own day rather than the whole table.
    private const int OTHER_DAY_HOUR = 1726041600;

    private string $projectDir = '';

    protected function tearDown(): void
    {
        if ('' !== $this->projectDir) {
            (new Filesystem())->remove($this->projectDir);
        }

        parent::tearDown();
    }

    public function testItImportsFrenchFormattedHoursOnceEach(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var LiveWeatherRecordRepository $repository */
        $repository = static::getContainer()->get(LiveWeatherRecordRepository::class);

        // Thin-space thousands separators, comma decimals, the third hour repeated the way the
        // spreadsheet repeats its last row, and a #DIV/0! marker on an unusable one.
        $this->writeFixture(<<<CSV
            recorded_hour,date,wind_sin,wind_cos,wind_speed,wind_angle
            "1 725 868 800,00","45 000,00","3,00","4,00","5,00","36,87"
            "1 725 872 400,00","45 000,04","0,00","0,00","0,00",#DIV/0!
            "1 725 876 000,00","45 000,08","-6,00","8,00","10,00","323,13"
            "1 725 876 000,00","45 000,08","-6,00","8,00","10,00","323,13"
            "","","","","",""
            CSV);

        $command = new ImportHourlyWindCSVCommand($repository, $this->projectDir);
        $command(new SymfonyStyle(new ArrayInput([]), new NullOutput()));

        $records = $this->importedRecords($em);

        $this->assertCount(3, $records, 'Three distinct hours, the repeated one counted once.');
        // A dead calm is a real measurement, not a gap: (0, 0) must be kept.
        $this->assertSame([0.0, 5.0, 10.0], array_map(
            static fn (array $record): float => round((float) $record['wind_speed'], 2),
            [$records[1], $records[0], $records[2]],
        ));
        // 3 east / 4 north gives a 5 kn wind bearing 37°, rounded to the whole degree the column holds.
        $this->assertSame(37, (int) $records[0]['wind_direction']);
        $this->assertNull($records[0]['humidity'], 'Nothing may be invented for the missing readings.');
        $this->assertNull($records[0]['pressure']);
        $this->assertNull($records[0]['temperature']);
        $this->assertNull($records[0]['wind_gusts']);
        $this->assertTrue((bool) $records[0]['hourly_mean']);
    }

    public function testItSkipsHoursThatAreAlreadyRecorded(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var LiveWeatherRecordRepository $repository */
        $repository = static::getContainer()->get(LiveWeatherRecordRepository::class);

        $this->writeFixture(<<<CSV
            recorded_hour,wind_sin,wind_cos
            "1 726 041 600,00","3,00","4,00"
            CSV);

        $command = new ImportHourlyWindCSVCommand($repository, $this->projectDir);
        $command(new SymfonyStyle(new ArrayInput([]), new NullOutput()));
        $command(new SymfonyStyle(new ArrayInput([]), new NullOutput()));

        $this->assertCount(
            1,
            $this->importedRecords($em, self::OTHER_DAY_HOUR),
            'Running the import twice must not duplicate an hour.',
        );
    }

    private function writeFixture(string $contents): void
    {
        $filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/hourly-wind-'.bin2hex(random_bytes(6));
        $filesystem->mkdir($this->projectDir.'/data/weather/hourly_wind');
        $filesystem->dumpFile($this->projectDir.'/data/weather/hourly_wind/history.csv', $contents."\n");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function importedRecords(EntityManagerInterface $em, int $from = self::FIRST_HOUR): array
    {
        /** @var list<array<string, mixed>> $records */
        $records = $em->getConnection()->fetchAllAssociative(
            'SELECT * FROM live_weather_record WHERE recorded_at >= :from AND recorded_at < :to ORDER BY recorded_at',
            [
                'from' => date('Y-m-d H:i:s', $from),
                'to' => date('Y-m-d H:i:s', $from + 86400),
            ],
        );

        return $records;
    }
}
