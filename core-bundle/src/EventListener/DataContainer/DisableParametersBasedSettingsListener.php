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

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Image;
use Contao\StringUtil;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class DisableParametersBasedSettingsListener implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    public function __construct(TranslatorInterface $translator, ContaoFrameworkInterface $framework)
    {
        $this->translator = $translator;
        $this->framework = $framework;
    }

    public function onLoadCallback(): void
    {
        if (!$this->container->hasParameter('contao.localconfig')) {
            return;
        }

        $localConfig = $this->container->getParameter('contao.localconfig');

        foreach (array_keys($localConfig) as $field) {
            if (!isset($GLOBALS['TL_DCA']['tl_settings']['fields'][$field])) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_settings']['fields'][$field]['eval']['disabled'] = true;
            $GLOBALS['TL_DCA']['tl_settings']['fields'][$field]['eval']['helpwizard'] = false;
            $GLOBALS['TL_DCA']['tl_settings']['fields'][$field]['xlabel'][] = [self::class, 'renderHelpIcon'];
        }
    }

    public function renderHelpIcon(): string
    {
        $adapter = $this->framework->getAdapter(Image::class);

        return $adapter->getHtml(
            'important.svg',
            $this->translator->trans('tl_settings.parameterBasedSetting.0', [], 'contao_tl_settings'),
            sprintf(
                'title="%s"',
                StringUtil::specialchars(
                    $this->translator->trans('tl_settings.parameterBasedSetting.1', [], 'contao_tl_settings')
                )
            )
        );
    }
}
