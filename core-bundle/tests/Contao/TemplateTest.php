<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Contao;

use Contao\BackendTemplate;
use Contao\CoreBundle\Image\Studio\FigureRenderer;
use Contao\CoreBundle\Tests\TestCase;
use Contao\FrontendTemplate;
use Contao\System;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarDumper\VarDumper;
use Webmozart\PathUtil\Path;

class TemplateTest extends TestCase
{
    use ExpectDeprecationTrait;

    protected function setUp(): void
    {
        parent::setUp();

        (new Filesystem())->mkdir(Path::join($this->getTempDir(), 'templates'));

        System::setContainer($this->getContainerWithContaoConfiguration($this->getTempDir()));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove(Path::join($this->getTempDir(), 'templates'));
    }

    public function testReplacesTheVariables(): void
    {
        (new Filesystem())->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_template.html5'),
            '<?= $this->value ?>'
        );

        $template = new BackendTemplate('test_template');
        $template->setData(['value' => 'test']);

        $obLevel = ob_get_level();
        $this->assertSame('test', $template->parse());
        $this->assertSame($obLevel, ob_get_level());
    }

    public function testHandlesExceptions(): void
    {
        (new Filesystem())->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_template.html5'),
            'test<?php throw new Exception ?>'
        );

        $template = new BackendTemplate('test_template');
        $obLevel = ob_get_level();

        ob_start();

        try {
            $template->parse();
            $this->fail('Parse should throw an exception');
        } catch (\Exception $e) {
            // Ignore
        }

        $this->assertSame('', ob_get_clean());
        $this->assertSame($obLevel, ob_get_level());
    }

    public function testHandlesExceptionsInsideBlocks(): void
    {
        (new Filesystem())->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_template.html5'),
            <<<'EOF'
                <?php
                    echo 'test1';
                    $this->block('a');
                    echo 'test2';
                    $this->block('b');
                    echo 'test3';
                    $this->block('c');
                    echo 'test4';
                    throw new Exception;
                EOF
        );

        $template = new BackendTemplate('test_template');
        $obLevel = ob_get_level();

        ob_start();

        try {
            $template->parse();
            $this->fail('Parse should throw an exception');
        } catch (\Exception $e) {
            // Ignore
        }

        $this->assertSame('', ob_get_clean());
        $this->assertSame($obLevel, ob_get_level());
    }

    public function testHandlesExceptionsInParentTemplate(): void
    {
        $filesystem = new Filesystem();

        $filesystem->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_parent.html5'),
            <<<'EOF'
                <?php
                    echo 'test1';
                    $this->block('a');
                    echo 'test2';
                    $this->endblock();
                    $this->block('b');
                    echo 'test3';
                    $this->endblock();
                    $this->block('c');
                    echo 'test4';
                    $this->block('d');
                    echo 'test5';
                    $this->block('e');
                    echo 'test6';
                    throw new Exception;
                EOF
        );

        $filesystem->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_template.html5'),
            <<<'EOF'
                <?php
                    echo 'test1';
                    $this->extend('test_parent');
                    echo 'test2';
                    $this->block('a');
                    echo 'test3';
                    $this->parent();
                    echo 'test4';
                    $this->endblock('a');
                    echo 'test5';
                    $this->block('b');
                    echo 'test6';
                    $this->endblock('b');
                    echo 'test7';
                EOF
        );

        $template = new BackendTemplate('test_template');
        $obLevel = ob_get_level();

        ob_start();

        try {
            $template->parse();
            $this->fail('Parse should throw an exception');
        } catch (\Exception $e) {
            // Ignore
        }

        $this->assertSame('', ob_get_clean());
        $this->assertSame($obLevel, ob_get_level());
    }

    public function testParsesNestedBlocks(): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile(Path::join($this->getTempDir(), 'templates/test_parent.html5'), '');

        $filesystem->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_template.html5'),
            <<<'EOF'
                <?php
                    echo 'test1';
                    $this->extend('test_parent');
                    echo 'test2';
                    $this->block('a');
                    echo 'test3';
                    $this->block('b');
                    echo 'test4';
                    $this->endblock('b');
                    echo 'test5';
                    $this->endblock('a');
                    echo 'test6';
                EOF
        );

        $template = new BackendTemplate('test_template');
        $obLevel = ob_get_level();

        ob_start();

        try {
            $template->parse();
            $this->fail('Parse should throw an exception');
        } catch (\Exception $e) {
            // Ignore
        }

        $this->assertSame('', ob_get_clean());
        $this->assertSame($obLevel, ob_get_level());
    }

    public function testLoadsTheAssetsPackages(): void
    {
        $packages = $this->createMock(Packages::class);
        $packages
            ->expects($this->once())
            ->method('getUrl')
            ->with('/path/to/asset', 'package_name')
            ->willReturnArgument(0)
        ;

        $container = $this->getContainerWithContaoConfiguration();
        $container->set('assets.packages', $packages);

        System::setContainer($container);

        $template = new FrontendTemplate();
        $template->asset('/path/to/asset', 'package_name');
    }

    public function testCanDumpTemplateVars(): void
    {
        $template = new FrontendTemplate();
        $template->setData(['test' => 1]);

        $dump = null;

        VarDumper::setHandler(
            static function ($var) use (&$dump): void {
                $dump = $var;
            }
        );

        $template->dumpTemplateVars();

        $this->assertSame(['test' => 1], $dump);
    }

    /**
     * @group legacy
     */
    public function testShowsDebugComments(): void
    {
        (new Filesystem())->dumpFile(
            Path::join($this->getTempDir(), 'templates/test_template.html5'),
            '<?= $this->value ?>'
        );

        $template = new BackendTemplate('test_template');
        $template->setData(['value' => 'test']);

        $sourceWithComments = "\n<!-- TEMPLATE START: templates/test_template.html5 -->\n"
            .'test'
            ."\n<!-- TEMPLATE END: templates/test_template.html5 -->\n";

        $this->assertSame('test', $template->parse());
        $this->assertSame($sourceWithComments, $template->setDebug(true)->parse());

        $this->assertSame('test', $template->setDebug(false)->parse());
        $this->assertSame('test', $template->setDebug(null)->parse());

        System::getContainer()->setParameter('kernel.debug', true);
        $GLOBALS['TL_CONFIG']['debugMode'] = true;

        $this->assertSame($sourceWithComments, $template->parse());
        $this->assertSame('test', $template->setDebug(false)->parse());

        $GLOBALS['TL_CONFIG']['debugMode'] = false;

        $this->expectDeprecation('%sTL_CONFIG.debugMode%s');

        $this->assertSame('test', $template->setDebug(null)->parse());
    }

    public function testFigureFunction(): void
    {
        $figureRenderer = $this->createMock(FigureRenderer::class);
        $figureRenderer
            ->expects($this->once())
            ->method('render')
            ->with('123', '_my_size', ['foo' => 'bar'], 'my_template')
            ->willReturn('<result>')
        ;

        $container = $this->getContainerWithContaoConfiguration($this->getFixturesDir());
        $container->set(FigureRenderer::class, $figureRenderer);

        System::setContainer($container);

        $this->assertSame('<result>', (new FrontendTemplate())->figure('123', '_my_size', ['foo' => 'bar'], 'my_template'));
    }

    public function testFigureFunctionUsesImageTemplateByDefault(): void
    {
        $figureRenderer = $this->createMock(FigureRenderer::class);
        $figureRenderer
            ->expects($this->once())
            ->method('render')
            ->with(1, null, [], 'image')
            ->willReturn('<result>')
        ;

        $container = $this->getContainerWithContaoConfiguration($this->getFixturesDir());
        $container->set(FigureRenderer::class, $figureRenderer);

        System::setContainer($container);

        (new FrontendTemplate())->figure(1, null);
    }

    /**
     * @dataProvider provideBuffer
     */
    public function testCompileReplacesLiteralInsertTags(string $buffer, string $expectedOutput): void
    {
        $page = new \stdClass();
        $page->minifyMarkup = false;

        $GLOBALS['objPage'] = $page;
        $GLOBALS['TL_KEYWORDS'] = '';

        $template = new class($buffer) extends FrontendTemplate {
            /**
             * @var string
             */
            private $testBuffer;

            public function __construct(string $testBuffer)
            {
                $this->testBuffer = $testBuffer;

                parent::__construct();
            }

            public function parse(): string
            {
                return $this->testBuffer;
            }

            public function testCompile(): string
            {
                $this->compile();

                return $this->strBuffer;
            }

            public static function replaceInsertTags($strBuffer, $blnCache = true)
            {
                return $strBuffer; // ignore insert tags
            }

            public static function replaceDynamicScriptTags($strBuffer)
            {
                return $strBuffer; // ignore dynamic script tags
            }
        };

        $this->assertSame($expectedOutput, $template->testCompile());

        unset($GLOBALS['objPage'],$GLOBALS['TL_KEYWORDS']);
    }

    public function provideBuffer(): \Generator
    {
        yield 'plain string' => [
            'foo bar',
            'foo bar',
        ];

        yield 'literal insert tags are replaced' => [
            'foo[{]bar[{]baz[}]',
            'foo&#123;&#123;bar&#123;&#123;baz&#125;&#125;',
        ];

        yield 'literal insert tags inside script tag are not replaced' => [
            '<script type="application/javascript">if (/[\[{]$/.test(foo)) {}</script>',
            '<script type="application/javascript">if (/[\[{]$/.test(foo)) {}</script>',
        ];

        yield 'multiple occurrences' => [
            '[{][}]<script>[{][}]</script>[{][}]<script>[{][}]</script>[{][}]',
            '&#123;&#123;&#125;&#125;<script>[{][}]</script>&#123;&#123;&#125;&#125;<script>[{][}]</script>&#123;&#123;&#125;&#125;',
        ];
    }
}
