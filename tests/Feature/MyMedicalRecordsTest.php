<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\PlayerMedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyMedicalRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_their_own_medical_files_without_the_confidential_diagnosis(): void
    {
        $user = User::factory()->create();
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $mate = Individual::create(['type' => 'player', 'first_name' => 'ياسين', 'last_name' => 'كريم']);
        $doctor = Individual::create(['type' => 'doctor', 'first_name' => 'يوسف', 'last_name' => 'قادري']);

        PlayerMedicalRecord::create(['player_id' => $me->id, 'doctor_id' => $doctor->id, 'injury_date' => '2026-09-20', 'injury_nature' => 'التواء الكاحل',
            'diagnosis' => 'تمزق جزئي في الأربطة', 'restrictions' => 'لا جري', 'next_exam_date' => '2026-10-07', 'record_status' => 'قيد التأهيل']);
        PlayerMedicalRecord::create(['player_id' => $me->id, 'doctor_id' => $doctor->id, 'injury_date' => '2026-10-02', 'injury_nature' => 'كدمة', 'record_status' => 'مفتوح/مصاب']);
        PlayerMedicalRecord::create(['player_id' => $mate->id, 'doctor_id' => $doctor->id, 'injury_date' => '2026-10-01', 'injury_nature' => 'شد عضلي', 'record_status' => 'مفتوح/مصاب']);

        $this->getJson('/api/medical-records/mine')->assertUnauthorized();
        $data = $this->actingAs($user)->getJson('/api/medical-records/mine')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('كدمة', $data[0]['injury_nature']); // newest first
        $old = $data[1];
        $this->assertSame(['التواء الكاحل', 'لا جري', 'قيد التأهيل', 'يوسف قادري', 'أحمد علي', $me->id], [$old['injury_nature'], $old['restrictions'], $old['record_status'], $old['doctor']['name'], $old['player']['name'], $old['player_id']]);
        $this->assertNull($old['diagnosis']); // confidential: stays with the medical staff

        $this->actingAs(User::factory()->create())->getJson('/api/medical-records/mine')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_member_is_told_of_every_change_and_of_the_deletion_of_their_file(): void
    {
        $user = User::factory()->create();
        $me = Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'علي', 'user_id' => $user->id]);
        $doctor = Individual::create(['type' => 'doctor', 'first_name' => 'يوسف', 'last_name' => 'قادري']);
        $other = Individual::create(['type' => 'doctor', 'first_name' => 'سمير', 'last_name' => 'بلحاج']);
        $record = PlayerMedicalRecord::create(['player_id' => $me->id, 'doctor_id' => $doctor->id, 'injury_date' => '2026-09-20', 'injury_nature' => 'التواء',
            'diagnosis' => 'سري', 'next_exam_date' => '2026-10-07', 'record_status' => 'قيد التأهيل']);
        $mine = fn () => $this->actingAs($user)->getJson('/api/medical-records/mine/notices')->assertOk()->json();

        // the same values sent again, the diagnosis changed: nothing to tell
        $this->actingAs($user)->postJson("/api/medical-records/update/{$record->id}", ['injury_date' => '2026-09-20', 'diagnosis' => 'آخر', 'next_exam_date' => '2026-10-07'])->assertOk();
        $this->assertSame([], $mine());

        // the exam moved, the doctor changed
        $this->actingAs($user)->postJson("/api/medical-records/update/{$record->id}", ['next_exam_date' => '2026-10-09', 'doctor_id' => $other->id])->assertOk();
        $n = $mine();
        $this->assertCount(1, $n);
        $this->assertSame('updated', $n[0]['kind']);
        $this->assertSame([
            ['field' => 'next_exam_date', 'label' => 'موعد الفحص القادم', 'from' => '2026-10-07', 'to' => '2026-10-09'],
            ['field' => 'doctor_id', 'label' => 'الطبيب المشرف', 'from' => 'يوسف قادري', 'to' => 'سمير بلحاج'],
        ], $n[0]['changes']);

        // a new stage is announced as such (its other fields are part of it)
        $this->actingAs($user)->postJson("/api/medical-records/update/{$record->id}", ['record_status' => 'بانتظار قرار العودة', 'medical_decision' => 'راحة'])->assertOk();
        $n = $mine();
        $this->assertSame(['stage', 'updated'], array_column($n, 'kind'));
        $this->assertSame(['field' => 'record_status', 'label' => 'الحالة', 'from' => 'قيد التأهيل', 'to' => 'بانتظار قرار العودة'], $n[0]['changes'][0]);

        $this->actingAs($user)->postJson("/api/medical-records/delete/{$record->id}")->assertOk();
        $n = $mine();
        $this->assertCount(3, $n);
        $this->assertSame(['deleted', $record->id, 'التواء'], [$n[0]['kind'], $n[0]['record_id'], $n[0]['injury_nature']]);
        $this->actingAs(User::factory()->create())->getJson('/api/medical-records/mine/notices')->assertOk()->assertExactJson([]);
    }
}
