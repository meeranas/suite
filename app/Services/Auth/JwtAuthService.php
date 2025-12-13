<?php

namespace App\Services\Auth;

use App\Models\User;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class JwtAuthService
{
    protected string $publicKey;
    protected string $algorithm = 'RS256';

    public function __construct()
    {
        $this->publicKey = config('auth.jwt_public_key', '');
    }

    /**
     * Verify JWT token and return decoded payload
     */
    public function verifyToken(string $token): ?array
    {
        try {
            // If no public key is configured, allow test tokens (base64 encoded JSON)
            // This works in all environments to allow testing without JWT keys
            if (empty($this->publicKey)) {
                // Try to decode as a simple test token (base64 encoded JSON)
                try {
                    $decoded = json_decode(base64_decode($token), true);
                    if ($decoded && isset($decoded['sub'])) {
                        // Check if token is expired
                        if (isset($decoded['exp']) && $decoded['exp'] < now()->timestamp) {
                            Log::warning('Test token expired');
                            return null;
                        }
                        return $decoded;
                    }
                } catch (Exception $e) {
                    // Not a test token, continue to normal verification
                }
                Log::warning('JWT public key not configured and token is not a valid test token');
                return null;
            }

            $decoded = JWT::decode($token, new Key($this->publicKey, $this->algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            Log::error('JWT verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Authenticate user from JWT token
     */
    public function authenticateFromToken(string $token): ?User
    {
        $payload = $this->verifyToken($token);

        if (!$payload) {
            return null;
        }

        $externalUserId = $payload['sub'] ?? $payload['user_id'] ?? null;
        $issuer = $payload['iss'] ?? null;

        if (!$externalUserId) {
            return null;
        }

        // Find or create user
        // First try to find by external_user_id
        $user = User::where('external_user_id', $externalUserId)->first();

        // Fallback: try to find by email (works in all environments for test tokens)
        if (!$user) {
            $email = $payload['email'] ?? null;
            if ($email) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    Log::info('User found by email, updating external_user_id', [
                        'user_id' => $user->id,
                        'email' => $email,
                        'external_user_id' => $externalUserId,
                    ]);
                    // Update external_user_id for future lookups
                    $user->update(['external_user_id' => $externalUserId]);
                }
            }
        }

        // Also try to find by user ID if sub is numeric (for test tokens)
        if (!$user && is_numeric($externalUserId)) {
            $user = User::find((int) $externalUserId);
            if ($user) {
                Log::info('User found by ID, updating external_user_id', [
                    'user_id' => $user->id,
                    'external_user_id' => $externalUserId,
                ]);
                // Update external_user_id for future lookups
                $user->update(['external_user_id' => $externalUserId]);
            }
        }

        if (!$user) {
            Log::info('User not found, will create new user from JWT payload', [
                'external_user_id' => $externalUserId,
                'email' => $payload['email'] ?? null,
            ]);
        }

        if (!$user) {
            // Create new user from JWT payload
            $user = $this->createUserFromJwt($payload, $externalUserId, $issuer);
        } else {
            // Update last verified timestamp
            $user->update([
                'last_jwt_verified_at' => now(),
            ]);
        }

        return $user;
    }

    /**
     * Create user from JWT payload
     */
    protected function createUserFromJwt(array $payload, string $externalUserId, ?string $issuer): User
    {
        $email = $payload['email'] ?? $payload['sub'] ?? null;
        $name = $payload['name'] ?? $payload['preferred_username'] ?? 'User';
        $subscriptionTier = $payload['subscription_tier'] ?? $payload['tier'] ?? null;

        if (!$email) {
            throw new Exception('Email not found in JWT payload');
        }

        $user = User::create([
            'external_user_id' => $externalUserId,
            'jwt_issuer' => $issuer,
            'email' => $email,
            'name' => $name,
            'subscription_tier' => $subscriptionTier ? (array) $subscriptionTier : null,
            'last_jwt_verified_at' => now(),
        ]);

        // Assign default role
        if (!$user->hasRole('user')) {
            $user->assignRole('user');
        }

        return $user;
    }

    /**
     * Extract user from Authorization header
     */
    public function getUserFromRequest(): ?User
    {
        $token = request()->bearerToken();

        if (!$token) {
            Log::warning('No bearer token found in request', [
                'headers' => request()->headers->all(),
            ]);
            return null;
        }

        Log::info('JWT token found, attempting authentication', [
            'token_length' => strlen($token),
            'token_preview' => substr($token, 0, 20) . '...',
        ]);

        return $this->authenticateFromToken($token);
    }
}

