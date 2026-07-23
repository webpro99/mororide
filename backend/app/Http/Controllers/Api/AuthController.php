<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ConciergeProfile;
use App\Models\DriverProfile;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function register(RegisterRequest $request, WalletService $walletService)
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => 'active',
            'password' => Hash::make($data['password']),
        ]);

        if ($user->role === 'rider') {
            RiderProfile::create(['user_id' => $user->id]);
        } elseif ($user->role === 'driver') {
            DriverProfile::create(['user_id' => $user->id, 'approval_state' => 'incomplete']);
        } elseif ($user->role === 'concierge') {
            ConciergeProfile::create(['user_id' => $user->id]);
        }

        $walletService->createWalletForUser($user);

        return $this->ok([
            'user' => $user->load('wallet'),
            'token' => $user->createToken('api')->plainTextToken,
        ], 'Registered', 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active.',
                'data' => null,
            ], 403);
        }

        return $this->ok([
            'user' => $user->load('wallet', 'driverProfile', 'conciergeProfile', 'riderProfile'),
            'token' => $user->createToken('api')->plainTextToken,
        ], 'Logged in');
    }

    public function me(Request $request)
    {
        return $this->ok($request->user()->load('wallet', 'driverProfile', 'conciergeProfile', 'riderProfile'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Logged out');
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $request->user()->update($data);

        return $this->ok($request->user()->fresh(), 'Profile updated');
    }
}
