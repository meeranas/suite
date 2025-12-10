# AI Control Hub - Complete Architecture Documentation

## 🏗️ Architecture Overview

This is a **modular, scalable AI Control Hub** built with Laravel 11 (API-first) and React, designed for managing AI Suites and Agents with advanced capabilities including RAG, web search, multi-model AI, and workflow chaining.

### Technology Stack

- **Backend**: Laravel 11 (API-first architecture)
- **Database**: PostgreSQL 15
- **Vector DB**: Chroma (for embeddings)
- **Frontend**: React with CoreUI admin template
- **Authentication**: JWT (from main platform) + Spatie Permissions
- **Containerization**: Docker + NGINX

---

## 📁 Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/          # API controllers
│   ├── Middleware/
│   │   └── JwtAuth.php   # JWT authentication middleware
│   └── Requests/         # Form requests
├── Models/               # Eloquent models
├── Policies/             # Authorization policies
├── Services/
│   ├── AI/               # AI model services (OpenAI, Gemini, Mistral, Claude)
│   ├── Auth/              # JWT authentication service
│   ├── Encryption/        # AES-256 encryption for secrets
│   ├── RAG/               # RAG service for file processing
│   ├── Report/            # PDF/DOCX report generation
│   ├── Search/            # Web search (Serper, Bing, Brave)
│   ├── VectorDB/          # Chroma vector DB integration
│   └── Workflow/          # Agent workflow orchestrator
└── Providers/

database/
├── migrations/            # All database migrations
└── seeders/              # Database seeders

resources/
└── react/                # React frontend application
    ├── src/
    │   ├── components/
    │   │   ├── admin/    # Admin components
    │   │   ├── chat/      # Chat UI components
    │   │   └── common/   # Shared components
    │   ├── pages/
    │   │   ├── admin/    # Admin pages
    │   │   └── user/     # User pages
    │   ├── services/     # API services
    │   └── utils/         # Utilities
    └── public/

routes/
└── api.php               # API routes
```

---

## 🗄️ Database Schema

### Core Tables

1. **suites** - AI Suite containers
   - `name`, `slug`, `status` (active/hidden/archived)
   - `subscription_tiers` (JSON array)
   - `created_by` (user_id)

2. **agents** - Individual AI agents within suites
   - `suite_id`, `name`, `slug`
   - `model_provider` (openai/gemini/mistral/claude)
   - `model_name`, `system_prompt`
   - `enable_rag`, `enable_web_search`
   - `external_api_configs` (JSON)
   - `order` (for workflow sequencing)

3. **agent_workflows** - Agent chaining configurations
   - `suite_id`, `name`
   - `agent_sequence` (JSON array of agent IDs)
   - `workflow_config` (JSON)

4. **chats** - User chat sessions
   - `user_id`, `suite_id`, `workflow_id`
   - `title`, `status`, `context` (JSON)

5. **messages** - Chat messages
   - `chat_id`, `agent_id`, `role` (user/assistant/system)
   - `content`, `metadata`, `rag_context`, `external_data`

6. **files** - Uploaded files for RAG
   - `user_id`, `chat_id`
   - `original_name`, `stored_name`, `path`
   - `is_processed`, `is_embedded`

7. **vector_embeddings** - Vector embeddings for RAG
   - `file_id`, `user_id`, `content`
   - `embedding` (JSON), `vector_id` (Chroma ID)

8. **external_api_configs** - External API credentials
   - `provider` (serper/bing/brave/crunchbase/patents)
   - `encrypted_api_key`, `encrypted_api_secret`

9. **usage_logs** - Token usage and cost tracking
   - `user_id`, `suite_id`, `agent_id`, `chat_id`
   - `action`, `model_provider`, `model_name`
   - `input_tokens`, `output_tokens`, `cost_usd`

---

## 🔐 Authentication & Authorization

### JWT Authentication

- Accepts JWT tokens from main platform via `Authorization: Bearer <token>` header
- Verifies token signature using public key
- Auto-creates users from JWT payload if not exists
- Updates `last_jwt_verified_at` timestamp

**Configuration**:
```env
JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n..."
```

### Spatie Permissions

- Roles: `admin`, `user`
- Permissions: Suite/Agent management (admin only)
- Row-level security: Users can only access their own chats/files

---

## 🤖 AI Model Integration

### Supported Providers

1. **OpenAI** (`openai`)
   - Models: `gpt-4`, `gpt-4-turbo`, `gpt-3.5-turbo`
   - API: `https://api.openai.com/v1/chat/completions`

2. **Google Gemini** (`gemini`)
   - Models: `gemini-pro`
   - API: `https://generativelanguage.googleapis.com/v1beta`

3. **Mistral AI** (`mistral`)
   - Models: `mistral-large`
   - API: `https://api.mistral.ai/v1/chat/completions`

