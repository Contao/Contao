<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Doctrine\Backup;

use Contao\CoreBundle\Doctrine\Backup\Backup;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Doctrine\Backup\BackupManagerException;
use Contao\CoreBundle\Doctrine\Backup\Config\CreateConfig;
use Contao\CoreBundle\Doctrine\Backup\Config\RestoreConfig;
use Contao\CoreBundle\Doctrine\Backup\DumperInterface;
use Contao\CoreBundle\Doctrine\Backup\RetentionPolicy;
use Contao\CoreBundle\Doctrine\Backup\RetentionPolicyInterface;
use Contao\CoreBundle\Filesystem\Dbafs\DbafsManager;
use Contao\CoreBundle\Filesystem\MountManager;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\MockObject\MockObject;

class BackupManagerTest extends ContaoTestCase
{
    private VirtualFilesystemInterface $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vfs = new VirtualFilesystem(
            (new MountManager())->mount(new InMemoryFilesystemAdapter()),
            $this->createMock(DbafsManager::class),
        );
    }

    public function testCreateCreateConfig(): void
    {
        $manager = $this->getBackupManager();
        $config = $manager->createCreateConfig();

        $this->assertSame(['foobar'], $config->getTablesToIgnore());
    }

    public function testCreateRestoreConfigThrowsIfNoBackupAvailableYet(): void
    {
        $manager = $this->getBackupManager();

        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('No backups found.');

        $manager->createRestoreConfig();
    }

    public function testCreateRestoreConfig(): void
    {
        $this->vfs->write(Backup::createNew(new \DateTime('-1 day'))->getFilename(), '');

        $manager = $this->getBackupManager();
        $config = $manager->createRestoreConfig();

        $this->assertSame(['foobar'], $config->getTablesToIgnore());
    }

    public function testListBackupsInCorrectOrder(): void
    {
        $backupPastWeek = Backup::createNew(new \DateTime('-1 week'));
        $backupNow = Backup::createNew();
        $backupTwoWeeksAgo = Backup::createNew(new \DateTime('-2 weeks'));

        $this->vfs->write($backupPastWeek->getFilename(), '');
        $this->vfs->write($backupNow->getFilename(), '');
        $this->vfs->write($backupTwoWeeksAgo->getFilename(), '');

        $manager = $this->getBackupManager();
        $backups = $manager->listBackups();

        $this->assertCount(3, $backups);
        $this->assertSame($backups[0]->getFilename(), $backupNow->getFilename());
        $this->assertSame($backups[1]->getFilename(), $backupPastWeek->getFilename());
        $this->assertSame($backups[2]->getFilename(), $backupTwoWeeksAgo->getFilename());

        $latestBackup = $manager->getLatestBackup();

        $this->assertSame($latestBackup->getFilename(), $backupNow->getFilename());
    }

    public function testIgnoresFilesThatAreNoBackups(): void
    {
        $this->vfs->write('backup__20211101141254.zip', ''); // incorrect file extension

        $manager = $this->getBackupManager();

        $this->assertCount(0, $manager->listBackups());
    }

    public function testSuccessfulCreate(): void
    {
        $connection = $this->mockConnection();
        $dumper = $this->mockDumper($connection);
        $backup = Backup::createNew(\DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-03T13:36:00+00:00'));

        $manager = $this->getBackupManager($connection, $dumper);
        $config = (new CreateConfig($backup))->withGzCompression(false);
        $manager->create($config);

        $this->assertSame(
            <<<'DUMP'
                -- Generated by the Contao Open Source CMS Backup Manager (version: v1).
                -- Generated at 2021-11-03T13:36:00+00:00
                Dumper content line one
                Dumper content line two

                DUMP,
            preg_replace('~\R~u', "\n", $this->vfs->read($backup->getFilename())),
        );
    }

    public function testIsGzipEncodedIfEnabled(): void
    {
        $connection = $this->mockConnection();
        $dumper = $this->mockDumper($connection);

        $manager = $this->getBackupManager($connection, $dumper);
        $config = $manager->createCreateConfig();
        $manager->create($config);

        // Assert it's gzipped
        $this->assertSame(
            0,
            mb_strpos($this->vfs->read($config->getBackup()->getFilename()), "\x1f\x8b\x08", 0, 'US-ASCII'),
        );
    }

    public function testUnsuccessfulCreate(): void
    {
        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('Error!');

        $connection = $this->mockConnection();

        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump')
            ->with($connection, $this->isInstanceOf(CreateConfig::class))
            ->willThrowException(new BackupManagerException('Error!'))
        ;

        $manager = $this->getBackupManager($connection, $dumper);
        $config = $manager->createCreateConfig();
        $manager->create($config);

        $this->assertFalse($this->vfs->fileExists($config->getBackup()->getFilename()));
    }

    public function testCleanup(): void
    {
        $connection = $this->mockConnection();
        $dumper = $this->mockDumper($connection);

        $backupNew = Backup::createNew();
        $backupExisting = Backup::createNew(new \DateTime('-1 day'));

        $this->vfs->write($backupExisting->getFilename(), '');

        $retentionPolicy = $this->createMock(RetentionPolicyInterface::class);
        $retentionPolicy
            ->expects($this->once())
            ->method('apply')
            ->with(
                $this->callback(static fn (Backup $backup) => $backup->getFilename() === $backupNew->getFilename()),
                $this->callback(
                    function (array $backups) use ($backupNew, $backupExisting) {
                        $this->assertCount(2, $backups);

                        return $backups[0]->getFilename() === $backupNew->getFilename()
                            && $backups[1]->getFilename() === $backupExisting->getFilename();
                    },
                ),
            )
            ->willReturn([$backupNew])
        ;

        $manager = $this->getBackupManager($connection, $dumper, $retentionPolicy);
        $config = $manager->createCreateConfig();
        $manager->create($config);

        $this->assertCount(1, $manager->listBackups());
        $this->assertSame($backupNew->getFilename(), $manager->getLatestBackup()->getFilename());
    }

    public function testDirectoryIsNotCleanedUpAfterUnsuccessfulCreate(): void
    {
        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump')
            ->willThrowException(new BackupManagerException('Error!'))
        ;

        $retentionPolicy = $this->createMock(RetentionPolicyInterface::class);
        $retentionPolicy
            ->expects($this->never())
            ->method('apply')
        ;

        $manager = $this->getBackupManager($this->mockConnection(), $dumper, $retentionPolicy);
        $config = $manager->createCreateConfig();

        try {
            $manager->create($config);
        } catch (BackupManagerException) {
            // irrelevant for this test
        }
    }

    /**
     * @dataProvider successfulRestoreProvider
     */
    public function testSuccessfulRestore(string $backupContent, RestoreConfig $config, array $expectedQueries): void
    {
        $this->vfs->write($config->getBackup()->getFilename(), $backupContent);

        $connection = $this->mockConnection();
        $connection
            ->expects($this->exactly(3))
            ->method('executeQuery')
            ->withConsecutive(...$expectedQueries)
        ;

        $manager = $this->getBackupManager($connection);
        $manager->restore($config);
    }

    public function testUnsuccessfulRestoreIfFileWasRemoved(): void
    {
        $backup = Backup::createNew();
        // Do not write it to vfs === backup does not exist

        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage(sprintf('Dump "%s" does not exist.', $backup->getFilename()));

        $manager = $this->getBackupManager($this->mockConnection());
        $manager->restore(new RestoreConfig($backup));
    }

    public function testUnsuccessfulRestoreIfHeaderIsMissing(): void
    {
        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('The Contao database importer only supports dumps generated by Contao.');

        $backup = Backup::createNew();
        $manager = $this->getBackupManager($this->mockConnection());

        $this->vfs->write(
            $backup->getFilename(),
            <<<'BACKUP'
                -- Generated at 2021-11-02T17:15:52+00:00
                SET NAMES utf8;
                SET FOREIGN_KEY_CHECKS = 0;

                -- BEGIN STRUCTURE tl_article
                DROP TABLE IF EXISTS `tl_article`;
                BACKUP,
        );

        $manager->restore(new RestoreConfig($backup));
    }

    public function testUnsuccessfulRestoreIfErrorDuringQuery(): void
    {
        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('Query wrong.');

        $backup = Backup::createNew();

        $this->vfs->write(
            $backup->getFilename(),
            <<<'BACKUP'
                -- Generated by the Contao Open Source CMS Backup Manager (version: v1).
                -- Generated at 2021-11-02T17:15:52+00:00
                SET NAMES utf8;
                BACKUP,
        );

        $connection = $this->mockConnection();
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Query wrong.'))
        ;

        $manager = $this->getBackupManager($connection);
        $manager->restore(new RestoreConfig($backup));
    }

    public function successfulRestoreProvider(): \Generator
    {
        $backup = Backup::createNew();

        yield 'Regular backup' => [
            <<<'BACKUP'
                -- Generated by the Contao Open Source CMS Backup Manager (version: v1).
                -- Generated at 2021-11-02T17:15:52+00:00
                SET NAMES utf8;
                SET FOREIGN_KEY_CHECKS = 0;

                -- BEGIN STRUCTURE tl_article
                DROP TABLE IF EXISTS `tl_article`;
                BACKUP,
            new RestoreConfig($backup),
            [
                ['SET NAMES utf8;'],
                ['SET FOREIGN_KEY_CHECKS = 0;'],
                ['DROP TABLE IF EXISTS `tl_article`;'],
            ],
        ];

        yield 'Gzip encoded backup' => [
            hex2bin('1f8b08000000000000034d8ccd8ac2301845f77d8abb5486485310a56516b67ed6a26da549615cd5d8c9ccc8482a31157c7b7f367a76f770398c21d5465be5f437f657b83f8da4334e75284fda4074bd6def2a178855fbdf9f902ba37eb5c5e0a2edf9d09910173e1c79ec3da41c023fe08c73e607924f423e0ec7c1877fc7132451cc7212e8ddcf347aee4559519616cd8ab64db2a46425f0093ff21ed598d2ac8090559dc8ba22b863a3ac3bb447edcdab7203398bd7846c01faca8414d8bd0ebbe806943e485bdf000000'),
            new RestoreConfig($backup),
            [
                ['SET NAMES utf8;'],
                ['SET FOREIGN_KEY_CHECKS = 0;'],
                ['DROP TABLE IF EXISTS `tl_article`;'],
            ],
        ];

        yield 'Backup without header but ignore origin check should be successful too' => [
            <<<'BACKUP'
                -- Generated at 2021-11-02T17:15:52+00:00
                SET NAMES utf8;
                SET FOREIGN_KEY_CHECKS = 0;

                -- BEGIN STRUCTURE tl_article
                DROP TABLE IF EXISTS `tl_article`;
                BACKUP,
            (new RestoreConfig($backup))->withIgnoreOriginCheck(true),
            [
                ['SET NAMES utf8;'],
                ['SET FOREIGN_KEY_CHECKS = 0;'],
                ['DROP TABLE IF EXISTS `tl_article`;'],
            ],
        ];
    }

    private function mockDumper(Connection $connection): DumperInterface&MockObject
    {
        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump')
            ->with($connection, $this->isInstanceOf(CreateConfig::class))
            ->willReturnCallback(
                static function () {
                    yield 'Dumper content line one';
                    yield 'Dumper content line two';
                },
            )
        ;

        return $dumper;
    }

    private function mockConnection(): Connection&MockObject
    {
        return $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethodsExcept(['transactional'])
            ->getMock()
        ;
    }

    private function getBackupManager(Connection|null $connection = null, DumperInterface|null $dumper = null, RetentionPolicyInterface|null $retentionPolicy = null): BackupManager
    {
        $connection ??= $this->createMock(Connection::class);
        $dumper ??= $this->createMock(DumperInterface::class);
        $retentionPolicy ??= new RetentionPolicy(5);

        return new BackupManager($connection, $dumper, $this->vfs, ['foobar'], $retentionPolicy);
    }
}
