<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\InsertTag;

use Contao\CoreBundle\InsertTag\CommonMarkExtension;
use Contao\CoreBundle\Routing\UrlResolver;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use PHPUnit\Framework\TestCase;

class CommonMarkExtensionTest extends TestCase
{
    public function testReplacesInsertTags(): void
    {
        $urlResolver = $this->createMock(UrlResolver::class);
        $urlResolver
            ->expects($this->once())
            ->method('resolve')
            ->with('{{news_url::42}}', false)
            ->willReturn('https://contao.org/news-alias that-needs-encoding.html')
        ;

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new CommonMarkExtension($urlResolver));

        $parser = new MarkdownParser($environment);
        $renderer = new HtmlRenderer($environment);
        $document = $parser->parse('[My text for my link]({{news_url::42}})');

        $html = (string) $renderer->renderDocument($document);

        $this->assertSame('<p><a href="https://contao.org/news-alias%20that-needs-encoding.html">My text for my link</a></p>'."\n", $html);
    }
}
