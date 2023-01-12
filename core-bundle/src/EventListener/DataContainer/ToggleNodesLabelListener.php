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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

class ToggleNodesLabelListener
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(string $table): void
    {
        if (
            !isset($GLOBALS['TL_DCA'][$table]['list']['global_operations']['toggleNodes'])
            || isset($GLOBALS['TL_DCA'][$table]['list']['global_operations']['toggleNodes']['label'])
            || (
                'ptg=all' !== ($GLOBALS['TL_DCA'][$table]['list']['global_operations']['toggleNodes']['href'] ?? null)
                && 'tg=all' !== ($GLOBALS['TL_DCA'][$table]['list']['global_operations']['toggleNodes']['href'] ?? null)
            )
            || null === ($session = $this->requestStack->getSession())
        ) {
            return;
        }

        /** @var AttributeBagInterface $sessionBag */
        $sessionBag = $session->getBag('contao_backend');
        $session = $sessionBag->all();

        $node = $table.'_tree';

        if (6 === (int) ($GLOBALS['TL_DCA'][$table]['list']['sorting']['mode'] ?? 0)) {
            $node = $table.'_'.($GLOBALS['TL_DCA'][$table]['config']['ptable'] ?? '').'_tree';
        }

        if (empty($session[$node]) || !\is_array($session[$node]) || 1 !== (int) current($session[$node])) {
            $GLOBALS['TL_DCA'][$table]['list']['global_operations']['toggleNodes']['label'] = &$GLOBALS['TL_LANG']['DCA']['expandNodes'];
        } else {
            $GLOBALS['TL_DCA'][$table]['list']['global_operations']['toggleNodes']['label'] = &$GLOBALS['TL_LANG']['DCA']['collapseNodes'];
        }
    }
}
