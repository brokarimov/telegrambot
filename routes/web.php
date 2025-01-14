<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [EmployeeController::class, 'index']);
Route::post('/employee', [TelegramBotController::class, 'createOrder']);

Route::get('/food', [FoodController::class, 'index']);
Route::post('/food', [FoodController::class, 'store']);

Route::get('/orders', [OrderController::class, 'index']);
