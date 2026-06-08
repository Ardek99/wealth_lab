<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608111224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE investor_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, objective VARCHAR(20) DEFAULT NULL, horizon VARCHAR(20) DEFAULT NULL, reaction_to_loss VARCHAR(20) DEFAULT NULL, target_return VARCHAR(20) DEFAULT NULL, max_loss VARCHAR(20) DEFAULT NULL, emergency_fund VARCHAR(20) DEFAULT NULL, savings_rate VARCHAR(20) DEFAULT NULL, known_products CLOB DEFAULT NULL, investing_experience VARCHAR(20) DEFAULT NULL, score INTEGER DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_BCBCFF3CA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BCBCFF3CA76ED395 ON investor_profiles (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE investor_profiles');
    }
}
