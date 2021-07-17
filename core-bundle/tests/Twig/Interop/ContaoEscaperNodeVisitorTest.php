<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Interop;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Extension\ContaoExtension;
use Contao\CoreBundle\Twig\Inheritance\TemplateHierarchyInterface;
use Contao\CoreBundle\Twig\Interop\ContaoEscaperNodeVisitor;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

class ContaoEscaperNodeVisitorTest extends TestCase
{
    public function testPriority(): void
    {
        $visitor = new ContaoEscaperNodeVisitor(static function () { return []; });

        $this->assertSame(1, $visitor->getPriority());
    }

    public function testEscapesEntities(): void
    {
        $templateContent = '<h1>{{ headline }}</h1><p>{{ content|raw }}</p>';

        $output = $this->getEnvironment($templateContent)->render(
            'modern.html.twig',
            [
                'headline' => '&amp; is the HTML entity for &',
                'content' => 'This is <i>raw HTML</i>.',
            ]
        );

        $this->assertSame('<h1>&amp;amp; is the HTML entity for &amp;</h1><p>This is <i>raw HTML</i>.</p>', $output);
    }

    public function testDoesNotDoubleEncode(): void
    {
        $templateContent = '<h1>{{ headline }}</h1><p>{{ content|raw }}</p>';

        $output = $this->getEnvironment($templateContent)->render(
            'legacy.html.twig',
            [
                'headline' => '&amp; will look like &',
                'content' => 'This is <i>raw HTML</i>.',
            ]
        );

        $this->assertSame('<h1>&amp; will look like &amp;</h1><p>This is <i>raw HTML</i>.</p>', $output);
    }

    public function testHandlesFiltersAndFunctions(): void
    {
        $templateContent = '{{ heart() }} {{ target|trim }}';

        $environment = $this->getEnvironment($templateContent);
        $environment->addFunction(
            new TwigFunction(
                'heart',
                static function () {
                    return '&#9829;';
                }
            )
        );

        $output = $environment->render(
            'legacy.html.twig',
            [
                'target' => ' Twig &amp; Contao ',
            ]
        );

        $this->assertSame('&#9829; Twig &amp; Contao', $output);
    }

    public function testUppercaseEntities(): void
    {
        $templateContent = '{{ content|upper }}';

        $output = $this->getEnvironment($templateContent)->render(
            'legacy.html.twig',
            [
                'content' => '&quot;a&quot; &amp; &lt;b&gt;',
            ]
        );

        $this->assertSame('&quot;A&quot; &amp; &lt;B&gt;', $output);
    }

    public function testHtmlAttrFilter(): void
    {
        $templateContent = '<span title={{ title|e(\'html_attr\') }}></span>';

        $output = $this->getEnvironment($templateContent)->render(
            'legacy.html.twig',
            [
                'title' => '{{flavor}} _is_ a flavor',
            ]
        );

        $this->assertSame('<span title=vanilla&#x20;_is_&#x20;a&#x20;flavor></span>', $output);
    }

    private function getEnvironment(string $templateContent): Environment
    {
        $loader = new ArrayLoader([
            'modern.html.twig' => $templateContent,
            'legacy.html.twig' => $templateContent,
        ]);

        $controller = $this->createMock(Controller::class);
        $controller
            ->method('replaceInsertTags')
            ->willReturnCallback(
                static function ($string) {
                    return str_replace('{{flavor}}', 'vanilla', $string);
                }
            )
        ;

        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->with(Controller::class)
            ->willReturn($controller)
        ;

        $environment = new Environment($loader);

        $contaoExtension = new ContaoExtension($environment, $this->createMock(TemplateHierarchyInterface::class), $framework);
        $contaoExtension->addContaoEscaperRule('/legacy\.html\.twig/');

        $environment->addExtension($contaoExtension);

        return $environment;
    }
}
