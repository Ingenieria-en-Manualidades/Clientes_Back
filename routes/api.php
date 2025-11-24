<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\Admon\UserController;
use App\Http\Controllers\CalidadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\AccidentesController;
use App\Http\Controllers\ObjetivoController;
use App\Http\Controllers\Tablero_SaeController;
use App\Http\Controllers\IndicadoresController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\MetaUnidadesController;
use App\Http\Controllers\ClienteUserController;
use App\Http\Controllers\UnidadesDiariasController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\PrivacyPolicyController;

// Authentication Routes
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/generartoken', [AuthController::class, 'generateToken']);
Route::post('/enviarEmailContraseña', [AuthController::class, 'sendRecoveryEmail']);
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
Route::post('/updatePasswordExpiration', [AuthController::class, 'updatePassword'])->middleware('auth:sanctum');

// Meta Routes
Route::post('/guardarMeta', [MetaController::class, 'create']);

// Calidad Routes
Route::post('/guardarCalidad', [CalidadController::class, 'create']);
Route::post('/verificarCalidad', [CalidadController::class, 'verificarValores']);

// Accidentes Routes
Route::post('/guardarAccidente', [AccidentesController::class, 'create']);

// Objetivos Routes
Route::post('/guardarObjetivos', [ObjetivoController::class, 'create']);
Route::post('/actualizarObjetivos', [ObjetivoController::class, 'update']);

// Tablero Routes
Route::post('/guardarTablero', [Tablero_SaeController::class, 'create']);

// Permission Routes
Route::get('/getListPermissions', [PermissionController::class, 'getListPermissions']);
Route::post('/relacionarUsuarioPermiso', [PermissionController::class, 'guardarUserPermission']);

////////////////////////////////////
// Cliente_user Routes
Route::get('/getClientsByUserId', [ClienteUserController::class, 'getClientsByUserId']);
//-----------------------------------getClientsByIds

////////////////////////////////////
// Clientes Routes
Route::get('/getClients', [ClienteController::class, 'getClients']);
Route::get('/getClientsByIds/{arrayIds}', [ClienteController::class, 'getClientsByIds']);
//-----------------------------------

// File Routes
// Route::get('/createFile', [CalidadController::class, 'createFile']);
Route::post('/guardarArchivo', [FileController::class, 'saveFileCalidad']);
Route::post('/listarArchivos', [FileController::class, 'store']);
Route::post('/descargar-pdf', [FileController::class, 'downloadFile']);
Route::post('/deleteFile', [FileController::class, 'delete']);

////////////////////////////////////
// Unidades Mensuales Routes
Route::post('/metaUnidadesExists', [MetaUnidadesController::class, 'exists']);
Route::post('/createMetaUnidades', [MetaUnidadesController::class, 'create']);
Route::put('/updateMetaUnidades', [MetaUnidadesController::class, 'update']);
Route::post('getListUnidadesMeta', [MetaUnidadesController::class, 'list']);
Route::get('getMetaUnidades/{meta_unidades_id}', [MetaUnidadesController::class, 'getMetaUnidades']);
Route::get('getAreas/{clienteID}', [MetaUnidadesController::class, 'getAreasImec']);
//-----------------------------------

////////////////////////////////////
// Unidades Diarias Routes
Route::get('/getDailyUnitsOfDay/{date}/{client_id}', [UnidadesDiariasController::class, 'getDailyUnitsOfDay']); //API GROOT
Route::post('/createUnidadesDiarias', [UnidadesDiariasController::class, 'create']);
Route::post('/updateUnidadesDiarias', [UnidadesDiariasController::class, 'update']);
Route::get('/getListUnidadesDiarias/{meta_unidades_id}', [UnidadesDiariasController::class, 'list']);
Route::get('/getUnidadesDiariaID/{unidades_diaria_id}', [UnidadesDiariasController::class, 'getUnidadesDiariaID']);
//-----------------------------------

////////////////////////////////////
// Survey Routes
Route::post('/saveSurvey', [SurveyController::class, 'setSaveSurvey']);
Route::get('/listCharges', [SurveyController::class, 'getListCharges']);
Route::get('/listClients', [SurveyController::class, 'getListClients']);
Route::get('/getInformationUser/{username}', [SurveyController::class, 'getInformationUser']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/policy',        [PrivacyPolicyController::class, 'show']);
    Route::get('/policy/status', [PrivacyPolicyController::class, 'status']);
    Route::post('/policy/accept',[PrivacyPolicyController::class, 'accept']);
});

////////////////////////////////////
// User Routes Frontend
Route::get('/getUsers', [UserController::class, 'getUsers']);
Route::get('/resetUser/{id}', [UserController::class, 'resetUser']);
Route::post('/createUser', [UserController::class, 'storeFrontend']);
Route::get('/getEmployeesImec/{clients_id}', [UserController::class, 'getEmployeesImecByClientsId']);