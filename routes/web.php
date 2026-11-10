<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\RequireAdministrator;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'index'])->name('home');
Route::post('/solicitudes', [PublicController::class, 'submit'])->middleware('throttle:10,1')->name('requests.store');
Route::middleware('guest')->group(function (): void {
    Route::get('/ingresar', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.store');
    Route::get('/registro', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/cuenta', [AuthController::class, 'account'])->name('account');
    Route::post('/cuenta', [AuthController::class, 'update'])->name('account.update');
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');
    Route::middleware(RequireAdministrator::class)->group(function (): void {
        Route::get('/gestion', [ManagementController::class, 'index'])->name('management');
        Route::get('/gestion/exportar/{report}', [ManagementController::class, 'export'])->name('management.export');
        Route::post('/gestion/accion', [ManagementController::class, 'action'])->name('management.action');
        Route::post('/gestion/pulso', [ManagementController::class, 'pulse'])->name('management.pulse');
        Route::get('/gestion/archivo/{client}/{implementation}/{asset}', [ManagementController::class, 'download'])->name('management.asset');
    });
});
