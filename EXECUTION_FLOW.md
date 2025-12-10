# Execution Flow: "Fetch All Drugs" Query

This document explains the complete execution flow when a user asks "fetch all drugs" and how third-party APIs are called.

## Step-by-Step Execution Flow

### 1. User Input → API Request
**Location:** `resources/react/src/pages/user/ChatPage.jsx`

```javascript
// User types: "fetch all drugs" and clicks Send
POST /api/chats/{chatId}/messages
Body: { "message": "fetch all drugs" }
```

---

### 2. ChatController Receives Request
**Location:** `app/Http/Controllers/Api/ChatController.php` → `sendMessage()`

```php
// Line 137-157
1. Validates the request
2. Creates a Message record in database
3. Updates chat's last_message_at timestamp
4. Determines if chat uses workflow or single agent
```

**For single agent (your case):**
```php
// Line 180-186
$result = $this->agentExecutionService->execute(
    $chat->agent,      // The agent configured for this chat
    $chat,             // Chat object
    $request->message   // "fetch all drugs"
);
```

---

### 3. AgentExecutionService Orchestrates Data Collection
**Location:** `app/Services/Agent/AgentExecutionService.php` → `execute()`

#### 3.1 Get Chat History (Line 38-39)
```php
$chatHistory = $this->getChatHistory($chat, 3);
// Returns last 3 conversation turns for context
```

#### 3.2 Check RAG (Document Uploads) - Line 45-53
```php
if ($agent->enable_rag) {
    $ragContext = $this->ragService->searchContext(...);
}
// In your case: enable_rag = false, so this is skipped
```

#### 3.3 Check Web Search - Line 55-66
```php
if ($agent->enable_web_search) {
    $webSearchResults = $this->searchService->search($userMessage);
}
// In your case: enable_web_search = true
// Calls Serper/Bing/Brave API to search the web
```

#### 3.4 **EXTERNAL API CALL** - Line 68-81
```php
if (!empty($agent->external_api_configs)) {
    // agent->external_api_configs = [2] (database ID of FDA config)
    $externalData = $this->externalApiService->fetchData(
        [2],                    // Array of ExternalApiConfig IDs
        "fetch all drugs"       // User query
    );
}
```

---

### 4. ExternalApiService Fetches Data
**Location:** `app/Services/ExternalApi/ExternalApiService.php` → `fetchData()`

#### 4.1 Loop Through API Config IDs (Line 27)
```php
foreach ($apiConfigs as $apiConfigId) {
    // apiConfigId = 2
```

#### 4.2 Lookup API Configuration (Line 30-32)
```php
$config = ExternalApiConfig::where('id', 2)
    ->where('is_active', true)
    ->first();

// Returns:
// {
//   id: 2,
//   name: "OpenFDA",
//   provider: "fda",
//   base_url: "https://api.fda.gov",
//   encrypted_api_key: "...",  // (FDA doesn't require API key, but stored if provided)
//   is_active: true
// }
```

#### 4.3 Determine Provider Type (Line 42-50)
```php
$provider = $config->provider; // "fda"

$data = match ($provider) {
    'fda' => $this->fetchFda($config, $query),
    // ... other providers
};
```

#### 4.4 Call FDA-Specific Fetch Method (Line 191-280)
**Location:** `fetchFda()` method

```php
protected function fetchFda(ExternalApiConfig $config, string $query): ?array
{
    // Step 1: Extract search term
    $searchTerm = $this->extractSearchTerm("fetch all drugs");
    // Result: "drugs" (removes "fetch", "all", etc.)
    
    // Step 2: Try multiple FDA endpoints
    $endpoints = [
        'drug' => 'https://api.fda.gov/drug/label.json',
        'device' => 'https://api.fda.gov/device/510k.json',
        'device_classification' => 'https://api.fda.gov/device/classification.json',
    ];
    
    // Step 3: Make HTTP Request to FDA API
    foreach ($endpoints as $type => $url) {
        if ($type === 'drug') {
            $response = Http::get('https://api.fda.gov/drug/label.json', [
                'search' => 'openfda.brand_name:"drugs" OR openfda.generic_name:"drugs" OR indications_and_usage:"drugs"',
                'limit' => 10,
            ]);
        }
        
        if ($response->successful()) {
            $data = $response->json();
            // FDA returns JSON like:
            // {
            //   "results": [
            //     {
            //       "openfda": {
            //         "brand_name": ["Aspirin"],
            //         "generic_name": ["acetylsalicylic acid"],
            //         ...
            //       },
            //       "indications_and_usage": ["..."],
            //       "description": ["..."],
            //       ...
            //     }
            //   ]
            // }
        }
    }
    
    // Step 4: Normalize Response
    foreach ($data as $result) {
        $normalized[] = [
            'brand_name' => implode(', ', $openfda['brand_name'] ?? []),
            'generic_name' => implode(', ', $openfda['generic_name'] ?? []),
            'indications_and_usage' => $result['indications_and_usage'] ?? [],
            'description' => $result['description'] ?? [],
            // ... more fields
        ];
    }
    
    // Step 5: Return Structured Data
    return [
        'source' => 'fda',
        'status' => 'SUCCESS',
        'data' => $normalized,  // Array of drug information
        'url' => 'https://www.fda.gov',
        'endpoint' => 'drug',
    ];
}
```

#### 4.5 Return Results (Line 72)
```php
return $results; // Array of API responses
// [
//   {
//     'source' => 'fda',
//     'status' => 'SUCCESS',
//     'data' => [...drug data...],
//     'url' => 'https://www.fda.gov'
//   }
// ]
```

---

