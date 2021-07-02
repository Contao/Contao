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

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class AllowedExcludedFieldsMigration extends AbstractMigration
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->getSchemaManager();

        if (!$schemaManager->tablesExist('tl_user_group')) {
            return false;
        }

        $groups = $this->connection->fetchAllAssociative("
            SELECT
                id, modules, filemounts, fop
            FROM
                tl_user_group
            WHERE
                alexf NOT LIKE '%tl_files::%'
        ");

        if (0 === count($groups)) {
            return false;
        }

        $shouldRun = false;

        foreach ($groups as $group) {
            if ($this->grantsEditPermission($group)) {
                $shouldRun = true;
            }
        }

        return $shouldRun;
    }

    public function run(): MigrationResult
    {
        $groups = $this->connection->fetchAllAssociative("
            SELECT
                id, modules, filemounts, fop, alexf
            FROM
                tl_user_group
            WHERE
                alexf NOT LIKE '%tl_files::%'
        ");

        foreach ($groups as $group) {
            if (!$this->grantsEditPermission($group)) {
                continue;
            }

            $alexf = array_merge(
                StringUtil::deserialize($group['alexf'], true),
                [
                    'tl_files::name',
                    'tl_files::protected',
                    'tl_files::syncExclude',
                    'tl_files::importantPartX',
                    'tl_files::importantPartY',
                    'tl_files::importantPartWidth',
                    'tl_files::importantPartHeight',
                    'tl_files::meta'
                ]
            );

            $this->connection
                ->prepare('UPDATE tl_user_group SET alexf = :alexf WHERE id = :id')
                ->executeStatement([
                    ':id' => $group['id'],
                    ':alexf' => serialize($alexf),
                ])
            ;
        }

        return $this->createResult(true);
    }

    private function grantsEditPermission(array $group): bool
    {
        $fop = StringUtil::deserialize($group['fop'], true);

        if (!\in_array('f2', $fop, true)) {
            return false;
        }

        $modules = StringUtil::deserialize($group['modules'], true);

        return \in_array('files', $modules, true) || !empty($group['filemounts']);
    }
}
