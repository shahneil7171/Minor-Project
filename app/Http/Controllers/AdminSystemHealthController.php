<?php

namespace App\Http\Controllers;

use App\Services\BackupReadinessService;
use App\Services\SystemHealthService;
use Illuminate\View\View;

/**
 * Admin > System > System Health (Phase 4, Parts 15-19).
 *
 * A read-only diagnostics page. It inspects configuration and reports only
 * PRESENT/ABSENT states — never a secret value. Every check is non-mutating:
 * nothing is written, linked, migrated or deleted from this page.
 *
 * Backup creation and restore are NOT offered here. Creating a backup stays
 * the explicit admin action on Admin > System > Backup (which already
 * existed); this page only reports whether the store is ready to be backed
 * up and lists existing backup FILES by name, size and date.
 */
class AdminSystemHealthController extends Controller
{
    /**
     * The health dashboard.
     */
    public function index(
        SystemHealthService $health,
        BackupReadinessService $backups,
    ): View {
        $this->authorizeAdmin();

        $checks = $health->checks();
        $readiness = $backups->readiness();

        return view('admin.system.health', [
            'checks'     => $checks,
            'overall'    => $health->overall($checks),
            'grouped'    => $this->groupByGroup($checks),
            'readiness'  => $readiness,
            'isReady'    => $backups->isReady($readiness),
            'backups'    => $backups->existingBackups(),
            'hasGateway' => false,
        ]);
    }

    /**
     * Group checks by their card section for rendering.
     *
     * @param  array<string, array>  $checks
     * @return array<string, array<int, array>>
     */
    private function groupByGroup(array $checks): array
    {
        $grouped = [];

        foreach ($checks as $check) {
            $grouped[$check['group']][] = $check;
        }

        return $grouped;
    }

    /**
     * Only authenticated administrators may view system diagnostics.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
