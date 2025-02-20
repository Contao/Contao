<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Loader;

use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\Exception\InvalidThemePathException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\PageFinder;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Loader\TemplateLocator;
use Contao\CoreBundle\Twig\Loader\ThemeNamespace;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Filesystem\Path;

class ContaoFilesystemLoaderTest extends TestCase
{
    public function testGetCacheKey(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithTemplates(
            [
                'foo.html.twig' => '/test/foo.html.twig',
            ],
        );

        $this->assertSame(
            'c:test/foo.html.twig',
            $loader->getCacheKey('@Contao/foo.html.twig'),
            'managed namespace',
        );

        $this->assertSame(
            'c:test/foo.html.twig',
            $loader->getCacheKey('@Contao_Test/foo.html.twig'),
            'specific namespace',
        );
    }

    public function testGetCacheKeyWithThemeTemplate(): void
    {
        $page = $this->mockClassWithProperties(PageModel::class);
        $page->templateGroup = 'templates/demo';

        $pageFinder = $this->createMock(PageFinder::class);
        $pageFinder
            ->method('getCurrentPage')
            ->willReturnOnConsecutiveCalls(null, $page)
        ;

        $loader = $this->getContaoFilesystemLoaderWithTemplates(
            [
                'foo.html.twig' => '/test/foo.html.twig',
            ],
            [
                'foo.html.twig' => '/theme/foo.html.twig',
            ],
            $pageFinder,
        );

        $this->assertSame(
            'c:test/foo.html.twig',
            $loader->getCacheKey('@Contao/foo.html.twig'),
        );

        $this->assertSame(
            'c:theme/foo.html.twig',
            $loader->getCacheKey('@Contao_Theme_demo/foo.html.twig'),
            'specific namespace is also available outside theme context',
        );

        // Reset and switch context
        $loader->reset();

        $this->assertSame(
            'c:theme/foo.html.twig',
            $loader->getCacheKey('@Contao/foo.html.twig'),
            'managed namespace',
        );

        $this->assertSame(
            'c:theme/foo.html.twig',
            $loader->getCacheKey('@Contao_Theme_demo/foo.html.twig'),
            'specific namespace',
        );
    }

    public function testGetSourceContext(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(null, [
            $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/paths/1'),
        ]);

        $source = $loader->getSourceContext('@Contao/1.html.twig');

        $this->assertSame('@Contao/1.html.twig', $source->getName());
        $this->assertSame(Path::join($projectDir, 'templates/1.html.twig'), Path::normalize($source->getPath()));
        $this->assertSame("foo\n", $source->getCode());
    }

    public function testGetSourceContextFromThemeTemplate(): void
    {
        $page = $this->mockClassWithProperties(PageModel::class);
        $page->templateGroup = 'templates/my/theme';

        $pageFinder = $this->createMock(PageFinder::class);
        $pageFinder
            ->method('getCurrentPage')
            ->willReturnOnConsecutiveCalls(null, $page)
        ;

        $loader = $this->getContaoFilesystemLoaderWithPaths(
            $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance'),
            themePaths: ['templates/my/theme'],
            pageFinder: $pageFinder,
        );

        $source = $loader->getSourceContext('@Contao/text.html.twig');

        $this->assertSame('@Contao/text.html.twig', $source->getName());
        $this->assertSame(Path::join($projectDir, 'templates/text.html.twig'), Path::normalize($source->getPath()));
        $this->assertStringContainsString('<global>', $source->getCode());

        // Reset and switch context
        $loader->reset();

        $source = $loader->getSourceContext('@Contao/text.html.twig');

        $this->assertSame('@Contao_Theme_my_theme/text.html.twig', $source->getName());
        $this->assertSame(Path::join($projectDir, 'templates/my/theme/text.html.twig'), Path::normalize($source->getPath()));
        $this->assertStringContainsString('<theme>', $source->getCode());
    }

