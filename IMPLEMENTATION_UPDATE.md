# AI Hub Implementation Update

## Summary
This document outlines the additional features implemented to fully align with the specification requirements.

## ✅ Completed Features

### 1. Single-Agent Chat Support
- **Migration**: Added `agent_id` column to `chats` table
- **Model Update**: `Chat` model now supports `agent_id` relationship
- **Service**: Created `AgentExecutionService` for single-agent execution
- **Controller**: Updated `ChatController` to handle both workflow and single-agent chats
- **API**: Chat creation now accepts either `workflow_id` OR `agent_id` (mutually exclusive)

### 2. External API Integration
- **Service**: Created `ExternalApiService` with support for:
  - **Crunchbase API**: Company data, funding, valuations, investors
  - **Google Patents API**: Patent search and analysis
  - **FDA OpenFDA API**: Medical device classifications and regulatory data
  - **News API**: Recent news articles and press releases
- **Error Handling**: APIs return normalized structure with `status` field (`SUCCESS` or `FAILED_OR_EMPTY`)
- **Integration**: External API data is automatically included in agent context when configured

### 3. Chat History Context Management
- **Feature**: Chat history (last 3 turns) is now included in AI context for continuity
- **Implementation**: 
  - `AgentExecutionService` includes chat history in context
  - `WorkflowOrchestrator` includes chat history for workflow agents
  - `PromptBuilder` formats chat history in user prompt
- **Benefit**: Agents maintain conversation context across multiple turns

### 4. File Retention & Cleanup
- **Command**: Created `files:cleanup` artisan command
- **Features**:
  - Deletes files older than 30 days (configurable)
  - Removes associated vector embeddings
  - Cleans up old chat sessions
- **Scheduling**: Added daily scheduled task in `routes/console.php`
- **Usage**: Run manually with `php artisan files:cleanup --days=30`

### 5. Admin Helper Endpoints
- **Controller**: Created `AdminController` with helper endpoints:
  - `GET /api/admin/providers` - List all AI providers with their models
  - `GET /api/admin/providers/{provider}/models` - Get models for specific provider
  - `GET /api/admin/external-apis` - List available external API providers
  - `GET /api/admin/subscription-tiers` - List subscription tiers
- **Purpose**: Supports dynamic dropdowns in admin UI

### 6. Enhanced Workflow Orchestrator
- **Update**: Integrated `ExternalApiService` into workflow execution
- **Update**: Added chat history context to workflow agents
- **Error Handling**: Improved error handling for external API failures

## Database Changes

### Migration: `2024_01_15_000001_add_agent_id_to_chats_table.php`
- Adds `agent_id` foreign key to `chats` table
- Supports single-agent chat sessions

## New Files Created

1. `app/Services/Agent/AgentExecutionService.php` - Single-agent execution service
2. `app/Services/ExternalApi/ExternalApiService.php` - External API integration service
3. `app/Http/Controllers/Api/AdminController.php` - Admin helper endpoints
4. `app/Console/Commands/CleanupOldFiles.php` - File retention cleanup command
5. `database/migrations/2024_01_15_000001_add_agent_id_to_chats_table.php` - Chat model update

## Updated Files

1. `app/Models/Chat.php` - Added `agent_id` field and relationship
2. `app/Http/Controllers/Api/ChatController.php` - Single-agent chat support
3. `app/Services/Workflow/WorkflowOrchestrator.php` - External API integration and chat history
4. `app/Services/AI/PromptBuilder.php` - Chat history formatting
5. `app/Services/AI/AiModelService.php` - Chat history context passing
6. `routes/api.php` - Admin helper routes
7. `routes/console.php` - Scheduled cleanup task

## API Endpoints

### New Endpoints
- `POST /api/chats` - Now accepts `agent_id` (alternative to `workflow_id`)
- `GET /api/admin/providers` - Get AI providers and models
- `GET /api/admin/providers/{provider}/models` - Get models for provider
- `GET /api/admin/external-apis` - Get external API list
- `GET /api/admin/subscription-tiers` - Get subscription tiers

### Updated Endpoints
- `POST /api/chats/{chat}/messages` - Now supports both workflow and single-agent execution

## Configuration

### External API Configuration
External APIs are configured via `ExternalApiConfig` model:
- Provider name (crunchbase, patents, fda, news)
- Encrypted API keys
- Active/inactive status
- Provider-specific configuration

### Agent Configuration
Agents can now specify:
- `external_api_configs`: Array of API provider IDs to use
- Example: `['crunchbase', 'patents']` enables both APIs for that agent

## Usage Examples

### Single-Agent Chat
```php
// Create chat with agent
POST /api/chats
{
    "suite_id": 1,
    "agent_id": 5,
    "title": "Market Analysis"
}

// Send message
POST /api/chats/{chat_id}/messages
{
    "message": "Analyze the market for CRISPR diagnostics"
}
```

### Workflow Chat (existing)
```php
// Create chat with workflow
POST /api/chats
{
    "suite_id": 1,
    "workflow_id": 2,
    "title": "Full Analysis"
}
```

### Admin: Get Models for Provider
```php
GET /api/admin/providers/openai/models
// Returns: ["gpt-4o", "gpt-4-turbo", "gpt-3.5-turbo", ...]
```

## Testing Checklist

- [ ] Single-agent chat creation and messaging
- [ ] Workflow chat execution (existing functionality)
- [ ] External API data fetching (Crunchbase, Patents, FDA, News)
- [ ] Chat history context in responses
- [ ] File cleanup command execution
- [ ] Admin helper endpoints
- [ ] Agent with external APIs enabled
- [ ] Agent with RAG + Web Search + External APIs combined

## Next Steps

1. Run migration: `php artisan migrate`
2. Configure external API keys in `external_api_configs` table
3. Test single-agent chats
4. Test external API integrations
5. Set up scheduled task for file cleanup (if not using Laravel scheduler)

## Notes

- External API keys must be encrypted using `EncryptionService`
- File cleanup runs daily via Laravel scheduler
- Chat history includes last 3 turns by default (configurable in services)
- All external API responses are normalized to consistent structure
- API failures are handled gracefully with `FAILED_OR_EMPTY` status





