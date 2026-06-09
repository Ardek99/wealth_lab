<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260609085137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE investor_profiles ADD COLUMN professional_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE investor_profiles ADD COLUMN family_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE investor_profiles ADD COLUMN age_range VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__investor_profiles AS SELECT id, objective, horizon, reaction_to_loss, target_return, max_loss, emergency_fund, savings_rate, known_products, investing_experience, score, user_id FROM investor_profiles');
        $this->addSql('DROP TABLE investor_profiles');
        $this->addSql('CREATE TABLE investor_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, objective VARCHAR(20) DEFAULT NULL, horizon VARCHAR(20) DEFAULT NULL, reaction_to_loss VARCHAR(20) DEFAULT NULL, target_return VARCHAR(20) DEFAULT NULL, max_loss VARCHAR(20) DEFAULT NULL, emergency_fund VARCHAR(20) DEFAULT NULL, savings_rate VARCHAR(20) DEFAULT NULL, known_products CLOB DEFAULT NULL, investing_experience VARCHAR(20) DEFAULT NULL, score INTEGER DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_BCBCFF3CA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO investor_profiles (id, objective, horizon, reaction_to_loss, target_return, max_loss, emergency_fund, savings_rate, known_products, investing_experience, score, user_id) SELECT id, objective, horizon, reaction_to_loss, target_return, max_loss, emergency_fund, savings_rate, known_products, investing_experience, score, user_id FROM __temp__investor_profiles');
        $this->addSql('DROP TABLE __temp__investor_profiles');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BCBCFF3CA76ED395 ON investor_profiles (user_id)');
    }
}
