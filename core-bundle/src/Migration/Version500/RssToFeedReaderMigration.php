<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Migration\Version500;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class RssToFeedReaderMigration extends AbstractMigration
{
    public function __construct(private Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('tl_module');

        if (!$schemaManager->tablesExist(['tl_news_feed'])) {
            return false;
        }

        return \array_key_exists('rss_feed', $columns) && \array_key_exists('rss_cache', $columns)
            && !\array_key_exists('feed_urls', $columns) && !\array_key_exists('feed_cache', $columns);
    }

    public function run(): MigrationResult
    {
        $this->renameField('rss_feed', 'feed_urls', 'text NULL');
        $this->renameField('rss_cache', 'feed_cache', 'int(10) unsigned NOT NULL default 3600');

        $this->connection->update('tl_module', ['type' => 'feed_reader'], ['type' => 'rssReader']);

        return $this->createResult(true);
    }

    private function renameField(string $from, string $to, string $type): void
    {
        $tableQuoted = $this->connection->quoteIdentifier('tl_module');
        $fromQuoted = $this->connection->quoteIdentifier($from);
        $toQuoted = $this->connection->quoteIdentifier($to);

        $this->connection->executeQuery("ALTER TABLE $tableQuoted CHANGE $fromQuoted $toQuoted $type");
    }
}
