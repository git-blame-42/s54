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

// Token CSRF em todo POST/PUT/DELETE da área logada (GET passa). Fica POR FORA do 'auth': o
// RouteWrapperMiddleware acrescenta os middlewares quando cada grupo fecha, então o grupo interno roda
// primeiro. Assim auth e admin decidem antes (sem sessão -> /login; Participante -> /dashboard) e o
// csrf só confere quem já pode estar ali.
Route::middleware('csrf')->group(function () {
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
    });
});
