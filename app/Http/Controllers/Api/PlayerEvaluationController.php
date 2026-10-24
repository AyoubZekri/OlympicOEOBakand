<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Individual;
use App\Models\PlayerEvaluation;
use App\Services\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerEvaluationController extends Controller
{
    /** The evaluations; ?member_id= (or ?player_id=): that player's only */
    public function index(Request $request)
    {
        $player = $request->query('member_id') ?? $request->query('player_id');
        $evaluations = PlayerEvaluation::with(['playerId', 'coachId', 'sportingDirectorId'])
            ->when($player !== null && $player !== '', fn ($q) => $q->where('player_id', (int) $player))
            ->orderBy('id', 'desc')->get();
        
        $data = $evaluations->map(function ($evaluation) {
            return [
                'id' => (string) $evaluation->id,
                'playerId' => (string) $evaluation->player_id,
                'playerName' => $evaluation->playerId ? $evaluation->playerId->first_name . ' ' . $evaluation->playerId->last_name : 'غير معروف',
                'season' => $evaluation->season ?? '',
                'evaluationType' => $evaluation->evaluation_type ?? '',
                'periodStart' => $evaluation->period_start ?? '',
                'periodEnd' => $evaluation->period_end ?? '',
                'matchesPlayed' => $evaluation->matches_played ?? 0,
                'minutesPlayed' => $evaluation->minutes_played ?? 0,
                'scoreDiscipline' => $evaluation->score_discipline ?? 0,
                'scoreFitness' => $evaluation->score_fitness ?? 0,
                'scoreTechnical' => $evaluation->score_technical ?? 0,
                'scoreTactical' => $evaluation->score_tactical ?? 0,
                'scoreMatchPerformance' => $evaluation->score_match_performance ?? 0,
                'scoreInstructions' => $evaluation->score_instructions ?? 0,
                'scoreBehavior' => $evaluation->score_behavior ?? 0,
                'totalScore' => $evaluation->total_score ?? 0,
                'strengths' => $evaluation->strengths ?? '',
                'weaknesses' => $evaluation->weaknesses ?? '',
                'recommendation' => $evaluation->recommendation ?? '',
                'createdAt' => $evaluation->created_at ? date('Y-m-d', strtotime($evaluation->created_at)) : '',
            ];
        });

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'playerId' => 'required|exists:individuals,id',
            'season' => 'nullable|string',
            'evaluationType' => 'required|string',
            'periodStart' => 'nullable|date',
            'periodEnd' => 'nullable|date',
            'matchesPlayed' => 'nullable|integer',
            'minutesPlayed' => 'nullable|integer',
            'scoreDiscipline' => 'nullable|integer|min:0|max:100',
            'scoreFitness' => 'nullable|integer|min:0|max:100',
            'scoreTechnical' => 'nullable|integer|min:0|max:100',
            'scoreTactical' => 'nullable|integer|min:0|max:100',
            'scoreMatchPerformance' => 'nullable|integer|min:0|max:100',
            'scoreInstructions' => 'nullable|integer|min:0|max:100',
            'scoreBehavior' => 'nullable|integer|min:0|max:100',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'recommendation' => 'nullable|string',
        ]);

        $totalScore = ($validated['scoreDiscipline'] ?? 0) + 
                      ($validated['scoreFitness'] ?? 0) + 
                      ($validated['scoreTechnical'] ?? 0) + 
                      ($validated['scoreTactical'] ?? 0) + 
                      ($validated['scoreMatchPerformance'] ?? 0) + 
                      ($validated['scoreInstructions'] ?? 0) + 
                      ($validated['scoreBehavior'] ?? 0);

        try {
            $evaluation = PlayerEvaluation::create([
                'player_id' => $validated['playerId'],
                'coach_id' => null, // Fallback if not authenticated
                'season' => $validated['season'] ?? null,
                'evaluation_type' => $validated['evaluationType'],
                'period_start' => $validated['periodStart'] ?? null,
                'period_end' => $validated['periodEnd'] ?? null,
                'matches_played' => $validated['matchesPlayed'] ?? null,
                'minutes_played' => $validated['minutesPlayed'] ?? null,
                'score_discipline' => $validated['scoreDiscipline'] ?? null,
                'score_fitness' => $validated['scoreFitness'] ?? null,
                'score_technical' => $validated['scoreTechnical'] ?? null,
                'score_tactical' => $validated['scoreTactical'] ?? null,
                'score_match_performance' => $validated['scoreMatchPerformance'] ?? null,
                'score_instructions' => $validated['scoreInstructions'] ?? null,
                'score_behavior' => $validated['scoreBehavior'] ?? null,
                'total_score' => $totalScore,
                'strengths' => $validated['strengths'] ?? null,
                'weaknesses' => $validated['weaknesses'] ?? null,
                'recommendation' => $validated['recommendation'] ?? null,
            ]);

            return response()->json([
                'message' => 'تم حفظ التقييم بنجاح',
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:player_evaluations,id',
            'playerId' => 'required|exists:individuals,id',
            'season' => 'nullable|string',
            'evaluationType' => 'required|string',
            'periodStart' => 'nullable|date',
            'periodEnd' => 'nullable|date',
            'matchesPlayed' => 'nullable|integer',
            'minutesPlayed' => 'nullable|integer',
            'scoreDiscipline' => 'nullable|integer|min:0|max:100',
            'scoreFitness' => 'nullable|integer|min:0|max:100',
            'scoreTechnical' => 'nullable|integer|min:0|max:100',
            'scoreTactical' => 'nullable|integer|min:0|max:100',
            'scoreMatchPerformance' => 'nullable|integer|min:0|max:100',
            'scoreInstructions' => 'nullable|integer|min:0|max:100',
            'scoreBehavior' => 'nullable|integer|min:0|max:100',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'recommendation' => 'nullable|string',
        ]);

        $totalScore = ($validated['scoreDiscipline'] ?? 0) + 
                      ($validated['scoreFitness'] ?? 0) + 
                      ($validated['scoreTechnical'] ?? 0) + 
                      ($validated['scoreTactical'] ?? 0) + 
                      ($validated['scoreMatchPerformance'] ?? 0) + 
                      ($validated['scoreInstructions'] ?? 0) + 
                      ($validated['scoreBehavior'] ?? 0);

        try {
            $evaluation = PlayerEvaluation::findOrFail($validated['id']);
            $evaluation->update([
                'player_id' => $validated['playerId'],
                'season' => $validated['season'] ?? null,
                'evaluation_type' => $validated['evaluationType'],
                'period_start' => $validated['periodStart'] ?? null,
                'period_end' => $validated['periodEnd'] ?? null,
                'matches_played' => $validated['matchesPlayed'] ?? null,
                'minutes_played' => $validated['minutesPlayed'] ?? null,
                'score_discipline' => $validated['scoreDiscipline'] ?? null,
                'score_fitness' => $validated['scoreFitness'] ?? null,
                'score_technical' => $validated['scoreTechnical'] ?? null,
                'score_tactical' => $validated['scoreTactical'] ?? null,
                'score_match_performance' => $validated['scoreMatchPerformance'] ?? null,
                'score_instructions' => $validated['scoreInstructions'] ?? null,
                'score_behavior' => $validated['scoreBehavior'] ?? null,
                'total_score' => $totalScore,
                'strengths' => $validated['strengths'] ?? null,
                'weaknesses' => $validated['weaknesses'] ?? null,
                'recommendation' => $validated['recommendation'] ?? null,
            ]);

            return response()->json(['message' => 'تم تحديث التقييم بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /** Each criterion's maximum mark (as in the evaluation form) */
    private const MAX = [
        'scoreDiscipline' => 10, 'scoreFitness' => 15, 'scoreTechnical' => 20, 'scoreTactical' => 15,
        'scoreMatchPerformance' => 20, 'scoreInstructions' => 10, 'scoreBehavior' => 10,
    ];

    /**
     * A group evaluation (from the Excel sheet): one season, type and period for every row, each row a player's marks.
     * Only who may evaluate; only the active players; every mark within its maximum. All saved, or none.
     */
    public function bulk(Request $request)
    {
        abort_unless(RolePermissions::can($request->user(), 'members', 'evaluate'), 403, 'ليست لديك صلاحية');
        $rules = [
            'season' => 'nullable|string|max:20',
            'evaluationType' => 'required|string|max:50',
            'periodStart' => 'nullable|date',
            'periodEnd' => 'nullable|date|after_or_equal:periodStart',
            'items' => 'required|array|min:1|max:500',
            'items.*.playerId' => 'required|integer|distinct',
            'items.*.strengths' => 'nullable|string|max:2000',
            'items.*.weaknesses' => 'nullable|string|max:2000',
            'items.*.recommendation' => 'nullable|string|max:100',
        ];
        foreach (self::MAX as $key => $max) {
            $rules["items.*.{$key}"] = "required|integer|min:0|max:{$max}";
        }
        $data = $request->validate($rules, [
            'items.required' => 'لا توجد تقييمات للحفظ',
            'items.*.playerId.distinct' => 'لاعب مكرر في الملف',
        ]);

        // Only the active players
        $ids = array_column($data['items'], 'playerId');
        $players = Individual::whereIn('id', $ids)->where('type', 'player')->where('status', 'active')->pluck('id')->all();
        $wrong = array_values(array_diff($ids, $players));
        if ($wrong) {
            return response()->json(['message' => 'بعض الصفوف ليست للاعبين نشطين', 'errors' => ['items' => ['ليس لاعباً نشطاً: ' . implode('، ', $wrong)]]], 422);
        }

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $item) {
                PlayerEvaluation::create([
                    'player_id' => $item['playerId'],
                    'coach_id' => null,
                    'season' => $data['season'] ?? null,
                    'evaluation_type' => $data['evaluationType'],
                    'period_start' => $data['periodStart'] ?? null,
                    'period_end' => $data['periodEnd'] ?? null,
                    'score_discipline' => $item['scoreDiscipline'],
                    'score_fitness' => $item['scoreFitness'],
                    'score_technical' => $item['scoreTechnical'],
                    'score_tactical' => $item['scoreTactical'],
                    'score_match_performance' => $item['scoreMatchPerformance'],
                    'score_instructions' => $item['scoreInstructions'],
                    'score_behavior' => $item['scoreBehavior'],
                    'total_score' => array_sum(array_map(fn ($k) => $item[$k], array_keys(self::MAX))),
                    'strengths' => $item['strengths'] ?? null,
                    'weaknesses' => $item['weaknesses'] ?? null,
                    'recommendation' => $item['recommendation'] ?? null,
                ]);
            }
        });

        return response()->json(['message' => 'تم حفظ ' . count($data['items']) . ' تقييم', 'count' => count($data['items'])], 201);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:player_evaluations,id',
        ]);

        try {
            $evaluation = PlayerEvaluation::findOrFail($validated['id']);
            
            // Delete child records to satisfy foreign key constraints
            \DB::table('improvement_programs')->where('evaluation_id', $evaluation->id)->delete();
            \DB::table('contract_reviews')->where('evaluation_id', $evaluation->id)->delete();
            
            $evaluation->delete();

            return response()->json(['message' => 'تم حذف التقييم بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

