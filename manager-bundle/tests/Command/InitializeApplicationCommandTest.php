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

use Contao\ManagerBundle\Command\InitializeApplicationCommand;
use Contao\ManagerBundle\Process\ProcessFactory;
use Contao\TestCase\ContaoTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class InitializeApplicationCommandTest extends ContaoTestCase
{
    public function testIsHidden(): void
    {
        $command = new InitializeApplicationCommand('project/dir', 'web');

        $this->assertTrue($command->isHidden());
    }

    public function testPurgesProdCacheDirectory(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('project/dir/var/cache/prod')
            ->willReturn(true)
        ;

        $filesystem
            ->expects($this->once())
            ->method('remove')
            ->with('project/dir/var/cache/prod')
        ;

        $command = new InitializeApplicationCommand(
            'project/dir', 'web', $filesystem, $this->getProcessFactoryMock()
        );

        (new CommandTester($command))->execute([]);
    }

    public function testDoesNotPurgeProdCacheDirectoryIfItDoesntExist(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('project/dir/var/cache/prod')
            ->willReturn(false)
        ;

        $filesystem
            ->expects($this->never())
            ->method('remove')
        ;

        $command = new InitializeApplicationCommand(
            'project/dir', 'web', $filesystem, $this->getProcessFactoryMock()
        );

        (new CommandTester($command))->execute([]);
    }

    public function testSuppressesFilesystemErrors(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('project/dir/var/cache/prod')
            ->willThrowException(new \Exception())
        ;

        $command = new InitializeApplicationCommand(
            'project/dir', 'web', $filesystem, $this->getProcessFactoryMock()
        );

        (new CommandTester($command))->execute([]);
    }

    /**
     * @dataProvider provideCommands
     */
    public function testExecutesCommands(array $options, array $flags): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('project/dir/var/cache/prod')
            ->willReturn(false)
        ;

        $process = $this->createMock(Process::class);
        $process
            ->method('isSuccessful')
            ->willReturn(true)
        ;

        $phpPath = (new PhpExecutableFinder())->find();
        $this->assertStringContainsString('php', $phpPath);

        $commandArguments = [
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'contao:install-web-dir', '--env=prod'], $flags)],
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'cache:clear', '--no-warmup', '--env=prod'], $flags)],
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'cache:clear', '--no-warmup', '--env=dev'], $flags)],
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'cache:warmup', '--env=prod'], $flags)],
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'assets:install', 'web', '--symlink', '--relative', '--env=prod'], $flags)],
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'contao:install', 'web', '--env=prod'], $flags)],
            [array_merge([$phpPath, 'project/dir/vendor/bin/contao-console', 'contao:symlinks', 'web', '--env=prod'], $flags)],
        ];

        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory
            ->expects($this->exactly(7))
            ->method('create')
            ->willReturn($process)
            ->withConsecutive(...$commandArguments)
        ;

        $command = new InitializeApplicationCommand(
            'project/dir', 'web', $filesystem, $processFactory
        );

        (new CommandTester($command))->execute([], $options);
    }

    public function provideCommands(): \Generator
    {
        yield 'no arguments' => [
            [],
            ['--no-ansi'],
        ];

        yield 'ansi' => [
            ['decorated' => true],
            ['--ansi'],
        ];

        yield 'normal' => [
            ['verbosity' => OutputInterface::VERBOSITY_NORMAL],
            ['--no-ansi'],
        ];

        yield 'verbose' => [
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
            ['--no-ansi', '-v'],
        ];

        yield 'very verbose' => [
            ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE],
            ['--no-ansi', '-vv'],
        ];

        yield 'debug' => [
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG],
            ['--no-ansi', '-vvv'],
        ];

        yield 'ansi and verbose' => [
            ['decorated' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
            ['--ansi', '-v'],
        ];
    }

    public function testThrowsIfCommandFails(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('project/dir/var/cache/prod')
            ->willReturn(false)
        ;

        $process = $this->createMock(Process::class);

        $process
            ->method('isSuccessful')
            ->willReturn(false)
        ;

        $process
            ->method('getErrorOutput')
            ->willReturn('<error>')
        ;

        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($process)
        ;

        $command = new InitializeApplicationCommand(
            'project/dir', 'web', $filesystem, $processFactory
        );

        $commandTester = (new CommandTester($command));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/An error occurred while executing the ".+" command: <error>/');

        $commandTester->execute([]);
    }

    public function testDelegatesOutputOfSubProcesses(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->with('project/dir/var/cache/prod')
            ->willReturn(false)
        ;

        $processes = [];

        for ($i = 1; $i <= 7; ++$i) {
            $processes[$i] = $this->createMock(Process::class);

            $processes[$i]
                ->method('isSuccessful')
                ->willReturn(true)
            ;

            $processes[$i]
                ->method('run')
                ->with($this->callback(
                    static function ($callable) use ($i) {
                        $callable('', "[output $i]");

                        return true;
                    }
                ))
            ;
        }

        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory
            ->method('create')
            ->willReturn(...$processes)
        ;

        $command = new InitializeApplicationCommand(
            'project/dir', 'web', $filesystem, $processFactory
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(
            '[output 1][output 2][output 3][output 4][output 5][output 6][output 7]',
            $commandTester->getDisplay()
        );
    }

    /**
     * @return ProcessFactory&MockObject
     */
    private function getProcessFactoryMock()
    {
        $process = $this->createMock(Process::class);
        $process
            ->method('isSuccessful')
            ->willReturn(true)
        ;

        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory
            ->method('create')
            ->willReturn($process)
        ;

        return $processFactory;
    }
}
