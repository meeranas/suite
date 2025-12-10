# Fix: Documents Not Being Found in RAG Search

## Problem
Files are uploaded and show as "Ready", but when asking questions, the agent says "No uploaded documents" were used.

## Root Cause
The `chat_id` column was added to `vector_embeddings` table, but:
1. The migration might not have been run
2. Existing embeddings don't have `chat_id` set
3. Chroma vector DB metadata might not have `chat_id`

## Solution

### Step 1: Run the Migration
```bash
docker-compose exec app php artisan migrate
```

### Step 2: Update Existing Embeddings
Run the command to update existing embeddings with `chat_id` from their files:
```bash
docker-compose exec app php artisan embeddings:update-chat-id
```

### Step 3: Re-process Files (if needed)
If files still don't work, you may need to re-process them. The easiest way is to:
1. Delete the file from the chat
2. Re-upload it

Or use the retry endpoint:
```bash
# Get file ID from database or API
docker-compose exec app php artisan tinker
>>> $file = \App\Models\File::where('original_name', 'cover-letter.pdf')->first();
>>> $file->id; // Note this ID
>>> exit

# Then retry processing
curl -X POST http://localhost/api/files/{file_id}/retry \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Step 4: Check Logs
Check Laravel logs to see what's happening:
```bash
docker-compose exec app tail -f storage/logs/laravel.log
```

Look for:
- "RAG searchContext called" - should show `chat_id`
- "Fallback search found embeddings" - should show count > 0
- Any errors about Chroma queries

## Verification

After running the fixes, test by:
1. Upload a new file to a chat
2. Wait for it to show "Ready"
3. Ask a question about the document
4. Check the logs to see if embeddings are found

## Notes

- The fallback database search should work even if Chroma fails
- New files should automatically get `chat_id` when processed
- Old files need to be updated using the command above

