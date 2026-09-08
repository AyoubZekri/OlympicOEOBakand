<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use Illuminate\Http\Request;

class CorrespondenceController extends Controller
{
    public function index()
    {
        $correspondences = Correspondence::with(['receiverId', 'senderId'])->orderBy('id', 'desc')->get();
        
        $data = [];
        foreach ($correspondences as $item) {
            $status = 'pending';
            if ($item->is_acknowledged) {
                $status = 'closed';
            }

            $data[] = [
                'id' => (string) $item->id,
                'memberId' => $item->receiver_id,
                'correspondenceNumber' => 'COR-' . str_pad($item->id, 4, '0', STR_PAD_LEFT),
                'date' => $item->created_at ? $item->created_at->format('Y-m-d') : '',
                'subject' => $item->subject ?? '',
                'type' => $item->message_type ?? '',
                'text' => $item->content ?? '',
                'requiredAction' => $item->required_action ?? '',
                'adminName' => $item->senderId ? $item->senderId->first_name . ' ' . $item->senderId->last_name : 'الإدارة',
                'status' => $status,
                'createdAt' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '',
            ];
        }

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'memberId' => 'required|exists:individuals,id',
            'subject' => 'required|string',
            'type' => 'required|string',
            'text' => 'required|string',
            'requiredAction' => 'nullable|string',
            'status' => 'required|string',
        ]);

        $item = Correspondence::create([
            'receiver_id' => $validated['memberId'],
            'subject' => $validated['subject'],
            'message_type' => $validated['type'],
            'content' => $validated['text'],
            'required_action' => $validated['requiredAction'] ?? '',
            'is_acknowledged' => $validated['status'] === 'closed' ? true : false,
            // Assume the sender is the logged in user or admin individual. For now, leave sender_id null or set a default
        ]);

        return response()->json(['message' => 'تم إضافة المراسلة بنجاح', 'id' => (string) $item->id], 201);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:correspondences,id',
            'memberId' => 'required|exists:individuals,id',
            'subject' => 'required|string',
            'type' => 'required|string',
            'text' => 'required|string',
            'requiredAction' => 'nullable|string',
            'status' => 'required|string',
        ]);

        $item = Correspondence::findOrFail($validated['id']);
        $item->update([
            'receiver_id' => $validated['memberId'],
            'subject' => $validated['subject'],
            'message_type' => $validated['type'],
            'content' => $validated['text'],
            'required_action' => $validated['requiredAction'] ?? '',
            'is_acknowledged' => $validated['status'] === 'closed' ? true : false,
        ]);

        return response()->json(['message' => 'تم التحديث بنجاح'], 200);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:correspondences,id',
        ]);

        Correspondence::destroy($validated['id']);

        return response()->json(['message' => 'تم الحذف بنجاح'], 200);
    }
}
