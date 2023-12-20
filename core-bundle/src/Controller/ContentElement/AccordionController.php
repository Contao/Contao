<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Fragment\Reference\ContentElementReference;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(category: 'miscellaneous', nestedFragments: true)]
class AccordionController extends AbstractContentElementController
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $elements = [];

        /** @var ContentElementReference $reference */
        foreach ($template->get('nested_fragments') as $reference) {
            $model = $reference->getContentModel();

            if (!$model instanceof ContentModel) {
                $model = $this->framework->getAdapter(ContentModel::class)->findByPk($model);
            }

            $elements[] = [
                'header' => $model->sectionHeadline,
                'reference' => $reference,
            ];
        }

        $template->set('elements', $elements);

        return $template->getResponse();
    }
}
