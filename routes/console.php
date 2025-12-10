<?php

use Illuminate\Support\Facades\Schedule;

// Reset demo environment daily at midnight (if needed)
// Schedule::command('migrate:fresh --seed --force')->dailyAt('00:00');

// Cleanup old files and chats (30-day retention)
Schedule::command('files:cleanup --days=30')->daily();
