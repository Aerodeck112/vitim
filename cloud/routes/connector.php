<?php

use App\Http\Controllers\Connector\ConnectorController;
use Illuminate\Support\Facades\Route;

// /connector/v1/* — pluginul WordPress și conectorul PHP (semnătură HMAC, fără sesiune)
Route::prefix('v1')->middleware('throttle:connector')->group(function () {
    Route::post('heartbeat', [ConnectorController::class, 'heartbeat']);
    Route::post('worklog', [ConnectorController::class, 'worklog']);
    Route::post('scan', [ConnectorController::class, 'scan']);
    Route::get('plugin', [ConnectorController::class, 'plugin']);
});
