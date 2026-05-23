<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522235619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_CAC89EACA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_CAC89EACA76ED395 ON accounts (user_id)');
        $this->addSql('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE goal (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, target_amount NUMERIC(10, 2) NOT NULL, current_amount NUMERIC(10, 2) NOT NULL, target_date DATE NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_FCDCEB2EA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FCDCEB2EA76ED395 ON goal (user_id)');
        $this->addSql('CREATE TABLE investment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_43CA0AD6A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_43CA0AD6A76ED395 ON investment (user_id)');
        $this->addSql('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, date DATE NOT NULL, account_id INTEGER NOT NULL, category_id INTEGER NOT NULL, CONSTRAINT FK_723705D19B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_723705D112469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_723705D19B6B5FBA ON "transaction" (account_id)');
        $this->addSql('CREATE INDEX IDX_723705D112469DE2 ON "transaction" (category_id)');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(180) NOT NULL, last_name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, phone_number VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "users" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_PHONE_NUMBER ON "users" (phone_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE goal');
        $this->addSql('DROP TABLE investment');
        $this->addSql('DROP TABLE "transaction"');
        $this->addSql('DROP TABLE "users"');
    }
}
