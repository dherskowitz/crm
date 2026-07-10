<?php

use Illuminate\Support\Facades\Schedule;

// Purge expired password reset and invitation tokens from the shared table.
Schedule::command('auth:clear-resets users')->daily();
Schedule::command('auth:clear-resets invitations')->daily();

// Notify owners of due/overdue tasks.
Schedule::command('app:notify-due-tasks')->hourly();

// Apply time-based (lead idle) automation rules.
Schedule::command('app:run-idle-automations')->daily();

// Soft-archive stale leads beyond the data-retention window.
Schedule::command('app:archive-stale-leads')->daily();
