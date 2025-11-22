<?php

use LBHurtado\HyperVerge\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/hyperverge/webhook', WebhookController::class)
    ->name('hyperverge.webhook');
