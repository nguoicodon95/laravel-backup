<?php

namespace Spatie\Backup\BackupDestination;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Exception;
use Spatie\Backup\Exceptions\InvalidBackupDestination;

class BackupDestination
{
    /** @var \Illuminate\Contracts\Filesystem\Filesystem */
    protected $disk;

    /** @var string */
    protected $diskName;

    /** @var string */
    protected $backupName;

    /** @var Exception */
    public $connectionError;

    public function __construct(Filesystem $disk = null, string $backupName, string $diskName)
    {
        $this->disk = $disk;

        $this->diskName = $diskName;

        $this->backupName = preg_replace('/[^a-zA-Z0-9.]/', '-', $backupName);
    }

    public function disk(): Filesystem
    {
        return $this->disk;
    }

    public function diskName(): string
    {
        return $this->diskName;
    }

    public function filesystemType(): string
    {
        if (is_null($this->disk)) {
            return 'unknown';
        }

        $adapterClass = get_class($this->disk->getDriver()->getAdapter());

        $filesystemType = last(explode('\\', $adapterClass));

        return strtolower($filesystemType);
    }

    public static function create(string $diskName, string $backupName): BackupDestination
    {
        try {
            $disk = app(Factory::class)->disk($diskName);

            return new static($disk, $backupName, $diskName);
        } catch (Exception $exception) {
            $backupDestination = new static(null, $backupName, $diskName);

            $backupDestination->connectionError = $exception;

            return $backupDestination;
        }
    }

    public function write(string $file)
    {
        if (is_null($this->disk)) {
            throw InvalidBackupDestination::diskNotSet();
        }

        $destination = $this->backupName.'/'.pathinfo($file, PATHINFO_BASENAME);

        $handle = fopen($file, 'r+');

        $this->disk->getDriver()->writeStream($destination, $handle);
    }

    public function writeFilesFromManifestWithoutCreatingZipLocally(Manifest $manifest)
    {
        $destination = $this->backupName.'/'.'test'.date('Ymdhis').'.tar.gz';

        $stream = popen("cat {$manifest->getPath()} | zip @");

        $this->disk->getDriver()->writeStream($destination, $stream);
    }

    public function backupName(): string
    {
        return $this->backupName;
    }

    public function backups(): BackupCollection
    {
        $files = $this->isReachable() ? $this->disk->allFiles($this->backupName) : [];

        return BackupCollection::createFromFiles(
            $this->disk,
            $files
        );
    }

    public function connectionError(): Exception
    {
        return $this->connectionError;
    }

    public function isReachable(): bool
    {
        if (is_null($this->disk)) {
            return false;
        }

        try {
            $this->disk->allFiles($this->backupName);

            return true;
        } catch (Exception $exception) {
            $this->connectionError = $exception;

            return false;
        }
    }

    public function usedStorage(): int
    {
        return $this->backups()->size();
    }

    /**
     * @return \Spatie\Backup\BackupDestination\Backup|null
     */
    public function newestBackup()
    {
        return $this->backups()->newest();
    }

    /**
     * @return \Spatie\Backup\BackupDestination\Backup|null
     */
    public function oldestBackup()
    {
        return $this->backups()->oldest();
    }

    public function newestBackupIsOlderThan(Carbon $date): bool
    {
        $newestBackup = $this->newestBackup();

        if (is_null($newestBackup)) {
            return true;
        }

        return $newestBackup->date()->gt($date);
    }
}
