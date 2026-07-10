<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Lead;
use App\Models\Task;
use App\Support\TaskPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * The task list. Defaults to the current user's tasks; managers/admins can
     * switch to the whole team's via `?scope=team`.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewTeam = $user->hasCapability('team_reporting');
        $scope = ($request->query('scope') === 'team' && $canViewTeam) ? 'team' : 'mine';

        $scopeOwner = fn (Builder $query) => $query
            ->when($scope === 'mine', fn (Builder $q) => $q->where('owner_id', $user->id));

        // Open tasks, soonest due first (no-due-date last). Completed tasks are
        // capped to the most recent so the list stays focused on what's actionable.
        $open = Task::query()
            ->with(['lead:id,name', 'owner:id,name'])
            ->open()
            ->tap($scopeOwner)
            ->orderByRaw('due_at is null, due_at asc')
            ->get();

        $completed = Task::query()
            ->with(['lead:id,name', 'owner:id,name'])
            ->whereNotNull('completed_at')
            ->tap($scopeOwner)
            ->orderByDesc('completed_at')
            ->limit(12)
            ->get();

        return Inertia::render('tasks/index', [
            'tasks' => $open->concat($completed)
                ->map(fn (Task $task) => TaskPresenter::present($task))
                ->all(),
            'scope' => $scope,
            'canViewTeam' => $canViewTeam,
        ]);
    }

    public function store(StoreTaskRequest $request, Lead $lead): RedirectResponse
    {
        $lead->tasks()->create([
            'owner_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'due_at' => $request->validated('due_at'),
        ]);

        return back()->with('status', 'task-created');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('completed', $data)) {
            $data['completed_at'] = $data['completed'] ? now() : null;
            unset($data['completed']);
        }

        $task->update($data);

        return back()->with('status', 'task-updated');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('status', 'task-deleted');
    }
}
