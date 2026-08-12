<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accept wind-only hourly observations: nullable weather readings and an hourly_mean flag';
    }

    public function up(Schema $schema): void
    {
        // Part of the station history only survives as spreadsheet exports holding one averaged row
        // per hour, with the wind intact and every other measurement lost. Those rows carry a null
        // reading rather than a fabricated zero, and the flag lets the ML export accept them despite
        // the six-samples-per-hour rule that guards genuinely partial hours.
        $this->addSql('ALTER TABLE live_weather_record ALTER humidity DROP NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ALTER pressure DROP NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ALTER temperature DROP NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ALTER wind_gusts DROP NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ADD hourly_mean BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Restoring NOT NULL would fail while any wind-only row is present, and there is nothing
        // truthful to backfill them with, so they go with the flag that identifies them.
        $this->addSql('DELETE FROM live_weather_record WHERE hourly_mean = TRUE');
        $this->addSql('ALTER TABLE live_weather_record DROP hourly_mean');
        $this->addSql('ALTER TABLE live_weather_record ALTER humidity SET NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ALTER pressure SET NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ALTER temperature SET NOT NULL');
        $this->addSql('ALTER TABLE live_weather_record ALTER wind_gusts SET NOT NULL');
    }
}
