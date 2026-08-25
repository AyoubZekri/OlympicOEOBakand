<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Individual;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IndividualController extends Controller
{
    public function index()
    {
        // Load the relationship for addedBy and team if needed
        $individuals = Individual::with(['addedBy', 'team'])->get();
        return response()->json($individuals);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:player,coach,employee',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'place_of_birth' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'Shirt_number' => 'nullable|integer',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'team_id' => 'nullable|exists:teams,id',
            'added_by' => 'nullable|exists:users,id',
        ]);

        // If you want to automatically set added_by to the authenticated user later:
        // if ($request->user()) {
        //     $validated['added_by'] = $request->user()->id;
        // }

        $individual = Individual::create($validated);

        return response()->json(['message' => 'Individual created successfully', 'individual' => $individual], 201);
    }

    public function show(Request $request)
    {
        $request->validate(['id' => 'required|exists:individuals,id']);
        
        $individual = Individual::with(['addedBy', 'team'])->findOrFail($request->id);
        return response()->json($individual);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:individuals,id']);
        $individual = Individual::findOrFail($request->id);

        $validated = $request->validate([
            'type' => 'sometimes|string|in:player,coach,employee',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'place_of_birth' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'Shirt_number' => 'nullable|integer',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'team_id' => 'nullable|exists:teams,id',
            // Typically added_by shouldn't change, but we can allow it if needed.
            'added_by' => 'nullable|exists:users,id',
        ]);

        $individual->update($validated);

        return response()->json(['message' => 'Individual updated successfully', 'individual' => $individual]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:individuals,id']);
        
        $individual = Individual::findOrFail($request->id);
        $individual->delete();

        return response()->json(['message' => 'Individual deleted successfully']);
    }

    public function printInternalSystem(Request $request)
    {
        $request->validate(['id' => 'required|exists:individuals,id']);
        
        $individual = Individual::findOrFail($request->id);
        $individual->is_internal_system_printed = true;
        $individual->save();

        return response()->json(['message' => 'Individual internal system printed successfully', 'individual' => $individual]);
    }
}
