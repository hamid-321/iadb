<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251102222207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE album (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, added_by_id INTEGER DEFAULT NULL, title VARCHAR(100) NOT NULL, artist VARCHAR(100) NOT NULL, genre VARCHAR(30) NOT NULL, track_list CLOB NOT NULL, cover VARCHAR(255) DEFAULT NULL, average_rating DOUBLE PRECISION DEFAULT NULL, CONSTRAINT FK_39986E4355B127A4 FOREIGN KEY (added_by_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_39986E4355B127A4 ON album (added_by_id)');
        $this->addSql('CREATE TABLE review (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reviewer_id INTEGER NOT NULL, album_id INTEGER NOT NULL, review_text CLOB DEFAULT NULL, rating INTEGER NOT NULL, timestamp DATETIME NOT NULL, CONSTRAINT FK_794381C670574616 FOREIGN KEY (reviewer_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_794381C61137ABCF FOREIGN KEY (album_id) REFERENCES album (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_794381C670574616 ON review (reviewer_id)');
        $this->addSql('CREATE INDEX IDX_794381C61137ABCF ON review (album_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(100) NOT NULL, role CLOB NOT NULL --(DC2Type:array)
        , password VARCHAR(100) NOT NULL, profile_picture VARCHAR(255) DEFAULT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE album');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE user');
    }
}
