<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiConfig;
use App\Services\Encryption\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalApiConfigController extends Controller
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function index(Request $request): JsonResponse
    {
        $configs = ExternalApiConfig::with('createdBy')
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        return response()->json($configs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:500',
            'api_type' => 'nullable|string|in:rest,graphql',
            'api_key' => 'nullable|string', // Optional for APIs that don't require keys
            'api_secret' => 'nullable|string',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $config = ExternalApiConfig::create([
            'name' => $request->name,
            'provider' => $request->provider,
            'base_url' => $request->base_url,
            'api_type' => $request->api_type ?? 'rest',
            'encrypted_api_key' => $request->api_key
                ? $this->encryption->encryptApiKey($request->api_key)
                : null,
            'encrypted_api_secret' => $request->api_secret
                ? $this->encryption->encrypt($request->api_secret)
                : null,
            'config' => $request->config ?? [],
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($config, 201);
    }

    public function show(ExternalApiConfig $externalApiConfig): JsonResponse
    {
        // Don't expose encrypted keys in response
        $config = $externalApiConfig->toArray();
        unset($config['encrypted_api_key'], $config['encrypted_api_secret']);

        return response()->json($config);
    }

    public function update(Request $request, ExternalApiConfig $externalApiConfig): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'provider' => 'sometimes|string|max:255',
            'base_url' => 'nullable|url|max:500',
            'api_type' => 'nullable|string|in:rest,graphql',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $updateData = $request->only(['name', 'provider', 'base_url', 'api_type', 'config']);
        $updateData['is_active'] = $request->boolean('is_active', $externalApiConfig->is_active);

        // Only update API key if provided
        if ($request->has('api_key') && $request->api_key !== null) {
            $updateData['encrypted_api_key'] = $request->api_key
                ? $this->encryption->encryptApiKey($request->api_key)
                : null;
        }

        // Only update API secret if provided
        if ($request->has('api_secret')) {
            $updateData['encrypted_api_secret'] = $request->api_secret
                ? $this->encryption->encrypt($request->api_secret)
                : null;
        }

        $externalApiConfig->update($updateData);

        // Don't expose encrypted keys in response
        $config = $externalApiConfig->toArray();
        unset($config['encrypted_api_key'], $config['encrypted_api_secret']);

        return response()->json($config);
    }

    public function destroy(ExternalApiConfig $externalApiConfig): JsonResponse
    {
        $externalApiConfig->delete();

        return response()->json(['message' => 'External API config deleted']);
    }
}


