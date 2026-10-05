<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Individual;
use App\Models\Matchs;
use App\Models\Team;
use App\Models\TravelItinerary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Team trips (travel_itineraries): where, why, how, the category with its chosen players and staff, and the day's schedule */
class TravelItineraryController extends Controller
{
    /** Schedule times of the trip day, in order */
    private const SCHEDULE = ['schedule_departure', 'schedule_arrival', 'schedule_meal', 'schedule_tech_meeting', 'schedule_match', 'schedule_return'];

    public function index()
    {
        $travels = TravelItinerary::with(['matchId.opponentClub:id,name', 'matchId.team:id,name', 'headOfDelegationId:id,first_name,last_name,type', 'team:id,name'])
            ->orderByDesc('departure_time')
            ->orderByDesc('id')
            ->get();

        // Names of every chosen member, in one query
        $ids = $travels->flatMap(fn ($t) => array_merge($t->staff_ids ?? [], $t->player_ids ?? []))->unique()->values();
        $people = $this->people($ids->all());

        return response()->json(['status' => 'success', 'data' => $travels->map(fn ($t) => $this->present($t, $people))]);
    }

    public function store(Request $request)
    {
        $travel = TravelItinerary::create($this->validated($request));

        return response()->json(['status' => 'success', 'data' => $this->presentOne($travel)], 201);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:travel_itineraries,id']);
        $travel = TravelItinerary::findOrFail($request->input('id'));
        $travel->update($this->validated($request, true));

        return response()->json(['status' => 'success', 'data' => $this->presentOne($travel)]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:travel_itineraries,id']);
        TravelItinerary::findOrFail($request->input('id'))->delete();

        return response()->json(['status' => 'success']);
    }

    /** What the form picks from: the members (with their category), the categories and the upcoming matches */
    public function options()
    {
        $members = Individual::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'type', 'team_id'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => trim("{$m->first_name} {$m->last_name}"), 'type' => $m->type, 'team_id' => $m->team_id]);

        $teams = Team::orderBy('name')->get(['id', 'name']);

        $matches = Matchs::with(['opponentClub:id,name', 'team:id,name'])
            ->where('match_date', '>=', CarbonImmutable::today())
            ->orderBy('match_date')
            ->limit(80)
            ->get()
            ->map(fn (Matchs $m) => $this->presentMatch($m));

