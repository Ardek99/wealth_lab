<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260609201622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__investments AS SELECT id, name, type, user_id, ticker, quantity, purchase_price, current_price, purchase_date, current_price_updated_at FROM investments');
        $this->addSql('DROP TABLE investments');
        $this->addSql('CREATE TABLE investments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, user_id INTEGER NOT NULL, ticker VARCHAR(50) DEFAULT NULL, quantity NUMERIC(15, 6) NOT NULL, purchase_price NUMERIC(15, 6) NOT NULL, current_price NUMERIC(15, 6) DEFAULT NULL, purchase_date DATE NOT NULL, current_price_updated_at DATETIME DEFAULT NULL, account_id INTEGER DEFAULT NULL, CONSTRAINT FK_74FD72E0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_74FD72E09B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO investments (id, name, type, user_id, ticker, quantity, purchase_price, current_price, purchase_date, current_price_updated_at) SELECT id, name, type, user_id, ticker, quantity, purchase_price, current_price, purchase_date, current_price_updated_at FROM __temp__investments');
        $this->addSql('DROP TABLE __temp__investments');
        $this->addSql('CREATE INDEX IDX_74FD72E0A76ED395 ON investments (user_id)');
        $this->addSql('CREATE INDEX IDX_74FD72E09B6B5FBA ON investments (account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__investments AS SELECT id, name, type, ticker, quantity, purchase_price, current_price, purchase_date, current_price_updated_at, user_id FROM investments');
        $this->addSql('DROP TABLE investments');
        $this->addSql('CREATE TABLE investments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, ticker VARCHAR(50) DEFAULT NULL, quantity NUMERIC(15, 6) NOT NULL, purchase_price NUMERIC(15, 6) NOT NULL, current_price NUMERIC(15, 6) DEFAULT NULL, purchase_date DATE NOT NULL, current_price_updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_74FD72E0A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO investments (id, name, type, ticker, quantity, purchase_price, current_price, purchase_date, current_price_updated_at, user_id) SELECT id, name, type, ticker, quantity, purchase_price, current_price, purchase_date, current_price_updated_at, user_id FROM __temp__investments');
        $this->addSql('DROP TABLE __temp__investments');
        $this->addSql('CREATE INDEX IDX_74FD72E0A76ED395 ON investments (user_id)');
    }
}
