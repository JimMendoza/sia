<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(__DIR__.'/api/auth.php');
Route::prefix('public')->group(__DIR__.'/api/public.php');

Route::middleware('auth:api')->prefix('admin')->group(__DIR__.'/api/admin.php');
