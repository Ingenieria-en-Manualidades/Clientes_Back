<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admon\ClienteController;
use App\Http\Controllers\Admon\DashboardController;
use App\Http\Controllers\Admon\UserController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\RoleDeleteController;
use App\Http\Middleware\RedirectIfNotAdmin;

// Ruta para la página de inicio de sesión
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');  // Redirigir al dashboard si el usuario ya está autenticado
    }
    return view('auth.login');
});


// Agrupar rutas que requieren autenticación
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Rutas para clientes
    Route::middleware([RedirectIfNotAdmin::class])->group(function () {
        Route::resource('clientes', ClienteController::class)->except(['create', 'show']);
    });
    Route::get('/clientes/endpoint', [ClienteController::class, 'endpoint'])->name('clientes.endpoint');
    Route::delete('clientes/{id}/eliminacion', [ClienteController::class, 'eliminacion'])->name('clientes.eliminacion');
    Route::get('clientes/deshabilitados', [ClienteController::class, 'deshabilitados'])->name('clientes.deshabilitados');
    Route::get('clientes/{id}/restaurar', [ClienteController::class, 'restaurar'])->name('clientes.restaurar');

    // Rutas para usuarios
    Route::resource('usuarios', UserController::class)->except(['create', 'show', 'edit']);
    Route::put('/usuarios/{id}/actualizar-contrasena', [UserController::class, 'resetPassword'])->name('usuarios.resetPassword');
    Route::get('usuarios/deshabilitados', [UserController::class, 'deshabilitados'])->name('usuarios.deshabilitados');
    Route::get('usuarios/{id}/restaurar', [UserController::class, 'restaurar'])->name('usuarios.restaurar');
    Route::post('/usuarios/toggle-active/{id}', [UserController::class, 'toggleActive'])->name('usuarios.toggleActive');

    // Rutas para roles
    Route::resource('roles', RolePermissionController::class)->except(['create', 'show', 'destroy']);
    Route::delete('roles/{id}', [RoleDeleteController::class, 'destroy'])->name('roles.destroy');
    Route::get('roles/deshabilitados', [RolePermissionController::class, 'deshabilitados'])->name('roles.deshabilitados');
    Route::get('roles/{id}/restaurar', [RolePermissionController::class, 'restaurar'])->name('roles.restaurar');
    Route::post('permissions', [RolePermissionController::class, 'storePermission'])->name('permissions.store');

});

// Ruta de fallback para manejar URLs incorrectas
Route::fallback(function () {
    if (auth()->check()) {
        return response()->view('errors.404', [], 404);
    } else {
        return redirect()->guest('login');
    }
});
