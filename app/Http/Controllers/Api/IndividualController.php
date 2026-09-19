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
            'type' => 'required|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'place_of_birth' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
                        'Shirt_number' => 'nullable|integer',
            'email' => 'nullable|email|max:255',
            'position' => 'nullable|string|max:255',
            'preferred_foot' => 'nullable|string|in:íãíä,íÓÇÑ,ßáÊÇåãÇ',
            'emergency_contact_name' => 'nullable|string|max:255',
                        'emergency_contact_phone' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'national_id_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:5120',
            'medical_certificate' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:5120',
            'insurance_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:5120',
            
            
            
            'status' => 'nullable|string|in:active,inactive,suspended',
            'team_id' => 'nullable|exists:teams,id',
            // Typically added_by shouldn't change, but we can allow it if needed.
            'added_by' => 'nullable|exists:users,id',
            'photo' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,heic,heif|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }

        if ($request->hasFile('national_id_document')) {
            $validated['national_id_document'] = $request->file('national_id_document')->store('documents', 'public');
        }

        if ($request->hasFile('medical_certificate')) {
            $validated['medical_certificate'] = $request->file('medical_certificate')->store('documents', 'public');
        }

        if ($request->hasFile('insurance_document')) {
            $validated['insurance_document'] = $request->file('insurance_document')->store('documents', 'public');
        }

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



