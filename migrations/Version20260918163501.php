<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918163501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the request_stats table used by GET /statistics.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE request_stats (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, request_hash BINARY(32) NOT NULL, `int1` INT UNSIGNED NOT NULL, `int2` INT UNSIGNED NOT NULL, limit_value INT UNSIGNED NOT NULL, str1 VARCHAR(64) NOT NULL, str2 VARCHAR(64) NOT NULL, hits INT UNSIGNED DEFAULT 1 NOT NULL, INDEX idx_request_stats_hits (hits), UNIQUE INDEX uniq_request_stats_hash (request_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE request_stats');
    }
}
