<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBackupController extends Controller
{
    /**
     * Tables included in a backup, in insert (parent → child) order.
     */
    private const TABLES = [
        'user_groups',
        'users',
        'addresses',
        'categories',
        'manufacturers',
        'product_options',
        'product_views',
        'coupons',
        'orders',
        'order_items',
        'reviews',
        'wishlist_items',
        'returns',
        'promotions',
        'newsletter_subscribers',
        'settings',
    ];

    /**
     * List available backups.
     */
    public function index()
    {
        $this->authorizeAdmin();

        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn ($file) => str_ends_with($file, '.json'))
            ->sortDesc()
            ->map(fn ($file) => [
                'path' => $file,
                'name' => basename($file),
                'size' => $disk->size($file),
                'modified' => $disk->lastModified($file),
            ])
            ->values();

        return view('admin.system.backup', compact('files'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin();
        abort_unless(auth()->user()->hasPermission('system.create'), 403);

        $snapshot = [
            'created_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            try {
                $snapshot['tables'][$table] = collect(\DB::table($table)->get())
                    ->map(fn ($row) => (array) $row)
                    ->all();
            } catch (\Throwable) {
                // Table may not exist yet on older installs — skip it.
                $snapshot['tables'][$table] = [];
            }
        }

        if (! Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }

        $filename = 'backup-' . now()->format('Ymd-His') . '.json';
        Storage::disk('local')->put('backups/' . $filename, json_encode($snapshot, JSON_PRETTY_PRINT));

        return redirect()->route('admin.backup.index')
            ->with('success', 'Backup created: ' . $filename);
    }

    public function download(string $filename): StreamedResponse
    {
        $this->authorizeAdmin();

        $path = 'backups/' . $this->safeName($filename);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    public function restore(Request $request, string $filename)
    {
        $this->authorizeAdmin();
        abort_unless(auth()->user()->hasPermission('system.delete'), 403);

        $path = 'backups/' . $this->safeName($filename);

        abort_unless(Storage::disk('local')->exists($path), 404);

        $snapshot = json_decode(Storage::disk('local')->get($path), true);
        $restored = [];

        \DB::transaction(function () use ($snapshot, &$restored) {
            // Delete children before parents to respect foreign keys.
            foreach (array_reverse(self::TABLES) as $table) {
                \DB::table($table)->delete();
            }

            foreach (self::TABLES as $table) {
                $rows = $snapshot['tables'][$table] ?? [];

                foreach (array_chunk($rows, 100) as $chunk) {
                    \DB::table($table)->insert($chunk);
                }

                $restored[$table] = count($rows);
            }
        });

        return redirect()->route('admin.backup.index')
            ->with('success', 'Backup restored (' . array_sum($restored) . ' rows across ' . count(array_filter($restored)) . ' tables).');
    }

    public function destroy(string $filename)
    {
        $this->authorizeAdmin();
        abort_unless(auth()->user()->hasPermission('system.delete'), 403);

        $path = 'backups/' . $this->safeName($filename);

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('admin.backup.index')->with('success', 'Backup deleted successfully.');
    }

    private function safeName(string $filename): string
    {
        return basename($filename);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
