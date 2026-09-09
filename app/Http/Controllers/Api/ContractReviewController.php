<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContractReview;
use Illuminate\Support\Facades\Validator;

class ContractReviewController extends Controller
{
    // Get reviews by evaluation_id
    public function index(Request $request)
    {
        $query = ContractReview::query();

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

    // Create a new review
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|integer',
            'evaluation_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => $validator->messages()
            ], 422);
        }

        $review = ContractReview::create($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Contract Review created successfully',
            'data' => $review
        ]);
    }

    // Update an existing review
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:contract_reviews,id',
            'player_id' => 'required|integer',
            'evaluation_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => $validator->messages()
            ], 422);
        }

        $review = ContractReview::find($request->id);
        $review->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Contract Review updated successfully',
            'data' => $review
        ]);
    }

    // Delete a review
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:contract_reviews,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => $validator->messages()
            ], 422);
        }

        $review = ContractReview::find($request->id);
        
        if ($review) {
            $review->delete();
            return response()->json([
                'status' => 200,
                'message' => 'Contract Review deleted successfully'
            ]);
        }

        return response()->json([
            'status' => 404,
            'error' => 'Review not found'
        ], 404);
    }
}
