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
        'training_sessions',
        'training_session_notices',
        'app_absences',
        'matches',
        'match_callups',
        'match_notices',
        'department_meetings',
        'meeting_attendees',
        'meeting_decisions',
        'meeting_notices',
        'travel_itineraries',
    ];

    public function version()
    {
        $parts = [];
        foreach ($this->tables() as $table => $hasUpdatedAt) {
            // Count + last id: rows added or deleted; last updated_at: rows edited
            $row = DB::table($table)->selectRaw(
                'count(*) as c, max(id) as m' . ($hasUpdatedAt ? ', max(updated_at) as u' : '')
            )->first();
            $parts[] = $table . ':' . $row->c . ':' . $row->m . ':' . ($row->u ?? '');
        }

        return response()->json(['version' => md5(implode('|', $parts))]);
    }

    /** The existing tables, and whether each has updated_at (asked to the database once a day) */
    private function tables(): array
    {
        return Cache::remember('alerts.version.tables', 86400, function () {
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
