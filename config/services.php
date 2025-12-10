<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
    ],

    'chroma' => [
        'url' => env('CHROMA_URL', 'http://chroma:8000'),
    ],

    'serper' => [
        'api_key' => env('SERPER_API_KEY'),
    ],

    'bing' => [
        'api_key' => env('BING_API_KEY'),
    ],

    'brave' => [
        'api_key' => env('BRAVE_API_KEY'),
    ],

];
