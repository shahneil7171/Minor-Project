<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin > System > Audit Logs (Phase 4, Part 14).
 *
 * Read-only view of the append-only administrative trail. No create, update
 * or delete action exists here on purpose: an audit trail an administrator
 * can edit is not an audit trail.
 *
 * AUTHORIZATION (defence in depth, Part 25)
 * --------------------------------------
 * 1. The route sits inside the existing `admin` middleware group (staff only).
 * 2. Every method re-checks server-side with authorizeAdmin() — hiding a menu
 *    link is never the protection.
 * 3. A customer, seller or delivery partner gets 403 (and is redirected to
 *    login when unauthenticated), never a partial view of the data.
 */
class AdminAuditLogController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * Filtered, paginated audit trail.
     */
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $action = (string) $request->get('action', 'all');
        $userId = (int) $request->get('user', 0);

        // Custom dates are parsed defensively — a bad value simply yields no
        // filter instead of a 500.
        $from = $this->parseDate($request->get('from'));
        $to = $this->parseDate($request->get('to'), endOfDay: true);

        $logs = AuditLog::query()
            ->with('user')
            ->action($action === 'all' ? null : $action)
            ->forUser($userId > 0 ? $userId : null)
            ->betweenDates($from, $to)
            ->latest('id')
            ->paginate(self::PER_PAGE);

        $logs->appends($request->query());

        return view('admin.system.audit-logs', [
            'logs'    => $logs,
            'action'  => $action,
            'userId'  => $userId,
            'from'    => $from?->format('Y-m-d'),
            'to'      => $to?->format('Y-m-d'),
            // The catalogue is the source of truth for the action filter.
            'actions' => AuditLogService::catalogue(),
            'users'   => $this->auditors(),
            'total'   => AuditLog::count(),
        ]);
    }

    /**
     * One audit entry in detail.
     */
    public function show(AuditLog $auditLog): View
    {
        $this->authorizeAdmin();

        $auditLog->load('user');

        return view('admin.system.audit-log', ['log' => $auditLog]);
    }

    /**
     * The users who have performed audited actions (for the User filter).
     *
     * Only id + name are selected — never a password or token.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function auditors()
    {
        return User::query()
            ->whereIn('account_type', ['admin', 'manager'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Parse an optional Y-m-d input.
     */
    private function parseDate(mixed $value, bool $endOfDay = false): ?\Illuminate\Support\Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', substr(trim($value), 0, 10));
        } catch (\Throwable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * Only authenticated administrators may read the audit trail.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
