<?php

namespace App\Services\Alerts;

use Illuminate\Support\Carbon;

/**
 * The app's alerts (src/View/widget/TaskAlerts/alertRules.ts), worked out on the server for the Android app's
 * Firebase notifications: the same rules on the same lists, the same keys (one notification per key), each with
 * the page it opens. Times are the club's (Algeria). Each alert: key, title, heading, detail, link, space.
 * The tasks' new ones, their messages and their appointments' alert time are sent when they happen (TaskController,
 * TaskSchedule::pushArrived), so they are not here.
 */
class AlertRules
{
    private const TZ = 'Africa/Algiers';

    private Carbon $now;

    public function __construct(?Carbon $now = null)
    {
        $this->now = ($now ?? Carbon::now())->copy()->setTimezone(self::TZ);
    }

    // ── Helpers ──

    private function at(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value, self::TZ)->setTimezone(self::TZ);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Whole days from today to the day (0 today, 1 tomorrow, negative past) */
    private function daysTo(?string $value): ?int
    {
        $d = $this->at($value ? substr($value, 0, 10) : null);

        return $d ? (int) round(($d->copy()->startOfDay()->getTimestamp() - $this->now->copy()->startOfDay()->getTimestamp()) / 86400) : null;
    }

    private static function inDays(int $n): string
    {
        return match (true) {
            $n === 0 => 'اليوم',
            $n === 1 => 'غداً',
            $n === 2 => 'بعد يومين',
            $n < 0 => 'منذ أيام',
            default => "بعد {$n} أيام",
        };
    }

    private static function day(?Carbon $d): string
    {
        return $d ? $d->format('Y/m/d') : '';
    }

    private static function when(?Carbon $d): string
    {
        return $d ? $d->format('Y/m/d H:i') : '';
    }

    /** «خلال 3 ساعات» / «خلال 40 دقيقة» */
    private function countdown(Carbon $to): string
    {
        $minutes = max(1, (int) ceil(($to->getTimestamp() - $this->now->getTimestamp()) / 60));

        return $minutes >= 60 ? 'خلال ' . (int) floor($minutes / 60) . ' ساعة' : "خلال {$minutes} دقيقة";
    }

    private function since(?string $value): float
    {
        $d = $this->at($value);

        return $d ? $this->now->getTimestamp() - $d->getTimestamp() : INF;
    }

