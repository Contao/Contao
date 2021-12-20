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
use Contao\CoreBundle\Doctrine\Backup\Config\RetentionPolicy;
use Contao\CoreBundle\Doctrine\Backup\DumperInterface;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class BackupManagerTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new Filesystem())->remove($this->getBackupDir());
        (new Filesystem())->mkdir($this->getBackupDir());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove($this->getBackupDir());
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
        $backup = Backup::createNewAtPath($this->getBackupDir());

        $manager = $this->getBackupManager();
        $manager->createRestoreConfig();
        $config = $manager->createCreateConfig();

        $this->assertSame(['foobar'], $config->getTablesToIgnore());
        $this->assertSame($backup->getFilepath(), $config->getBackup()->getFilepath());
    }

    public function testListBackupsInCorrectOrder(): void
    {
        $backupPastWeek = Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-1 week'));
        $backupNow = Backup::createNewAtPath($this->getBackupDir());
        $backupTwoWeeksAgo = Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-2 weeks'));

        $manager = $this->getBackupManager();
        $backups = $manager->listBackups();

        $this->assertCount(3, $backups);
        $this->assertSame($backups[0]->getFilepath(), $backupNow->getFilepath());
        $this->assertSame($backups[1]->getFilepath(), $backupPastWeek->getFilepath());
        $this->assertSame($backups[2]->getFilepath(), $backupTwoWeeksAgo->getFilepath());

        $latestBackup = $manager->getLatestBackup();

        $this->assertSame($latestBackup->getFilepath(), $backupNow->getFilepath());
    }

    public function testIgnoresFilesThatAreNoBackups(): void
    {
        $manager = $this->getBackupManager();

        // Wrong file extension
        (new Filesystem())->dumpFile($this->getBackupDir().'/backup__20211101141254.zip', '');

        // In subfolder
        (new Filesystem())->dumpFile($this->getBackupDir().'/subfolder/backup__20211101141254.sql', '');

        $this->assertCount(0, $manager->listBackups());
    }

    /**
     * @dataProvider getAutocommitSettings
     */
    public function testSuccessfulCreate(bool $autoCommitEnabled): void
    {
        $connection = $this->mockConnection($autoCommitEnabled);
        $dumper = $this->mockDumper($connection);
        $manager = $this->getBackupManager($connection, $dumper);

        $backup = Backup::createNewAtPath(
            $this->getBackupDir(),
            \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-03T13:36:00+00:00')
        );

        $config = (new CreateConfig($backup))->withGzCompression(false);
        $manager->create($config);

        $this->assertSame(
            <<<'DUMP'
                -- Generated by the Contao Open Source CMS Backup Manager (version: v1).
                -- Generated at 2021-11-03T13:36:00+00:00
                Dumper content line one
                Dumper content line two

                DUMP,
            preg_replace('~\R~u', "\n", file_get_contents($backup->getFilepath()))
        );
    }

    public function testIsGzipEncodedIfEnabled(): void
    {
        $connection = $this->mockConnection(true);
        $dumper = $this->mockDumper($connection);

        $manager = $this->getBackupManager($connection, $dumper);
        $config = $manager->createCreateConfig();
        $manager->create($config);

        // Assert it's gzipped
        $this->assertSame(
            0,
            mb_strpos(file_get_contents($config->getBackup()->getFilepath()), "\x1f"."\x8b"."\x08", 0, 'US-ASCII')
        );
    }

    /**
     * @dataProvider getAutocommitSettings
     */
    public function testUnsuccessfulCreate(bool $autoCommitEnabled): void
    {
        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('Error!');

        $connection = $this->mockConnection($autoCommitEnabled);

        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump')
            ->with(
                $connection,
                $this->isInstanceOf(CreateConfig::class)
            )
            ->willThrowException(new BackupManagerException('Error!'))
        ;

        $manager = $this->getBackupManager($connection, $dumper);
        $config = $manager->createCreateConfig();
        $manager->create($config);

        $this->assertFalse((new Filesystem())->exists($config->getBackup()->getFilepath()));
    }

    public function getAutocommitSettings(): \Generator
    {
        yield [true];
        yield [false];
    }

    /**
     * @dataProvider retentionPolicyProvider
     *
     * @param array<\DateTime> $existingBackupDates
     * @param array<Backup>    $expectedRemainingBackups
     */
    public function testRetentionPolicy(array $existingBackupDates, \DateTime $newBackupDate, RetentionPolicy $retentionPolicy, array $expectedRemainingBackups): void
    {
        $connection = $this->mockConnection(true);
        $dumper = $this->mockDumper($connection);
        $manager = $this->getBackupManager($connection, $dumper, $retentionPolicy);

        foreach ($existingBackupDates as $backupDate) {
            Backup::createNewAtPath($this->getBackupDir(), $backupDate);
        }

        $config = new CreateConfig(Backup::createNewAtPath($this->getBackupDir(), $newBackupDate));

        $manager->create($config);

        $this->assertSame(
            $expectedRemainingBackups,
            array_map(fn (Backup $backup) => Path::makeRelative($backup->getFilepath(), $this->getBackupDir()), $manager->listBackups()),
        );
    }

    public function retentionPolicyProvider(): \Generator
    {
        yield 'Test should delete oldest when only keepMax is configured' => [
            [
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-15T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-14T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-13T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-12T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-11T13:36:00+00:00'),
            ],
            \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T13:36:00+00:00'),
            new RetentionPolicy(5),
            [
                'backup__20211116133600.sql.gz',
                'backup__20211115133600.sql.gz',
                'backup__20211114133600.sql.gz',
                'backup__20211113133600.sql.gz',
                'backup__20211112133600.sql.gz',
            ],
        ];

        yield 'Test keepMax configured to 0 does not clean up at all' => [
            [
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-15T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-14T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-13T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-12T13:36:00+00:00'),
            ],
            \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T13:36:00+00:00'),
            new RetentionPolicy(0),
            [
                'backup__20211116133600.sql.gz',
                'backup__20211115133600.sql.gz',
                'backup__20211114133600.sql.gz',
                'backup__20211113133600.sql.gz',
                'backup__20211112133600.sql.gz',
            ],
        ];

        yield 'Test keepMax configured to 0 and keepPeriods correctly keeps the correct backups' => [
            [
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T08:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T06:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-07T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-09-07T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-07T18:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-12T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-11T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-13T13:36:00+00:00'),
            ],
            \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T13:36:00+00:00'),
            new RetentionPolicy(0, [1, 7, 30]), // Should keep the latest plus the oldest of the periods 1 day ago, 7 days ago, 30 days ago
            [
                'backup__20211116133600.sql.gz', // This is the latest
                'backup__20211116063600.sql.gz', // This is the oldest for -1 day ago
                'backup__20211111133600.sql.gz', // This is the oldest for -7 days ago
                'backup__20211107133600.sql.gz', // This is the oldest for -30 days ago
            ],
        ];

        yield 'Test keepMax configured to 2 and keepPeriods correctly keeps the correct backups' => [
            [
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T08:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T06:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-07T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-09-07T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-07T18:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-12T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-11T13:36:00+00:00'),
                \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-13T13:36:00+00:00'),
            ],
            \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-11-16T13:36:00+00:00'),
            new RetentionPolicy(2, [1, 7, 30]), // Should keep the latest plus the oldest of the periods 1 day ago, 7 days ago, 30 days ago
            [
                'backup__20211116133600.sql.gz', // This is the latest
                'backup__20211116063600.sql.gz', // This is the oldest for -1 day ago
                // According to keepPeriods, we'd keep more backups but we are limited by the total
            ],
        ];
    }

    public function testDirectoryIsNotCleanedUpAfterUnsuccessfulCreate(): void
    {
        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump')
            ->willThrowException(new BackupManagerException('Error!'))
        ;

        $manager = $this->getBackupManager($this->mockConnection(true), $dumper);
        $config = $manager->createCreateConfig();

        Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-1 day'));
        Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-2 days'));
        Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-3 days'));
        Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-4 days'));
        $oldest = Backup::createNewAtPath($this->getBackupDir(), new \DateTime('-1 week'));

        try {
            $manager->create($config);
        } catch (BackupManagerException $exception) {
            // irrelevant for this test
        }

        $this->assertCount(5, $manager->listBackups());
        $this->assertSame($oldest->getFilepath(), $manager->listBackups()[4]->getFilepath());
    }

    /**
     * @dataProvider successfulRestoreProvider
     */
    public function testSuccessfulRestore(string $backupContent, RestoreConfig $config, array $expectedQueries): void
    {
        (new Filesystem())->dumpFile($config->getBackup()->getFilepath(), $backupContent);

        $connection = $this->mockConnection(true);
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
        $backup = Backup::createNewAtPath($this->getBackupDir());

        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage(sprintf('Dump does not exist at "%s".', $backup->getFilepath()));

        $manager = $this->getBackupManager($this->mockConnection(true));

        (new Filesystem())->remove($backup->getFilepath());

        $manager->restore(new RestoreConfig($backup));
    }

    public function testUnsuccessfulRestoreIfHeaderIsMissing(): void
    {
        $backup = Backup::createNewAtPath($this->getBackupDir());

        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('The Contao database importer only supports dumps generated by Contao.');

        $manager = $this->getBackupManager($this->mockConnection(true));

        (new Filesystem())->dumpFile(
            $backup->getFilepath(),
            <<<'BACKUP'
                -- Generated at 2021-11-02T17:15:52+00:00
                SET NAMES utf8;
                SET FOREIGN_KEY_CHECKS = 0;

                -- BEGIN STRUCTURE tl_article
                DROP TABLE IF EXISTS `tl_article`;
                BACKUP
        );

        $manager->restore(new RestoreConfig($backup));
    }

    public function testUnsuccessfulRestoreIfErrorDuringQuery(): void
    {
        $backup = Backup::createNewAtPath($this->getBackupDir());

        $this->expectException(BackupManagerException::class);
        $this->expectExceptionMessage('Query wrong.');

        $connection = $this->mockConnection(true);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Query wrong.'))
        ;

        $manager = $this->getBackupManager($connection);

        (new Filesystem())->dumpFile(
            $backup->getFilepath(),
            <<<'BACKUP'
                -- Generated by the Contao Open Source CMS Backup Manager (version: v1).
                -- Generated at 2021-11-02T17:15:52+00:00
                SET NAMES utf8;
                BACKUP
        );

        $manager->restore(new RestoreConfig($backup));
    }

    public function successfulRestoreProvider(): \Generator
    {
        $backup = Backup::createNewAtPath($this->getBackupDir());

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

    /**
     * @return DumperInterface&MockObject
     */
    private function mockDumper(Connection $connection): DumperInterface
    {
        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump')
            ->with(
                $connection,
                $this->isInstanceOf(CreateConfig::class)
            )
            ->willReturnCallback(
                static function () {
                    yield 'Dumper content line one';
                    yield 'Dumper content line two';
                }
            )
        ;

        return $dumper;
    }

    /**
     * @return Connection&MockObject
     */
    private function mockConnection(bool $autoCommitEnabled): Connection
    {
        $connection = $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethodsExcept(['transactional'])
            ->getMock()
        ;

        $connection
            ->expects($this->once())
            ->method('isAutoCommit')
            ->willReturn($autoCommitEnabled)
        ;

        if ($autoCommitEnabled) {
            $connection
                ->expects($this->exactly(2))
                ->method('setAutoCommit')
                ->withConsecutive(
                    [false],
                    [true],
                )
            ;
        }

        return $connection;
    }

    private function getBackupDir(): string
    {
        return Path::join($this->getTempDir(), 'backups');
    }

    private function getBackupManager(Connection $connection = null, DumperInterface $dumper = null, RetentionPolicy $retentionPolicy = null): BackupManager
    {
        $connection ??= $this->createMock(Connection::class);
        $dumper ??= $this->createMock(DumperInterface::class);
        $retentionPolicy ??= new RetentionPolicy(5);

        return new BackupManager($connection, $dumper, $this->getBackupDir(), ['foobar'], $retentionPolicy);
    }
}
