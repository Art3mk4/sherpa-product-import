<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблиц products, product_attributes, product_images, import_jobs';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Миграция поддерживает только PostgreSQL',
        );

        $this->addSql('CREATE TABLE products (
            id            BIGSERIAL PRIMARY KEY,
            external_code VARCHAR(255) NOT NULL,
            name          VARCHAR(500) NOT NULL,
            description   TEXT NULL,
            price         NUMERIC(12,2) NOT NULL,
            discount      NUMERIC(7,2) NULL,
            created_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now(),
            updated_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_products_external_code ON products (external_code)');
        $this->addSql('CREATE INDEX idx_products_price ON products (price)');
        $this->addSql('CREATE INDEX idx_products_name_lower ON products (lower(name))');

        $this->addSql('CREATE TABLE product_attributes (
            id         BIGSERIAL PRIMARY KEY,
            product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            key        VARCHAR(255) NOT NULL,
            value      TEXT NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_attr_product_key ON product_attributes (product_id, key)');

        $this->addSql('CREATE TABLE product_images (
            id         BIGSERIAL PRIMARY KEY,
            product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            url        VARCHAR(2048) NOT NULL,
            path       VARCHAR(512) NULL,
            position   SMALLINT NOT NULL DEFAULT 0
        )');
        // url длинный — уникальность по хэшу от выражения; ON CONFLICT обязан совпадать с выражением индекса
        $this->addSql('CREATE UNIQUE INDEX uniq_img_product_url ON product_images (product_id, md5(url))');

        $this->addSql('CREATE TABLE import_jobs (
            id             UUID PRIMARY KEY,
            status         VARCHAR(20) NOT NULL,
            original_name  VARCHAR(255) NOT NULL,
            file_path      VARCHAR(512) NOT NULL,
            total_rows     INT NOT NULL DEFAULT 0,
            processed_rows INT NOT NULL DEFAULT 0,
            success_count  INT NOT NULL DEFAULT 0,
            error_count    INT NOT NULL DEFAULT 0,
            errors         JSONB NOT NULL DEFAULT \'[]\',
            warnings       JSONB NOT NULL DEFAULT \'[]\',
            message        TEXT NULL,
            created_at     TIMESTAMP(0) NOT NULL DEFAULT now(),
            started_at     TIMESTAMP(0) NULL,
            finished_at    TIMESTAMP(0) NULL
        )');
        $this->addSql('CREATE INDEX idx_import_jobs_created ON import_jobs (created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Миграция поддерживает только PostgreSQL',
        );

        $this->addSql('DROP TABLE product_images');
        $this->addSql('DROP TABLE product_attributes');
        $this->addSql('DROP TABLE import_jobs');
        $this->addSql('DROP TABLE products');
    }
}