        return response()->json(['status' => 'success', 'data' => ['members' => $members, 'teams' => $teams, 'matches' => $matches]]);
    }

    /** $partial (update): the required fields are checked only when sent */
    private function validated(Request $request, bool $partial = false): array
    {
        $time = 'nullable|date_format:H:i';
        $required = $partial ? 'sometimes|required' : 'required';
        $data = $request->validate([
            'match_id' => 'nullable|exists:matches,id',
            'team_id' => 'nullable|exists:teams,id',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'integer|exists:individuals,id',
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'integer|exists:individuals,id',
            'destination' => "{$required}|string|max:255",
            'travel_reason' => 'nullable|string|max:255',
            'departure_location' => 'nullable|string|max:255',
            'departure_time' => "{$required}|date",
            'transport_method' => 'nullable|string|max:255',
            'accommodation_place' => 'nullable|string|max:255',
            'return_time' => 'nullable|date|after_or_equal:departure_time',
            'head_of_delegation_id' => 'nullable|exists:individuals,id',
            'staff_details' => 'nullable|string|max:5000',
            'players_count' => 'nullable|integer|min:0|max:200',
            'schedule_departure' => $time,
            'schedule_arrival' => $time,
            'schedule_meal' => $time,
            'schedule_tech_meeting' => $time,
            'schedule_match' => $time,
            'schedule_return' => $time,
            'special_notes' => 'nullable|string|max:5000',
        ], [
            'destination.required' => 'اكتب وجهة التنقل',
            'departure_time.required' => 'حدد موعد الانطلاق',
            'return_time.after_or_equal' => 'موعد العودة يجب أن يكون بعد موعد الانطلاق',
            'date_format' => 'صيغة الوقت غير صحيحة',
        ]);

        // Only the fields that were sent are changed on update
        $data = array_intersect_key($data, $request->all());

        // The chosen players give the count; the chosen staff are also written as text (older screens read it)
        if (array_key_exists('player_ids', $data)) {
            $data['player_ids'] = array_values(array_unique(array_map('intval', $data['player_ids'] ?? [])));
            $data['players_count'] = count($data['player_ids']);
        }
        if (array_key_exists('staff_ids', $data)) {
            $data['staff_ids'] = array_values(array_unique(array_map('intval', $data['staff_ids'] ?? [])));
            $data['staff_details'] = $data['staff_ids']
                ? implode('، ', array_column($this->people($data['staff_ids']), 'name'))
                : ($data['staff_details'] ?? null);
        }
        return $data;
    }

    /** id => [id, name, type] of the given members */
    private function people(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        return Individual::whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'type'])
            ->mapWithKeys(fn ($m) => [$m->id => ['id' => $m->id, 'name' => trim("{$m->first_name} {$m->last_name}"), 'type' => $m->type]])
            ->all();
    }

    private function presentOne(TravelItinerary $travel): array
    {
        $travel = $travel->fresh(['matchId.opponentClub', 'matchId.team', 'headOfDelegationId', 'team']);
        return $this->present($travel, $this->people(array_merge($travel->staff_ids ?? [], $travel->player_ids ?? [])));
    }

    private function presentMatch(Matchs $m): array
    {
        $opponent = $m->opponentClub?->name ?? $m->opponent;
        return [
            'id' => $m->id,
            'title' => $opponent ? "مباراة ضد {$opponent}" : ($m->match_title ?: 'مباراة'),
            'at' => $m->match_date ? CarbonImmutable::parse($m->match_date)->format('Y-m-d H:i') : null,
            'place' => $m->location,
            'team_id' => $m->team_id,
            'team' => $m->team?->name,
            'competition' => $m->competition,
        ];
    }

    private function present(TravelItinerary $t, array $people = []): array
    {
        $pick = fn (?array $ids) => array_values(array_filter(array_map(fn ($id) => $people[$id] ?? null, $ids ?? [])));
        $head = $t->headOfDelegationId;
        $match = $t->matchId;
        $data = [
            'id' => $t->id,
            'match_id' => $t->match_id,
            'match' => $match ? $this->presentMatch($match) : null,
            'team_id' => $t->team_id,
            'team_name' => $t->team?->name,
            'staff_ids' => $t->staff_ids ?? [],
            'staff' => $pick($t->staff_ids),
            'player_ids' => $t->player_ids ?? [],
            'players' => $pick($t->player_ids),
            'destination' => $t->destination,
            'travel_reason' => $t->travel_reason,
            'departure_location' => $t->departure_location,
            'departure_time' => $t->departure_time ? CarbonImmutable::parse($t->departure_time)->format('Y-m-d H:i') : null,
            'transport_method' => $t->transport_method,
            'accommodation_place' => $t->accommodation_place,
            'return_time' => $t->return_time ? CarbonImmutable::parse($t->return_time)->format('Y-m-d H:i') : null,
            'head_of_delegation_id' => $t->head_of_delegation_id,
            'head_of_delegation_name' => $head ? trim("{$head->first_name} {$head->last_name}") : null,
            'staff_details' => $t->staff_details,
            'players_count' => $t->players_count,
            'special_notes' => $t->special_notes,
            'created_at' => $t->created_at?->format('Y-m-d H:i'),
        ];
        foreach (self::SCHEDULE as $key) {
            $data[$key] = $t->{$key} ? substr((string) $t->{$key}, 0, 5) : null;
        }
        return $data;
    }

    /**
     * Personal space: the trips I am on (head of the delegation, staff or player), newest first,
     * each with my role in it (my_role: head | staff | player).
     */
    public function mine(Request $request)
    {
        $memberIds = Individual::where('user_id', $request->user()->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!$memberIds) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $roleOf = function (TravelItinerary $t) use ($memberIds): ?string {
            if (in_array((int) $t->head_of_delegation_id, $memberIds, true)) {
                return 'head';
            }
            if (array_intersect(array_map('intval', $t->staff_ids ?? []), $memberIds)) {
                return 'staff';
            }
            if (array_intersect(array_map('intval', $t->player_ids ?? []), $memberIds)) {
                return 'player';
            }
            return null;
        };

        $travels = TravelItinerary::with(['matchId.opponentClub:id,name', 'matchId.team:id,name', 'headOfDelegationId:id,first_name,last_name,type', 'team:id,name'])
            ->orderByDesc('departure_time')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($t) => $roleOf($t) !== null)
            ->values();

        $people = $this->people($travels->flatMap(fn ($t) => array_merge($t->staff_ids ?? [], $t->player_ids ?? []))->unique()->values()->all());

        return response()->json(['status' => 'success', 'data' => $travels->map(fn ($t) => array_merge($this->present($t, $people), ['my_role' => $roleOf($t)]))]);
    }
}
