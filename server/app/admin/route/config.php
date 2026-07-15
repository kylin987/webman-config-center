<?php

use app\admin\controller\ConfigController;
use app\admin\controller\ClientTokenController;
use app\admin\controller\AuthController;
use app\admin\middleware\AdminAuthMiddleware;
use Webman\Route;

Route::post('/api/admin/v1/auth/login', [AuthController::class, 'login']);
Route::group('/api/admin/v1', function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/config', [ConfigController::class, 'index']);
    Route::get('/config/detail', [ConfigController::class, 'show']);
    Route::get('/config/history', [ConfigController::class, 'history']);
    Route::post('/config/publish', [ConfigController::class, 'publish']);
    Route::post('/config/rollback', [ConfigController::class, 'rollback']);
    Route::post('/clientToken', [ClientTokenController::class, 'create']);
})->middleware(AdminAuthMiddleware::class);
