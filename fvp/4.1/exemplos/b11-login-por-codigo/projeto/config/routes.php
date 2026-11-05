<?php

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\AuthenticationsController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\LoginCodesController;
use Core\Router\Route;

Route::get('/', [HomeController::class, 'index'])->name('root');

// Authentication
Route::get('/login', [AuthenticationsController::class, 'new'])->name('users.login');
Route::post('/login', [AuthenticationsController::class, 'authenticate'])->name('users.authenticate');
Route::get('/logout', [AuthenticationsController::class, 'destroy'])->name('users.logout');

// Login por código (FVP B11). Rotas públicas, como /login: ficam fora dos grupos de
// middleware. Nomes login_codes.* no padrão recurso.ação; /login/code tem dois
// segmentos e /login um, então o roteador não confunde as duas. GET mostra o
// formulário, POST processa (new/create para o pedido, verify/authenticate para o código).
// Próximo passo: app/Controllers/LoginCodesController.php (as quatro ações).
Route::get('/login/code', [LoginCodesController::class, 'new'])->name('login_codes.new');
Route::post('/login/code', [LoginCodesController::class, 'create'])->name('login_codes.create');
Route::get('/login/code/verify', [LoginCodesController::class, 'verify'])->name('login_codes.verify');
Route::post('/login/code/verify', [LoginCodesController::class, 'authenticate'])->name('login_codes.authenticate');

// Área do usuário (somente autenticados)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Área administrativa (somente autenticados com perfil admin)
    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');
    });
});
