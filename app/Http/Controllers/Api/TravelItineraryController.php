<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Individual;
use App\Models\Matchs;
use App\Models\TravelItinerary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Team trips (travel_itineraries): where, why, how, who leads the delegation and the day's schedule */
class TravelItineraryController extends Controller
{
    /** Schedule times of the trip day, in order */
    private const SCHEDULE = ['schedule_departure', 'schedule_arrival', 'schedule_meal', 'schedule_tech_meeting', 'schedule_match', 'schedule_return'];

    public function index()
    {
        $travels = TravelItinerary::with(['matchId.opponentClub:id,name', 'matchId.team:id,name', 'headOfDelegationId:id,first_name,last_name,type'])
            ->orderByDesc('departure_time')
            ->orderByDesc('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $travels->map(fn ($t) => $this->present($t))]);
    }

    public function store(Request $request)
    {
        $travel = TravelItinerary::create($this->validated($request));

        return response()->json(['status' => 'success', 'data' => $this->present($travel->fresh(['matchId.opponentClub', 'matchId.team', 'headOfDelegationId']))], 201);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:travel_itineraries,id']);
        $travel = TravelItinerary::findOrFail($request->input('id'));
        $travel->update($this->validated($request, true));

        return response()->json(['status' => 'success', 'data' => $this->present($travel->fresh(['matchId.opponentClub', 'matchId.team', 'headOfDelegationId']))]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:travel_itineraries,id']);
        TravelItinerary::findOrFail($request->input('id'))->delete();

        return response()->json(['status' => 'success']);
    }

    /** What the form picks from: the members (head of delegation) and the matches of the last month and to come */
    public function options()
    {
        $members = Individual::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'type'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => trim("{$m->first_name} {$m->last_name}"), 'type' => $m->type]);

        $matches = Matchs::with(['opponentClub:id,name', 'team:id,name'])
            ->where('match_date', '>=', CarbonImmutable::today()->subDays(30))
            ->orderBy('match_date')
            ->limit(80)
            ->get()
            ->map(fn (Matchs $m) => $this->presentMatch($m));

        return response()->json(['status' => 'success', 'data' => ['members' => $members, 'matches' => $matches]]);
    }

    /** $partial (update): the required fields are checked only when sent */
    private function validated(Request $request, bool $partial = false): array
    {
        $time = 'nullable|date_format:H:i';
        $required = $partial ? 'sometimes|required' : 'required';
        $data = $request->validate([
            'match_id' => 'nullable|exists:matches,id',
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
        return array_intersect_key($data, $request->all());
    }

    private function presentMatch(Matchs $m): array
    {
        $opponent = $m->opponentClub?->name ?? $m->opponent;
        return [
            'id' => $m->id,
            'title' => $opponent ? "مباراة ضد {$opponent}" : ($m->match_title ?: 'مباراة'),
            'at' => $m->match_date ? CarbonImmutable::parse($m->match_date)->format('Y-m-d H:i') : null,
            'place' => $m->location,
            'team' => $m->team?->name,
            'competition' => $m->competition,
        ];
    }

    private function present(TravelItinerary $t): array
    {
        $head = $t->headOfDelegationId;
        $match = $t->matchId;
        $data = [
            'id' => $t->id,
            'match_id' => $t->match_id,
            'match' => $match ? $this->presentMatch($match) : null,
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
}