4. **Anthropic Claude** (`claude`)
   - Models: `claude-3-opus`
   - API: `https://api.anthropic.com/v1/messages`

### Configuration

```env
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=...
MISTRAL_API_KEY=...
CLAUDE_API_KEY=...
```

---

## 🔍 RAG (Retrieval-Augmented Generation)

### Process Flow

1. **File Upload**: User uploads PDF/DOCX/XLSX/TXT
2. **Text Extraction**: Extract text using libraries (PhpOffice/PhpWord, smalot/pdfparser)
3. **Chunking**: Split into 1000-character chunks with 200-char overlap
4. **Embedding**: Generate embeddings using OpenAI `text-embedding-3-small`
5. **Storage**: Store in Chroma vector DB + PostgreSQL metadata
6. **Retrieval**: Similarity search on user query

### Vector DB (Chroma)

- **URL**: `http://chroma:8000` (Docker service)
- **Collection**: `ai_suite_embeddings`
- **Row-level security**: Filter by `user_id` in metadata

---

## 🌐 Web Search Integration

### Supported Providers

1. **Serper** (`serper`)
   - API: `https://google.serper.dev/search`
   - Fast Google search results

2. **Bing Search** (`bing`)
   - API: `https://api.bing.microsoft.com/v7.0/search`
   - Microsoft Bing results

3. **Brave Search** (`brave`)
   - API: `https://api.search.brave.com/res/v1/web/search`
   - Privacy-focused search

### Configuration

Store API keys encrypted in `external_api_configs` table.

---

## 🔗 Workflow Orchestration

### Agent Chaining

1. Admin creates workflow with agent sequence: `[agent_1_id, agent_2_id, agent_3_id]`
2. User sends message to chat with workflow
3. Backend executes agents sequentially:
   - Agent 1 processes message → output
   - Agent 2 processes Agent 1 output → output
   - Agent 3 processes Agent 2 output → final response
4. Each agent can use RAG, web search, external APIs independently

### Workflow Config

```json
{
  "stop_on_error": false,
  "max_iterations": 10,
  "conditions": {}
}
```

---

## 📊 Usage Tracking & Cost Calculation

### Token Pricing (per 1M tokens)

- **GPT-4**: Input $30, Output $60
- **GPT-4 Turbo**: Input $10, Output $30
- **GPT-3.5 Turbo**: Input $0.5, Output $1.5
- **Gemini Pro**: Input $0.5, Output $1.5
- **Mistral Large**: Input $8, Output $24
- **Claude 3 Opus**: Input $15, Output $75

All usage logged in `usage_logs` table with cost calculation.

---

## 📄 Report Generation

### PDF Reports

- Uses `barryvdh/laravel-dompdf`
- Template: `resources/views/reports/chat-pdf.blade.php`
- Includes chat title, messages, timestamps

### DOCX Reports

- Uses `PhpOffice/PhpWord`
- Structured document with formatting
- Downloadable via signed URLs (60-minute expiry)

---

## 🐳 Docker Setup

### Services

1. **app** - Laravel application (PHP 8.2 + NGINX)
2. **db** - PostgreSQL 15
3. **redis** - Redis 7 (caching/queues)
4. **chroma** - Chroma vector DB
5. **pgadmin** - PostgreSQL admin UI (port 8080)

### Ports

- `8000` - Laravel API
- `3000` - React dev server
- `5432` - PostgreSQL
- `6379` - Redis
- `8001` - Chroma API
- `8080` - pgAdmin

---

## 🚀 Installation Guide

### Prerequisites

- Docker & Docker Compose
- Node.js 18+ (for React)
- Composer (for PHP dependencies)

### Step 1: Clone & Setup

```bash
cd "/Users/meer/Documents/Projects/AI Suite"
cp .env.example .env
php artisan key:generate
```

### Step 2: Install Dependencies

```bash
# PHP dependencies
composer install

# React dependencies
cd resources/react
npm install
```

### Step 3: Configure Environment

Edit `.env`:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel

# JWT
JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n..."

# AI Providers
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=...
MISTRAL_API_KEY=...
CLAUDE_API_KEY=...

# Vector DB
CHROMA_URL=http://chroma:8000

# Search APIs
SERPER_API_KEY=...
BING_API_KEY=...
BRAVE_API_KEY=...
```

### Step 4: Start Docker Services

```bash
docker-compose up -d
```

### Step 5: Run Migrations

```bash
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

### Step 6: Setup Spatie Permissions

```bash
docker-compose exec app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
docker-compose exec app php artisan migrate
```

### Step 7: Create Roles

```bash
docker-compose exec app php artisan tinker
```

```php
use Spatie\Permission\Models\Role;
Role::create(['name' => 'admin']);
Role::create(['name' => 'user']);
```

