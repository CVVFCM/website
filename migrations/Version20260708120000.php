<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forgie image uploads (attached by visitors, forwarded to admins, purged on schedule)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forgie_upload (id UUID NOT NULL, conversation_id UUID NOT NULL, data TEXT NOT NULL, mime_type VARCHAR(100) NOT NULL, filename VARCHAR(255) NOT NULL, size INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_forgie_upload_created_at ON forgie_upload (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE forgie_upload');
    }
}
