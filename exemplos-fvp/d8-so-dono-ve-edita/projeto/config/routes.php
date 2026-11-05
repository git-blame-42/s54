<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\AuthenticationsController;
use App\Controllers\DashboardController;
use App\Controllers\EventsController;
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

    // Eventos do usuário logado (CRUD RESTful). A rota /events/new fica antes de /events/{id}.
    Route::get('/events', [EventsController::class, 'index'])->name('events.index');
    Route::get('/events/page/{page}', [EventsController::class, 'index'])->name('events.paginate');
    Route::get('/events/new', [EventsController::class, 'new'])->name('events.new');
    Route::post('/events', [EventsController::class, 'create'])->name('events.create');
    Route::get('/events/{id}', [EventsController::class, 'show'])->name('events.show');
    Route::get('/events/{id}/edit', [EventsController::class, 'edit'])->name('events.edit');
    Route::put('/events/{id}', [EventsController::class, 'update'])->name('events.update');
    Route::delete('/events/{id}', [EventsController::class, 'destroy'])->name('events.destroy');

    // Área administrativa (somente autenticados com perfil admin)
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');
    });
});
