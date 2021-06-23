<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Migration\Version412;

use Contao\CoreBundle\Entity\Migration as MigrationEntity;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\CoreBundle\ServiceAnnotation\Migration;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * This migration creates or updates the tl_migration table and is tagged with a high priority.
 * 
 * @internal
 */
class CreateMigrationTableMigration extends AbstractMigration
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(EntityManagerInterface $entityManager, Connection $connection)
    {
        $this->entityManager = $entityManager;
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $updateSchemaSql = $this->getUpdateSchema();

        // We need to update tl_migration in shouldRun, because AbstractRecordedMigration relies on it
        foreach ($updateSchemaSql as $sql) {
            $this->connection->executeQuery($sql);
        }

        return !empty($updateSchemaSql);
    }

    public function run(): MigrationResult
    {
        return $this->createResult(true);
    }

    private function getUpdateSchema(): array
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $updateSchemaSql = array_filter($schemaTool->getUpdateSchemaSql([$this->entityManager->getClassMetadata(MigrationEntity::class)]), function(string $sql) {
            return false !== strpos($sql, ' tl_migration ');
        });

        return $updateSchemaSql;
    }
}
