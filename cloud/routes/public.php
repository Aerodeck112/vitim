<?php

use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// pagini publice fără CSRF: dezabonarea dintr-un click (cerută de Gmail / Outlook) și webhook-ul Meta
Route::get('/d/{code}', [UnsubscribeController::class, 'show'])->where('code', '[A-Za-z0-9]{4,16}')->name('unsubscribe');
Route::post('/d/{code}', [UnsubscribeController::class, 'confirm'])->where('code', '[A-Za-z0-9]{4,16}')->middleware('throttle:30,1')->name('unsubscribe.confirm');
Route::get('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:60,1')->name('webhooks.whatsapp');
Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1');
