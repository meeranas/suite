# Data Source Priority Flow

When a user asks a question, the system retrieves answers from multiple sources in a **strict priority order**.

## 🔄 Execution Flow

```
User Question
    ↓
┌─────────────────────────────────────────┐
│  AgentExecutionService.execute()        │
└─────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────┐
│  STEP 1: Documents (RAG) - HIGHEST      │
│  Priority                                │
│  • Searches uploaded documents           │
│  • Admin-uploaded files (agent_id)      │
│  • User-uploaded files (chat_id)        │
│  • Returns top 5 relevant chunks         │
└─────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────┐
│  STEP 2: External APIs - SECOND         │
│  Priority                                │
│  • USPTO, FDA, Crunchbase, etc.          │
│  • Structured data sources               │
│  • Can use Tools (AI calls on-demand)   │
│  • OR fetch upfront if tools disabled    │
└─────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────┐
│  STEP 3: Web Search - THIRD Priority    │
│  • Serper, Bing, Brave APIs              │
│  • Only if tools are NOT being used      │
│  • Last fallback for general info        │
└─────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────┐
│  STEP 4: LLM (GPT/Gemini)               │
│  • Synthesizes answer from context      │
│  • NEVER used as a data source          │
│  • Only reasons over provided context    │
└─────────────────────────────────────────┘
    ↓
Final Answer (with citations)
```

## 📊 Priority Rules

### When Data Sources Conflict:
1. **DOC (Documents)** → Highest priority
   - User/admin uploaded documents
   - Most trusted source
   
2. **API (External APIs)** → Second priority
   - Structured sources (FDA, USPTO, Crunchbase)
   - Verified data from official APIs
   
3. **WEB (Web Search)** → Lowest priority
   - General web search results
   - Used as last fallback

### Example Conflict Resolution:
```
DOC says: "Device is Class II"
API says: "Device is Class III"
WEB says: "Device is Class I"

→ System will use DOC (Class II) and highlight the contradiction
```

## 🔍 How Each Source Works

### 1. Documents (RAG)
**Location:** `app/Services/RAG/RagService.php`

```php
// Searches vector embeddings in ChromaDB
$ragContext = $this->ragService->searchContext(
    $userMessage,        // User's question
    $chat->user,         // User object
    $chat->id,           // User-uploaded files
    $agent->id,          // Admin-uploaded files
    5                    // Top 5 chunks
);
```

**What it searches:**
- Admin-uploaded files (stored with `agent_id`)
- User-uploaded files (stored with `chat_id`)
- Only active (non-deleted) files
- Uses semantic similarity search

### 2. External APIs
**Location:** `app/Services/ExternalApi/ExternalApiService.php`

**Two modes:**

**A. Tool-based (when tools enabled):**
- AI decides which API to call
- Calls APIs on-demand based on question
- More efficient, only calls what's needed

**B. Fetch upfront (when tools disabled):**
- Fetches from all configured APIs
- Passes all data to AI at once
- Less efficient but simpler

**Supported APIs:**
- FDA (drug/device data)
- USPTO (patents)
- Crunchbase (company data)
- ClinicalTrials.gov
- Custom REST APIs

### 3. Web Search
**Location:** `app/Services/Search/SearchService.php`

```php
// Only runs if:
// 1. enable_web_search = true
// 2. Tools are NOT being used
if ($agent->enable_web_search && !$useTools) {
    $webSearchResults = $this->searchService->search($userMessage);
}
```

**Providers:**
- Serper (default)
- Bing
- Brave

**Note:** Web search is skipped if External APIs are using tools (to avoid duplicate searches)

### 4. LLM Synthesis
**Location:** `app/Services/AI/AiModelService.php`

The LLM (GPT/Gemini) is **NEVER** a data source. It only:
- Synthesizes answers from provided context
- Reasons over DOC/WEB/API data
- Formats the response
- Adds citations

**Strict rule:** If no context is found → "INSUFFICIENT VERIFIED DATA"

## 📝 Answer Format

Every answer includes:

1. **Executive Summary**
   - Summary of findings
   - Data sources used
   - Missing data

2. **Data Sources Used**
   - List of documents (filenames)
   - Web search results (URLs)
   - External APIs used

3. **Verified Insights**
   - Each claim tagged: [DOC], [WEB], [API]
   - No uncited claims allowed

4. **Citations & References**
   - Full URLs
   - Document snippets
   - API response snippets

5. **Insufficient Data**
   - What couldn't be verified
   - Why
   - What's needed

6. **Final Conclusion**
   - Evidence-backed only
   - No speculation
   - Highlights contradictions

## 🚫 What the LLM Cannot Do

- ❌ Use "general knowledge" as a source
- ❌ Make up facts, numbers, or statistics
- ❌ Guess when data is missing
- ❌ Create fake companies/entities
- ✅ Only reason over provided context

## 💡 Example Flow

**User asks:** "What is the FDA classification of this device?"

1. **RAG Search** → Finds relevant chunks from uploaded FDA guidance documents
2. **External API** → Calls FDA API to get device classification
3. **Web Search** → Skipped (not needed if API has data)
4. **LLM** → Synthesizes answer:
   - "Based on uploaded documents [DOC] and FDA API [API], the device is Class II..."
   - Cites both sources
   - If conflict: prioritizes DOC, highlights contradiction

## 🔧 Configuration

Each agent can enable/disable sources:

```php
$agent->enable_rag = true;              // Documents
$agent->enable_external_apis = true;     // External APIs
$agent->enable_web_search = true;       // Web Search
$agent->external_api_configs = [1, 2];  // Which APIs to use
```

**Priority is always:** DOC > API > WEB > LLM (synthesis only)

