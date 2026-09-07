<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentOperation;
use App\Models\EquipmentMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentOperationController extends Controller
{
    public function index()
    {
        $operations = EquipmentOperation::with(['member', 'movements.equipment', 'addedBy'])->orderBy('id', 'desc')->get();
        return response()->json($operations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:individuals,id',
            'operation_date' => 'required|date',
            'sports_season' => 'nullable|string',
            'items' => 'required|array',
            'items.*.equipment_id' => 'required|exists:equipments,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.condition' => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            $operation = EquipmentOperation::create([
                'member_id' => $validated['member_id'],
                'operation_date' => $validated['operation_date'],
                'sports_season' => $validated['sports_season'] ?? null,
                'added_by' => auth()->id() ?? 1,
            ]);

            foreach ($validated['items'] as $item) {
                $equipment = Equipment::findOrFail($item['equipment_id']);
                
                if ($equipment->available_quantity < $item['quantity']) {
                    throw new \Exception("ط§ظ„ظƒظ…ظٹط© ط§ظ„ظ…ط·ظ„ظˆط¨ط© ظ…ظ† ط§ظ„ط¹طھط§ط¯ {$equipment->name} ط؛ظٹط± ظ…طھظˆظپط±ط©.");
                }

                EquipmentMovement::create([
                    'operation_id' => $operation->id,
                    'equipment_id' => $equipment->id,
                    'quantity' => $item['quantity'],
                    'movement_status' => 'طھط³ظ„ظٹظ…',
                    'delivery_date' => $validated['operation_date'],
                    'delivery_condition' => $item['condition'],
                ]);

                $equipment->available_quantity -= $item['quantity'];
                $equipment->save();
            }

            DB::commit();

            return response()->json(['message' => 'طھظ… طھط³ط¬ظٹظ„ ط§ظ„ط¹ظ…ظ„ظٹط© ط¨ظ†ط¬ط§ط­', 'operation' => $operation->load('movements.equipment')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function returnEquipment(Request $request)
    {
        $validated = $request->request->add(['movement_id' => $request->movement_id]);
        $validated = $request->validate([
            'movement_id' => 'required|exists:equipment_movements,id',
            'return_date' => 'required|date',
            'return_condition' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $movement = EquipmentMovement::findOrFail($validated['movement_id']);
            
            if ($movement->movement_status === 'ط¥ط±ط¬ط§ط¹') {
                throw new \Exception("ظ‡ط°ط§ ط§ظ„ط¹طھط§ط¯ طھظ… ط¥ط±ط¬ط§ط¹ظ‡ ظ…ط³ط¨ظ‚ط§ظ‹.");
            }

            $movement->update([
                'movement_status' => 'ط¥ط±ط¬ط§ط¹',
                'return_date' => $validated['return_date'],
                'return_condition' => $validated['return_condition'],
            ]);

            $equipment = Equipment::findOrFail($movement->equipment_id);
            $equipment->available_quantity += $movement->quantity;
            $equipment->save();

            DB::commit();
            return response()->json(['message' => 'طھظ… ط¥ط±ط¬ط§ط¹ ط§ظ„ط¹طھط§ط¯ ط¨ظ†ط¬ط§ط­', 'movement' => $movement], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function undoReturnEquipment(Request $request)
    {
        $validated = $request->validate([
            'movement_id' => 'required|exists:equipment_movements,id',
        ]);

        DB::beginTransaction();
        try {
            $movement = EquipmentMovement::findOrFail($validated['movement_id']);
            
            $movement->update([
                'movement_status' => 'طھط³ظ„ظٹظ…', // Handover status, since we are undoing a return
                'return_date' => null,
                'return_condition' => null,
            ]);

            $equipment = Equipment::findOrFail($movement->equipment_id);
            $equipment->available_quantity -= $movement->quantity;
            $equipment->save();

            DB::commit();
            return response()->json(['message' => 'طھظ… ط§ظ„طھط±ط§ط¬ط¹ ط¹ظ† ط§ظ„ط¥ط±ط¬ط§ط¹ ط¨ظ†ط¬ط§ط­', 'movement' => $movement], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:equipment_operations,id',
            'member_id' => 'required|exists:individuals,id',
            'operation_date' => 'required|date',
            'sports_season' => 'nullable|string',
            'items' => 'required|array',
            'items.*.equipment_id' => 'required|exists:equipments,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.condition' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $operation = EquipmentOperation::findOrFail($validated['id']);
            $operation->update([
                'member_id' => $validated['member_id'],
                'operation_date' => $validated['operation_date'],
                'sports_season' => $validated['sports_season'] ?? null,
            ]);

            // Restore all old quantities first
            foreach ($operation->movements as $movement) {
                $equipment = Equipment::find($movement->equipment_id);
                if ($equipment) {
                    $equipment->available_quantity += $movement->quantity;
                    $equipment->save();
                }
            }

            // Delete old movements
            $operation->movements()->delete();

            // Create new movements
            foreach ($validated['items'] as $item) {
                $equipment = Equipment::findOrFail($item['equipment_id']);
                
                if ($equipment->available_quantity < $item['quantity']) {
                    throw new \Exception("ط§ظ„ظƒظ…ظٹط© ط؛ظٹط± ظƒط§ظپظٹط©");
                }

                EquipmentMovement::create([
                    'operation_id' => $operation->id,
                    'equipment_id' => $equipment->id,
                    'quantity' => $item['quantity'],
                    'movement_status' => 'طھط³ظ„ظٹظ…',
                    'delivery_date' => $validated['operation_date'],
                    'delivery_condition' => $item['condition'],
                ]);

                $equipment->available_quantity -= $item['quantity'];
                $equipment->save();
            }

            DB::commit();
            return response()->json(['message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط¹ظ…ظ„ظٹط© ط¨ظ†ط¬ط§ط­', 'operation' => $operation->load('movements.equipment')], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:equipment_operations,id',
        ]);

        DB::beginTransaction();
        try {
            $operation = EquipmentOperation::findOrFail($validated['id']);
            
            // Restore quantities
            foreach ($operation->movements as $movement) {
                if ($movement->movement_status === 'طھط³ظ„ظٹظ…') {
                    $equipment = Equipment::find($movement->equipment_id);
                    if ($equipment) {
                        $equipment->available_quantity += $movement->quantity;
                        $equipment->save();
                    }
                }
            }
            
            // Delete movements and operation
            $operation->movements()->delete();
            $operation->delete();

            DB::commit();
            return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ط¹ظ…ظ„ظٹط©'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

