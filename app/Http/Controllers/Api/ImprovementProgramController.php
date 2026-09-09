<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImprovementProgram;
use Illuminate\Support\Facades\Validator;

class ImprovementProgramController extends Controller
{
    // Get programs by evaluation_id or player_id
    public function index(Request $request)
    {
        $query = ImprovementProgram::query();

        if ($request->has('evaluation_id')) {
            $query->where('evaluation_id', $request->evaluation_id);
        }
        
        if ($request->has('player_id')) {
            $query->where('player_id', $request->player_id);
        }

        return response()->json([
            'status' => 200,
            'data' => $query->get()
        ]);
    }

    // Create a new program
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'evaluation_id' => 'required',
            'player_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => $validator->messages()
            ], 422);
        }

        $program = ImprovementProgram::create($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Program created successfully',
            'data' => $program
        ]);
    }

    // Update an existing program
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:improvement_programs,id',
            'evaluation_id' => 'required',
            'player_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => $validator->messages()
            ], 422);
        }

        $program = ImprovementProgram::find($request->id);
        $program->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Program updated successfully',
            'data' => $program
        ]);
    }

    // Delete a program
    public function delete(Request $request)
    {
        $program = ImprovementProgram::find($request->id);
        
        if ($program) {
            $program->delete();
            return response()->json([
                'status' => 200,
                'message' => 'Program deleted successfully'
            ]);
        }

        return response()->json([
            'status' => 404,
            'error' => 'Program not found'
        ], 404);
    }
}
