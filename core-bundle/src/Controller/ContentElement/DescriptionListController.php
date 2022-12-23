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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(category: 'texts')]
class DescriptionListController extends AbstractContentElementController
{
    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $descriptions = [];
        $data = StringUtil::deserialize($model->data);

        if (!empty($data) && \is_array($data)) {
            foreach ($data as $row) {
                $descriptions[] = [
                    'term' => $row['key'],
                    'details' => $row['value'],
                ];
            }
        }

        $template->set('descriptions', $descriptions);

        return $template->getResponse();
    }
}
