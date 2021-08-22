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
Route::post('user-info', 'ApiRegisterController@getUserInfo');
Route::post('login-user', 'ApiRegisterController@loginUser');
Route::post('registro', 'ApiRegisterController@userRegister');
Route::post('get-users', 'ApiRegisterController@getUsers');
Route::post('update-users', 'ApiRegisterController@updateUsers');
Route::post('delete-users', 'ApiRegisterController@deleteUsers');
//transportista
Route::post('transportista', 'ApiRegisterController@transportistaRegister');
Route::post('get-transportistas', 'ApiRegisterController@getTransportistas');
Route::post('update-transportistas', 'ApiRegisterController@updateTransportistas');
Route::post('delete-transportistas', 'ApiRegisterController@deleteTransportista');
Route::post('get-transportistas-datatable', 'ApiRegisterController@getTransportistaTable');
//roles
Route::post('roles', 'ApiRolesController@userRol');
//materias
Route::post('asignaturas', 'ApiSubjectsController@subjects');
Route::post('get-asignaturas', 'ApiSubjectsController@getSubjects');
Route::post('publicaciones', 'ApiPublicationsController@publications');
Route::post('get-asignaturas-separate', 'ApiSubjectsController@getSubjectsSeparate');
Route::post('delete-subject', 'ApiSubjectsController@deleteSubjects');
Route::post('update-subject', 'ApiSubjectsController@updateSubjects');
//grados
Route::post('grados', 'ApiAdminController@schoolGrade');
Route::post('get-grados', 'ApiAdminController@getGrades');
//rutas
Route::post('rutas', 'ApiAdminController@ruta');
Route::post('get-rutas', 'ApiAdminController@getRutas');
Route::post('delete-driver-from-ruta', 'ApiAdminController@deleteDriver');
Route::post('add-driver-to-ruta', 'ApiAdminController@addDriver');
Route::post('update-rutas', 'ApiAdminController@updateAllDriver');
Route::post('delete-rutas', 'ApiAdminController@deleteRuta');
//estudiantes
Route::post('add-estudiantes', 'ApiRegisterController@estudiantes');
Route::post('get-estudiantes', 'ApiRegisterController@getEstudiantesInfo');
Route::post('del-solicitud-estudiante', 'ApiRegisterController@deleteSolicitudEstudiantes');
Route::post('get-solicitud-estudiante', 'ApiAdminController@getAllStudents');


// Route::post('emitir_simple',  function (Request $request){
//     return json_encode(array("result" => "si"));
// });

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
