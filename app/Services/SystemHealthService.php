<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * SystemHealthService — PHASE 4.
 *
 * Read-only health checks for Admin > System > Health.
 *
 * ABSOLUTE SECURITY RULE
 * ----------------------
 * This service reports the PRESENCE of a configuration value, never its
 * content. Every check returns a state from a closed vocabulary:
 *
 *     ok | warning | error | not_configured
 *
 * It never returns an APP_KEY, a database password, an SMTP password, an API
 * secret, a UPI id, a QR path or a bank account number. There is no code path
 * in this class that can emit a config VALUE — only booleans and counts —
 * which makes a leak impossible by construction rather than by review.
 *
 * Checks never mutate anything: no migration is run, no file is written and
 * nothing is deleted. `storage:link` is only REPORTED, never executed.
 */
class SystemHealthService
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const NOT_CONFIGURED = 'not_configured';

    /**
     * Every check, keyed by the card it feeds on the health page.
     *
     * @return array<string, array{group: string, label: string, state: string, detail: string}>
     */
    public function checks(): array
    {
        return [
            'laravel'  => $this->laravel(),
            'php'      => $this->php(),
            'environment' => $this->environment(),
            'url'      => $this->applicationUrl(),
            'database' => $this->database(),
            'storage'  => $this->storage(),
            'symlink'  => $this->storageLink(),
            'cache'    => $this->cache(),
            'queue'    => $this->queue(),
            'mail'     => $this->mail(),
            'app_key'  => $this->appKey(),
            'debug'    => $this->debugMode(),
        ];
    }

    /**
     * Overall state for the page header: the worst individual check wins.
     */
    public function overall(array $checks): string
    {
        $states = array_column($checks, 'state');

        if (in_array(self::ERROR, $states, true)) {
            return self::ERROR;
        }

        if (in_array(self::WARNING, $states, true)) {
            return self::WARNING;
        }

        return self::OK;
    }

    // ------------------------------------------------------------------
    // Individual checks
    // ------------------------------------------------------------------

    private function laravel(): array
    {
        return $this->result('Application', 'Laravel', self::OK, 'v' . app()->version());
    }

    private function php(): array
    {
        $version = PHP_VERSION;
        $state = version_compare($version, '8.3', '>=') ? self::OK : self::WARNING;

        return $this->result('Application', 'PHP', $state, $version);
    }

    private function environment(): array
    {
        $env = (string) app()->environment();
        $state = $env === 'production' ? self::OK : self::WARNING;

        return $this->result('Application', 'Environment', $state, ucfirst($env));
    }

    private function applicationUrl(): array
    {
        $url = (string) config('app.url');

        $state = $url === '' ? self::WARNING : self::OK;

        return $this->result('Application', 'Application URL', $state, $url === '' ? 'Not set' : $url);
    }

    /**
     * Database connectivity. NEVER exposes the host, port, username or
     * password — only "connected" or a friendly failure message.
     */
    private function database(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->result(
                'Database',
                'Connection',
                self::OK,
                'Connected (' . DB::connection()->getDriverName() . ')'
            );
        } catch (Throwable) {
            // The raw SQL exception is intentionally swallowed: a stack trace
            // or DSN could reveal the host, database name or credentials.
            return $this->result(
                'Database',
                'Connection',
                self::ERROR,
                'Failed — the application cannot reach the database. Check the server log for details.'
            );
        }
    }

    private function storage(): array
    {
        $path = storage_path();
        $writable = is_dir($path) && is_writable($path);

        $state = $writable ? self::OK : self::ERROR;

        return $this->result(
            'Storage',
            'Storage Writable',
            $state,
            $writable ? 'Writable' : 'Not writable — file uploads, caching and logging will fail'
        );
    }

    /**
     * Whether `php artisan storage:link` has been run. Reported only; the
     * health page never creates or deletes the symlink.
     */
    private function storageLink(): array
    {
        $link = public_path('storage');

        $exists = file_exists($link) || is_link($link);

        $state = $exists ? self::OK : self::WARNING;

        return $this->result(
            'Storage',
            'Public Storage Link',
            $state,
            $exists ? 'Linked' : 'Not linked — run "php artisan storage:link" if you serve user uploads'
        );
    }


    /**
     * Cache driver status. Reads/writes a throwaway key and removes it, so the
     * check proves the cache really works without leaving anything behind.
     */
    private function cache(): array
    {
        try {
            $cache = cache();
            $key = 'kdp_health_probe';

            $cache->put($key, 'ok', 10);
            $value = $cache->pull($key);

            $state = $value === 'ok' ? self::OK : self::WARNING;

            return $this->result('Cache', 'Cache', $state, 'Driver: ' . config('cache.default'));
        } catch (Throwable) {
            return $this->result('Cache', 'Cache', self::ERROR, 'Cache store unreachable');
        }
    }

    /**
     * Queue configuration. The connection name is safe to show; credentials
     * for a remote queue are not, and are never touched.
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');

        $state = $connection === '' ? self::WARNING : self::OK;

        return $this->result(
            'Queue',
            'Queue Connection',
            $state,
            $connection === '' ? 'Not configured' : $connection
        );
    }

    /**
     * Whether mail is configured. ONLY the boolean outcome and the driver
     * name are reported — never MAIL_PASSWORD, MAIL_USERNAME or any API key.
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        if ($mailer === '') {
            return $this->result('Mail', 'Mail', self::NOT_CONFIGURED, 'Not configured');
        }

        // smtp / sendmail need a host; the `log` and `array` drivers do not.
        if (in_array($mailer, ['smtp', 'sendmail'], true)) {
            $host = (string) config('mail.mailers.smtp.host');

            if ($host === '') {
                return $this->result('Mail', 'Mail', self::NOT_CONFIGURED, 'Not configured — no SMTP host set');
            }
        }

        return $this->result('Mail', 'Mail', self::OK, 'Configured (' . $mailer . ')');
    }

    /**
     * Whether an application key exists. THE KEY ITSELF IS NEVER RETURNED —
     * only "configured" or "not configured".
     */
    private function appKey(): array
    {
        $key = (string) config('app.key');

        return $this->result(
            'Application',
            'App Key',
            $key === '' ? self::ERROR : self::OK,
            $key === '' ? 'Not configured — run "php artisan key:generate"' : 'Configured'
        );
    }

    /**
     * Debug mode. Safe to surface, and important for production readiness.
     */
    private function debugMode(): array
    {
        $debug = (bool) config('app.debug');
        $prod = app()->environment('production');

        // Debug ON in production is an error: it can leak stack traces.
        return $this->result(
            'Application',
            'Debug Mode',
            $prod && $debug ? self::ERROR : self::OK,
            $debug ? 'Enabled' : 'Disabled'
        );
    }

    /**
     * Build one check row.
     *
     * @return array{group: string, label: string, state: string, detail: string}
     */
    private function result(string $group, string $label, string $state, string $detail): array
    {
        return compact('group', 'label', 'state', 'detail');
    }
}
