<?php

namespace App\Services\Tasks;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The part of the iCalendar RRULE standard (RFC 5545) the periodic tasks use:
 *   FREQ=DAILY|WEEKLY|MONTHLY; INTERVAL=n; BYDAY=MO,TU,…; BYMONTHDAY=1..31; BYHOUR=0..23; BYMINUTE=0..59; UNTIL=YYYYMMDD[THHMMSSZ]
 * Occurrences are counted from an anchor (the template's start), like DTSTART.
 */
class RecurrenceRule
{
    private const DAYS = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

    private string $freq;
    private int $interval;
    /** @var int[] ISO weekdays 1 (Mon) … 7 (Sun) */
    private array $byDay = [];
    private ?int $byMonthDay = null;
    private int $hour;
    private int $minute;
    private ?CarbonImmutable $until = null;

    public function __construct(string $rule, private CarbonImmutable $anchor)
    {
        $parts = [];
        foreach (explode(';', strtoupper(trim(preg_replace('/^RRULE:/i', '', $rule)))) as $pair) {
            if (str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $parts[trim($key)] = trim($value);
            }
        }

        $this->freq = $parts['FREQ'] ?? '';
        if (!in_array($this->freq, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
            throw new InvalidArgumentException('FREQ must be DAILY, WEEKLY or MONTHLY');
        }
        $this->interval = max(1, (int) ($parts['INTERVAL'] ?? 1));

        if (!empty($parts['BYDAY'])) {
            foreach (explode(',', $parts['BYDAY']) as $day) {
                if (!isset(self::DAYS[$day])) {
                    throw new InvalidArgumentException("Unknown BYDAY value $day");
                }
                $this->byDay[] = self::DAYS[$day];
            }
        }
        if (!empty($parts['BYMONTHDAY'])) {
            $this->byMonthDay = max(1, min(31, (int) $parts['BYMONTHDAY']));
        }
        $this->hour = isset($parts['BYHOUR']) ? max(0, min(23, (int) $parts['BYHOUR'])) : $anchor->hour;
        $this->minute = isset($parts['BYMINUTE']) ? max(0, min(59, (int) $parts['BYMINUTE'])) : $anchor->minute;
        if (!empty($parts['UNTIL'])) {
            $this->until = CarbonImmutable::parse($parts['UNTIL'])->endOfDay();
        }
    }

    public static function isValid(string $rule): bool
    {
        try {
            new self($rule, CarbonImmutable::now());
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** First occurrence strictly after $after (or at/after the anchor), null when the rule has ended */
    public function nextAfter(CarbonImmutable $after): ?CarbonImmutable
    {
        $day = ($after->lessThan($this->anchor) ? $this->anchor : $after)->startOfDay();

        // Enough days for any monthly rule with a long interval
        for ($i = 0; $i < 366 * 2; $i++, $day = $day->addDay()) {
            if (!$this->matchesDay($day)) {
                continue;
            }
            $at = $day->setTime($this->hour, $this->minute);
            if ($at->lessThan($this->anchor) || $at->lessThanOrEqualTo($after)) {
                continue;
            }
            if ($this->until && $at->greaterThan($this->until)) {
                return null;
            }
            return $at;
        }
        return null;
    }

    private function matchesDay(CarbonImmutable $day): bool
    {
        $anchorDay = $this->anchor->startOfDay();
        switch ($this->freq) {
            case 'DAILY':
                return ((int) $anchorDay->diffInDays($day)) % $this->interval === 0;

            case 'WEEKLY':
                $days = $this->byDay ?: [$this->anchor->dayOfWeekIso];
                if (!in_array($day->dayOfWeekIso, $days, true)) {
                    return false;
                }
                $weeks = intdiv((int) $anchorDay->startOfWeek()->diffInDays($day->startOfWeek()), 7);
                return $weeks % $this->interval === 0;

            case 'MONTHLY':
                $wanted = $this->byMonthDay ?? $this->anchor->day;
                // Short months: use their last day
                if ($day->day !== min($wanted, $day->daysInMonth)) {
                    return false;
                }
                $months = ($day->year - $anchorDay->year) * 12 + ($day->month - $anchorDay->month);
                return $months % $this->interval === 0;
        }
        return false;
    }
}
