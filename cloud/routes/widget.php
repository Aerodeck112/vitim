<?php

use App\Http\Controllers\Widget\WidgetController;
use Illuminate\Support\Facades\Route;

// /widget/v1/* — widgetul de chat de pe site-urile clienților (cheie publică + Origin permis, fără sesiune)
Route::post('config', [WidgetController::class, 'config']);
Route::post('start', [WidgetController::class, 'start']);
Route::post('message', [WidgetController::class, 'message']);
Route::post('history', [WidgetController::class, 'history']);
Route::post('contact', [WidgetController::class, 'contact']);
Route::post('forms/view', [WidgetController::class, 'formView']);
Route::post('forms/submit', [WidgetController::class, 'formSubmit']);
