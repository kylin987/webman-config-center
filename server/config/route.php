<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

Route::get('/health', function () {
    return json(['status' => 'ok']);
});

Route::get('/', function () {
    return response((string) file_get_contents(public_path('index.html')))->withHeader('Content-Type', 'text/html; charset=utf-8');
});

foreach (glob(base_path() . '/app/api/route/*.php') as $filename) {
    include_once $filename;
}

foreach (glob(base_path() . '/app/admin/route/*.php') as $filename) {
    include_once $filename;
}

Route::disableDefaultRoute();




