<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Doctrine\Backup;

use Contao\CoreBundle\Doctrine\Backup\Config\CreateConfig;
use Contao\CoreBundle\Doctrine\Backup\Config\RestoreConfig;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class BackupManager
{
    public const DUMP_HEADER = '-- Generated by the Contao Open Source CMS Backup Manager (version: v1).';

    private Connection $connection;
    private DumperInterface $dumper;
    private VirtualFilesystemInterface $backupsStorage;
    private array $tablesToIgnore;
    private RetentionPolicyInterface $retentionPolicy;

    public function __construct(Connection $connection, DumperInterface $dumper, VirtualFilesystemInterface $backupsStorage, array $tablesToIgnore, RetentionPolicyInterface $retentionPolicy)
    {
        $this->connection = $connection;
        $this->dumper = $dumper;
        $this->backupsStorage = $backupsStorage;
        $this->tablesToIgnore = $tablesToIgnore;
        $this->retentionPolicy = $retentionPolicy;
    }

    public function createNewBackup(\DateTime $dateTime = null): Backup
    {
        $now = $dateTime ?? new \DateTime('now');
        $now->setTimezone(new \DateTimeZone('UTC'));

        $filename = sprintf('backup__%s.sql.gz', $now->format(Backup::DATETIME_FORMAT));

        return new Backup($filename);
    }

    public function createCreateConfig(): CreateConfig
    {
        $config = new CreateConfig($this->createNewBackup());

        return $config->withTablesToIgnore($this->tablesToIgnore);
    }

    public function createRestoreConfig(): RestoreConfig
    {
        $latestBackup = $this->getLatestBackup();

        if (null === $latestBackup) {
            throw new BackupManagerException('No backups found.');
        }

        $config = new RestoreConfig($latestBackup);

        return $config->withTablesToIgnore($this->tablesToIgnore);
    }

    public function getLatestBackup(): ?Backup
    {
        return $this->listBackups()[0] ?? null;
    }

    /**
     * Lists all backups (the latest one first).
     *
     * @return array<Backup>
     */
    public function listBackups(): array
    {
        $backups = [];

        foreach ($this->backupsStorage->listContents('', false, VirtualFilesystemInterface::BYPASS_DBAFS) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            try {
                $backup = new Backup($file->getPath());
            } catch (BackupManagerException $e) {
                continue;
            }

            $this->updateBackupWithSize($backup);
            $backups[] = $backup;
        }

        usort($backups, static fn (Backup $a, Backup $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $backups;
    }

    /**
     * @throws BackupManagerException
     */
    public function create(CreateConfig $config): void
    {
        $this->executeTransactional(fn () => $this->doCreate($config));
    }

    /**
     * @throws BackupManagerException
     *
     * @return resource
     */
    public function readStream(Backup $backup)
    {
        if (null === $this->getBackupByName($backup->getFilename())) {
            throw new BackupManagerException('Cannot read stream of a non-existent backup.');
        }

        return $this->backupsStorage->readStream($backup->getFilename());
    }

    public function getBackupByName(string $fileName): ?Backup
    {
        if (!$this->backupsStorage->fileExists($fileName)) {
            return null;
        }

        return new Backup($fileName);
    }

    /**
     * @throws BackupManagerException
     */
    public function restore(RestoreConfig $config): void
    {
        $this->executeTransactional(fn () => $this->doRestore($config));
    }

    private function updateBackupWithSize(Backup $backup): void
    {
        try {
            $backup->setSize($this->backupsStorage->getFileSize($backup->getFilename()));
        } catch (\Exception $e) {
            $backup->setSize(0);
        }
    }

    private function executeTransactional(\Closure $func): void
    {
        $isAutoCommit = $this->connection->isAutoCommit();

        if ($isAutoCommit) {
            $this->connection->setAutoCommit(false);
        }

        try {
            $this->connection->transactional($func);
        } catch (BackupManagerException $e) {
            throw $e;
        } catch (\Throwable $t) {
            throw new BackupManagerException($t->getMessage(), 0, $t);
        } finally {
            if ($isAutoCommit) {
                $this->connection->setAutoCommit(true);
            }
        }
    }

    private function doCreate(CreateConfig $config): void
    {
        $backup = $config->getBackup();

        // Ensure the target file exists and is empty
        $this->backupsStorage->write($backup->getFilename(), '');

        $tmpFile = (new Filesystem())->tempnam(sys_get_temp_dir(), 'ctobckupmgr');
        $fileHandle = fopen($tmpFile, 'r+w');
        $deflateContext = $config->isGzCompressionEnabled() ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 9]) : null;

        // Header lines
        $this->writeLine(self::DUMP_HEADER, $fileHandle, $deflateContext);
        $this->writeLine('-- Generated at '.$config->getBackup()->getCreatedAt()->format(\DateTimeInterface::ATOM), $fileHandle, $deflateContext);

        try {
            foreach ($this->dumper->dump($this->connection, $config) as $data) {
                $this->writeLine($data, $fileHandle, $deflateContext);
            }

            $this->finishWriting($backup, $fileHandle, $deflateContext);
            $this->tidyDirectory($config->getBackup());
            (new Filesystem())->remove($tmpFile);
        } catch (BackupManagerException $exception) {
            $this->backupsStorage->delete($backup->getFilename());
            (new Filesystem())->remove($tmpFile);

            throw $exception;
        }
    }

    /**
     * @param resource                 $fileHandle
     * @param \DeflateContext|resource $deflateContext
     */
    private function writeLine(string $data, $fileHandle, $deflateContext): void
    {
        $data .= PHP_EOL;

        if ($deflateContext) {
            $data = deflate_add($deflateContext, $data, ZLIB_NO_FLUSH);
        }

        @fwrite($fileHandle, $data);
        fflush($fileHandle);
    }

    /**
     * @param resource                 $fileHandle
     * @param \DeflateContext|resource $deflateContext
     */
    private function finishWriting(Backup $backup, $fileHandle, $deflateContext): void
    {
        if ($deflateContext) {
            fwrite($fileHandle, deflate_add($deflateContext, '', ZLIB_FINISH));
        }

        $this->backupsStorage->writeStream($backup->getFilename(), $fileHandle);
        fclose($fileHandle);

        $this->updateBackupWithSize($backup);
    }

    private function doRestore(RestoreConfig $config): void
    {
        $backup = $config->getBackup();

        if (!$this->backupsStorage->fileExists($backup->getFilename())) {
            throw new BackupManagerException(sprintf('Dump "%s" does not exist.', $backup->getFilename()));
        }

        $tmpFile = (new Filesystem())->tempnam(sys_get_temp_dir(), 'ctobckupmgr');
        $handle = fopen($tmpFile, 'w');
        stream_copy_to_stream($this->backupsStorage->readStream($backup->getFilename()), $handle);
        fclose($handle);
        $handle = gzopen($tmpFile, 'r');

        $currentQuery = '';
        $checkedForHeader = $config->ignoreOriginCheck();

        while ($line = gzgets($handle)) {
            $line = trim($line);

            if (!$checkedForHeader) {
                if (self::DUMP_HEADER !== $line) {
                    throw new BackupManagerException('The Contao database importer only supports dumps generated by Contao.');
                }

                $checkedForHeader = true;
                continue;
            }

            // Ignore comments
            if (0 === strpos($line, '--')) {
                continue;
            }

            $currentQuery .= $line;

            // Current query ends
            if (';' === substr($currentQuery, -1)) {
                $this->executeWrappedQuery($currentQuery);
                $currentQuery = '';
            }
        }

        if ('' !== $currentQuery) {
            $this->executeWrappedQuery($currentQuery);
        }
    }

    private function tidyDirectory(Backup $currentBackup): void
    {
        $allBackups = $this->listBackups();
        $backupsToKeep = $this->retentionPolicy->apply($currentBackup, $allBackups);

        foreach (array_diff($allBackups, $backupsToKeep) as $backup) {
            $this->backupsStorage->delete($backup->getFilename());
        }
    }

    private function executeWrappedQuery(string $query): void
    {
        try {
            $this->connection->executeQuery($query);
        } catch (\Exception $e) {
            throw new BackupManagerException($e->getMessage(), 0, $e);
        }
    }
}
