<?php

namespace App\Http\Controllers\Api;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends ApiController
{
    /**
     * Register (or refresh) a push token for the authenticated user. Tokens are
     * globally unique, so a token that moves to a new account is reassigned.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:expo,ios,android,web'],
        ]);

        $token = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'expo',
                'last_used_at' => now(),
            ]
        );

        return $this->ok([
            'id' => $token->id,
            'platform' => $token->platform,
        ], 'Device registered for push notifications', 201);
    }

    /**
     * Remove a push token (e.g. on sign-out). Only the owner can remove it.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->ok(null, 'Device unregistered');
    }
}
