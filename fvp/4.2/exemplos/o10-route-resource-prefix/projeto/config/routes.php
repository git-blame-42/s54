<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\EventsController as AdminEventsController;
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

    // Área administrativa (somente autenticados com perfil admin)
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');

        // Eventos (CRUD RESTful): as 8 rotas admin.events.* de uma vez, com 'new' e
        // 'page/{page}' antes de '{id}' (ver Route::resource).
        Route::resource('/admin/events', AdminEventsController::class, 'admin.events');
    });
});
