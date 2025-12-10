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
            // Development mode: If no public key is configured, allow test tokens
            if (empty($this->publicKey)) {
                if (app()->environment(['local', 'testing', 'development'])) {
                    // Try to decode as a simple test token (base64 encoded JSON)
                    try {
                        $decoded = json_decode(base64_decode($token), true);
                        if ($decoded && isset($decoded['sub'])) {
                            return $decoded;
                        }
                    } catch (Exception $e) {
                        // Not a test token, continue to normal verification
                    }
                }
                Log::warning('JWT public key not configured');
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
        // In development, try to find by email if external_user_id doesn't match
        $user = User::where('external_user_id', $externalUserId)->first();

        // Development fallback: try to find by email
        if (!$user && app()->environment(['local', 'testing', 'development'])) {
            $email = $payload['email'] ?? null;
            if ($email) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    // Update external_user_id for future lookups
                    $user->update(['external_user_id' => $externalUserId]);
                }
            }
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
            return null;
        }

        return $this->authenticateFromToken($token);
    }
}

