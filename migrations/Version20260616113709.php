<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616113709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users ADD COLUMN period_start_day INTEGER DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, first_name, last_name, email, phone_number, roles, password FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(180) NOT NULL, last_name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, phone_number VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO "users" (id, first_name, last_name, email, phone_number, roles, password) SELECT id, first_name, last_name, email, phone_number, roles, password FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "users" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_PHONE_NUMBER ON "users" (phone_number)');
    }
}
