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
Route::post('get-profesor', 'ApiSubjectsController@getTeachers');
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
Route::post('get-rutas-for-user', 'ApiRegisterController@getRutasforUser');
//estudiantes
Route::post('add-estudiantes', 'ApiRegisterController@estudiantes');
Route::post('get-estudiantes', 'ApiRegisterController@getEstudiantesInfo');
Route::post('del-solicitud-estudiante', 'ApiRegisterController@deleteSolicitudEstudiantes');
Route::post('get-solicitud-estudiante', 'ApiAdminController@getAllStudents');
Route::post('aprobar-estudiante', 'ApiAdminController@aprobarEstudiante');
Route::post('rechazar-estudiante', 'ApiAdminController@rechazarEstudiante');
Route::post('get-estudiante-transporte', 'ApiAdminController@getStudentTransporte');

Route::post('get-my-estudiante', 'ApiAdminController@getMyStudents');
Route::post('get-my-subject', 'ApiSubjectsController@getMySubjects');
Route::post('get-materia-de-estudiante', 'ApiAdminController@getMateriaFromEstudiante');

//notificaciones
Route::post('send-notification', 'ApiAdminController@createNotification');
Route::post('get-notification', 'ApiAdminController@getNotifications');
Route::post('del-notification', 'ApiAdminController@deleteNotifications');

//profesor
Route::post('get-materia-from-teacher', 'ApiAdminController@getSubjectFromGrade');
Route::post('get-materia-from-only-teacher', 'ApiAdminController@getSubjectFromTeacher');
Route::post('create-registro-notas', 'ApiAdminController@createNotas');
Route::post('get-notas', 'ApiAdminController@getNotas');
Route::post('del-notas', 'ApiAdminController@deleteNotas');
Route::post('close-notas', 'ApiAdminController@closeNota');
Route::post('get-notas-by-parcial', 'ApiAdminController@getNotaByParcial');
Route::post('update-notas', 'ApiAdminController@updateNota');
Route::post('update-nota-final', 'ApiAdminController@updateQuimes');

Route::post('matricula-transporte', 'ApiAdminController@matricularTransporte');
//movile
Route::post('get-user-movile-info', 'ApiAdminController@getMovilInfo');
Route::post('add-mobile-user', 'ApiAdminController@registerUserMobile');
Route::post('get-mobile-user-info', 'ApiAdminController@loginUserMobile');
Route::post('get-ruta-mobile', 'ApiAdminController@getRutasMobile');
Route::post('publicar-comunicado', 'ApiAdminController@publishComunicado');
Route::post('get-comunicados', 'ApiAdminController@getPublish');
Route::post('get-ruta-mobile-transporte', 'ApiAdminController@getRutasTransporte');
Route::post('del-comunicados', 'ApiAdminController@delComunicados');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
