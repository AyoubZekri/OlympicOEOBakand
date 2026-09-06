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
        $operations = EquipmentOperation::with(['member', 'movements.equipment', 'addedBy'])->get();
        return response()->json($operations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:individuals,id',
            'operation_date' => 'required|date',
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
                'added_by' => auth()->id() ?? 1,
            ]);

            foreach ($validated['items'] as $item) {
                $equipment = Equipment::findOrFail($item['equipment_id']);
                
                if ($equipment->available_quantity < $item['quantity']) {
                    throw new \Exception("الكمية المطلوبة للعتاد {$equipment->name} غير متوفرة.");
                }

                EquipmentMovement::create([
                    'operation_id' => $operation->id,
                    'equipment_id' => $equipment->id,
                    'quantity' => $item['quantity'],
                    'movement_status' => 'مسلم',
                    'delivery_date' => $validated['operation_date'],
                    'delivery_condition' => $item['condition'],
                ]);

                $equipment->available_quantity -= $item['quantity'];
                $equipment->save();
            }

            DB::commit();

            return response()->json(['message' => 'Operation created successfully', 'operation' => $operation->load('movements.equipment')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function returnEquipment(Request $request)
    {
        $validated = $request->validate([
            'movement_id' => 'required|exists:equipment_movements,id',
            'return_date' => 'required|date',
            'return_condition' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $movement = EquipmentMovement::findOrFail($validated['movement_id']);
            
            if ($movement->movement_status === 'مرتجع') {
                throw new \Exception("هذا العتاد تم إرجاعه مسبقاً.");
            }

            $movement->update([
                'movement_status' => 'مرتجع',
                'return_date' => $validated['return_date'],
                'return_condition' => $validated['return_condition'],
            ]);

            $equipment = Equipment::findOrFail($movement->equipment_id);
            $equipment->available_quantity += $movement->quantity;
            $equipment->save();

            DB::commit();
            return response()->json(['message' => 'Equipment returned successfully', 'movement' => $movement], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

