<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260614104311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, balance NUMERIC(15, 2) DEFAULT NULL, type VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, user_id INTEGER NOT NULL, bank_connection_id INTEGER DEFAULT NULL, CONSTRAINT FK_CAC89EACA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_CAC89EAC87756E0A FOREIGN KEY (bank_connection_id) REFERENCES bank_connections (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_CAC89EACA76ED395 ON accounts (user_id)');
        $this->addSql('CREATE INDEX IDX_CAC89EAC87756E0A ON accounts (bank_connection_id)');
        $this->addSql('CREATE TABLE bank_connections (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, module VARCHAR(255) NOT NULL, credentials CLOB NOT NULL, label VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, last_error_message CLOB DEFAULT NULL, last_sync_date DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_9D7D4A6AA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9D7D4A6AA76ED395 ON bank_connections (user_id)');
        $this->addSql('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, budget_group VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE goals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, target_amount NUMERIC(10, 2) NOT NULL, current_amount NUMERIC(10, 2) NOT NULL, target_date DATE NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_C7241E2FA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C7241E2FA76ED395 ON goals (user_id)');
        $this->addSql('CREATE TABLE investments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, ticker VARCHAR(50) DEFAULT NULL, quantity NUMERIC(15, 6) NOT NULL, purchase_price NUMERIC(15, 6) NOT NULL, current_price NUMERIC(15, 6) DEFAULT NULL, purchase_date DATE NOT NULL, current_price_updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, account_id INTEGER DEFAULT NULL, CONSTRAINT FK_74FD72E0A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_74FD72E09B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_74FD72E0A76ED395 ON investments (user_id)');
        $this->addSql('CREATE INDEX IDX_74FD72E09B6B5FBA ON investments (account_id)');
        $this->addSql('CREATE TABLE investor_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, objective VARCHAR(20) DEFAULT NULL, horizon VARCHAR(20) DEFAULT NULL, reaction_to_loss VARCHAR(20) DEFAULT NULL, target_return VARCHAR(20) DEFAULT NULL, max_loss VARCHAR(20) DEFAULT NULL, emergency_fund VARCHAR(20) DEFAULT NULL, savings_rate VARCHAR(20) DEFAULT NULL, known_products CLOB DEFAULT NULL, investing_experience VARCHAR(20) DEFAULT NULL, professional_status VARCHAR(20) DEFAULT NULL, family_status VARCHAR(20) DEFAULT NULL, age_range VARCHAR(10) DEFAULT NULL, score INTEGER DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_BCBCFF3CA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BCBCFF3CA76ED395 ON investor_profiles (user_id)');
        $this->addSql('CREATE TABLE recurring_transaction (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, day_of_month INTEGER NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, account_id INTEGER DEFAULT NULL, category_id INTEGER DEFAULT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_D3509AA69B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D3509AA612469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D3509AA6A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D3509AA69B6B5FBA ON recurring_transaction (account_id)');
        $this->addSql('CREATE INDEX IDX_D3509AA612469DE2 ON recurring_transaction (category_id)');
        $this->addSql('CREATE INDEX IDX_D3509AA6A76ED395 ON recurring_transaction (user_id)');
        $this->addSql('CREATE TABLE "transactions" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, date DATE NOT NULL, external_id VARCHAR(255) DEFAULT NULL, account_id INTEGER NOT NULL, category_id INTEGER NOT NULL, recurring_transaction_id INTEGER DEFAULT NULL, CONSTRAINT FK_EAA81A4C9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EAA81A4C5B71D755 FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transaction (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C9B6B5FBA ON "transactions" (account_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C12469DE2 ON "transactions" (category_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C5B71D755 ON "transactions" (recurring_transaction_id)');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(180) NOT NULL, last_name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, phone_number VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "users" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_PHONE_NUMBER ON "users" (phone_number)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE bank_connections');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE goals');
        $this->addSql('DROP TABLE investments');
        $this->addSql('DROP TABLE investor_profiles');
        $this->addSql('DROP TABLE recurring_transaction');
        $this->addSql('DROP TABLE "transactions"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
