# Fix: Chroma Vector Database Connection Issues

## Problem
The logs show "Chroma API error: " with empty error messages, indicating Chroma (vector database) is not accessible. This causes RAG to fall back to database search, which may not work as well.

## Solution

### Option 1: Start Chroma Container (Recommended)

Chroma is defined in `docker-compose.yml` but might not be running. Start it:

```bash
# Start Chroma container
docker-compose up -d chroma

# Verify it's running
docker-compose ps chroma

# Check Chroma logs
docker-compose logs chroma
```

### Option 2: Verify Chroma Configuration

Check if Chroma URL is correct:

```bash
# Inside the app container
docker-compose exec app php artisan tinker
>>> config('services.chroma.url')
# Should show: "http://chroma:8000"
>>> exit
```

### Option 3: Test Chroma Connection

Test if Chroma is accessible:

```bash
# From app container
docker-compose exec app curl http://chroma:8000/api/v1/heartbeat

# Should return: {"nanosecond heartbeat": ...}
```

### Option 4: Use Database-Only Mode (Temporary)

If Chroma continues to fail, the system will automatically use database fallback. However, this is less efficient for similarity search. The database fallback will:

1. Store embeddings in the `vector_embeddings` table ✅
2. Use simple text-based search (not semantic similarity) ⚠️
3. Still filter by `chat_id` to prevent cross-chat contamination ✅

## What Was Fixed

1. **Better error logging**: Now shows actual connection errors instead of empty messages
2. **Connection timeout**: Added 5-second timeout to prevent hanging
3. **Proper fallback**: Database fallback now works correctly for searches
4. **Collection check**: Improved collection existence check

## Verify It's Working

After starting Chroma, check the logs:

```bash
docker-compose exec app tail -f storage/logs/laravel.log | grep -i chroma
```

You should see:
- ✅ No more "Chroma storage failed" warnings
- ✅ Successful storage messages (or at least connection attempts with proper errors)

## Note

The database fallback is working, so your RAG should still function, but with reduced similarity search quality. Starting Chroma will improve search accuracy.

