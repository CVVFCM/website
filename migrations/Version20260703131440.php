<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703131440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forgie conversation history (ai-chat message store)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forgie_conversation (conversation_id UUID NOT NULL, messages JSON NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (conversation_id))');
        $this->addSql('CREATE INDEX idx_forgie_conversation_updated_at ON forgie_conversation (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE forgie_conversation');
    }
}
