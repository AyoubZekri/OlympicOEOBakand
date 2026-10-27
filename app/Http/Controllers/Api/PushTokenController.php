<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/** The Android app's phone: its notifications' token, given once signed in (null: signed out, no more notifications) */
class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $token = $request->validate(['token' => 'nullable|string|max:4096'])['token'] ?? null;
        $user = $request->user();
        if ($token) {
            // One phone, one account: the one signed in now
            User::where('fcm_token', $token)->where('id', '!=', $user->id)->update(['fcm_token' => null]);
        }
        $user->forceFill(['fcm_token' => $token])->save();

        return response()->json(['message' => 'ok']);
    }
}
