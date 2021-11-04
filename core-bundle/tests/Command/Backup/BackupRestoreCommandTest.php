<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Command\Backup;

use Contao\CoreBundle\Command\Backup\BackupRestoreCommand;
use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\BackupManagerException;
use Contao\CoreBundle\Doctrine\Backup\Config\RestoreConfig;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class BackupRestoreCommandTest extends TestCase
{
    /**
     * @dataProvider successfulCommandRunProvider
     */
    public function testSuccessfulCommandRun(array $arguments, \Closure $expectedRestoreConfig, string $expectedOutput): void
    {
        $command = new BackupRestoreCommand($this->createBackupManager($expectedRestoreConfig));

        $commandTester = new CommandTester($command);
        $code = $commandTester->execute($arguments);
        $normalizedOutput = preg_replace("/\\s+\n/", "\n", $commandTester->getDisplay(true));

        $this->assertStringContainsString($expectedOutput, $normalizedOutput);
        $this->assertSame(0, $code);
    }

    /**
     * @dataProvider unsuccessfulCommandRunProvider
     */
    public function testUnsuccessfulCommandRun(array $arguments, string $expectedOutput): void
    {
        $backupManager = $this->createMock(BackupManager::class);
        $backupManager
            ->expects($this->once())
            ->method('restore')
            ->willThrowException(new BackupManagerException('Some error.'))
        ;

        $command = new BackupRestoreCommand($backupManager);

        $commandTester = new CommandTester($command);
        $code = $commandTester->execute($arguments);

        $this->assertStringContainsString($expectedOutput, $commandTester->getDisplay(true));
        $this->assertSame(1, $code);
    }

    public function unsuccessfulCommandRunProvider(): \Generator
    {
        yield 'Text format' => [
            [],
            '[ERROR] Some error.',
        ];

        yield 'JSON format' => [
            ['--format' => 'json'],
            '{"error":"Some error."}',
        ];
    }

    public function successfulCommandRunProvider(): \Generator
    {
        yield 'Default arguments' => [
            [],
            function (RestoreConfig $config) {
                $this->assertSame([], $config->getTablesToIgnore());
                $this->assertSame('test__20211101141254.sql.gz', $config->getBackup()->getFilepath());
                $this->assertFalse($config->ignoreOriginCheck());

                return true;
            },
            '[OK] Successfully restored backup from "test__20211101141254.sql.gz".',
        ];

        yield 'Different tables to ignore' => [
            ['--ignore-tables' => 'foo,bar'],
            function (RestoreConfig $config) {
                $this->assertSame(['foo', 'bar'], $config->getTablesToIgnore());
                $this->assertSame('test__20211101141254.sql.gz', $config->getBackup()->getFilepath());
                $this->assertFalse($config->ignoreOriginCheck());

                return true;
            },
            '[OK] Successfully restored backup from "test__20211101141254.sql.gz".',
        ];

        yield 'Different target file' => [
            ['file' => 'somewhere/else/file__20211101141254.sql'],
            function (RestoreConfig $config) {
                $this->assertSame([], $config->getTablesToIgnore());
                $this->assertSame('somewhere/else/file__20211101141254.sql', $config->getBackup()->getFilepath());
                $this->assertFalse($config->ignoreOriginCheck());

                return true;
            },
            '[OK] Successfully restored backup from "somewhere/else/file__20211101141254.sql".',
        ];

        yield 'Force restore' => [
            ['--force' => true],
            function (RestoreConfig $config) {
                $this->assertSame([], $config->getTablesToIgnore());
                $this->assertSame('test__20211101141254.sql.gz', $config->getBackup()->getFilepath());
                $this->assertTrue($config->ignoreOriginCheck());

                return true;
            },
            '[OK] Successfully restored backup from "test__20211101141254.sql.gz".',
        ];

        yield 'JSON format' => [
            ['--format' => 'json'],
            function (RestoreConfig $config) {
                $this->assertSame([], $config->getTablesToIgnore());
                $this->assertSame('test__20211101141254.sql.gz', $config->getBackup()->getFilepath());
                $this->assertFalse($config->ignoreOriginCheck());

                return true;
            },
            '{"createdAt":"2021-11-01T14:12:54+0000","size":100,"humanReadableSize":"100 B","path":"test__20211101141254.sql.gz"}',
        ];
    }

    private function createBackupManager(\Closure $expectedCreateConfig): BackupManager
    {
        $backupManager = $this->createMock(BackupManager::class);

        $backup = $this->getMockBuilder(Backup::class)
            ->setConstructorArgs(['test__20211101141254.sql.gz'])
            ->onlyMethods(['getSize'])
        ;
        $backup = $backup->getMock();
        $backup
            ->method('getSize')
            ->willReturn(100)
        ;

        $backupManager
            ->expects($this->once())
            ->method('createRestoreConfig')
            ->willReturn(new RestoreConfig($backup))
        ;

        $backupManager
            ->expects($this->once())
            ->method('restore')
            ->with($this->callback($expectedCreateConfig))
        ;

        return $backupManager;
    }
}
