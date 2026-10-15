<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\AuthenticationsController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use Core\Router\Route;

Route::get('/', [HomeController::class, 'index'])->name('root');

// Authentication
Route::get('/login', [AuthenticationsController::class, 'new'])->name('users.login');
Route::post('/login', [AuthenticationsController::class, 'authenticate'])->name('users.authenticate');
Route::get('/logout', [AuthenticationsController::class, 'destroy'])->name('users.logout');

// Área do usuário (somente autenticados)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Área administrativa (somente autenticados com perfil admin).
    // O grupo interno registra seus middlewares primeiro: aqui a ordem é
    // [Admin, Authenticate]. O Admin trata sozinho o usuário sem login,
    // então o aninhamento em 'auth' é redundante, mas inofensivo.
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');
    });
});
