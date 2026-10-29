<?php

namespace Tests\Feature;

use App\Models\AdministrativeMatchReport;
use App\Models\Individual;
use App\Models\Matchs;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MatchReportImageTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, array $data)
    {
        return $this->actingAs($user)->post('/api/administrative-reports/image', $data, ['Accept' => 'application/json']);
    }

    public function test_a_match_report_is_a_picture(): void
    {
        $user = User::factory()->create();
        $coach = Individual::create(['type' => 'staff', 'first_name' => 'مدرب', 'last_name' => 'أول']);
        $match = Matchs::create(['competition' => 'البطولة', 'opponent' => 'النجم', 'match_date' => '2026-10-05 15:00', 'location' => 'الملعب',
            'coach_id' => $coach->id, 'admin_id' => $user->id, 'team_id' => Team::create(['name' => 'أكابر'])->id]);

        // A picture or a PDF, needed
        $this->upload($user, ['match_id' => $match->id])->assertStatus(422)->assertJsonPath('message', 'أرفق صورة أو ملف التقرير');
        $this->upload($user, ['match_id' => $match->id, 'image' => UploadedFile::fake()->create('report.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertStatus(422)->assertJsonPath('message', 'التقرير يجب أن يكون صورة أو ملف PDF');

        // Saved: the match's report made with its picture, shown with it
        $first = $this->upload($user, ['match_id' => $match->id, 'image' => UploadedFile::fake()->create('report.jpg', 200, 'image/jpeg')])
            ->assertOk()->json('data.image_path');
        $this->assertStringContainsString('uploads/match_reports/match_report_' . $match->id . '_', $first);
        $this->actingAs($user)->getJson('/api/administrative-reports/' . $match->id)->assertOk()->assertJsonPath('data.image_path', $first);

        // A PDF replaces it, on the same report
        $this->travel(2)->seconds();
        $second = $this->upload($user, ['match_id' => $match->id, 'image' => UploadedFile::fake()->create('report2.pdf', 200, 'application/pdf')])
            ->assertOk()->json('data.image_path');
        $this->assertNotSame($first, $second);
        $this->assertSame(1, AdministrativeMatchReport::count());

        // Its files are there; removed after the test
        foreach ([$first, $second] as $url) {
            $file = public_path(parse_url($url, PHP_URL_PATH));
            $this->assertFileExists($file);
            @unlink($file);
        }
    }
}
