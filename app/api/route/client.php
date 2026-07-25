<?php

use app\api\controller\ClientConfigController;
use Webman\Route;

Route::get('/api/client/v1/config', [ClientConfigController::class, 'show']);
Route::post('/api/client/v1/config/publish', [ClientConfigController::class, 'publish']);
