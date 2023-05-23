<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230523072401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reusable_packagings (id SERIAL NOT NULL, product_id INT DEFAULT NULL, reusable_packaging_id INT DEFAULT NULL, units INT NOT NULL, data JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8F963D044584665A ON reusable_packagings (product_id)');
        $this->addSql('CREATE INDEX IDX_8F963D04B26ADE57 ON reusable_packagings (reusable_packaging_id)');
        $this->addSql('COMMENT ON COLUMN reusable_packagings.data IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE reusable_packagings ADD CONSTRAINT FK_8F963D044584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reusable_packagings ADD CONSTRAINT FK_8F963D04B26ADE57 FOREIGN KEY (reusable_packaging_id) REFERENCES reusable_packaging (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT id AS product_id, reusable_packaging_id, reusable_packaging_unit FROM sylius_product WHERE reusable_packaging_id IS NOT NULL');
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $this->addSql('INSERT INTO reusable_packagings (product_id, reusable_packaging_id, unis) VALUES (:product_id, :reusable_packaging_id, :units)', $row);
        }

        $this->addSql('ALTER TABLE sylius_product DROP reusable_packaging_id');
        $this->addSql('ALTER TABLE sylius_product DROP reusable_packaging_unit');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reusable_packagings');
        $this->addSql('ALTER TABLE sylius_product ADD reusable_packaging_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product ADD reusable_packaging_unit DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT fk_677b9b74b26ade57 FOREIGN KEY (reusable_packaging_id) REFERENCES reusable_packaging (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_677b9b74b26ade57 ON sylius_product (reusable_packaging_id)');
    }
}
