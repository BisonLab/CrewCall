<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210112122830 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE crewcall_reset_password_request_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE crewcall_reset_password_request (id INT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashedToken VARCHAR(100) NOT NULL, requestedAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expiresAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_30081095A76ED395 ON crewcall_reset_password_request (user_id)');
        $this->addSql('COMMENT ON COLUMN crewcall_reset_password_request.requestedAt IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN crewcall_reset_password_request.expiresAt IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE crewcall_reset_password_request ADD CONSTRAINT FK_30081095A76ED395 FOREIGN KEY (user_id) REFERENCES crewcall_person (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX uniq_561ceb1bc05fb297');
        $this->addSql('DROP INDEX uniq_561ceb1b92fc23a8');
        $this->addSql('DROP INDEX uniq_561ceb1ba0d96fbf');
        $this->addSql('ALTER TABLE crewcall_person ADD is_verified BOOLEAN DEFAULT \'true\' NOT NULL');
        $this->addSql('ALTER TABLE crewcall_person DROP username_canonical');
        $this->addSql('ALTER TABLE crewcall_person DROP email_canonical');
        $this->addSql('ALTER TABLE crewcall_person DROP salt');
        $this->addSql('ALTER TABLE crewcall_person DROP last_login');
        $this->addSql('ALTER TABLE crewcall_person DROP confirmation_token');
        $this->addSql('ALTER TABLE crewcall_person DROP password_requested_at');

        $this->addSql('ALTER TABLE crewcall_person ADD system_roles JSON');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_561CEB1BF85E0677 ON crewcall_person (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_561CEB1BE7927C74 ON crewcall_person (email)');
    }

    public function postUp(Schema $schema) : void
    {
        // TODO: Convert roles to system_roles and remove roles.
        $qb = $this->connection->createQueryBuilder();

        $qb->select('p.id, p.roles')
            ->from('crewcall_person' , 'p');
        $people = $qb->execute();

        foreach ($people as $person) {
            $roles = unserialize($person['roles']);
print_r($roles);
            $json = json_encode($roles);
print($json);
            $uq = $this->connection->createQueryBuilder();
            $uq->update('crewcall_person', 'p')
                    ->set('system_roles', "'".$json."'")
                    ->where('id = :pid')
                    ->setParameter('pid', $person['id']);
            $uq->execute();
        }
        $this->addSql('ALTER TABLE crewcall_person DROP roles');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE crewcall_reset_password_request_id_seq CASCADE');
        $this->addSql('DROP TABLE crewcall_reset_password_request');
        $this->addSql('DROP INDEX UNIQ_561CEB1BF85E0677');
        $this->addSql('DROP INDEX UNIQ_561CEB1BE7927C74');
        $this->addSql('ALTER TABLE crewcall_person ADD username_canonical VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE crewcall_person ADD email_canonical VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE crewcall_person ADD salt VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE crewcall_person ADD last_login TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE crewcall_person ADD confirmation_token VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE crewcall_person ADD password_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE crewcall_person DROP is_verified');
        $this->addSql('ALTER TABLE crewcall_person ALTER system_roles TYPE TEXT');
        $this->addSql('ALTER TABLE crewcall_person ALTER system_roles DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_561ceb1bc05fb297 ON crewcall_person (confirmation_token)');
        $this->addSql('CREATE UNIQUE INDEX uniq_561ceb1b92fc23a8 ON crewcall_person (username_canonical)');
        $this->addSql('CREATE UNIQUE INDEX uniq_561ceb1ba0d96fbf ON crewcall_person (email_canonical)');
    }
}
