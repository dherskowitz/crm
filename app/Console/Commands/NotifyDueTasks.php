<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-due-tasks')]
#[Description('Notify task owners of tasks that are due or overdue')]
class NotifyDueTasks extends Command
{
    public function handle(): int
    {
        Task::query()
            ->with('owner')
            ->whereNull('completed_at')
            ->whereNull('due_notified_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->chunkById(200, function ($tasks) {
                foreach ($tasks as $task) {
                    $task->owner?->notify(new TaskDue($task));
                    $task->forceFill(['due_notified_at' => now()])->save();
                }
            });

        return self::SUCCESS;
    }
}
