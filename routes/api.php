<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\Admon\UserController;

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