    private static function clip(?string $text, int $n = 60): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string) $text));

        return mb_strlen($t) > $n ? mb_substr($t, 0, $n) . '…' : $t;
    }

    private static function join(array $parts): string
    {
        return implode(' · ', array_filter($parts, fn ($p) => $p !== null && $p !== ''));
    }

    private static function alert(string $key, string $title, string $heading, string $detail, string $link, string $space): array
    {
        return compact('key', 'title', 'heading', 'detail', 'link', 'space');
    }

    private static function page(string $space, string $personal, string $management): string
    {
        return $space === 'personal' ? $personal : $management;
    }

    // ── Disciplinary actions ──

    private static function decided(array $a): bool
    {
        return trim((string) ($a['admin_notes'] ?? '')) !== '' || trim((string) ($a['decision_outcome'] ?? '')) !== '' || trim((string) ($a['decision_reasons'] ?? '')) !== '';
    }

    private static function replied(array $a): bool
    {
        return trim((string) ($a['player_statements'] ?? '')) !== '';
    }

    private static function discLink(array $a, string $space): string
    {
        return self::page($space, '/my-disciplinary', '/disciplinary') . '?action=' . $a['id'];
    }

    public function disciplinary(array $items): array
    {
        $out = [];
        foreach ($items as $a) {
            if (($a['status'] ?? '') === 'ملغى') {
                continue;
            }
            $date = $a['deadlineOrHearingDate'] ?? null;
            $left = $this->daysTo($date);
            $clar = ($a['actionType'] ?? '') === 'طلب توضيح';
            $hearing = ($a['actionType'] ?? '') === 'استدعاء جلسة';
            $open = in_array($a['status'] ?? '', ['مفتوح', 'متأخر'], true);
            $heading = $a['reason'] ?? ($a['actionType'] ?? 'إجراء تأديبي');
            $link = self::discLink($a, 'personal');
            if ($clar && !self::replied($a) && !self::decided($a) && $left !== null && $left < 0) {
                $out[] = self::alert("disc:late:{$a['id']}:{$date}", 'تأخرت في الرد على طلب التوضيح', $heading, 'انتهى الأجل ' . self::day($this->at($date)), $link, 'personal');
            } elseif ($clar && !self::replied($a) && !self::decided($a) && $left !== null && $left <= 2) {
                $out[] = self::alert("disc:due:{$a['id']}:{$date}", 'اقترب أجل الرد على طلب التوضيح', $heading, 'آخر أجل ' . self::inDays($left), $link, 'personal');
            } elseif ($hearing && !self::decided($a) && $left !== null && $left >= 0 && $left <= 2) {
                $out[] = self::alert("disc:hearing:{$a['id']}:{$date}", 'اقترب موعد جلسة الاستماع', $heading, 'الجلسة ' . self::inDays($left), $link, 'personal');
            } elseif (self::decided($a)) {
                $out[] = self::alert("disc:decision:{$a['id']}", 'صدر القرار في الإجراء الخاص بك', $heading, self::join([$a['actionType'] ?? '', $a['decision_outcome'] ?? '']), $link, 'personal');
            } elseif ($open) {
                $out[] = self::alert("disc:{$a['id']}", 'تم إنشاء محضر خاص بك', $heading, (string) ($a['actionType'] ?? ''), $link, 'personal');
            }
        }

        return $out;
    }

    public function managerDisciplinary(array $items): array
    {
        $out = [];
        foreach ($items as $a) {
            if (($a['status'] ?? '') === 'ملغى') {
                continue;
            }
            $date = $a['deadlineOrHearingDate'] ?? null;
            $left = $this->daysTo($date);
            $who = $a['memberName'] ?? 'العضو';
            $clar = ($a['actionType'] ?? '') === 'طلب توضيح';
            $hearing = ($a['actionType'] ?? '') === 'استدعاء جلسة';
            $heading = $a['reason'] ?? ($a['actionType'] ?? 'إجراء تأديبي');
            $link = self::discLink($a, 'management');
            if ($clar && !self::replied($a) && !self::decided($a) && $left !== null && $left < 0) {
                $out[] = self::alert("mgr:late:{$a['id']}:{$date}", 'تأخر عضو في الرد على طلب توضيح', $heading, "{$who} · انتهى الأجل", $link, 'management');
            } elseif ($clar && self::replied($a) && !self::decided($a)) {
                $out[] = self::alert("mgr:replied:{$a['id']}", 'ردّ عضو على طلب التوضيح', $heading, "{$who} · بانتظار القرار", $link, 'management');
            } elseif ($hearing && !self::decided($a) && $left !== null && $left >= 0 && $left <= 2) {
                $out[] = self::alert("mgr:hearing:{$a['id']}:{$date}", 'جلسة استماع قريبة', $heading, "{$who} · " . self::inDays($left), $link, 'management');
            } elseif (self::decided($a) && empty($a['signed_document'])) {
                $out[] = self::alert("mgr:doc:{$a['id']}", 'بانتظار رفع الوثيقة الممضاة', $heading, $who, $link, 'management');
            }
        }

        return $out;
    }

    public function officerHearings(array $items): array
    {
        $out = [];
        foreach ($items as $h) {
            if (($h['status'] ?? '') === 'ملغى') {
                continue;
            }
            $date = $h['deadlineOrHearingDate'] ?? null;
            $left = $this->daysTo($date);
            $link = "/disciplinary?action={$h['id']}";
            $who = 'اللاعب: ' . ($h['memberName'] ?? 'العضو');
            if ($left !== null && $left >= 0 && $left <= 2) {
                $out[] = self::alert("disc:officer-soon:{$h['id']}:{$date}", 'اقترب موعد الجلسة التي تديرها', $h['reason'] ?? 'جلسة استماع', self::join([$who, self::inDays($left), $h['hearingTime'] ?? '']), $link, 'management');
            } else {
                $out[] = self::alert("disc:officer:{$h['id']}:" . ($h['hearingOfficer'] ?? ''), 'تم تعيينك مسؤولاً عن جلسة استماع', $h['reason'] ?? 'جلسة استماع', self::join([$who, self::day($this->at($date))]), $link, 'management');
            }
        }

        return $out;
    }

    // ── Training sessions ──

    private function sessionAt(array $s, ?string $time): ?Carbon
    {
        $day = explode('T', (string) ($s['date'] ?? ''))[0];

        return $day && $time ? $this->at("{$day} {$time}") : null;
    }

    public function training(array $sessions, string $space, bool $canAttend = false): array
    {
        $out = [];
        foreach ($sessions as $s) {
            if (empty($s['id']) || empty($s['date']) || empty($s['start']) || empty($s['end'])) {
                continue;
            }
            $start = $this->sessionAt($s, $s['start']);
            $end = $this->sessionAt($s, $s['end']);
            if (!$start || !$end) {
                continue;
            }
            $raw = trim((string) ($s['status'] ?? ''));
            $status = in_array($raw, ['ملغاة', 'مكتملة', 'جارية'], true) ? $raw
                : ($this->now->lt($start) ? 'مجدولة' : ($this->now->lte($end) ? 'جارية' : 'مكتملة'));
            if ($status === 'ملغاة') {
                continue;
            }
            $team = $s['team_name'] ?? 'حصة تدريبية';
            $heading = $space === 'personal' ? "حصة تدريب {$team}" : "حصة تدريب · {$team}";
            $base = self::page($space, '/my-training', '/training-sessions');
            $make = fn (string $kind, string $title, string $detail, bool $attendance = false) => self::alert(
                "train:{$space}:{$s['id']}:{$kind}:{$s['date']}:{$s['start']}", $title, $heading, $detail,
                $attendance ? "/training-sessions/{$s['id']}/attendance" : "{$base}?session={$s['id']}", $space,
            );
            $time = "{$s['start']} – {$s['end']}";
            if ($status === 'جارية') {
                $out[] = $space === 'management' && $canAttend
                    ? $make('train_live', 'بدأت حصة التدريب', "جارية الآن حتى {$s['end']} · سجّل الحضور", true)
                    : $make('train_live', 'بدأت حصة التدريب', self::join(["جارية الآن حتى {$s['end']}", $s['location'] ?? '']));
            } elseif ($status === 'مجدولة' && $start->gt($this->now) && $start->getTimestamp() - $this->now->getTimestamp() <= 3 * 3600) {
                $out[] = $make('train_soon', 'اقترب موعد حصة التدريب', self::join(['تبدأ ' . $this->countdown($start), $time, $s['location'] ?? '']));
            } elseif ($status === 'مكتملة' && $this->now->getTimestamp() - $end->getTimestamp() <= 3 * 3600) {
                if ($space === 'management') {
                    if (empty($s['attendance_taken']) && $canAttend) {
                        $out[] = $make('train_ended', 'لم يُسجل حضور حصة التدريب', 'انتهت الحصة: سجّل الحضور والغياب', true);
                    }
                } else {
                    $mine = trim((string) ($s['my_absence'] ?? ''));
                    $out[] = $make('train_ended', 'انتهت حصة التدريب', $mine ? "حضوري: {$mine}" : $time);
                }
            }
        }

        return $out;
    }

    public function trainingNotices(array $notices): array
    {
        return array_map(function ($n) {
            $when = self::join([$n['date'] ?? '', isset($n['start'], $n['end']) ? "{$n['start']} – {$n['end']}" : ($n['start'] ?? '')]);
            [$title, $detail] = match ($n['kind'] ?? '') {
                'cancelled', 'deleted' => ['تم إلغاء حصة التدريب', $when],
                'updated' => ['تم تعديل موعد حصة التدريب', self::join([$when, $n['location'] ?? ''])],
                'restored' => ['أُعيدت برمجة حصة التدريب', self::join([$when, $n['location'] ?? ''])],
                default => ['تمت برمجة حصة تدريبية', self::join([$when, $n['location'] ?? ''])],
            };

            return self::alert("tnotice:{$n['id']}", $title, trim('حصة تدريب ' . ($n['team_name'] ?? '')), $detail, "/my-training?session={$n['session_id']}", 'personal');
        }, $notices);
    }

    // ── Matches ──

    private function matchDate(array $m): ?Carbon
    {
        return $this->at($m['match_date'] ?? null);
    }

    private static function hasScore(array $m): bool
    {
        return isset($m['team_score'], $m['opponent_score']) && $m['team_score'] !== '' && $m['opponent_score'] !== '';
    }

    private function matchState(array $m): string
    {
        if (($m['match_status'] ?? '') === 'ملغاة') {
            return 'cancelled';
        }
        if (($m['match_status'] ?? '') === 'مؤجلة') {
            return 'postponed';
        }
        if (self::hasScore($m)) {
            return 'finished';
        }
        $date = $this->matchDate($m);
        if (!$date) {
            return 'upcoming';
        }
        $hours = ($this->now->getTimestamp() - $date->getTimestamp()) / 3600;

        return $hours >= 0 && $hours <= 2.5 ? 'live' : ($hours > 2.5 ? 'awaiting' : 'upcoming');
    }

    private static function opponent(array $m): string
    {
        return $m['opponent'] ?? ($m['opponentClub']['name'] ?? ($m['opponent_club']['name'] ?? 'خصم غير محدد'));
    }

    public function matches(array $matches, string $space, array $can = []): array
    {
        $manager = !empty($can['callups']) || !empty($can['lineup']) || !empty($can['attendance']) || !empty($can['report']);
        if ($space === 'management' && !$manager) {
            return [];
        }
        $out = [];
        foreach ($matches as $m) {
            $date = $this->matchDate($m);
            $state = $this->matchState($m);
            if (!$date || in_array($state, ['cancelled', 'postponed'], true)) {
                continue;
            }
            $vs = 'ضد ' . self::opponent($m);
            $heading = $space === 'personal' ? "مباراة {$vs}" : self::join(["مباراة {$vs}", $m['team']['name'] ?? '']);
            $base = self::page($space, '/my-matches', '/matches');
            $push = function (string $kind, string $title, string $detail, bool $attendance = false) use (&$out, $m, $space, $heading, $base) {
                $out[] = self::alert("match:{$space}:{$m['id']}:{$kind}:{$m['match_date']}", $title, $heading, $detail,
                    $attendance ? "/matches/{$m['id']}/attendance" : "{$base}?match={$m['id']}", $space);
            };
            $left = $date->getTimestamp() - $this->now->getTimestamp();
            $when = self::when($date);
            $soon = self::join(['تنطلق ' . ($left > 0 ? $this->countdown($date) : ''), $m['location'] ?? '']);

            if ($space === 'personal') {
                if (!empty($m['my_callup']) && $state === 'upcoming') {
                    $push('match_callup', 'تم استدعاؤك للمباراة', self::join([!empty($m['my_callup']['is_starter']) ? 'أساسي' : 'احتياط', $when]));
                }
                if ($state === 'live') {
                    $push('match_live', 'المباراة جارية الآن', self::join([$m['competition'] ?? '', $m['location'] ?? '']));
                } elseif ($state === 'upcoming' && $left <= 24 * 3600) {
                    $push('match_soon', 'اقترب موعد المباراة', $soon);
                } elseif ($state === 'finished' && -$left <= 24 * 3600) {
                    $push('match_ended', 'انتهت المباراة', "{$m['team_score']} - {$m['opponent_score']}");
                }
                continue;
            }

            $counted = array_key_exists('callups_count', $m) && $m['callups_count'] !== null;
            $callups = (int) ($m['callups_count'] ?? 0);
            if ($state === 'upcoming') {
                if (!empty($can['callups']) && $counted && $callups === 0 && $left <= 48 * 3600) {
                    $push('match_no_callups', 'لم يتم استدعاء اللاعبين بعد', "المباراة {$when}");
                } elseif (!empty($can['lineup']) && $counted && $callups > 0 && (int) ($m['starters_count'] ?? 0) === 0 && $left <= 90 * 60) {
                    $push('match_no_lineup', 'حان وقت تحديد التشكيلة الأساسية', "{$callups} لاعب مستدعى");
                }
                if ($left <= 24 * 3600) {
                    $push('match_soon', 'اقترب موعد المباراة', $soon);
                }
                continue;
            }
            if ($state === 'live') {
                $push('match_live', 'المباراة جارية الآن', self::join([$m['competition'] ?? '', $m['location'] ?? '']));
            }
            if (-$left > 7 * 86400) {
                continue;
            }
            if (!empty($can['attendance']) && $counted && empty($m['attendance_taken_at'])) {
                $push('match_no_attendance', 'لم يُسجل حضور المباراة', 'لُعبت ' . self::day($date) . ' · سجّل الحضور والغياب', true);
            }
            if (empty($can['report'])) {
                continue;
            }
            if ($state === 'awaiting') {
                $push('match_awaiting', 'بانتظار تسجيل نتيجة المباراة', 'لُعبت ' . self::day($date));
            } elseif ($state === 'finished' && $counted) {
                if ($callups > 0 && (int) ($m['rated_count'] ?? 0) === 0) {
                    $push('match_no_ratings', 'لم يُقيَّم أداء اللاعبين بعد', "{$callups} لاعب مستدعى");
                }
                if ((int) ($m['reports_count'] ?? 0) === 0) {
                    $push('match_no_report', 'لم يُحرر تقرير المباراة بعد', "النتيجة {$m['team_score']} - {$m['opponent_score']}");
                }
            }
        }

        return $out;
    }

    public function matchNotices(array $notices): array
    {
        return array_map(function ($n) {
            $when = self::when($this->at($n['match_date'] ?? null));
            [$title, $detail] = match ($n['kind'] ?? '') {
                'cancelled', 'deleted' => ['تم إلغاء المباراة', $when],
                'postponed' => ['تم تأجيل المباراة', $when ? "كانت مبرمجة {$when}" : 'الموعد الجديد يحدد لاحقاً'],
                'updated' => ['تم تعديل موعد المباراة', self::join([$when, $n['location'] ?? ''])],
                'restored' => ['أُعيدت برمجة المباراة', self::join([$when, $n['location'] ?? ''])],
                default => ['تمت برمجة مباراة', self::join([$n['competition'] ?? '', $when, $n['location'] ?? ''])],
            };

            return self::alert("mnotice:{$n['id']}", $title, 'مباراة ضد ' . ($n['opponent'] ?: 'خصم غير محدد'), $detail, "/my-matches?match={$n['match_id']}", 'personal');
        }, $notices);
    }

    // ── Absences ──

    private static function absenceType(?string $type): string
    {
        $t = (string) $type;

        return match (true) {
            str_contains($t, 'عطلة') => 'طلب عطلة',
            str_contains($t, 'تأخر') => 'تأخر',
            str_contains($t, 'مغادرة') => 'مغادرة',
            default => 'غياب',
        };
    }

    private static function absenceStatus(array $a): string
    {
        $s = (string) ($a['justification_status'] ?? '');

        return match ($s) {
            'مقبول', 'accepted' => 'accepted',
            'مرفوض', 'rejected' => 'rejected',
            'قيد_الدراسة', 'pending' => 'pending',
            default => (!empty($a['reason']) || !empty($a['attachment_url'])) ? 'pending' : 'none',
        };
    }

    private static function isRequest(array $a): bool
    {
        return self::absenceType($a['absence_type'] ?? '') === 'طلب عطلة' || ($a['record_source'] ?? '') === 'طلب العضو';
    }

    private static function absenceDate(array $a): string
    {
        return (string) ($a['event_date'] ?? ($a['session_date'] ?? ''));
    }

    private static function recordTitle(array $a): string
    {
        $type = self::absenceType($a['absence_type'] ?? '');

        return ($a['record_source'] ?? '') === 'طلب العضو' && $type !== 'طلب عطلة' ? "إعلام ب{$type}" : $type;
    }

    private function absenceWhen(array $a): string
    {
        return self::join([$a['event_category'] ?? '', substr(self::absenceDate($a), 0, 10)]);
    }

    /** Unjustified absences of the last 30 days, 3 or more by member */
    private function repeated(array $records): array
    {
        $by = [];
        foreach ($records as $a) {
            $d = $this->daysTo(self::absenceDate($a));
            if (self::absenceType($a['absence_type'] ?? '') !== 'غياب' || self::isRequest($a)) {
                continue;
            }
            if (!in_array(self::absenceStatus($a), ['none', 'rejected'], true) || $d === null || $d > 0 || $d < -30) {
                continue;
            }
            $by[(string) ($a['player_id'] ?? '')][] = $a;
        }

        return array_values(array_filter($by, fn ($list) => count($list) >= 3));
    }

    public function absences(array $records): array
    {
        $out = [];
        foreach ($records as $a) {
            $type = self::absenceType($a['absence_type'] ?? '');
            $status = self::absenceStatus($a);
            $request = self::isRequest($a);
            $when = $this->absenceWhen($a) ?: 'بدون تاريخ';
            $link = "/my-absences?absence={$a['id']}";
            if (in_array($status, ['accepted', 'rejected'], true) && $this->since($a['decision_date'] ?? null) <= 7 * 86400) {
                $ok = $status === 'accepted';
                $why = trim((string) ($a['decision_note'] ?? ''));
                $key = "abs:dec:{$a['id']}:{$status}:" . ($a['decision_date'] ?? '');
                $out[] = $request
                    ? self::alert($key, $ok ? 'تم قبول طلبك' : 'تم رفض طلبك', self::recordTitle($a) . " · {$when}", $ok ? ($a['duration'] ?? 'تمت الموافقة على طلبك') : ($why ? "السبب: {$why}" : 'لم تتم الموافقة على طلبك'), $link, 'personal')
                    : self::alert($key, $ok ? 'تم قبول تبريرك' : 'تم رفض تبريرك', "{$type} · {$when}", $ok ? 'أصبح مبرراً' : ($why ? "السبب: {$why}" : 'يبقى غير مبرر'), $link, 'personal');
            }
            $until = $this->at($a['justify_until'] ?? null);
            $left = $until ? $until->getTimestamp() - $this->now->getTimestamp() : null;
            if ($status === 'none' && $left !== null && $left > 0) {
                $out[] = self::alert("abs:new:{$a['id']}:" . ($a['absence_type'] ?? '') . ':' . ($a['justify_until'] ?? ''), "سُجل عليك {$type}", $when, 'قدّم تبريرك (وثيقة أو نص) خلال 24 ساعة', $link, 'personal');
            } elseif ($status === 'none' && !$request && $left !== null && $left <= 0 && -$left <= 86400) {
                $out[] = self::alert("abs:expired:{$a['id']}:" . ($a['justify_until'] ?? ''), 'انتهت مهلة التبرير', "{$type} · {$when}", "يبقى {$type} غير مبرر", $link, 'personal');
            }
            if ($status === 'pending' && !$request && ($a['justified_by'] ?? '') === 'administration') {
                $out[] = self::alert("abs:admin:{$a['id']}:" . mb_strlen((string) ($a['reason'] ?? '')) . ':' . ($a['attachment_url'] ?? ''), 'قدّمت الإدارة تبريراً عنك', "{$type} · {$when}", 'بانتظار القرار', $link, 'personal');
            }
            if ($status === 'accepted' && $request) {
                $d = $this->daysTo(self::absenceDate($a));
                if ($d === 0 || $d === 1) {
                    $out[] = self::alert("abs:soon:{$a['id']}:" . self::absenceDate($a), $type === 'طلب عطلة' ? 'تبدأ عطلتك ' . self::inDays($d) : self::recordTitle($a) . ' ' . self::inDays($d), self::recordTitle($a) . " · {$when}", (string) ($a['duration'] ?? 'تمت الموافقة عليه'), $link, 'personal');
                }
            }
        }
        foreach ($this->repeated($records) as $items) {
            $n = count($items);
            $out[] = self::alert("abs:repeat:{$n}:" . implode('-', array_column($items, 'id')), "لديك {$n} غيابات غير مبررة خلال 30 يوماً", "{$n} غيابات غير مبررة", 'قد يؤدي تكرار الغياب إلى إجراء تأديبي', "/my-absences?absence={$items[0]['id']}", 'personal');
        }

        return $out;
    }

    public function managerAbsences(array $records, bool $canDecide): array
    {
        $out = [];
        if ($canDecide) {
            foreach ($records as $a) {
                if (self::absenceStatus($a) !== 'pending') {
                    continue;
                }
                $request = self::isRequest($a);
                $d = $this->daysTo(self::absenceDate($a));
                $soon = $request && $d !== null && $d <= 1;
                $out[] = self::alert("abs:pending:{$a['id']}:" . mb_strlen((string) ($a['reason'] ?? '')) . ':' . ($soon ? 'soon' : ''),
                    $request ? self::recordTitle($a) . ' بانتظار القرار' : 'تبرير بانتظار القرار', $a['player_name'] ?? 'عضو',
                    self::join([$soon && $d !== null ? 'يبدأ ' . self::inDays($d) : '', self::absenceType($a['absence_type'] ?? '') . ' · ' . $this->absenceWhen($a), self::clip($a['reason'] ?? '', 40)]),
                    '/absence-requests?tab=requests', 'management');
            }
        }
        $today = $this->now->format('Y-m-d');
        foreach ($records as $a) {
            if (self::absenceStatus($a) !== 'accepted' || !self::isRequest($a)) {
                continue;
            }
            preg_match('/\d{4}-\d{2}-\d{2}/', (string) ($a['duration'] ?? ''), $m);
            $from = $this->daysTo(self::absenceDate($a));
            $to = $this->daysTo($m[0] ?? self::absenceDate($a));
            if ($from === null || $to === null || $from > 0 || $to < 0) {
                continue;
            }
            $leave = self::absenceType($a['absence_type'] ?? '') === 'طلب عطلة';
            $out[] = self::alert("abs:today:{$a['id']}:{$today}", $leave ? 'في عطلة اليوم' : 'غياب معلن اليوم', $a['player_name'] ?? 'عضو',
                self::join([$a['event_category'] ?? '', $a['duration'] ?? '']) ?: 'بإعلام مسبق مقبول', '/absence-requests?tab=registry', 'management');
        }
        foreach ($this->repeated($records) as $items) {
            $n = count($items);
            $out[] = self::alert("abs:mrepeat:{$items[0]['player_id']}:{$n}:" . implode('-', array_column($items, 'id')), 'تكرر الغياب بدون تبرير', $items[0]['player_name'] ?? 'عضو',
                "{$n} غيابات غير مبررة خلال 30 يوماً", '/absence-requests?tab=registry', 'management');
        }

        return $out;
    }

    // ── Meetings and decisions ──

    private function meetStart(array $m): ?Carbon
    {
        $date = substr((string) ($m['date'] ?? ''), 0, 10);

        return $date ? $this->at($date . ' ' . (($m['time'] ?? '') ?: '00:00')) : null;
    }

    public function meetings(array $meetings, bool $sees, bool $seesDecisions): array
    {
        $out = [];
        foreach ($meetings as $m) {
            $start = $this->meetStart($m);
            $link = "/my-meetings?meeting={$m['id']}";
            $topic = $m['topic'] ?? 'اجتماع';
            if ($sees && $start && $start->gt($this->now)) {
                $out[] = self::alert("meet:inv:{$m['id']}", 'تمت دعوتك لاجتماع', $topic, self::join([self::when($start), $m['location'] ?? '']), $link, 'personal');
                $left = $start->getTimestamp() - $this->now->getTimestamp();
                if ($left <= 24 * 3600) {
                    $last = !empty($m['can_propose']) && $left <= 3 * 3600;
                    $out[] = self::alert("meet:soon:{$m['id']}:{$m['date']}:" . ($m['time'] ?? ''), 'اقترب موعد الاجتماع', $topic,
                        self::join(['يبدأ ' . $this->countdown($start), $last ? 'آخر فرصة لإرسال نقاطك' : ($m['location'] ?? '')]), $link, 'personal');
                }
                foreach ($m['points'] ?? [] as $p) {
                    if (!empty($p['author']) && empty($p['mine']) && !empty($p['id']) && $this->since($p['created_at'] ?? null) <= 48 * 3600) {
                        $out[] = self::alert("meet:pt:{$p['id']}", "أرسل {$p['author']} نقطة للنقاش", $topic, self::clip($p['text'] ?? ''), $link, 'personal');
                    }
                }
            } elseif ($sees && $start && $this->now->getTimestamp() - $start->getTimestamp() <= 2 * 3600) {
                $out[] = self::alert("meet:live:{$m['id']}:{$m['date']}:" . ($m['time'] ?? ''), 'الاجتماع جارٍ الآن', $topic, (string) ($m['location'] ?? ''), $link, 'personal');
            }
            if (!$seesDecisions) {
                continue;
            }
            foreach ($m['decisions'] ?? [] as $d) {
                if (empty($d['mine']) || (int) ($d['progress'] ?? 0) >= 100) {
                    continue;
                }
                $title = self::clip(trim((string) ($d['text'] ?? '')) ?: ($d['category'] ?? 'قرار'));
                $out[] = self::alert("dec:as:{$d['id']}", 'كُلفت بقرار', $title, "من اجتماع: {$topic}", $link, 'personal');
                $days = !empty($d['deadline']) ? $this->daysTo($d['deadline']) : null;
                if ($days !== null && $days < 0) {
                    $out[] = self::alert("dec:late:{$d['id']}:{$d['deadline']}", 'قرار مكلف به متأخر', $title, 'انتهى الأجل · ' . (int) ($d['progress'] ?? 0) . '%', $link, 'personal');
                } elseif ($days !== null && $days <= 2) {
                    $out[] = self::alert("dec:due:{$d['id']}:{$d['deadline']}", 'اقترب أجل قرار مكلف به', $title, 'آخر أجل ' . self::inDays($days), $link, 'personal');
                }
            }
        }

        return $out;
    }

    public function managerMeetings(array $meetings, array $decisions, array $can, $me, array $invited): array
    {
        $out = [];
        $invited = array_map('strval', $invited);
        $byMeeting = [];
        foreach ($decisions as $d) {
            $byMeeting[(string) ($d['meeting_id'] ?? '')][] = $d;
        }
        foreach ($meetings as $m) {
            $start = $this->meetStart($m);
            if (!$start) {
                continue;
            }
            $topic = $m['topic'] ?? 'اجتماع';
            $link = "/meetings?meeting={$m['id']}";
            if ($start->gt($this->now)) {
                if (!empty($can['meetings']) && !in_array((string) $m['id'], $invited, true)) {
                    foreach ($m['points'] ?? [] as $p) {
                        if (!is_array($p) || empty($p['id']) || (string) ($p['user_id'] ?? '') === (string) $me) {
                            continue;
                        }
                        if ($this->since($p['created_at'] ?? null) <= 48 * 3600) {
                            $out[] = self::alert("mmeet:pt:{$p['id']}", 'نقطة جديدة من ' . ($p['author'] ?? 'عضو'), $topic, self::clip($p['text'] ?? ''), $link, 'management');
                        }
                    }
                    if ($start->getTimestamp() - $this->now->getTimestamp() <= 24 * 3600) {
                        $out[] = self::alert("mmeet:soon:{$m['id']}:{$m['date']}:" . ($m['time'] ?? ''), 'اجتماع قريب', $topic,
                            self::join(['يبدأ ' . $this->countdown($start), count($m['attendees'] ?? []) . ' مدعو']), $link, 'management');
                    }
                }
                continue;
            }
            $held = $this->now->getTimestamp() - $start->getTimestamp();
            if ($held > 7 * 86400) {
                continue;
            }
            $attendees = $m['attendees'] ?? [];
            $recorded = array_filter($attendees, fn ($a) => in_array($a['status'] ?? '', ['حاضر', 'متأخر', 'غائب مبرر', 'غائب غير مبرر'], true));
            if (!empty($can['attendance']) && count($attendees) > 0 && !$recorded) {
                $out[] = self::alert("mmeet:att:{$m['id']}:{$m['date']}", 'لم يُسجل حضور الاجتماع', $topic, 'عُقد ' . self::day($start), "/meetings/{$m['id']}/attendance", 'management');
            }
            if (!empty($can['addDecisions']) && $held > 2 * 3600 && $held <= 3 * 86400 && empty($byMeeting[(string) $m['id']])) {
                $out[] = self::alert("mmeet:nodec:{$m['id']}:{$m['date']}", 'لم تُسجل قرارات الاجتماع بعد', $topic, 'عُقد ' . self::day($start), '/decisions', 'management');
            }
        }
        if (!empty($can['decisions'])) {
            foreach ($decisions as $d) {
                if (empty($d['deadline']) || (int) ($d['progress'] ?? 0) >= 100) {
                    continue;
                }
                $days = $this->daysTo($d['deadline']);
                if ($days === null || $days >= 0) {
                    continue;
                }
                $title = self::clip(trim((string) ($d['text'] ?? ($d['decision_text'] ?? ''))) ?: ($d['category'] ?? 'قرار'));
                $out[] = self::alert("mdec:late:{$d['id']}:{$d['deadline']}", 'قرار متأخر عن أجله', $title, 'متأخر ' . (-$days) . ' أيام · ' . (int) ($d['progress'] ?? 0) . '%', '/decisions', 'management');
            }
        }

        return $out;
    }

    public function meetingNotices(array $notices): array
    {
        return array_map(function ($n) {
            $when = self::when($this->meetStart(['date' => $n['date'] ?? '', 'time' => $n['time'] ?? '']));
            [$title, $detail] = match ($n['kind'] ?? '') {
                'deleted' => ['تم إلغاء الاجتماع', $when ? "كان مبرمجاً {$when}" : ''],
                'uninvited' => ['لم تعد مدعواً لهذا الاجتماع', $when],
                default => ['تم تعديل الاجتماع', self::join([$when, $n['location'] ?? ''])],
            };
            $link = ($n['kind'] ?? '') === 'updated' ? "/my-meetings?meeting={$n['meeting_id']}" : '/my-meetings';

            return self::alert("mnot:{$n['id']}", $title, $n['topic'] ?? 'اجتماع', $detail, $link, 'personal');
        }, $notices);
    }

    // ── Trips ──

    public function travels(array $travels): array
    {
        $out = [];
        $roles = ['head' => 'رئيس الوفد', 'staff' => 'ضمن الطاقم', 'player' => 'لاعب في الوفد'];
        foreach ($travels as $t) {
            $dep = $this->at($t['departure_time'] ?? null);
            if (!$dep) {
                continue;
            }
            $ret = $this->at($t['return_time'] ?? null);
            $link = "/my-travels?travel={$t['id']}";
            $dest = $t['destination'] ?? 'تنقل';
            if ($dep->gt($this->now)) {
                $out[] = self::alert("trv:add:{$t['id']}", 'تمت إضافتك لتنقل', $dest, self::join([$roles[$t['my_role'] ?? ''] ?? '', self::when($dep)]), $link, 'personal');
                if ($dep->getTimestamp() - $this->now->getTimestamp() <= 24 * 3600) {
                    $out[] = self::alert("trv:soon:{$t['id']}:{$t['departure_time']}", 'اقترب موعد الانطلاق', $dest,
                        self::join(['الانطلاق ' . $this->countdown($dep), !empty($t['departure_location']) ? "من {$t['departure_location']}" : '']), $link, 'personal');
                }
            } elseif ($this->now->getTimestamp() <= ($ret ? $ret->getTimestamp() : $dep->getTimestamp() + 86400)) {
                $out[] = self::alert("trv:live:{$t['id']}:{$t['departure_time']}", 'التنقل جارٍ', $dest, $ret ? 'العودة ' . self::when($ret) : 'العودة غير محددة', $link, 'personal');
            }
        }

        return $out;
    }

    public function travelNotices(array $notices): array
    {
        return array_map(function ($n) {
            $dep = self::when($this->at($n['departure_time'] ?? null));
            [$title, $detail] = match ($n['kind'] ?? '') {
                'deleted' => ['تم إلغاء التنقل', $dep ? "كان الانطلاق {$dep}" : ''],
                'removed' => ['لم تعد ضمن هذا التنقل', $dep],
                default => ['تم تعديل التنقل', $dep ? "الانطلاق {$dep}" : ''],
            };
            $link = ($n['kind'] ?? '') === 'updated' ? "/my-travels?travel={$n['travel_id']}" : '/my-travels';

            return self::alert("tnot:{$n['id']}", $title, $n['destination'] ?? 'تنقل', $detail, $link, 'personal');
        }, $notices);
    }

    public function managerTravels(array $travels, array $mine): array
    {
        $out = [];
        foreach ($travels as $t) {
            $dep = $this->at($t['departure_time'] ?? null);
            if (!$dep || $dep->lte($this->now)) {
                continue;
            }
            $left = $dep->getTimestamp() - $this->now->getTimestamp();
            $link = "/travels?travel={$t['id']}";
            $dest = $t['destination'] ?? 'تنقل';
            $players = count($t['players'] ?? []) ?: (int) ($t['players_count'] ?? 0);
            $staff = count($t['staff'] ?? []);
            if ($left <= 24 * 3600 && !in_array($t['id'], $mine)) {
                $out[] = self::alert("mtrv:soon:{$t['id']}:{$t['departure_time']}", 'تنقل قريب', $dest, self::join(['الانطلاق ' . $this->countdown($dep), "{$players} لاعب", "{$staff} طاقم"]), $link, 'management');
            }
            if ($left <= 3 * 86400) {
                if (empty($t['head_of_delegation_id'])) {
                    $out[] = self::alert("mtrv:nohead:{$t['id']}", 'تنقل بدون رئيس وفد', $dest, 'الانطلاق ' . self::when($dep), $link, 'management');
                }
                if ($players + $staff === 0) {
                    $out[] = self::alert("mtrv:empty:{$t['id']}", 'لم يُحدد أعضاء الوفد بعد', $dest, 'الانطلاق ' . self::when($dep), $link, 'management');
                }
            }
        }

        return $out;
    }

    // ── Medical files ──

    private static function examDone(array $r): bool
    {
        return !empty($r['last_exam_date']) && !empty($r['next_exam_date']) && substr($r['last_exam_date'], 0, 10) >= substr($r['next_exam_date'], 0, 10);
    }

    public function medical(array $records): array
    {
        $out = [];
        foreach ($records as $r) {
            $link = "/my-medical?record={$r['id']}";
            $heading = $r['injury_nature'] ?? 'إصابة';
            if ($this->since($r['created_at'] ?? null) <= 7 * 86400) {
                $out[] = self::alert("med:new:{$r['id']}", 'تم فتح ملف طبي لإصابتك', $heading, !empty($r['doctor']['name']) ? "الطبيب: {$r['doctor']['name']}" : '', $link, 'personal');
            }
            if (($r['record_status'] ?? '') === 'مغلق/متعافي') {
                continue;
            }
            $exam = !empty($r['next_exam_date']) ? $this->daysTo($r['next_exam_date']) : null;
            if ($exam !== null && !self::examDone($r) && ($exam === 0 || $exam === 1)) {
                $out[] = self::alert("med:exam:{$r['id']}:{$r['next_exam_date']}", 'موعد فحصك الطبي ' . self::inDays($exam), $heading, !empty($r['doctor']['name']) ? "مع {$r['doctor']['name']}" : 'راجع الطبيب', $link, 'personal');
            } elseif ($exam !== null && !self::examDone($r) && $exam < 0) {
                $out[] = self::alert("med:missed:{$r['id']}:{$r['next_exam_date']}", 'فات موعد فحصك الطبي', $heading, 'تواصل مع الطاقم الطبي لتحديد موعد جديد', $link, 'personal');
            }
            $back = !empty($r['absence_to']) ? $this->daysTo($r['absence_to']) : null;
            if ($back !== null && $back >= 0 && $back <= 2) {
                $out[] = self::alert("med:back:{$r['id']}:{$r['absence_to']}", 'تنتهي فترة غيابك ' . self::inDays($back), $heading, 'بانتظار قرار العودة من الطبيب', $link, 'personal');
            }
        }

        return $out;
    }

    public function managerMedical(array $records): array
    {
        $out = [];
        foreach ($records as $r) {
            if (($r['record_status'] ?? '') === 'مغلق/متعافي') {
                continue;
            }
            $p = $r['player'] ?? (is_array($r['player_id'] ?? null) ? $r['player_id'] : ($r['playerId'] ?? null));
            $name = $p ? ($p['name'] ?? trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) : 'لاعب';
            $nature = $r['injury_nature'] ?? 'إصابة';
            $link = "/medical?record={$r['id']}";
            $exam = !empty($r['next_exam_date']) ? $this->daysTo($r['next_exam_date']) : null;
            if ($exam !== null && !self::examDone($r) && ($exam === 0 || $exam === 1)) {
                $out[] = self::alert("mmed:exam:{$r['id']}:{$r['next_exam_date']}", 'فحص طبي ' . self::inDays($exam), $name, $nature, $link, 'management');
            } elseif ($exam !== null && !self::examDone($r) && $exam < 0) {
                $out[] = self::alert("mmed:late:{$r['id']}:{$r['next_exam_date']}", 'فات موعد فحص طبي', $name, $nature, $link, 'management');
            }
            $injured = !empty($r['injury_date']) ? $this->daysTo($r['injury_date']) : null;
            if (($r['record_status'] ?? '') === 'مفتوح/مصاب' && $injured !== null && -$injured >= 2) {
                $out[] = self::alert("mmed:init:{$r['id']}", 'إصابة بانتظار التشخيص الأولي', $name, "{$nature} · منذ " . (-$injured) . ' أيام', $link, 'management');
            }
            $back = !empty($r['absence_to']) ? $this->daysTo($r['absence_to']) : null;
            if ($back !== null && $back < 0) {
                $out[] = self::alert("mmed:back:{$r['id']}:{$r['absence_to']}", 'انتهت مدة الغياب دون قرار العودة', $name, $nature, $link, 'management');
            }
        }

        return $out;
    }

    public function medicalNotices(array $notices): array
    {
        $stages = [
            'بانتظار الفحص النهائي' => 'تم التشخيص الأولي لإصابتك',
            'قيد التأهيل' => 'بدأت مرحلة التأهيل',
            'بانتظار قرار العودة' => 'صدر القرار الطبي في إصابتك',
            'مغلق/متعافي' => 'تمت الموافقة على عودتك للتدريبات',
        ];

        return array_map(function ($n) use ($stages) {
            $changes = $n['changes'] ?? [];
            [$title, $detail] = match ($n['kind'] ?? '') {
                'deleted' => ['تم حذف ملفك الطبي', 'من طرف الطاقم الطبي'],
                'stage' => [$stages[$changes[0]['to'] ?? ''] ?? 'تم تحديث حالتك الطبية', (string) ($changes[0]['to'] ?? '')],
                default => [
                    collect($changes)->contains(fn ($c) => ($c['field'] ?? '') === 'next_exam_date') ? 'تم تغيير موعد فحصك الطبي' : 'تم تعديل ملفك الطبي',
                    self::join(array_map(fn ($c) => ($c['label'] ?? '') . ': ' . ($c['to'] ?? 'أُزيل'), $changes)),
                ],
            };
            $link = ($n['kind'] ?? '') === 'deleted' ? '/my-medical' : "/my-medical?record={$n['record_id']}";

            return self::alert("mnot:{$n['id']}", $title, $n['injury_nature'] ?? 'ملفي الطبي', $detail, $link, 'personal');
        }, $notices);
    }

    // ── Debts ──

    public function debts(array $debts): array
    {
        $out = [];
        foreach ($debts as $d) {
            if (($d['status'] ?? '') === 'paid' || (float) ($d['remaining'] ?? 0) <= 0 || empty($d['due_date'])) {
                continue;
            }
            $days = $this->daysTo($d['due_date']);
            if ($days === null || $days > 3) {
                continue;
            }
            $kind = $d['kind'] ?? 'loan';
            $what = $kind === 'purchase' ? 'مصروف بالدين' : 'دين';
            $left = 'الباقي ' . number_format((float) $d['remaining'], 0, ',', ' ') . ' دج';
            $heading = !empty($d['title']) ? ($d['creditor'] ?? '') . " · {$d['title']}" : (string) ($d['creditor'] ?? $what);
            $link = ($kind === 'purchase' ? '/payments' : '/debts') . "?debt={$d['id']}";
            [$type, $title, $detail] = $days > 0
                ? ['debt_soon', "اقترب موعد تسديد {$what}", self::inDays($days) . " · {$left}"]
                : ($days === 0 ? ['debt_due', "حان موعد تسديد {$what}", "اليوم · {$left}"] : ['debt_late', "فات موعد تسديد {$what}", 'منذ ' . (-$days) . " أيام · {$left}"]);
            $out[] = self::alert("debt:{$kind}:{$d['id']}:{$type}:{$d['due_date']}", $title, $heading, $detail, $link, 'management');
        }

        return $out;
    }

    // ── Members' opinions ──

    public function feedback(array $items): array
    {
        $out = [];
        foreach ($items as $f) {
            if (empty($f['replied_at']) || !empty($f['reply_seen'])) {
                continue;
            }
            $title = match ($f['type'] ?? '') {
                'suggestion' => 'تم تقييم اقتراحك والرد عليه',
                'complaint' => 'تم الرد على شكواك',
                default => 'تم الرد على ملاحظتك',
            };
            $out[] = self::alert("fb:reply:{$f['id']}:{$f['replied_at']}", $title, $f['subject'] ?? 'رأيي', self::clip($f['reply'] ?? '', 70), "/my-feedback?feedback={$f['id']}", 'personal');
        }

        return $out;
    }

    public function managerFeedback(array $items): array
    {
        $labels = ['suggestion' => 'اقتراح', 'complaint' => 'شكوى', 'note' => 'ملاحظة'];
        $out = [];
        foreach ($items as $f) {
            if (!empty($f['replied_at'])) {
                continue;
            }
            $label = $labels[$f['type'] ?? ''] ?? 'رأي';
            $who = $f['member_name'] ?? 'عضو';
            $link = "/feedback?feedback={$f['id']}";
            if (empty($f['read'])) {
                $out[] = self::alert("fb:new:{$f['id']}", $label . (($f['type'] ?? '') === 'suggestion' ? ' جديد' : ' جديدة') . ' من عضو', $f['subject'] ?? $label, self::join([$who, self::clip($f['body'] ?? '', 50)]), $link, 'management');
                continue;
            }
            $age = $this->daysTo(substr((string) ($f['created_at'] ?? ''), 0, 10));
            if ($age !== null && -$age >= 3) {
                $out[] = self::alert("fb:waiting:{$f['id']}", "{$label} بانتظار الرد", $f['subject'] ?? $label, "{$who} · منذ " . (-$age) . ' أيام', $link, 'management');
            }
        }

        return $out;
    }

    // ── Tasks (what the clock brings: late, deadline near; the management's too) ──

    private static function taskOpen(array $t): bool
    {
        return in_array($t['status'] ?? '', ['new', 'in_progress', 'on_hold'], true);
    }

    public function myTasks(array $tasks): array
    {
        $out = [];
        foreach ($tasks as $t) {
            if (!self::taskOpen($t)) {
                continue;
            }
            $due = $this->at($t['due_at'] ?? null);
            $link = "/?task={$t['id']}";
            if ($due && $due->lt($this->now)) {
                $out[] = self::alert("task:late:{$t['id']}:{$t['due_at']}", 'تأخرت مهمة', $t['title'] ?? 'مهمة', 'آخر أجل: ' . self::when($due), $link, 'personal');
            } elseif ($due && !empty($t['seen']) && $due->getTimestamp() - $this->now->getTimestamp() <= 3 * 3600) {
                $out[] = self::alert("task:due:{$t['id']}:{$t['due_at']}", 'اقترب آخر أجل مهمة', $t['title'] ?? 'مهمة', 'آخر أجل: ' . self::when($due), $link, 'personal');
            }
        }

        return $out;
    }

    /** $myName: who created it is not told about its own urgent task */
    public function managerTasks(array $tasks, $me, ?string $myName = null): array
    {
        $out = [];
        foreach ($tasks as $t) {
            if ((string) ($t['assignee']['id'] ?? '') === (string) $me || !self::taskOpen($t)) {
                continue;
            }
            $due = $this->at($t['due_at'] ?? null);
            $alertAt = $this->at($t['alert_at'] ?? null);
            $given = $t['assignee']['name'] ?? 'بلا مكلّف';
            $link = "/?task={$t['id']}";
            $title = $t['title'] ?? 'مهمة';
            if ($due && $due->lt($this->now)) {
                $out[] = self::alert("mtask:late:{$t['id']}:{$t['due_at']}", 'مهمة متأخرة', $title, "{$given} · آخر أجل: " . self::when($due), $link, 'management');
            } elseif (($t['kind'] ?? '') === 'scheduled' && $alertAt && $alertAt->lte($this->now)) {
                $onTheDay = $due && $due->isSameDay($this->now);
                $out[] = self::alert("mtask:alert:{$t['id']}:{$t['alert_at']}:" . ($onTheDay ? 'day' : 'near'), $onTheDay ? 'حان موعد مهمة' : 'اقترب موعد مهمة', $title,
                    self::join([$t['event_label'] ?? '', $given]), $link, 'management');
            } elseif ($due && $due->getTimestamp() - $this->now->getTimestamp() <= 3 * 3600) {
                $out[] = self::alert("mtask:due:{$t['id']}:{$t['due_at']}", 'اقترب آخر أجل مهمة', $title, "{$given} · آخر أجل: " . self::when($due), $link, 'management');
            }
            if (($t['kind'] ?? '') === 'urgent' && !empty($t['assignee']) && empty($t['seen']) && ($t['created_by'] ?? null) !== $myName) {
                $out[] = self::alert("mtask:urgent:{$t['id']}", 'مهمة عاجلة لم يفتحها المكلّف بعد', $title, $t['assignee']['name'] ?? '', $link, 'management');
            }
        }

        return $out;
    }
}