### Step 8: Start React Dev Server

```bash
cd resources/react
npm run dev
```

---

## 📡 API Endpoints

### Authentication

All API endpoints require JWT token in `Authorization: Bearer <token>` header.

### Suites

- `GET /api/suites` - List suites (filtered by subscription tier)
- `POST /api/suites` - Create suite (admin only)
- `GET /api/suites/{id}` - Get suite details
- `PUT /api/suites/{id}` - Update suite
- `DELETE /api/suites/{id}` - Delete suite

### Agents

- `GET /api/suites/{suite}/agents` - List agents in suite
- `POST /api/suites/{suite}/agents` - Create agent
- `GET /api/agents/{id}` - Get agent details
- `PUT /api/agents/{id}` - Update agent
- `DELETE /api/agents/{id}` - Delete agent

### Workflows

- `GET /api/suites/{suite}/workflows` - List workflows
- `POST /api/suites/{suite}/workflows` - Create workflow
- `PUT /api/workflows/{id}` - Update workflow
- `DELETE /api/workflows/{id}` - Delete workflow

### Chats

- `GET /api/chats` - List user's chats
- `POST /api/chats` - Create new chat
- `GET /api/chats/{id}` - Get chat with messages
- `POST /api/chats/{id}/messages` - Send message (triggers workflow)
- `DELETE /api/chats/{id}` - Delete chat

### Files

- `GET /api/files` - List user's files
- `POST /api/files` - Upload file (triggers RAG processing)
- `GET /api/files/{id}` - Get file details
- `DELETE /api/files/{id}` - Delete file

### Reports

- `POST /api/chats/{id}/reports/pdf` - Generate PDF report
- `POST /api/chats/{id}/reports/docx` - Generate DOCX report
- `GET /api/reports/download/{filename}` - Download report (signed URL)

---

## 🎨 Frontend Structure

### React Application

- **Framework**: React 18 + React Router 6
- **UI Library**: CoreUI React
- **State Management**: React Context API
- **HTTP Client**: Axios
- **Charts**: Recharts

### Key Components

1. **AdminLayout** - Admin dashboard with sidebar
2. **UserLayout** - User chat interface
3. **SuiteManager** - Create/edit suites
4. **AgentManager** - Create/edit agents
5. **WorkflowBuilder** - Drag-and-drop workflow builder
6. **ChatInterface** - Chat UI with message history
7. **FileUpload** - Drag-and-drop file upload
8. **UsageDashboard** - Token usage charts (ApexCharts/Chart.js)

---

## 🔒 Security Features

1. **AES-256 Encryption**: All API keys encrypted at rest
2. **Signed URLs**: File downloads with time-limited tokens
3. **Row-level Security**: Users can only access their own data
4. **JWT Verification**: Token signature verification
5. **Rate Limiting**: 60 requests/minute per user/IP

---

## 📦 Required Composer Packages

Add to `composer.json`:

```json
{
  "require": {
    "spatie/laravel-permission": "^6.0",
    "firebase/php-jwt": "^6.9",
    "barryvdh/laravel-dompdf": "^2.0",
    "phpoffice/phpword": "^1.2",
    "smalot/pdfparser": "^2.0",
    "guzzlehttp/guzzle": "^7.2"
  }
}
```

---

## 🧪 Testing

```bash
# Run tests
docker-compose exec app php artisan test

# Run specific test
docker-compose exec app php artisan test --filter ChatTest
```

---

## 📝 Next Steps

1. **Install Composer Packages**: Run `composer require` for all dependencies
2. **Setup React Frontend**: Complete React component implementation
3. **Implement PDF/DOCX Extraction**: Add PhpOffice and PDF parser libraries
4. **Configure JWT Public Key**: Add your main platform's JWT public key
5. **Add External API Integrations**: Implement Crunchbase, Patents APIs
6. **Create Seeders**: Add sample suites, agents, workflows
7. **Setup Queue Workers**: For background RAG processing
8. **Add Monitoring**: Integrate logging/monitoring tools

---

## 🐛 Troubleshooting

### Chroma Connection Issues

```bash
# Check Chroma service
docker-compose logs chroma

# Restart Chroma
docker-compose restart chroma
```

### JWT Verification Fails

- Verify `JWT_PUBLIC_KEY` in `.env` is correct
- Ensure public key format includes `\n` for newlines
- Check token issuer matches expected issuer

### RAG Processing Fails

- Ensure OpenAI API key is set
- Check file storage permissions
- Verify Chroma is running and accessible

---

## 📚 Additional Resources

- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Chroma Vector DB](https://docs.trychroma.com/)
- [Spatie Permissions](https://spatie.be/docs/laravel-permission)
- [CoreUI React](https://coreui.io/react/)

---

**Built with ❤️ for scalable AI applications**

