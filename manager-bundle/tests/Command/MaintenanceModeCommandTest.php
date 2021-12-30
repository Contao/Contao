<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\Command;

use Contao\ManagerBundle\Command\MaintenanceModeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

class MaintenanceModeCommandTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @dataProvider enableProvider
     */
    public function testEnable(string $expectedTemplateName, array $expectedTemplateVars, string $customTemplateName = null, string $customTemplateVars = null): void
    {
        $twig = $this->getTwigMock();
        $twig
            ->expects($this->once())
            ->method('render')
            ->with($expectedTemplateName, $expectedTemplateVars)
            ->willReturn('parsed-template')
        ;

        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with('/path/to/var/maintenance.html', 'parsed-template')
        ;

        $params = ['state' => 'enable'];

        if ($customTemplateName) {
            $params['--template'] = $customTemplateName;
        }

        if ($customTemplateVars) {
            $params['--templateVars'] = $customTemplateVars;
        }

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $twig, [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute($params);

        $this->assertStringContainsString('[OK] Maintenance mode enabled', $commandTester->getDisplay(true));
    }

    public function testDisable(): void
    {
        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('remove')
            ->with('/path/to/var/maintenance.html')
        ;

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['state' => 'disable']);

        $this->assertStringContainsString('[OK] Maintenance mode disabled', $commandTester->getDisplay(true));
    }

    public function testOutputIfEnabled(): void
    {
        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('/path/to/var/maintenance.html')
            ->willReturn(true)
        ;

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString(' ! [NOTE] Maintenance mode is enabled.', $commandTester->getDisplay(true));
    }

    public function testOutputIfDisabled(): void
    {
        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('/path/to/var/maintenance.html')
            ->willReturn(false)
        ;

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString(' [INFO] Maintenance mode is disabled.', $commandTester->getDisplay(true));
    }

    public function testOutputWithJsonFormat(): void
    {
        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('/path/to/var/maintenance.html')
            ->willReturn(false)
        ;

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'json']);

        $json = json_decode($commandTester->getDisplay(true), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['enabled' => false, 'maintenanceFilePath' => '/path/to/var/maintenance.html'], $json);
    }

    public function testAliasesLexikMaintenanceCommands(): void
    {
        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), [], $this->getFilesystemMock());

        $this->assertContains('lexik:maintenance:lock', $command->getAliases());
        $this->assertContains('lexik:maintenance:unlock', $command->getAliases());
    }

    public function testDoesNotAliasLexikMaintenanceCommandsIfBundleIsInstalled(): void
    {
        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), ['LexikMaintenanceBundle'], $this->getFilesystemMock());

        $this->assertNotContains('lexik:maintenance:lock', $command->getAliases());
        $this->assertNotContains('lexik:maintenance:unlock', $command->getAliases());
    }

    public function testHandlesLexikMaintenanceLock(): void
    {
        $this->expectDeprecation('Since contao/manager-bundle 4.13: Using "lexik:maintenance:lock" command is deprecated. Use "contao:maintenance-mode enable" instead.');

        $twig = $this->getTwigMock();
        $twig
            ->expects($this->once())
            ->method('render')
            ->willReturn('parsed-template')
        ;

        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with('/path/to/var/maintenance.html', 'parsed-template')
        ;

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $twig, [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['lexik:maintenance:lock']);

        $this->assertStringContainsString('[OK] Maintenance mode enabled', $commandTester->getDisplay(true));
    }

    public function testHandlesLexikMaintenanceUnlock(): void
    {
        $this->expectDeprecation('Since contao/manager-bundle 4.13: Using "lexik:maintenance:unlock" command is deprecated. Use "contao:maintenance-mode disable" instead.');

        $filesystem = $this->getFilesystemMock();
        $filesystem
            ->expects($this->once())
            ->method('remove')
            ->with('/path/to/var/maintenance.html')
        ;

        $command = new MaintenanceModeCommand('/path/to/var/maintenance.html', $this->getTwigMock(), [], $filesystem);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['lexik:maintenance:unlock']);

        $this->assertStringContainsString('[OK] Maintenance mode disabled', $commandTester->getDisplay(true));
    }

    public function enableProvider(): \Generator
    {
        yield 'Test defaults' => [
            '@ContaoCore/Error/service_unavailable.html.twig',
            [
                'statusCode' => 503,
                'language' => 'en',
                'template' => '@ContaoCore/Error/service_unavailable.html.twig',
            ],
        ];

        yield 'Test custom template name' => [
            '@CustomBundle/maintenance.html.twig',
            [
                'statusCode' => 503,
                'language' => 'en',
                'template' => '@CustomBundle/maintenance.html.twig',
            ],
            '@CustomBundle/maintenance.html.twig',
        ];

        yield 'Test custom template name and template vars' => [
            '@CustomBundle/maintenance.html.twig',
            [
                'statusCode' => 503,
                'language' => 'de',
                'template' => '@CustomBundle/maintenance.html.twig',
                'foo' => 'bar',
            ],
            '@CustomBundle/maintenance.html.twig',
            '{"language":"de", "foo": "bar"}',
        ];
    }

    private function getFilesystemMock()
    {
        return $this->getMockBuilder(Filesystem::class)
            ->disableAutoReturnValueGeneration() // Ensure we don't call any other method than the ones we mock
            ->getMock()
        ;
    }

    private function getTwigMock()
    {
        return $this->getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->disableAutoReturnValueGeneration() // Ensure we don't call any other method than the ones we mock
            ->getMock()
        ;
    }
}
