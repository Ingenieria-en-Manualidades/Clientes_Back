<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\Admon\UserController;
use App\Http\Controllers\CalidadController;
use App\Http\Controllers\AccidentesController;
use App\Http\Controllers\ObjetivoController;
use App\Http\Controllers\Tablero_SaeController;
use App\Http\Controllers\IndicadoresController;
use App\Http\Controllers\PermissionController;

// Authentication Routes
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/generartoken', [AuthController::class, 'generateToken']);
Route::get('/verificarToken/{token}', [AuthController::class, 'setVerificarToken']);
Route::post('/verificarTokenLogin', [AuthController::class, 'setVerificarLogin'])->middleware('auth:sanctum');
Route::get('borrarToken/{token}', [AuthController::class, 'deleteToken']);

// User Routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/roles', [RolePermissionController::class, 'getRoles']);
Route::get('roles-clientes', [UserController::class, 'getRolesAndClientes']);
Route::post('/updatePassword', [UserController::class, 'updatePassword']);

// Meta Routes
Route::post('/guardarMeta', [MetaController::class, 'create']);

// Calidad Routes
Route::post('/guardarCalidad', [CalidadController::class, 'create']);

// Accidentes Routes
Route::post('/guardarAccidente', [AccidentesController::class, 'create']);

// Objetivos Routes
Route::post('/guardarObjetivos', [ObjetivoController::class, 'create']);
Route::post('/actualizarObjetivos', [ObjetivoController::class, 'update']);

// Tablero Routes
Route::post('/guardarTablero', [Tablero_SaeController::class, 'create']);

// Permission Routes
Route::post('/relacionarUsuarioPermiso', [PermissionController::class, 'guardarUserPermission']);

// File Routes
// Route::get('/createFile', [CalidadController::class, 'createFile']);
Route::post('/guardarArchivo', [FileController::class, 'saveFileCalidad']);
Route::post('/listarArchivos', [FileController::class, 'store']);
Route::post('/descargar-pdf', [FileController::class, 'downloadFile']);
