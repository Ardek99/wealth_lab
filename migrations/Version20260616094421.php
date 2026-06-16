<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616094421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__transactions AS SELECT id, name, type, amount, date, external_id, account_id, category_id, recurring_transaction_id FROM transactions');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, date DATE NOT NULL, external_id VARCHAR(255) DEFAULT NULL, account_id INTEGER NOT NULL, category_id INTEGER NOT NULL, recurring_transaction_id INTEGER DEFAULT NULL, CONSTRAINT FK_EAA81A4C9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C5B71D755 FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transaction (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO transactions (id, name, type, amount, date, external_id, account_id, category_id, recurring_transaction_id) SELECT id, name, type, amount, date, external_id, account_id, category_id, recurring_transaction_id FROM __temp__transactions');
        $this->addSql('DROP TABLE __temp__transactions');
        $this->addSql('CREATE INDEX IDX_EAA81A4C5B71D755 ON transactions (recurring_transaction_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C12469DE2 ON transactions (category_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C9B6B5FBA ON transactions (account_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_transaction_account_external ON transactions (account_id, external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__transactions AS SELECT id, name, type, amount, date, external_id, account_id, category_id, recurring_transaction_id FROM "transactions"');
        $this->addSql('DROP TABLE "transactions"');
        $this->addSql('CREATE TABLE "transactions" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, date DATE NOT NULL, external_id VARCHAR(255) DEFAULT NULL, account_id INTEGER NOT NULL, category_id INTEGER NOT NULL, recurring_transaction_id INTEGER DEFAULT NULL, CONSTRAINT FK_EAA81A4C9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C5B71D755 FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transaction (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "transactions" (id, name, type, amount, date, external_id, account_id, category_id, recurring_transaction_id) SELECT id, name, type, amount, date, external_id, account_id, category_id, recurring_transaction_id FROM __temp__transactions');
        $this->addSql('DROP TABLE __temp__transactions');
        $this->addSql('CREATE INDEX IDX_EAA81A4C9B6B5FBA ON "transactions" (account_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C12469DE2 ON "transactions" (category_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C5B71D755 ON "transactions" (recurring_transaction_id)');
    }
}
