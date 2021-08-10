<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('registro', 'ApiRegisterController@userRegister');
Route::post('transportista', 'ApiRegisterController@transportistaRegister');
Route::post('roles', 'ApiRolesController@userRol');
Route::post('asignaturas', 'ApiSubjectsController@subjects');
Route::post('publicaciones', 'ApiPublicationsController@publications');
Route::post('grados', 'ApiAdminController@schoolGrade');
Route::post('get-grados', 'ApiAdminController@getGrades');
Route::post('rutas', 'ApiAdminController@ruta');


// Route::post('emitir_simple',  function (Request $request){
//     return json_encode(array("result" => "si"));
// });

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
