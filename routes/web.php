<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\BulkLeadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadExportController;
use App\Http\Controllers\LeadImportController;
use App\Http\Controllers\MergeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::inertia('help', 'help/index')->name('help');

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::post('leads/bulk', [BulkLeadController::class, 'update'])->name('leads.bulk');
    Route::get('leads/export', [LeadExportController::class, 'index'])->middleware('can:export')->name('leads.export');

    Route::post('saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
    Route::delete('saved-views/{savedView}', [SavedViewController::class, 'destroy'])->name('saved-views.destroy');
    Route::get('leads/{lead}/detail', [LeadController::class, 'detail'])->whereNumber('lead')->name('leads.detail');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('leads.show');
    Route::patch('leads/{lead}', [LeadController::class, 'update'])->whereNumber('lead')->name('leads.update');
    Route::post('leads/{lead}/claim', [LeadController::class, 'claim'])->whereNumber('lead')->name('leads.claim');
    Route::post('leads/{lead}/reassign', [LeadController::class, 'reassign'])->whereNumber('lead')->name('leads.reassign');
    Route::post('leads/{lead}/tags', [LeadController::class, 'syncTags'])->whereNumber('lead')->name('leads.tags');

    Route::post('leads/{lead}/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::patch('activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::post('activities/{activity}/pin', [ActivityController::class, 'pin'])->name('activities.pin');
    Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::post('leads/{lead}/attachments', [AttachmentController::class, 'store'])->whereNumber('lead')->name('attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('leads/{lead}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('duplicates', [DuplicateController::class, 'index'])->name('duplicates.index');
    Route::post('duplicates/leads', [MergeController::class, 'mergeLeads'])->name('duplicates.leads');
    Route::post('duplicates/companies', [MergeController::class, 'mergeCompanies'])->name('duplicates.companies');
    Route::post('duplicates/leads/unmerge', [MergeController::class, 'unmergeLeads'])->name('duplicates.leads.unmerge');
    Route::post('duplicates/companies/unmerge', [MergeController::class, 'unmergeCompanies'])->name('duplicates.companies.unmerge');
});

// Admin-only user management, invitations, and the launch CSV import.
Route::middleware(['auth', 'verified', 'can:manage_users'])->group(function () {
    Route::get('leads/import', [LeadImportController::class, 'index'])->name('leads.import');
    Route::post('leads/import/preview', [LeadImportController::class, 'preview'])->name('leads.import.preview');
    Route::post('leads/import', [LeadImportController::class, 'store'])->name('leads.import.store');

    Route::get('automations', [AutomationRuleController::class, 'index'])->name('automations.index');
    Route::post('automations', [AutomationRuleController::class, 'store'])->name('automations.store');
    Route::patch('automations/{automationRule}', [AutomationRuleController::class, 'update'])->name('automations.update');
    Route::delete('automations/{automationRule}', [AutomationRuleController::class, 'destroy'])->name('automations.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/resend', [UserController::class, 'resend'])->name('users.resend');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

// Admin-only audit log of model changes captured by the activity log.
Route::middleware(['auth', 'verified', 'can:view_audit_log'])->group(function () {
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
});

// Public set-password page reached from the emailed invitation link.
Route::get('invitation/setup', [AcceptInvitationController::class, 'create'])->name('invitation.setup');
Route::post('invitation/setup', [AcceptInvitationController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('invitation.store');

require __DIR__.'/settings.php';
