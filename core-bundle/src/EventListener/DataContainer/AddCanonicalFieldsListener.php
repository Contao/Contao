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

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\PageModel;

/**
 * @Callback(table="tl_page", target="config.onload")
 */
class AddCanonicalFieldsListener
{
    private ContaoFramework $contaoFramework;

    public function __construct(ContaoFramework $contaoFramework)
    {
        $this->contaoFramework = $contaoFramework;
    }

    public function __invoke(DataContainer $dc): void
    {
        /** @var PageModel $pageModel */
        $pageModel = $this->contaoFramework->getAdapter(PageModel::class);
        $page = $pageModel->findWithDetails($dc->id);

        if ('regular' !== $page->type || !$page->enableCanonical) {
            return;
        }

        PaletteManipulator::create()
            ->addField('canonicalKeepParams', 'serpPreview')
            ->addField('canonicalLink', 'serpPreview')
            ->applyToPalette('regular', 'tl_page')
        ;
    }
}
