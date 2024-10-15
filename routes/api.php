<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ObjetivesController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\Admon\UserController;
use App\Http\Controllers\CalidadController;
use App\Http\Controllers\AccidentesController;
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\Tablero_SaeController;
use App\Http\Controllers\IndicadoresController;
use App\Http\Controllers\PermissionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/roles', [RolePermissionController::class, 'getRoles']);

Route::post('login', [AuthController::class, 'login']);
Route::get('roles-clientes', [UserController::class, 'getRolesAndClientes']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/generartoken', [AuthController::class, 'generateToken']);
Route::get('/verificarToken/{token}', [AuthController::class, 'setVerificarToken']);
Route::post('/verificarTokenLogin', [AuthController::class, 'setVerificarLogin'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('borrarToken/{token}', [AuthController::class, 'deleteToken']);
Route::post('/updatePassword', [UserController::class, 'updatePassword']);

//Routes Create Objetives
Route::post('/guardarObjetivos', [ObjetivesController::class, 'guardarObjetivos']);

//Routes Calidad
//Create
Route::post('/guardarCalidad', [CalidadController::class, 'guardarCalidad']);

//Routes Accidente
//Create
Route::post('/guardarAccidente', [AccidentesController::class, 'guardarAccidentes']);

//Routes Produccion
//Create
Route::post('/guardarProduccion', [ProduccionController::class, 'create']);
//Update
Route::post('/actualizarProduccion', [ProduccionController::class, 'update']);

//Routes Tablero
//Create
Route::post('/guardarTablero', [Tablero_SaeController::class, 'guardarTablero']);

//Routes Usuario y permiso
//Create
Route::post('/relacionarUsuarioPermiso', [PermissionController::class, 'guardarUserPermission']);