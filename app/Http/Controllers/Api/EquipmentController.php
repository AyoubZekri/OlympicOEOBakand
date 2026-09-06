<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('equipments', 'public');
            $validated['image'] = url('storage/' . $path);
        }

        $validated['added_by'] = auth()->id() ?? 1;

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
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('equipments', 'public');
            $validated['image'] = url('storage/' . $path);
        }

        $equipment->update($validated);

        return response()->json(['message' => 'Equipment updated successfully', 'equipment' => $equipment], 200);
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:equipments,id']);
        $equipment = Equipment::findOrFail($request->id);
        $equipment->delete();
        return response()->json(['message' => 'Equipment deleted successfully'], 200);
    }
}

