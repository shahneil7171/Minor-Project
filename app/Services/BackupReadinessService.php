<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * BackupReadinessService — PHASE 4.
 *
 * Answers "could this store be backed up safely right now?" without ever
 * performing a destructive restore.
 *
 * DELIBERATE NON-GOALS
 * --------------------
 * - It NEVER restores. Nothing here writes to the database, truncates a table
 *   or overwrites live data. Restoring stays an explicit, separate admin
 *   action on the existing Admin > System > Backup page.
 * - It NEVER exposes a backup's contents. Only file names, sizes and
 *   modified dates are surfaced.
 * - It NEVER serves a file from the public directory. Backups live on the
 *   private `local` disk (storage/app/private) and are only reachable through
 *   the permission-guarded download route.
 *
 * Configuration is reported as "Environment-managed": secrets belong in .env
 * / the hosting platform, never in a file this app writes.
 */
class BackupReadinessService
{
    /**
     * Readiness of every backup target.
     *
     * @return array<string, array{label: string, ready: bool, detail: string}>
     */
    public function readiness(): array
    {
        return [
            'database' => $this->database(),
            'uploads'  => $this->uploads(),
            'storage'  => $this->storage(),
            'config'   => $this->configuration(),
        ];
    }

    /**
     * True when every target is ready.
     *
     * @param  array<string, array{label: string, ready: bool, detail: string}>|null  $readiness
     */
    public function isReady(?array $readiness = null): bool
    {
        $readiness ??= $this->readiness();

        foreach ($readiness as $item) {
            if (! $item['ready']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Existing backup files (metadata only — never contents).
     *
     * @return array<int, array{name: string, size: int, modified: int}>
     */
    public function existingBackups(int $limit = 20): array
    {
        try {
            return collect(Storage::disk('local')->files('backups'))
                ->filter(fn ($file) => str_ends_with($file, '.json'))
                ->sortDesc()
                ->take($limit)
                ->map(fn ($file) => [
                    'name'     => basename($file),
                    'size'     => (int) Storage::disk('local')->size($file),
                    'modified' => (int) Storage::disk('local')->lastModified($file),
                ])
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Database connectivity only. No credentials are read or reported.
     */
    private function database(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'label' => 'Database',
                'ready' => true,
                'detail' => 'Connected — a dump can be taken. Driver: ' . DB::connection()->getDriverName(),
            ];
        } catch (Throwable) {
            return [
                'label'  => 'Database',
                'ready'  => false,
                'detail' => 'Not reachable — a backup cannot be created until the connection is restored.',
            ];
        }
    }

    /**
     * Uploaded product images / QR codes and the like.
     */
    private function uploads(): array
    {
        $path = public_path('uploads');
        $exists = is_dir($path);

        return [
            'label'  => 'Uploaded Files',
            'ready'  => $exists,
            'detail' => $exists
                ? 'Upload directory present — include public/uploads in your backup.'
                : 'No uploads directory yet (nothing has been uploaded).',
        ];
    }

    private function storage(): array
    {
        $path = storage_path();
        $writable = is_dir($path) && is_writable($path);

        return [
            'label'  => 'Storage',
            'ready'  => $writable,
            'detail' => $writable
                ? 'Writable — backups are written to the private local disk.'
                : 'Not writable — backups cannot be stored.',
        ];
    }

    /**
     * Secrets are environment-managed, so there is nothing to back up here
     * beyond the .env file itself (which this app never writes or exposes).
     */
    private function configuration(): array
    {
        $envFile = base_path('.env');

        return [
            'label'  => 'Configuration',
            'ready'  => is_file($envFile),
            'detail' => 'Environment-managed — keep .env under version control ignore and back it up separately.',
        ];
    }
}
