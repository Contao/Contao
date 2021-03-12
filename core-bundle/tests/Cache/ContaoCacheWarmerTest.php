<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Cache;

use Contao\CoreBundle\Cache\ContaoCacheWarmer;
use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Tests\TestCase;
use Contao\System;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class ContaoCacheWarmerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        (new Filesystem())->mkdir([
            Path::join(self::getTempDir(), 'var/cache'),
            Path::join(self::getTempDir(), 'other'),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove(Path::join($this->getTempDir(), 'var/cache/contao'));
    }

    public function testCreatesTheCacheFolder(): void
    {
        $container = $this->getContainerWithContaoConfiguration($this->getTempDir());
        $container->set('database_connection', $this->createMock(Connection::class));

        System::setContainer($container);

        $warmer = $this->getCacheWarmer();
        $warmer->warmUp(Path::join($this->getTempDir(), 'var/cache'));

        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/config'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/config/autoload.php'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/config/config.php'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/config/templates.php'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/dca'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/dca/tl_test.php'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/languages'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/languages/en'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/languages/en/default.php'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/sql'));
        $this->assertFileExists(Path::join($this->getTempDir(), 'var/cache/contao/sql/tl_test.php'));

        $this->assertStringContainsString(
            "\$GLOBALS['TL_TEST'] = true;",
            file_get_contents(Path::join($this->getTempDir(), 'var/cache/contao/config/config.php'))
        );

        $this->assertStringContainsString(
            "'dummy' => 'templates'",
            file_get_contents(Path::join($this->getTempDir(), 'var/cache/contao/config/templates.php'))
        );

        $this->assertStringContainsString(
            "\$GLOBALS['TL_DCA']['tl_test'] = [\n",
            file_get_contents(Path::join($this->getTempDir(), 'var/cache/contao/dca/tl_test.php'))
        );

        $this->assertStringContainsString(
            "\$GLOBALS['TL_LANG']['MSC']['first']",
            file_get_contents(Path::join($this->getTempDir(), 'var/cache/contao/languages/en/default.php'))
        );

        $this->assertStringContainsString(
            "\$this->arrFields = array (\n  'id' => 'int(10) unsigned NOT NULL auto_increment',\n);",
            file_get_contents(Path::join($this->getTempDir(), 'var/cache/contao/sql/tl_test.php'))
        );
    }

    public function testIsAnOptionalWarmer(): void
    {
        $this->assertTrue($this->getCacheWarmer()->isOptional());
    }

    public function testDoesNotCreateTheCacheFolderIfThereAreNoContaoResources(): void
    {
        $warmer = $this->getCacheWarmer(null, null, 'empty-bundle');
        $warmer->warmUp(Path::join($this->getTempDir(), 'other'));

        $this->assertFileNotExists(Path::join($this->getTempDir(), 'var/cache/contao'));
    }

    public function testDoesNotCreateTheCacheFolderIfTheInstallationIsIncomplete(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('query')
            ->willThrowException(new \Exception())
        ;

        $framework = $this->mockContaoFramework();
        $framework
            ->expects($this->never())
            ->method('initialize')
        ;

        $warmer = $this->getCacheWarmer($connection, $framework);
        $warmer->warmUp(Path::join($this->getTempDir(), 'var/cache/contao'));

        $this->assertFileNotExists(Path::join($this->getTempDir(), 'var/cache/contao'));
    }

    /**
     * @param Connection&MockObject      $connection
     * @param ContaoFramework&MockObject $framework
     */
    private function getCacheWarmer(Connection $connection = null, ContaoFramework $framework = null, string $bundle = 'test-bundle'): ContaoCacheWarmer
    {
        if (null === $connection) {
            $connection = $this->createMock(Connection::class);
        }

        if (null === $framework) {
            $framework = $this->mockContaoFramework();
        }

        $fixtures = Path::join($this->getFixturesDir(), 'vendor/contao/'.$bundle.'/Resources/contao');

        $filesystem = new Filesystem();
        $finder = new ResourceFinder($fixtures);
        $locator = new FileLocator($fixtures);
        $locales = ['en-US', 'en'];

        return new ContaoCacheWarmer($filesystem, $finder, $locator, $fixtures, $connection, $framework, $locales);
    }
}
