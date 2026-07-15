<?php

use app\admin\controller\ConfigController;
use app\admin\controller\ClientTokenController;
use app\admin\middleware\AdminTokenMiddleware;
use Webman\Route;

Route::group('/api/admin/v1', function () {
    Route::get('/config', [ConfigController::class, 'index']);
    Route::get('/config/history', [ConfigController::class, 'history']);
    Route::post('/config/publish', [ConfigController::class, 'publish']);
    Route::post('/clientToken', [ClientTokenController::class, 'create']);
})->middleware(AdminTokenMiddleware::class);
