<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerEvaluationController extends Controller
{
    public function index()
    {
        $evaluations = PlayerEvaluation::with(['playerId', 'coachId', 'sportingDirectorId'])->orderBy('id', 'desc')->get();
        
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
                'coach_id' => auth()->id() ?? 1, // Fallback if not authenticated
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

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:player_evaluations,id',
        ]);

        try {
            $evaluation = PlayerEvaluation::findOrFail($validated['id']);
            $evaluation->delete();

            return response()->json(['message' => 'تم حذف التقييم بنجاح'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