### 5. Build AI Context
**Location:** `app/Services/Agent/AgentExecutionService.php` → Line 85-90

```php
$aiContext = [
    'rag' => [],                    // Empty (RAG disabled)
    'web_search' => [...],          // Web search results
    'external_data' => [...],       // FDA API results
    'chat_history' => [...],        // Previous conversation
];
```

---

### 6. Generate AI Response
**Location:** `app/Services/AI/AiModelService.php` → `generateResponse()`

#### 6.1 Build System Prompt
**Location:** `app/Services/AI/PromptBuilder.php` → `buildSystemPrompt()`

```php
// Creates system prompt with:
// - Base rules about evidence-based responses
// - Agent-specific instructions
// - Context priority rules (DOC > API > WEB)
```

#### 6.2 Build User Prompt with Context
**Location:** `app/Services/AI/PromptBuilder.php` → `buildUserPrompt()`

```php
// Line 303-314: Adds external API data to prompt
if (!empty($externalApiData)) {
    $prompt .= "EXTERNAL API EVIDENCE [API]:\n";
    foreach ($externalApiData as $index => $data) {
        $prompt .= "[API-" . ($index + 1) . "] Source: " . $data['source'] . "\n";
        $prompt .= "Data: " . json_encode($data['data'], JSON_PRETTY_PRINT) . "\n";
        $prompt .= "URL: " . $data['url'] . "\n\n";
    }
}
```

**Final Prompt Structure:**
```
USER QUESTION: fetch all drugs

UPLOADED DOCUMENT EVIDENCE [DOC]:
No relevant document chunks found for this query.

WEB SEARCH EVIDENCE [WEB]:
[WEB-1] Title: ...
Snippet: ...
URL: ...

EXTERNAL API EVIDENCE [API]:
[API-1] Source: fda
Data: {
  "brand_name": "Aspirin",
  "generic_name": "acetylsalicylic acid",
  "indications_and_usage": [...],
  ...
}
URL: https://www.fda.gov

INSTRUCTIONS:
Answer the user question using ONLY the evidence provided above.
Every claim MUST be tagged with [DOC], [WEB], [API], or [AGENT].
```

#### 6.3 Call OpenAI/Gemini/Claude API
**Location:** `app/Services/AI/AiModelService.php`

```php
// Makes HTTP request to AI provider (e.g., OpenAI)
POST https://api.openai.com/v1/chat/completions
Headers: {
    "Authorization": "Bearer {API_KEY}",
    "Content-Type": "application/json"
}
Body: {
    "model": "gpt-3.5-turbo",
    "messages": [
        {"role": "system", "content": "{system_prompt}"},
        {"role": "user", "content": "{user_prompt_with_context}"}
    ],
    "temperature": 0.7,
    "max_tokens": 2000
}
```

---

### 7. Store Response & Return to User
**Location:** `app/Services/Agent/AgentExecutionService.php` → Line 95-108

```php
// Store assistant message in database
Message::create([
    'chat_id' => $chat->id,
    'agent_id' => $agent->id,
    'role' => 'assistant',
    'content' => $response['content'],  // AI-generated response
    'rag_context' => $ragContext,
    'external_data' => $externalData,   // FDA API results stored
    ...
]);

// Log usage for billing
$this->aiService->logUsage(...);

// Return response
return [
    'content' => $response['content'],
    'external_data' => $externalData,
    ...
];
```

---

### 8. Frontend Displays Response
**Location:** `resources/react/src/pages/user/ChatPage.jsx`

```javascript
// Response received from backend
setMessages([...messages, {
    role: 'assistant',
    content: response.data.response  // AI-generated answer with [API] citations
}]);
```

---

## Key Points

### API Configuration Lookup
- **Agent stores:** `external_api_configs = [2]` (database IDs)
- **Service looks up:** `ExternalApiConfig::where('id', 2)`
- **Gets provider:** `provider = 'fda'`
- **Calls method:** `fetchFda($config, $query)`

### FDA API Call Details
1. **URL:** `https://api.fda.gov/drug/label.json`
2. **Method:** GET
3. **Parameters:**
   - `search`: Query string for drug search
   - `limit`: Number of results (10)
4. **No Authentication Required:** FDA OpenFDA is public
5. **Response Format:** JSON with drug label data

### Error Handling
- If API fails: Logs warning, returns error status
- If no results: Returns `'status' => 'FAILED_OR_EMPTY'`
- AI still receives context but knows data is missing

### Data Flow Summary
```
User Query
  ↓
ChatController
  ↓
AgentExecutionService
  ↓
ExternalApiService.fetchData([2], "fetch all drugs")
  ↓
ExternalApiConfig::find(2) → provider = "fda"
  ↓
fetchFda() → HTTP GET to api.fda.gov
  ↓
Normalize FDA response
  ↓
Return to AgentExecutionService
  ↓
Build AI prompt with [API] data
  ↓
Call OpenAI/Gemini API
  ↓
AI generates response citing [API] sources
  ↓
Store & return to user
```

---

## Testing the Flow

To verify the flow is working:

1. **Check Logs:**
   ```bash
   docker-compose exec app tail -f storage/logs/laravel.log
   ```

2. **Expected Log Entries:**
   - `External API fetch started` (if logging added)
   - `FDA API request successful` (if successful)
   - `External API fetch failed` (if error)

3. **Check Database:**
   ```sql
   SELECT external_data FROM messages WHERE chat_id = ? ORDER BY created_at DESC LIMIT 1;
   ```
   Should contain FDA API response data.

4. **Check AI Response:**
   - Should include `[API]` citations
   - Should reference FDA data
   - Should list drug information from FDA API

