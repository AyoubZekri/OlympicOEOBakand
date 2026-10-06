<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A very light "has anything changed?" for the alerts: the page asks every few seconds,
 * and reloads its alerts only when the answer changes (no page refresh needed).
 */
class AlertsController extends Controller
{
    /** The tables the alerts are made from */
    private const TABLES = [
        'tasks',
        'task_status_history',
        'task_attachments',
        'disciplinary_cases',
        'disciplinary_actions',
        'individuals', // a member moved to another category: their sessions / matches change
        'teams',
        'training_sessions',
        'training_session_notices',
        'app_absences',
        'matches',
        'match_callups',
        'match_notices',
        'match_goals',
        'player_evaluations',
        'administrative_match_reports',
        'hearing_attendees',
        'department_meetings',
        'meeting_attendees',
        'meeting_decisions',
        'meeting_notices',
        'travel_itineraries',
        'travel_notices',
        'player_medical_records',
        'medical_notices',
        'debts',
        'debt_repayments',
        'payment_expenses', // the purchases on credit
        'credit_payments',
    ];

    public function version()
    {
        $parts = [];
        foreach ($this->tables() as $table => $hasUpdatedAt) {
            // Count + last id: rows added or deleted; last updated_at: rows edited
            try {
                $row = DB::table($table)->selectRaw(
                    'count(*) as c, max(id) as m' . ($hasUpdatedAt ? ', max(updated_at) as u' : '')
                )->first();
                $parts[] = $table . ':' . $row->c . ':' . $row->m . ':' . ($row->u ?? '');
            } catch (\Throwable $e) {
                // a table changed since it was listed: list again next time, the others still tell
                Cache::forget($this->tablesKey());
            }
        }

        return response()->json(['version' => md5(implode('|', $parts))]);
    }

    /** The cache key of the table list: a new table in TABLES lists them again at once */
    private function tablesKey(): string
    {
        return 'alerts.version.tables.' . md5(implode(',', self::TABLES));
    }

    /**
     * The existing tables, and whether each has updated_at (asked to the database every 5 minutes,
     * so a table created by a migration is watched soon after, without clearing the cache)
     */
    private function tables(): array
    {
        return Cache::remember($this->tablesKey(), 300, function () {
            $tables = [];
            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table)) {
                    $tables[$table] = Schema::hasColumn($table, 'updated_at');
                }
            }

            return $tables;
        });
    }
}
