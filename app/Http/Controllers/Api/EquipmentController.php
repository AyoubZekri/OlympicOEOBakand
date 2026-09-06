<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function index()
    {
        $equipments = Equipment::all();
        return response()->json($equipments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_quantity' => 'required|numeric',
            'available_quantity' => 'required|numeric',
        ]);

        $equipment = Equipment::create($validated);

        return response()->json(['message' => 'Equipment created successfully', 'equipment' => $equipment], 201);
    }

    public function show(Request $request)
    {
        $request->validate(['id' => 'required|exists:equipments,id']);
        
        $equipment = Equipment::findOrFail($request->id);
        return response()->json($equipment);
    }

    public function update(Request $request)
    {
        $request->validate(['id' => 'required|exists:equipments,id']);
        $equipment = Equipment::findOrFail($request->id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'total_quantity' => 'sometimes|numeric',
            'available_quantity' => 'sometimes|numeric',
        ]);

        $equipment->update($validated);

        return response()->json(['message' => 'Equipment updated successfully', 'equipment' => $equipment]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:equipments,id']);
        
        $equipment = Equipment::findOrFail($request->id);
        $equipment->delete();

        return response()->json(['message' => 'Equipment deleted successfully']);
    }
}
