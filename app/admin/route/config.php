<?php

use app\admin\controller\ConfigController;
use app\admin\controller\ClientAccountController;
use app\admin\controller\AuthController;
use app\admin\middleware\AdminAuthMiddleware;
use Webman\Route;

Route::post('/api/admin/v1/auth/login', [AuthController::class, 'login']);
Route::post('/api/admin/v1/auth/mfa/verify', [AuthController::class, 'verifyMfa']);
Route::group('/api/admin/v1', function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword']);
    Route::post('/profile/mfa/setup', [AuthController::class, 'startMfaSetup']);
    Route::post('/profile/mfa/enable', [AuthController::class, 'enableMfa']);
    Route::post('/profile/mfa/disable', [AuthController::class, 'disableMfa']);
    Route::get('/config', [ConfigController::class, 'index']);
    Route::get('/config/detail', [ConfigController::class, 'show']);
    Route::get('/config/history', [ConfigController::class, 'history']);
    Route::post('/config/publish', [ConfigController::class, 'publish']);
    Route::post('/config/rollback', [ConfigController::class, 'rollback']);
    Route::post('/config/delete', [ConfigController::class, 'delete']);
    Route::get('/config/export', [ConfigController::class, 'export']);
    Route::post('/config/import', [ConfigController::class, 'import']);
    Route::get('/clientAccount', [ClientAccountController::class, 'index']);
    Route::post('/clientAccount', [ClientAccountController::class, 'create']);
    Route::post('/clientAccount/update', [ClientAccountController::class, 'update']);
    Route::post('/clientAccount/disable', [ClientAccountController::class, 'disable']);
})->middleware(AdminAuthMiddleware::class);
