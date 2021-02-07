<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210113094215 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crewcall_function DROP function_type');
        $this->addSql('ALTER TABLE crewcall_person ADD last_login TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE crewcall_person DROP roles');
        $this->addSql('ALTER TABLE crewcall_person ALTER is_verified DROP DEFAULT');
        $this->addSql('ALTER TABLE crewcall_person ALTER system_roles SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE crewcall_function ADD function_type VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE crewcall_person ADD roles TEXT NOT NULL');
        $this->addSql('ALTER TABLE crewcall_person DROP last_login');
        $this->addSql('ALTER TABLE crewcall_person ALTER is_verified SET DEFAULT \'true\'');
        $this->addSql('COMMENT ON COLUMN crewcall_person.roles IS \'(DC2Type:array)\'');
    }
}
