<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened to a medical file (a new stage, a change, deleted), announced to its member */
class MedicalNotice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'changes' => 'array',
    ];

    /** The fields the member is told about (the diagnosis stays with the medical staff) */
    public const FIELDS = [
        'injury_date' => 'تاريخ الإصابة',
        'next_exam_date' => 'موعد الفحص القادم',
        'last_exam_date' => 'تاريخ آخر فحص',
        'absence_from' => 'بداية فترة الغياب',
        'absence_to' => 'نهاية فترة الغياب',
        'doctor_id' => 'الطبيب المشرف',
        'injury_nature' => 'طبيعة الإصابة',
        'incident_location' => 'مكان الإصابة',
        'initial_recommendation' => 'التوصية الأولية',
        'restrictions' => 'القيود',
        'medical_decision' => 'القرار الطبي',
    ];

    private const DATES = ['injury_date', 'next_exam_date', 'last_exam_date', 'absence_from', 'absence_to'];

    /** The file's told fields, as the member reads them (dates "Y-m-d", the doctor's name) */
    public static function snapshot(PlayerMedicalRecord $r): array
    {
        $values = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $value = $r->getAttributes()[$field] ?? null;
            if ($field === 'doctor_id') {
                $doctor = $value ? Individual::find($value) : null;
                $value = $doctor ? trim($doctor->first_name . ' ' . $doctor->last_name) : null;
            } elseif (in_array($field, self::DATES, true)) {
                $value = $value ? substr((string) $value, 0, 10) : null;
            }
            $value = is_string($value) ? trim($value) : $value;
            $values[$field] = ($value === '' ? null : $value);
        }

        return $values;
    }

    /** What changed between two snapshots: [{field, label, from, to}] */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach (self::FIELDS as $field => $label) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[] = ['field' => $field, 'label' => $label, 'from' => $before[$field] ?? null, 'to' => $after[$field] ?? null];
            }
        }

        return $changes;
    }

    /** Records what happened to the file, for the member */
    public static function record(PlayerMedicalRecord $r, string $kind, $playerId, ?array $changes = null): ?self
    {
        if (!$playerId) {
            return null;
        }

        return self::create([
            'record_id' => $r->id,
            'player_id' => (int) $playerId,
            'kind' => $kind,
            'injury_nature' => $r->injury_nature,
            'changes' => $changes,
        ]);
    }
}
