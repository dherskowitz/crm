<?php

use App\Http\Controllers\Api\WebhookLeadController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/leads', WebhookLeadController::class)
    ->middleware('verify.webhook')
    ->name('webhooks.leads');
