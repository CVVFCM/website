<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record signed wind gaps (vs forecast and vs ML model) on each live weather observation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE live_weather_record ADD wind_speed_gap_forecast DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE live_weather_record ADD wind_direction_gap_forecast DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE live_weather_record ADD wind_speed_gap_model DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE live_weather_record ADD wind_direction_gap_model DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE live_weather_record DROP wind_speed_gap_forecast');
        $this->addSql('ALTER TABLE live_weather_record DROP wind_direction_gap_forecast');
        $this->addSql('ALTER TABLE live_weather_record DROP wind_speed_gap_model');
        $this->addSql('ALTER TABLE live_weather_record DROP wind_direction_gap_model');
    }
}
