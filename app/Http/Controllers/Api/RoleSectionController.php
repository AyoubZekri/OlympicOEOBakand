<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleSection;
use Illuminate\Http\Request;

/** The roles page's sections: listed, added, renamed, deleted (their roles stay, in no section); a role moved into one */
class RoleSectionController extends Controller
{
    public function index()
    {
        return response()->json(RoleSection::orderBy('id')->get(['id', 'name']));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $section = RoleSection::create(['name' => trim($data['name'])]);

        return response()->json(['message' => 'تمت الإضافة', 'section' => $section->only(['id', 'name'])], 201);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['id' => 'required|integer|exists:role_sections,id', 'name' => 'required|string|max:100']);
        $section = RoleSection::findOrFail($data['id']);
        $section->update(['name' => trim($data['name'])]);

        return response()->json(['message' => 'تم الحفظ', 'section' => $section->only(['id', 'name'])]);
    }

    /** One or several at once (the selection bar); their roles are left in no section */
    public function destroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'])['ids'];
        Role::whereIn('section_id', $ids)->update(['section_id' => null]);
        $deleted = RoleSection::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'تم الحذف', 'deleted' => $deleted]);
    }

    /** A role into a section (null: in none) — dragged onto it on the computer, chosen on the phone */
    public function move(Request $request)
    {
        $data = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'section_id' => 'nullable|integer|exists:role_sections,id',
        ]);
        Role::whereKey($data['role_id'])->update(['section_id' => $data['section_id'] ?? null]);

        return response()->json(['message' => 'تم النقل']);
    }
}
