<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer\Undo;

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Callback(target="list.operations.jumpToParent.button", table="tl_undo")
 *
 * @internal
 */
class JumpToParentButtonListener
{
    use UndoListenerTrait;

    public function __construct(ContaoFramework $framework, Connection $connection, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->translator = $translator;
    }

    public function __invoke(array $row, ?string $href = '', string $label = '', string $title = '', string $icon = '', string $attributes = ''): string
    {
        $table = $row['fromTable'];
        $originalRow = StringUtil::deserialize($row['data'])[$table][0];
        $parent = $this->getParentTableForRow($table, $originalRow);
        $image = $this->framework->getAdapter(Image::class);

        if (!$parent || !$this->checkIfParentExists($parent)) {
            return $image->getHtml('parent_.svg', $label).' ';
        }

        $newTitle = sprintf(
            $this->translator->trans('tl_undo.parent_modal', [], 'contao_tl_undo'),
            $table,
            $originalRow['id']
        );

        $backend = $this->framework->getAdapter(Backend::class);

        return sprintf(
            '<a href="%s" title="%s" onclick="Backend.openModalIframe({\'title\':\'%s\',\'url\': this.href });return false">%s</a> ',
            $backend->addToUrl($this->getParentLinkParameters($parent, $table)),
            StringUtil::specialchars($newTitle),
            StringUtil::specialchars($newTitle),
            $image->getHtml($icon, $label)
        );
    }

    private function getParentLinkParameters(array $parent, string $table): string
    {
        $params = [];

        if (empty($parent)) {
            return '';
        }

        $controller = $this->framework->getAdapter(Controller::class);
        $controller->loadDataContainer($parent['table']);

        $module = $this->getModuleForTable($parent['table']);

        if (!$module) {
            return '';
        }

        $params['do'] = $module['_module_name'];

        if (DataContainer::MODE_TREE === $GLOBALS['TL_DCA'][$parent['table']]['list']['sorting']['mode']) {
            // Limit tree to right parent node
            $params['pn'] = $parent['id'];
        } elseif ($module['tables'][0] !== $table) {
            // If $table is the main table of a module, we just go to do=$module,
            // else we append the right table and ID
            $params['table'] = $table;
            $params['id'] = $parent['id'];
        }

        return http_build_query($params, '', '&amp;', PHP_QUERY_RFC3986);
    }

    private function getModuleForTable(string $table): array
    {
        $module = null;

        foreach ($GLOBALS['BE_MOD'] as $group) {
            foreach ($group as $name => $config) {
                if (\is_array($config['tables'] ?? null) && \in_array($table, $config['tables'], true)) {
                    $module = $config;
                    $module['_module_name'] = $name;
                }
            }
        }

        return $module;
    }
}
