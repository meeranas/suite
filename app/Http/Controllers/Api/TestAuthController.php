<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TestAuthController extends Controller
{
    /**
     * Generate a test JWT token for development
     * 
     * NOTE: This is for development/testing only!
     * In production, tokens should come from your main platform.
     */
    public function generateTestToken(Request $request): JsonResponse
    {
        // Allow in all environments if JWT public key is not configured
        // This enables testing without setting up JWT keys
        $jwtPublicKey = config('auth.jwt_public_key', '');
        if (app()->environment('production') && !empty($jwtPublicKey)) {
            return response()->json(['error' => 'Not available in production when JWT keys are configured'], 403);
        }

        $request->validate([
            'email' => 'required|email',
            'role' => 'nullable|in:admin,user',
        ]);

        $email = $request->email;
        $role = $request->role ?? 'user';

        // Find or create user
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Create a test user
            $account = \App\Models\Account::firstOrCreate(['name' => 'Test Account']);

            $user = User::create([
                'account_id' => $account->id,
                'first_name' => explode('@', $email)[0],
                'last_name' => 'User',
                'email' => $email,
                'password' => Hash::make('password'),
                'subscription_tier' => [$role === 'admin' ? 'enterprise' : 'pro'],
                'owner' => $role === 'admin',
            ]);

            // Assign role
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        // Generate a simple test token (base64 encoded JSON)
        // In production, this would be a real JWT signed by your main platform
        $payload = [
            'sub' => (string) $user->id, // external_user_id
            'email' => $user->email,
            'name' => $user->name,
            'iss' => 'test-platform',
            'iat' => now()->timestamp,
            'exp' => now()->addDays(7)->timestamp,
            'subscription_tier' => $user->subscription_tier ?? ['pro'],
        ];

        // For testing, we'll create a simple base64 encoded token
        // The JWT service will decode this in development mode
        $token = base64_encode(json_encode($payload));

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
            'note' => 'This is a test token for development. In production, use tokens from your main platform.',
        ]);
    }

    /**
     * Alternative: Login with email/password and get a test token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Generate test token
        $payload = [
            'sub' => (string) $user->id, // external_user_id
            'email' => $user->email,
            'name' => $user->name,
            'iss' => 'test-platform',
            'iat' => now()->timestamp,
            'exp' => now()->addDays(7)->timestamp,
            'subscription_tier' => $user->subscription_tier ?? ['pro'],
        ];

        $token = base64_encode(json_encode($payload));

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }
}

