<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the WMO weather code on forecast records so the homepage sky condition is served from the DB';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_forecast_record ADD weather_code INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weather_forecast_record DROP weather_code');
    }
}
