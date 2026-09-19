<?php
namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    public function index()
    {
        return response()->json(Club::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('clubs', 'public');
            $data['logo'] = url('storage/' . $path);
        }

        $club = Club::create($data);
        return response()->json($club, 201);
    }

    public function update(Request $request, $id)
    {
        $club = Club::findOrFail($id);
        
        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('clubs', 'public');
            $data['logo'] = url('storage/' . $path);
        }

        $club->update($data);
        return response()->json($club);
    }

    public function destroy($id)
    {
        Club::destroy($id);
        return response()->json(['message' => 'Club deleted successfully']);
    }
}
