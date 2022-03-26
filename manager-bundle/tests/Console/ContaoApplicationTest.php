<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\Console;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerBundle\Console\ContaoApplication;
use Contao\ManagerBundle\ContaoManager\Plugin;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Contao\TestCase\ContaoTestCase;
use Symfony\Component\Console\Input\ArgvInput;

class ContaoApplicationTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->backupServerEnvGetPost();
    }

    protected function tearDown(): void
    {
        $this->restoreServerEnvGetPost();
        $this->resetStaticProperties([Plugin::class, ContaoKernel::class]);

        parent::tearDown();
    }

    public function testApplicationNameAndVersion(): void
    {
        $app = new ContaoApplication(ContaoKernel::fromInput($this->getTempDir(), new ArgvInput(array_merge($_SERVER['argv'] ?? [], ['-eprod']))));

        $this->assertSame('Contao Managed Edition', $app->getName());
        $this->assertSame(ContaoCoreBundle::getVersion(), $app->getVersion());
    }

    public function testDoesNotHaveNoDebugOption(): void
    {
        $app = new ContaoApplication(ContaoKernel::fromInput($this->getTempDir(), new ArgvInput(array_merge($_SERVER['argv'] ?? [], ['-eprod']))));
        $options = $app->getDefinition()->getOptions();

        $this->assertArrayNotHasKey('no-debug', $options);
    }
}
