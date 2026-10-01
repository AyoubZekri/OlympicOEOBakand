<?php

namespace Tests\Feature;

use App\Models\Individual;
use App\Models\Matchs;
use App\Models\Role;
use App\Models\TravelItinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelItineraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'admin', 'type' => 'full', 'permissions' => '{}'])->id]);
    }

    public function test_create_list_update_and_delete_a_travel(): void
    {
        $match = Matchs::create(['opponent' => 'النجم', 'match_date' => now()->addDays(3)->setTime(16, 0), 'location' => 'ملعب 5 جويلية']);
        $head = Individual::create(['type' => 'admin', 'first_name' => 'سمير', 'last_name' => 'بن علي']);

        $travel = $this->actingAs($this->admin)->postJson('/api/travels/create', [
            'match_id' => $match->id,
            'destination' => 'الجزائر العاصمة',
            'travel_reason' => 'مباراة رسمية',
            'departure_location' => 'مقر النادي',
            'departure_time' => now()->addDays(2)->setTime(8, 0)->format('Y-m-d H:i'),
            'return_time' => now()->addDays(3)->setTime(23, 0)->format('Y-m-d H:i'),
            'transport_method' => 'حافلة النادي',
            'head_of_delegation_id' => $head->id,
            'players_count' => 22,
            'schedule_departure' => '08:00',
            'schedule_match' => '16:00',
        ])->assertCreated()->json('data');

        $this->assertSame('مباراة ضد النجم', $travel['match']['title']);
        $this->assertSame('سمير بن علي', $travel['head_of_delegation_name']);
        $this->assertSame('16:00', $travel['schedule_match']);

        $this->actingAs($this->admin)->getJson('/api/travels')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.destination', 'الجزائر العاصمة');

        // Only the sent fields change
        $this->actingAs($this->admin)->postJson('/api/travels/update', ['id' => $travel['id'], 'players_count' => 20])->assertOk()
            ->assertJsonPath('data.players_count', 20)->assertJsonPath('data.destination', 'الجزائر العاصمة');

        $this->actingAs($this->admin)->postJson('/api/travels/delete', ['id' => $travel['id']])->assertOk();
        $this->assertSame(0, TravelItinerary::count());
    }

    public function test_validation_and_options(): void
    {
        $this->actingAs($this->admin)->postJson('/api/travels/create', ['destination' => 'وهران'])->assertStatus(422)
            ->assertJsonValidationErrors('departure_time');
        $this->actingAs($this->admin)->postJson('/api/travels/create', [
            'destination' => 'وهران', 'departure_time' => '2026-10-05 08:00', 'return_time' => '2026-10-04 08:00',
        ])->assertStatus(422)->assertJsonValidationErrors('return_time');
        $this->actingAs($this->admin)->postJson('/api/travels/create', [
            'destination' => 'وهران', 'departure_time' => '2026-10-05 08:00', 'schedule_meal' => '25:99',
        ])->assertStatus(422);

        Individual::create(['type' => 'player', 'first_name' => 'أحمد', 'last_name' => 'محمد']);
        Matchs::create(['opponent' => 'قديمة جداً', 'match_date' => now()->subDays(60)]);
        Matchs::create(['opponent' => 'قادمة', 'match_date' => now()->addDays(5)]);
        $options = $this->actingAs($this->admin)->getJson('/api/travels/options')->assertOk()->json('data');
        $this->assertSame('أحمد محمد', $options['members'][0]['name']);
        $this->assertSame(['مباراة ضد قادمة'], array_column($options['matches'], 'title'));
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/travels')->assertUnauthorized();
    }
}
