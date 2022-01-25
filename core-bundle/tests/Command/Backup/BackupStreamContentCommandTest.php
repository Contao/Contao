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

use Contao\CoreBundle\Command\Backup\BackupListCommand;
use Contao\CoreBundle\Command\Backup\BackupStreamContentCommand;
use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\DumperInterface;
use Contao\CoreBundle\Doctrine\Backup\RetentionPolicyInterface;
use Contao\CoreBundle\Filesystem\Dbafs\DbafsManager;
use Contao\CoreBundle\Filesystem\MountManager;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Tests\TestCase;
use Doctrine\DBAL\Connection;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class BackupStreamContentCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->vfs = new VirtualFilesystem(
            new MountManager(new InMemoryFilesystemAdapter()),
            $this->createMock(DbafsManager::class)
        );
    }

    public function testStreamsFileProperly(): void
    {
        $this->vfs->write('test__20211101141254.sql.gz', 'dump content');

        [$code, $commandTester] = $this->runCommand();

        $this->assertSame('dump content', $commandTester->getDisplay());
        $this->assertSame(Command::SUCCESS, $code);
    }

    public function testErrorIfBackupIsMissing(): void
    {
        [$code, $commandTester] = $this->runCommand();

        $this->assertStringContainsString('[ERROR] Backup "test__20211101141254.sql.gz" not found.', $commandTester->getDisplay());
        $this->assertSame(Command::FAILURE, $code);
    }

    private function runCommand(): array
    {
        $backupManager = new BackupManager(
            $this->createMock(Connection::class),
            $this->createMock(DumperInterface::class),
            $this->vfs,
            [],
            $this->createMock(RetentionPolicyInterface::class)
        );

        $command = new BackupStreamContentCommand($backupManager);

        $commandTester = new CommandTester($command);
        $code = $commandTester->execute(['name' => 'test__20211101141254.sql.gz']);

        return [$code, $commandTester];
    }
}
