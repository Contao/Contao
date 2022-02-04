<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\System;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class RecordPreviewListener
{
    private ContaoFramework $framework;
    private Connection $connection;

    public function __construct(ContaoFramework $framework, Connection $connection)
    {
        $this->framework = $framework;
        $this->connection = $connection;
    }

    /**
     * @Hook("loadDataContainer")
     */
    public function registerDeleteCallbacks(string $table): void
    {
        if ($GLOBALS['TL_DCA'][$table]['config']['notDeletable'] ?? false) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['config']['ondelete_callback'][] = [
            'contao.listener.data_container.record_preview', 'storePrecompiledRecordPreview',
        ];
    }

    public function storePrecompiledRecordPreview(DataContainer $dc, string $undoId): void
    {
        try {
            $row = $this->connection
                ->executeQuery('SELECT * FROM '.$this->connection->quoteIdentifier($dc->table).' WHERE id = ?', [$dc->id])
                ->fetchAssociative()
            ;

            $preview = $this->compilePreview($dc, $row);
        } catch (\Exception $exception) {
            $preview = '';
        }

        $this->connection->update('tl_undo', ['preview' => $preview], ['id' => $undoId]);
    }

    private function compilePreview(DataContainer $dc, array $row): string
    {
        if (DataContainer::MODE_PARENT === ($GLOBALS['TL_DCA'][$dc->table]['list']['sorting']['mode'] ?? null)) {
            $callback = $GLOBALS['TL_DCA'][$dc->table]['list']['sorting']['child_record_callback'] ?? null;

            if (\is_array($callback)) {
                $system = $this->framework->getAdapter(System::class);

                return $system->importStatic($callback[0])->{$callback[1]}($row);
            }

            if (\is_callable($callback)) {
                return $callback($row);
            }
        }

        if ($GLOBALS['TL_DCA'][$dc->table]['list']['label']['showColumns'] ?? false) {
            return serialize($dc->generateRecordLabel($row, $dc->table));
        }

        return $dc->generateRecordLabel($row, $dc->table);
    }
}
