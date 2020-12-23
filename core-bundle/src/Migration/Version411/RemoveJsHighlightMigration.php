<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Migration\Version411;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class RemoveJsHighlightMigration extends AbstractMigration
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

        if (!$schemaManager->tablesExist(['tl_layout'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_layout');

        if (!isset($columns['scripts'])) {
            return false;
        }

        $layout = $this->connection->fetchAssociative("
            SELECT
                COUNT(*) AS cnt
            FROM
                tl_layout
            WHERE
                scripts LIKE '%js_highlight%'
        ");

        return $layout['cnt'] > 0;
    }

    public function run(): MigrationResult
    {
        $layouts = $this->connection->fetchAllAssociative("
            SELECT
                id, scripts
            FROM
                tl_layout
            WHERE
                scripts LIKE '%js_highlight%'
        ");

        foreach ($layouts as $layout) {
            $scripts = StringUtil::deserialize($layout['scripts']);

            if (!empty($scripts) && \is_array($scripts)) {
                $key = array_search('js_highlight', $scripts, true);

                if (false !== $key) {
                    unset($scripts[$key]);

                    $stmt = $this->connection->prepare('
                        UPDATE
                            tl_layout
                        SET
                            scripts = :scripts
                        WHERE
                            id = :id
                    ');

                    $stmt->execute([':scripts' => serialize(array_values($scripts)), ':id' => $layout['id']]);
                }
            }
        }

        return $this->createResult(true);
    }
}
