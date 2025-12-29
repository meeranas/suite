<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SuiteController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Test authentication endpoints (development only)
Route::prefix('test')->group(function () {
    Route::post('auth/token', [\App\Http\Controllers\Api\TestAuthController::class, 'generateTestToken']);
    Route::post('auth/login', [\App\Http\Controllers\Api\TestAuthController::class, 'login']);
});

// Public download route (uses signed token authentication)
Route::get('reports/download/{filename}', [\App\Http\Controllers\Api\ReportController::class, 'download'])
    ->name('api.reports.download');

Route::middleware(['api', 'jwt.auth'])->group(function () {
    // User
    Route::get('user', [\App\Http\Controllers\Api\UserController::class, 'show']);

    // Suites
    Route::apiResource('suites', SuiteController::class);

    // Agents
    Route::apiResource('suites.agents', AgentController::class)->shallow();
    Route::get('agents', [AgentController::class, 'indexAll']);
    Route::get('agents/{agent}/files', [AgentController::class, 'getFiles']);

    // Workflows
    Route::apiResource('suites.workflows', WorkflowController::class)->shallow();
    Route::get('workflows', [WorkflowController::class, 'indexAll']);

    // Chats
    Route::apiResource('chats', ChatController::class);
    Route::post('chats/{chat}/messages', [ChatController::class, 'sendMessage']);
    Route::get('chats/{chat}/files', [ChatController::class, 'getFiles']);

    // Files
    Route::apiResource('files', FileController::class);
    Route::get('files/{file}/download', [FileController::class, 'download']);
    Route::post('files/{file}/retry', [\App\Http\Controllers\Api\FileRetryController::class, 'retry']);

    // Reports
    Route::post('chats/{chat}/reports/pdf', [ReportController::class, 'generatePdf']);
    Route::post('chats/{chat}/reports/docx', [ReportController::class, 'generateDocx']);

    // Usage
    Route::get('usage', [\App\Http\Controllers\Api\UsageController::class, 'index']);

    // Admin helpers
    Route::prefix('admin')->group(function () {
        Route::get('providers', [\App\Http\Controllers\Api\AdminController::class, 'getProviders']);
        Route::get('providers/{provider}/models', [\App\Http\Controllers\Api\AdminController::class, 'getModels']);
        Route::get('external-apis', [\App\Http\Controllers\Api\AdminController::class, 'getExternalApis']);
        Route::get('subscription-tiers', [\App\Http\Controllers\Api\AdminController::class, 'getSubscriptionTiers']);
    });

    // External API Configs (Admin only)
    Route::apiResource('external-api-configs', \App\Http\Controllers\Api\ExternalApiConfigController::class);
});