    public function testGetsSourceContextFromHtml5File(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(
            $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy'),
        );

        $source = $loader->getSourceContext('@Contao/foo.html5');

        $this->assertSame('@Contao/foo.html5', $source->getName());
        $this->assertSame(Path::join($projectDir, 'templates/foo.html5'), Path::normalize($source->getPath()));
        $this->assertSame("A\nB", $source->getCode(), 'block names must end up as tokens separated by \n');
    }

    public function testGetsSourceContextFromNestedHtml5File(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(
            $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/legacy'),
        );

        $source = $loader->getSourceContext('@Contao/bar.html5');

        $this->assertSame('@Contao/bar.html5', $source->getName());
        $this->assertSame(Path::join($projectDir, 'templates/bar.html5'), Path::normalize($source->getPath()));
        $this->assertSame("A\nB", $source->getCode(), 'block names including those of parent templates must end up as tokens separated by \n');
    }

    public function testExists(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance'),
        );

        $this->assertTrue($loader->exists('@Contao/text.html.twig'));
        $this->assertFalse($loader->exists('@Contao/foo.html.twig'));
    }

    public function testExistsWithThemeTemplate(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance'),
            themePaths: ['templates/my/theme'],
        );

        $this->assertTrue($loader->exists('@Contao_Theme_my_theme/text.html.twig'));
        $this->assertFalse($loader->exists('@Contao_Theme_my_theme/foo.html.twig'));
    }

    #[DataProvider('provideTemplateFilemtimeSamples')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testIsFresh(array $mtimeMappings, bool $isFresh, bool $isThemeContext = false): void
    {
        $pageFinder = null;

        if ($isThemeContext) {
            $page = $this->mockClassWithProperties(PageModel::class, [
                'templateGroup' => 'templates/my/theme',
            ]);

            $pageFinder = $this->createMock(PageFinder::class);
            $pageFinder
                ->expects($this->once())
                ->method('getCurrentPage')
                ->willReturn($page)
            ;
        }

        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');
        $loader = $this->getContaoFilesystemLoaderWithPaths($projectDir, [], ['templates/my/theme'], $pageFinder);

        $this->mockFilemtime($mtimeMappings);

        $this->assertSame($isFresh, $loader->isFresh('@Contao/text.html.twig', 1623924000));
    }

    public static function provideTemplateFilemtimeSamples(): iterable
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');
        $cacheTime = 1623924000;

        $fresh = $cacheTime;
        $expired = $cacheTime + 100;

        $textPath1 = Path::join($projectDir, 'templates/text.html.twig');
        $textPath2 = Path::join($projectDir, 'contao/templates/some/random/text.html.twig');
        $themePath = Path::join($projectDir, 'templates/my/theme/text.html.twig');

        yield 'all fresh in chain' => [
            [
                $textPath1 => $fresh,
                $textPath2 => $fresh,
                $themePath => $fresh,
            ],
            true,
        ];

        yield 'at least one expired  in chain' => [
            [
                $textPath1 => $fresh,
                $textPath2 => $expired,
                $themePath => $fresh,
            ],
            false,
        ];

        yield 'theme template expired but not in theme context' => [
            [
                $textPath1 => $fresh,
                $textPath2 => $fresh,
                $themePath => $expired,
            ],
            true,
        ];

        yield 'theme template expired and in theme context' => [
            [
                $textPath1 => $fresh,
                $textPath2 => $fresh,
                $themePath => $expired,
            ],
            false,
            true,
        ];

        yield 'filemtime fails' => [
            [
                $textPath1 => $fresh,
                $themePath => $fresh,
                // do not register $textPath2
            ],
            false,
        ];
    }

    #[DataProvider('provideInvalidDynamicParentQueries')]
    public function testGetDynamicParentThrowsIfTemplateCannotBeFound(string $identifier, string $sourcePath, string $expectedException): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance'),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedException);

        $loader->getDynamicParent($identifier, $sourcePath);
    }

    public static function provideInvalidDynamicParentQueries(): iterable
    {
        yield 'invalid chain' => [
            'random',
            '/path/to/template/x.html.twig',
            'The template "random" could not be found in the template hierarchy.',
        ];

        $templatePath = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance/contao/templates/some/random/text.html.twig');

        yield 'last in chain' => [
            'text',
            $templatePath,
            'The template "'.$templatePath.'" does not have a parent "text" it can extend from.',
        ];
    }

    public function testGetFirstThrowsIfChainDoesNotExist(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The template "foo" could not be found in the template hierarchy.');

        $loader->getFirst('foo.html.twig');
    }

    #[DataProvider('provideThemeSlugs')]
    public function testGetInheritanceChains(string|null $themeSlug, array $expectedChains): void
    {
        $loader = $this->getContaoFilesystemLoaderWithPaths(
            Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance'),
            [],
            ['templates/my/theme'],
        );

        $this->assertSame($expectedChains, $loader->getInheritanceChains($themeSlug));
    }

    public static function provideThemeSlugs(): iterable
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');

        $defaultChains = [
            'text' => [
                Path::join($projectDir, 'templates/text.html.twig') => '@Contao_Global/text.html.twig',
                Path::join($projectDir, 'contao/templates/some/random/text.html.twig') => '@Contao_App/text.html.twig',
            ],
            'my/theme/text' => [Path::join($projectDir, 'templates/my/theme/text.html.twig') => '@Contao_Global/my/theme/text.html.twig'],
            'bar' => [Path::join($projectDir, 'contao/templates/bar.html.twig') => '@Contao_App/bar.html.twig'],
            'nested-dir/foo' => [Path::join($projectDir, 'contao/templates/other/nested-dir/foo.html.twig') => '@Contao_App/nested-dir/foo.html.twig'],
        ];

        yield 'no theme slug' => [
            null,
            $defaultChains,
        ];

        yield 'non-existing slug or no theme templates' => [
            'foo-theme',
            $defaultChains,
        ];

        yield 'existing theme slug and templates' => [
            'my_theme',
            [
                'text' => [
                    Path::join($projectDir, 'templates/my/theme/text.html.twig') => '@Contao_Theme_my_theme/text.html.twig',
                    Path::join($projectDir, 'templates/text.html.twig') => '@Contao_Global/text.html.twig',
                    Path::join($projectDir, 'contao/templates/some/random/text.html.twig') => '@Contao_App/text.html.twig',
                ],
                'my/theme/text' => [Path::join($projectDir, 'templates/my/theme/text.html.twig') => '@Contao_Global/my/theme/text.html.twig'],
                'bar' => [Path::join($projectDir, 'contao/templates/bar.html.twig') => '@Contao_App/bar.html.twig'],
                'nested-dir/foo' => [Path::join($projectDir, 'contao/templates/other/nested-dir/foo.html.twig') => '@Contao_App/nested-dir/foo.html.twig'],
            ],
        ];
    }

    public function testThrowsInvalidThemePathExceptionWhenGeneratingSlug(): void
    {
        $page = $this->mockClassWithProperties(PageModel::class, [
            'templateGroup' => 'templates/my_theme',
        ]);

        $pageFinder = $this->createMock(PageFinder::class);
        $pageFinder
            ->expects($this->once())
            ->method('getCurrentPage')
            ->willReturn($page)
        ;

        $loader = new ContaoFilesystemLoader(
            new NullAdapter(),
            $this->createMock(TemplateLocator::class),
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $pageFinder,
            '/',
        );

        $this->expectException(InvalidThemePathException::class);
        $this->expectExceptionMessage('The theme path "my_theme" contains one or more invalid characters: "_"');

        $this->assertFalse($loader->exists('@Contao/foo.html.twig'));
    }

    public function testGetsHierarchy(): void
    {
        $projectDir = Path::canonicalize(__DIR__.'/../../Fixtures/Twig/inheritance');

        $resourceFinder = $this->createMock(ResourceFinder::class);
        $resourceFinder
            ->method('getExistingSubpaths')
            ->with('templates')
            ->willReturn([
                'CoreBundle' => Path::join($projectDir, 'vendor-bundles/CoreBundle/Resources/contao/templates'),
                'foo' => Path::join($projectDir, 'system/modules/foo/templates'),
                'BarBundle' => Path::join($projectDir, 'vendor-bundles/BarBundle/contao/templates'),
                'App' => Path::join($projectDir, 'contao/templates'),
            ])
        ;

        $themePaths = [
            'templates/my/theme',
        ];

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn($themePaths)
        ;

        $templateLocator = new TemplateLocator(
            $projectDir,
            $resourceFinder,
            new ThemeNamespace(),
            $connection,
        );

        $loader = new ContaoFilesystemLoader(
            new NullAdapter(),
            $templateLocator,
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $this->createMock(PageFinder::class),
            $projectDir,
        );

        $expectedChains = [
            'text' => [
                $themePath = Path::join($projectDir, 'templates/my/theme/text.html.twig') => '@Contao_Theme_my_theme/text.html.twig',
                $globalPath = Path::join($projectDir, 'templates/text.html.twig') => '@Contao_Global/text.html.twig',
                $appPath = Path::join($projectDir, 'contao/templates/some/random/text.html.twig') => '@Contao_App/text.html.twig',
                $barPath = Path::join($projectDir, 'vendor-bundles/BarBundle/contao/templates/text.html.twig') => '@Contao_BarBundle/text.html.twig',
                $fooPath = Path::join($projectDir, 'system/modules/foo/templates/any/text.html.twig') => '@Contao_foo/text.html.twig',
                $corePath = Path::join($projectDir, 'vendor-bundles/CoreBundle/Resources/contao/templates/text.html.twig') => '@Contao_CoreBundle/text.html.twig',
            ],
            'my/theme/text' => [Path::join($projectDir, 'templates/my/theme/text.html.twig') => '@Contao_Global/my/theme/text.html.twig'],
            'bar' => [Path::join($projectDir, 'contao/templates/bar.html.twig') => '@Contao_App/bar.html.twig'],
            'nested-dir/foo' => [Path::join($projectDir, 'contao/templates/other/nested-dir/foo.html.twig') => '@Contao_App/nested-dir/foo.html.twig'],
        ];

        // Full hierarchy
        $this->assertSame(
            $expectedChains,
            $loader->getInheritanceChains('my_theme'),
            'get all chains',
        );

        // Get first with theme
        $this->assertSame(
            '@Contao_Theme_my_theme/text.html.twig',
            $loader->getFirst('text', 'my_theme'),
            'get first template in chain (theme)',
        );

        // Get first
        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getFirst('text'),
            'get first template in chain',
        );

        // Next element by path
        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $themePath, 'my_theme'),
            'chain: theme -> global',
        );

        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getDynamicParent('text.html.twig', 'other/template.html.twig'),
            'chain: root -> global (using short name)',
        );

        $this->assertSame(
            '@Contao_Global/text.html.twig',
            $loader->getDynamicParent('text', 'other/template.html.twig'),
            'chain: root -> global (using identifier)',
        );

        $this->assertSame(
            '@Contao_App/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $globalPath),
            'chain: global -> app',
        );

        $this->assertSame(
            '@Contao_BarBundle/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $appPath),
            'chain: app -> bar bundle',
        );

        $this->assertSame(
            '@Contao_foo/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $barPath),
            'chain: bar bundle -> foo bundle',
        );

        $this->assertSame(
            '@Contao_CoreBundle/text.html.twig',
            $loader->getDynamicParent('text.html.twig', $fooPath),
            'chain: foo bundle -> core bundle',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The template "'.$corePath.'" does not have a parent "text" it can extend from.');

        $loader->getDynamicParent('text.html.twig', $corePath);
    }

    public function testGetAllDynamicParentByThemeSlug(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithTemplates(
            [
                'foo.html.twig' => '/test/foo.html.twig',
            ],
            [
                'foo.html.twig' => '/theme/foo.html.twig',
            ],
        );

        $this->assertSame(
            [
                'demo' => '@Contao_Theme_demo/foo.html.twig',
                '' => '@Contao_Test/foo.html.twig',
            ],
            $loader->getAllDynamicParentsByThemeSlug('foo.html.twig', ''),
        );
    }

    public function testGetAllFirstByThemeSlug(): void
    {
        $loader = $this->getContaoFilesystemLoaderWithTemplates(
            [
                'foo.html.twig' => '/test/foo.html.twig',
            ],
            [
                'foo.html.twig' => '/theme/foo.html.twig',
            ],
        );

        $this->assertSame(
            [
                'demo' => '@Contao_Theme_demo/foo.html.twig',
                '' => '@Contao_Test/foo.html.twig',
            ],
            $loader->getAllFirstByThemeSlug('foo.html.twig'),
        );
    }

    public function testGetCurrentThemeSlug(): void
    {
        $page1 = $this->mockClassWithProperties(PageModel::class, ['templateGroup' => null]);
        $page2 = $this->mockClassWithProperties(PageModel::class, ['templateGroup' => 'templates/foo/bar']);

        $pageFinder = $this->createMock(PageFinder::class);
        $pageFinder
            ->method('getCurrentPage')
            ->willReturnOnConsecutiveCalls($page1, $page2)
        ;

        $loader = new ContaoFilesystemLoader(
            new NullAdapter(),
            $this->createMock(TemplateLocator::class),
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $pageFinder,
            '/',
        );

        $this->assertNull($loader->getCurrentThemeSlug(), 'no theme slug (page 1)');

        $loader->reset();

        $this->assertSame('foo_bar', $loader->getCurrentThemeSlug(), 'theme slug from context (page 2)');
    }

    public function testPersistsAndRecallsHierarchy(): void
    {
        $cacheAdapter = new ArrayAdapter();

        $templateLocator1 = $this->createMock(TemplateLocator::class);
        $templateLocator1
            ->method('findThemeDirectories')
            ->willReturn([])
        ;

        $templateLocator1
            ->method('findResourcesPaths')
            ->willReturn([])
        ;

        $templateLocator1
            ->method('findTemplates')
            ->with('/templates')
            ->willReturn([
                'foo.html.twig' => '/templates/foo.html.twig',
            ])
        ;

        $loader1 = new ContaoFilesystemLoader(
            $cacheAdapter,
            $templateLocator1,
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $this->createMock(PageFinder::class),
            '/',
        );

        $this->assertEmpty(array_filter($cacheAdapter->getValues()), 'cache is empty at initial state');
        $loader1->warmUp();
        $this->assertNotEmpty(array_filter($cacheAdapter->getValues()), 'cache is written after hierarchy was built');

        // Recall
        $templateLocator2 = $this->createMock(TemplateLocator::class);
        $templateLocator2
            ->expects($this->never())
            ->method('findThemeDirectories')
        ;

        $templateLocator2
            ->expects($this->never())
            ->method('findResourcesPaths')
        ;

        $templateLocator2
            ->expects($this->never())
            ->method('findTemplates')
        ;

        $loader2 = new ContaoFilesystemLoader(
            $cacheAdapter,
            $templateLocator2,
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $this->createMock(PageFinder::class),
            '/',
        );

        $this->assertSame(
            ['foo' => ['/templates/foo.html.twig' => '@Contao_Global/foo.html.twig']],
            $loader2->getInheritanceChains(),
            'hierarchy is restored from cache without any filesystem access',
        );
    }

    public function testNumericNames(): void
    {
        $templateLocator = $this->createMock(TemplateLocator::class);
        $templateLocator
            ->method('findThemeDirectories')
            ->willReturn(['2025' => '2025'])
        ;

        $templateLocator
            ->method('findResourcesPaths')
            ->willReturn([])
        ;

        $templateLocator
            ->method('findTemplates')
            ->willReturnMap([
                ['2025', ['1.html.twig' => '2025/1.html.twig']],
                ['/templates', ['1.html.twig' => '1.html.twig']],
            ])
        ;

        $loader = new ContaoFilesystemLoader(
            new NullAdapter(),
            $templateLocator,
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $this->createMock(PageFinder::class),
            '/',
        );

        $this->assertSame(
            [
                2025 => '@Contao_Theme_2025/1.html.twig',
                '' => '@Contao_Global/1.html.twig',
            ],
            $loader->getAllFirstByThemeSlug('1'),
        );
    }

    /**
     * @param array<string, string> $templates
     * @param array<string, string> $themeTemplates
     */
    private function getContaoFilesystemLoaderWithTemplates(array $templates, array|null $themeTemplates = null, PageFinder|null $pageFinder = null): ContaoFilesystemLoader
    {
        $templateLocator = $this->createMock(TemplateLocator::class);
        $templateLocator
            ->method('findThemeDirectories')
            ->willReturn(
                null !== $themeTemplates ? ['demo' => '/theme'] : [],
            )
        ;

        $templateLocator
            ->method('findResourcesPaths')
            ->willReturn([
                'Test' => ['/test'],
            ])
        ;

        $templateMap = [
            ['/templates', []],
            ['/test', $templates],
        ];

        if (null !== $themeTemplates) {
            $templateMap[] = ['/theme', $themeTemplates];
        }

        $templateLocator
            ->method('findTemplates')
            ->willReturnMap($templateMap)
        ;

        return new ContaoFilesystemLoader(
            new NullAdapter(),
            $templateLocator,
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $pageFinder ?? $this->createMock(PageFinder::class),
            '/',
        );
    }

    /**
     * @param list<string> $additionalPaths
     * @param list<string> $themePaths
     */
    private function getContaoFilesystemLoaderWithPaths(string|null $projectDir = null, array $additionalPaths = [], array $themePaths = [], PageFinder|null $pageFinder = null): ContaoFilesystemLoader
    {
        $projectDir ??= Path::canonicalize(__DIR__.'/../../Fixtures/Twig/default-project');

        $paths = array_combine(array_map(
            static fn (int $key): string => "Test{$key}Bundle",
            array_keys($additionalPaths),
        ), $additionalPaths);

        if (is_dir(Path::join($projectDir, 'contao/templates'))) {
            $paths['App'] = Path::join($projectDir, 'contao/templates');
        }

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn($themePaths)
        ;

        $resourceFinder = $this->createMock(ResourceFinder::class);
        $resourceFinder
            ->method('getExistingSubpaths')
            ->with('templates')
            ->willReturn($paths)
        ;

        $templateLocator = new TemplateLocator(
            $projectDir,
            $resourceFinder,
            new ThemeNamespace(),
            $connection,
        );

        return new ContaoFilesystemLoader(
            new NullAdapter(),
            $templateLocator,
            new ThemeNamespace(),
            $this->createMock(ContaoFramework::class),
            $pageFinder ?? $this->createMock(PageFinder::class),
            $projectDir,
        );
    }

    /**
     * @param array<string, int> $pathToMtime
     */
    private function mockFilemtime(array $pathToMtime): void
    {
        $namespaces = ['Contao\CoreBundle\Twig\Loader', 'Twig\Loader'];

        foreach ($namespaces as $namespace) {
            $mock = \sprintf(
                <<<'EOPHP'
                    namespace %s;

                    function filemtime(string $filename) {
                        if (null !== ($mtime = unserialize('%s')[\Symfony\Component\Filesystem\Path::canonicalize($filename)] ?? null)) {
                            return $mtime;
                        }

                        return false;
                    }
                    EOPHP,
                $namespace,
                serialize($pathToMtime),
            );

            eval($mock);
        }
    }
}
