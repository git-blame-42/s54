<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\EventsController as AdminEventsController;
use App\Controllers\AuthenticationsController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\Organizer\EventsController as OrganizerEventsController;
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

        // Eventos (CRUD RESTful). 'new' vem antes de '{id}', senão 'new' seria capturado como id.
        Route::get('/admin/events', [AdminEventsController::class, 'index'])->name('admin.events.index');
        Route::get('/admin/events/page/{page}', [AdminEventsController::class, 'index'])
            ->name('admin.events.paginate');
        Route::get('/admin/events/new', [AdminEventsController::class, 'new'])->name('admin.events.new');
        Route::post('/admin/events', [AdminEventsController::class, 'create'])->name('admin.events.create');
        Route::get('/admin/events/{id}', [AdminEventsController::class, 'show'])->name('admin.events.show');
        Route::get('/admin/events/{id}/edit', [AdminEventsController::class, 'edit'])->name('admin.events.edit');
        Route::put('/admin/events/{id}', [AdminEventsController::class, 'update'])->name('admin.events.update');
        Route::delete('/admin/events/{id}', [AdminEventsController::class, 'destroy'])
            ->name('admin.events.destroy');
    });

    // Área do Organizador (autenticado com perfil organizer): CRUD só dos próprios Eventos.
    // Fica dentro do grupo 'auth'. O middleware do grupo interno roda antes do externo: sem login o
    // Organizer deixa passar e o Authenticate redireciona para /login.
    Route::middleware('organizer')->group(function () {
        Route::get('/organizer/events', [OrganizerEventsController::class, 'index'])->name('organizer.events.index');
        Route::get('/organizer/events/page/{page}', [OrganizerEventsController::class, 'index'])
            ->name('organizer.events.paginate');
        Route::get('/organizer/events/new', [OrganizerEventsController::class, 'new'])->name('organizer.events.new');
        Route::post('/organizer/events', [OrganizerEventsController::class, 'create'])->name('organizer.events.create');
        Route::get('/organizer/events/{id}', [OrganizerEventsController::class, 'show'])->name('organizer.events.show');
        Route::get('/organizer/events/{id}/edit', [OrganizerEventsController::class, 'edit'])
            ->name('organizer.events.edit');
        Route::put('/organizer/events/{id}', [OrganizerEventsController::class, 'update'])
            ->name('organizer.events.update');
        Route::delete('/organizer/events/{id}', [OrganizerEventsController::class, 'destroy'])
            ->name('organizer.events.destroy');
    });
});
