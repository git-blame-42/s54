<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\AuthenticationsController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\UsersController;
use Core\Router\Route;

Route::get('/', [HomeController::class, 'index'])->name('root');

// Authentication
Route::get('/login', [AuthenticationsController::class, 'new'])->name('users.login');
Route::post('/login', [AuthenticationsController::class, 'authenticate'])->name('users.authenticate');
Route::get('/logout', [AuthenticationsController::class, 'destroy'])->name('users.logout');

// Cadastro (público): 'users.login' já existe, por isso os nomes users.new e users.create
Route::get('/users/new', [UsersController::class, 'new'])->name('users.new');
Route::post('/users', [UsersController::class, 'create'])->name('users.create');

// Área do usuário (somente autenticados)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Área administrativa (somente autenticados com perfil admin)
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');
    });
});
