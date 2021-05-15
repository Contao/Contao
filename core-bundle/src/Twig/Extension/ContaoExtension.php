<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Twig\Extension;

use Contao\CoreBundle\Twig\Inheritance\DynamicExtendsTokenParser;
use Contao\CoreBundle\Twig\Inheritance\HierarchyProvider;
use Contao\CoreBundle\Twig\Interop\ContaoEscaper;
use Contao\CoreBundle\Twig\Interop\ContaoEscaperNodeVisitor;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\EscaperExtension;

class ContaoExtension extends AbstractExtension
{
    /**
     * @var HierarchyProvider
     */
    private $hierarchyProvider;

    /**
     * @var array
     */
    private $contaoEscaperFilterRules = [];

    public function __construct(Environment $environment, HierarchyProvider $hierarchyProvider)
    {
        /** @var EscaperExtension $escaperExtension */
        $escaperExtension = $environment->getExtension(EscaperExtension::class);

        $escaperExtension->setEscaper(
            'contao_html',
            [(new ContaoEscaper()), '__invoke']
        );

        $this->hierarchyProvider = $hierarchyProvider;

        // Use our escaper on all templates in the `@Contao` and `@Contao_*`
        // namespaces
        $this->addContaoEscaperRule('%^@Contao(_[a-zA-Z0-9_-]*)?/%');
    }

    /**
     * Add a contao escaper rule.
     *
     * If a template name matches any of the defined rules it will be processed
     * with the `contao_html` escaper strategy. Make sure your rule will only
     * match templates with input encoded contexts!
     */
    public function addContaoEscaperRule(string $regularExpression): void
    {
        if (\in_array($regularExpression, $this->contaoEscaperFilterRules, true)) {
            return;
        }

        $this->contaoEscaperFilterRules[] = $regularExpression;
    }

    public function getNodeVisitors(): array
    {
        return [
            // Enables the 'contao_twig' escaper for Contao templates with
            // input encoding
            new ContaoEscaperNodeVisitor(
                function () {
                    return $this->contaoEscaperFilterRules;
                }
            ),
        ];
    }

    public function getTokenParsers(): array
    {
        return [
            // Registers a parser for the 'extends' tag which will overwrite
            // the one of Twig's CoreExtension
            new DynamicExtendsTokenParser($this->hierarchyProvider),
        ];
    }
}
