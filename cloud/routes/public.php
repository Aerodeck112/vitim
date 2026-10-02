<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\SubscribeConfirmController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// pagini publice fără CSRF: dezabonarea dintr-un click (cerută de Gmail / Outlook) și webhook-ul Meta
Route::get('/d/{code}', [UnsubscribeController::class, 'show'])->where('code', '[A-Za-z0-9]{4,16}')->name('unsubscribe');
Route::post('/d/{code}', [UnsubscribeController::class, 'confirm'])->where('code', '[A-Za-z0-9]{4,16}')->middleware('throttle:30,1')->name('unsubscribe.confirm');
Route::get('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:60,1')->name('webhooks.whatsapp');
Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1');
Route::get('/t/o/{code}.gif', [TrackingController::class, 'open'])->middleware('throttle:300,1')->name('track.open');
Route::get('/t/c/{code}', [TrackingController::class, 'click'])->middleware('throttle:300,1')->name('track.click');
Route::get('/m/{organization}/{file}', [MediaController::class, 'show'])->where(['organization' => '[0-9]+', 'file' => '[A-Za-z0-9]{32}\.(jpg|png|gif|webp)'])->name('media');
Route::get('/confirmare/{code}', [SubscribeConfirmController::class, 'show'])->where('code', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('forms.confirm');
Route::post('/confirmare/{code}', [SubscribeConfirmController::class, 'store'])->where('code', '[A-Za-z0-9]{40}')->middleware('throttle:30,1')->name('forms.confirm.store');
