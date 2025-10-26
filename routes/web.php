<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

// Dashboard routes
Route::get('/dashboard/orders-summary', [DashboardController::class, 'getOrdersSummary']);
Route::get('/dashboard/stats', [DashboardController::class, 'getDashboardStatsOnly']);

// Route::get('/dashboard/orders-summary-optimized', [DashboardController::class, 'getOrdersSummaryOptimized']);
