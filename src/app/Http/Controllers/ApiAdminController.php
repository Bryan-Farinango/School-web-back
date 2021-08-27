<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Enums\AccionProcesoEnum;
use App\Models\Enums\EstadoFirmaEnum;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Ruta;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Http\Request;
use App\Models\Proceso;
use App\Models\Enums\TipoProcesoEnum;
use App\Models\Firma;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\In;
use MongoDB\BSON\UTCDateTime;

class ApiAdminController extends Controller
{
    public function schoolGrade(Request $request){
        $name = $request->input('nombre_grado');
        $jornada = $request->input('jornada');
        $descripcion = $request->input('descripcion');
        $rangoEdad = $request->input('rango_edad');

        if (empty($name)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'nombre requerido.'
                ]
            );
        }
        if (empty($jornada)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'jornada requerida.'
                ]
            );
        }
        if (empty($descripcion)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'descripcion requerida.'
                ]
            );
        }
        if (empty($rangoEdad)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'rango de edad requerido.'
                ]
            );
        }

        $grades = [
            'nombre_grado' => $name,
            'jornada' => $jornada,
            'descripcion' => $descripcion,
            'rango_edad' => $rangoEdad,
        ];

        $gradeValidation = Grade::where('nombre_grado', $name)
            ->orderBy("created_at", "desc")
            ->first();

        if ($gradeValidation != null) {
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => "El Grado ya existe."
                ]
            );
        }

        try {
            $grade = Grade::create($grades);
        }catch (Exception $e){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear el grado.',
                    'error' => $e
                ]
            );
        }
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Grado creado correctamente.'
            ]
        );

    }

    public function getGrades(Request $request){
        $apiKey = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $grades =  Grade::all();
        $getGrades = array();
        foreach ($grades as $g){
            $gradesArray = array(
                'nombre_grado' => $g['nombre_grado'],
                'grado_id' => $g['_id'],
                'jornada' => $g['jornada'],
                'rango_edad' => $g['rango_edad']
            );
            array_push($getGrades, $gradesArray);
        }

        return response()->json(
            [
                'resultado' => true,
                'grados' => $getGrades
            ]
        );

    }

    //rutas
    public function ruta(Request $request){
        $titulo = $request->input('titulo_ruta');
        $numeroRuta = $request->input('numero_ruta');
        $ciudad = $request->input('ciudad');
        $sector1 = $request->input('sector_1');
        $sector2 = $request->input('sector_2');
        $sector3 = $request->input('sector_3');
        $transportistaId = $request->input('transportista_id');
        if (empty($titulo)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'titulo requerido.'
                ]
            );
        }
        if (empty($ciudad)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'ciudad requerida.'
                ]
            );
        }
        if (empty($numeroRuta)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'numero de ruta requerido.'
                ]
            );
        }
        if (empty($sector1)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'sector 1 requerido.'
                ]
            );
        }
        if (empty($sector2)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'sector 2 requerido.'
                ]
            );
        }
        if (empty($sector3)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'sector 3 requerido.'
                ]
            );
        }

        $rutas = [
            'titulo_ruta' => $titulo,
            'numero_ruta' => $numeroRuta,
            'ciudad' => $ciudad,
            'sector_1' => $sector1,
            'sector_2' => $sector2,
            'sector_3' => $sector3,
            'transportista_id' => $transportistaId
        ];

        $rutaValidation = Ruta::where('numero_ruta', $numeroRuta)
            ->orderBy("created_at", "desc")
            ->first();

        if ($rutaValidation != null) {
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => "La ruta ya existe."
                ]
            );
        }

        try {
            $ruta = Ruta::create($rutas);
        }catch (Exception $e){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear el grado.',
                    'error' => $e
                ]
            );
        }
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Ruta creada correctamente.'
            ]
        );



    }
    public function getRutas(Request $request){
        $apiKey = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $rutas =  Ruta::all();

        $getRutas = array();
        foreach ($rutas as $r){
            $transportistaName = Driver::find($r['transportista_id'] );
            $rutasArray = array(
                'ruta_id' => $r['_id'],
                'titulo_ruta' => $r['titulo_ruta'],
                'numero_ruta' => $r['numero_ruta'],
                'ciudad' => $r['ciudad'],
                'sector_1' => $r['sector_1'],
                'sector_2' => $r['sector_2'],
                'sector_3' => $r['sector_3']


            );

            if ($transportistaName != null){
                $rutasArray += [
                    'transportista_id' => $r['transportista_id'],
                    'nombre_transportista' => $transportistaName['nombres'],
                    'apellido_transportista' => $transportistaName['apellidos']
                ];
            }else{
                $r['transportista_id'] = 'empty';
                $r->save();

                $rutasArray += [
                    'transportista_id' => 'empty',

                ];
            }
            array_push($getRutas, $rutasArray);
        }

        return response()->json(
            [
                'resultado' => true,
                'rutas' => $getRutas
            ]
        );

    }
    public function deleteDriver(Request $request){
        $apiKey = $request->input('api_key_admin');
        $transportista_id = $request->input('transportista_id');
        $ruta_id = $request->input('ruta_id');

        $dataMatch = [
            'transportista_id' => $transportista_id,
            '_id' => $ruta_id
        ];

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $ruta = Ruta::where($dataMatch)->get()->first();
        if ($ruta == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'El transportista no existe.',
                ]
            );
        }

        $ruta->transportista_id = 'empty';
        $ruta->save();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Transportista eliminado de la Ruta',
            ]
        );
    }
    public function addDriver(Request $request){
        $apiKey = $request->input('api_key_admin');
        $nombreTransportista = $request->input('nombre_transportista');
        $ruta_id = $request->input('ruta_id');
        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $driveradd = Driver::where('nombres', $nombreTransportista)->get()->first();
        $ruta = Ruta::find($ruta_id);

        $ruta->transportista_id = $driveradd->_id;
        $ruta->save();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Transportista añadido correctamente.',
                'ruta' => $ruta,
                'driver' => $driveradd
            ]
        );
    }
    public function updateAllDriver(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $rutaId = $request->input('ruta_id');
        $titulo = $request->input('titulo_ruta');
        $numero = $request->input('numero_ruta');
        $ciudad = $request->input('ciudad');
        $sector1 = $request->input('sector_1');
        $sector2 = $request->input('sector_2');
        $sector3 = $request->input('sector_3');


        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $rutaObj = Ruta::where("_id", $rutaId)->get()->first();

        if ($rutaObj == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "La ruta no existe."
                ];
        }

        $rutaObj->titulo_ruta = $titulo;
        $rutaObj->numero_ruta = $numero;
        $rutaObj->ciudad = $ciudad;
        $rutaObj->sector_1 = $sector1;
        $rutaObj->sector_2 = $sector2;
        $rutaObj->sector_3 = $sector3;
        $rutaObj->save();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Ruta actualizada.',
            ]
        );
    }
    public function deleteRuta(Request $request){

        $api_key_admin = $request->input('api_key_admin');
        $rutaId = $request->input('ruta_id');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $ruta = Ruta::find($rutaId);
        if ($ruta == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "No existe la ruta."
                ];
        }

        $ruta->delete();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Cuenta Borrada.'
            ]
        );
    }

    //students
    public function getAllStudents(Request $request){
        $apiKey = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $students =  Student::where('estado', 0)->get();
        $getStudent = array();
        foreach ($students as $g){
            $gradeName = 'No existe el grado';
            $grado = Grade::find($g['grado_id']);
            if ($grado != null){
                $gradeName = $grado->nombre_grado;
            }
            $studentArray = array(
                'estudiante_id' => $g['_id'],
                'nombres' => $g['nombres'],
                'apellidos' => $g['apellidos'],
                'identificacion' => $g['identificacion'],
                'edad' => $g['edad'],
                'genero' => $g['genero'],
                'nombre_grado' => $gradeName,
                'jornada' => $g['jornada'],

            );
            array_push($getStudent, $studentArray);
        }

        return response()->json(
            [
                'resultado' => true,
                'solicitudes' => $getStudent
            ]
        );

    }
    public function aprobarEstudiante(Request $request){
        //matricular
        $apiKey = $request->input('api_key_admin');
        $estudiante_id = $request->input('estudiante_id');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $student = Student::find($estudiante_id);
        $student->estado = 1;
        $student->save();


        $subjects = Subject::where('grado_id', $student->grado_id)->get();
        $newArray = array();
        foreach ($subjects as $s){
            $subjectsArray = array(
                'materia_id' => $s['_id'],
                'profesor_id' => $s['usuario_id'],
                'grado_id' => $s['grado_id'],
                'nombre_asignatura' => $s['nombre_asignatura'],

            );
            array_push($newArray, $subjectsArray);
        }


        $student->push(
            'materias' , $newArray
        );

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Estudante Matriculado'
            ]
        );

    }
    public function rechazarEstudiante(Request $request){
        $apiKey = $request->input('api_key_admin');
        $estudiante_id = $request->input('estudiante_id');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $student = Student::find($estudiante_id);
        $student->estado = 2;
        $student->save();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Matrícula rechazada'
            ]
        );
    }

    //teacher-apis
    public function getMyStudents(Request $request){
        $teacherId = $request->input('usuario_id');
        $materiaId = $request->input('asignatura_id');
        $gradoId = $request->input('grado_id');

        if(empty($teacherId)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Campo profesor_id vacío.'
                ]
            );
        }

        if(empty($materiaId)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Campo materia_id vacío.'
                ]
            );
        }

        if(empty($gradoId)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Campo grado_id vacío.'
                ]
            );
        }

        $dataMatch = array();

        if ($materiaId != 'todos'){
            $dataMatch += [
                "materias.materia_id" => $materiaId
            ];
        }

        if ($gradoId != 'todos'){
            $dataMatch += [
                "materias.grado_id" => $gradoId
            ];
        }

        $dataMatch += [
            "materias.profesor_id" => $teacherId
        ];

        $students = Student::where($dataMatch)->get();

        return response()->json(
            [
                'resultado' => true,
                'estudiantes' => $students
            ]
        );
    }
    public function getMateriaFromEstudiante(Request $request){
        $estudiante_id = $request->input('estudiante_id');
        $usuario_id = $request->input('usuario_id');
        $estudiante = Student::find($estudiante_id);
        if ($estudiante == null ){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'El estudiante no existe'
                ]
            );
        }
        /*
         * $newArray = array();
        foreach ($subjects as $s){
            $subjectsArray = array(
                'materia_id' => $s['_id'],
                'profesor_id' => $s['usuario_id'],
                'grado_id' => $s['grado_id'],
                'nombre_asignatura' => $s['nombre_asignatura'],

            );
            array_push($newArray, $subjectsArray);
        }
         */
        $newArr = array();
        foreach ( $estudiante->materias as $materia){
            if ($materia['profesor_id'] == $usuario_id){
                $materiaArr = array(
                    'materia_id' => $materia['materia_id'],
                    'profesor_id' => $materia['profesor_id'],
                    'grado_id' => $materia['grado_id'],
                    'nombre_asignatura' => $materia['nombre_asignatura'],

                );
            }
            if (isset($materiaArr)){
                array_push($newArr, $materiaArr);
            }

        }



        return response()->json(
            [
                'resultado' => true,
                'materias' => $newArr
            ]
        );


    }
    public function createNotification(Request $request){

        $tema = $request->input('tema');
        $titulo = $request->input('titulo');
        $fecha = $request->input('fecha');
        $mensaje = $request->input('mensaje');
        $estudiante_id = $request->input('estudiante_id');
        $materia_id = $request->input('materia_id');
        $usuario_id = $request->input('usuario_id');

        if (empty($tema)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Tema requerido.'
                ]
            );
        }
        if (empty($usuario_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'usuario_id requerido.'
                ]
            );
        }
        if (empty($titulo)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'titulo requerido.'
                ]
            );
        }
        if (empty($fecha)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'fecha requerida.'
                ]
            );
        }
        if (empty($mensaje)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'mensaje requerido.'
                ]
            );
        }
        if (empty($estudiante_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'estudiante_id requerido.'
                ]
            );
        }
        if (empty($materia_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'materia_id requerido.'
                ]
            );
        }

        $notificacionArray = [
            'tema' => $tema,
            'titulo'=> $titulo,
            'fecha'=> $fecha,
            'mensaje'=> $mensaje,
            'estudiante_id'=> $estudiante_id,
            'usuario_id'=> $usuario_id,
            'materia_id'=> $materia_id
        ];
        try {
            $notificaionCreate = Notification::create($notificacionArray);
        }catch (Exception $e){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear el grado.',
                    'error' => $e
                ]
            );
        }

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Notificación enviada.'
            ]
        );
    }

    //user-apis
    public function getNotifications(Request $request){
        $usuario_id = $request->input('usuario_id');

        $notificationes = Notification::where('usuario_id',$usuario_id)->get();
        $newArr = array();

        foreach ($notificationes as $notification){
            $notificationArray = array();

            $estudiante = Student::find($notification['estudiante_id']);
            if ($estudiante != null){
                $notificationArray += [
                    "notificacion_nombres" => $estudiante->nombres,
                    "notificacion_apellidos" => $estudiante->apellidos
                ];
            }else{
                $notificationArray += [
                    "notificacion_nombres" => "No existe el usuario",
                    "notificacion_apellidos" => "No existe el usuario"
                ];
            }

            $materia = Subject::find($notification['materia_id']);
            if ($materia != null){
                $notificationArray += [
                    "notificacion_materia" => $materia->nombre_asignatura,
                ];
                $grado = Grade::find($materia->grado_id);
                    if  ($grado != null){
                        $notificationArray += [
                            "notificacion_grado" => $grado->nombre_grado
                        ];
                    }
                if (isset($materia->usuario_id)){
                    $profesor = Usuario::find($materia->usuario_id);
                    if  ($profesor != null){
                        $notificationArray += [
                            "notificacion_profesor" => $profesor->nombres,
                            "notificacion_profesor_apellidos" => $profesor->apellidos
                        ];
                    }
                }else{
                    $notificationArray += [
                        "notificacion_profesor" => "no tiene asignado un profesor",
                        "notificacion_profesor_apellidos" => "no tiene asignado un profesor"
                    ];
                }


            }else{
                $notificationArray += [
                    "notificacion_materia" => "No existe la materia",
                    "notificacion_grado" => "No existe el grado",
                    "notificacion_profesor" => "Materia sin Profesor"
                ];
            }

            $notificationArray += [
                'notificacion_id' => $notification['_id'],
                'notificacion_titulo' => $notification['titulo'],
                'notificacion_tema' => $notification['tema'],
                'notificacion_fecha' => $notification['fecha'],
                'notificacion_mensaje' => $notification['mensaje']
            ];
            array_push($newArr, $notificationArray);
        }
        return response()->json(
            [
                'resultado' => true,
                'notificaciones' => $newArr
            ]
        );
    }
    public function deleteNotifications(Request $request){
        $notificacion_id = $request->input('notificacion_id');

        $notificacion = Notification::find($notificacion_id);
        if ($notificacion_id == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'La notificación no existe'
                ]
            );
        }
        $notificacion->delete();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Notificación eliminada'
            ]
        );

    }

    //notas apis
    public function getSubjectFromGrade(Request $request){
        $grado_id = $request->input('grado_id');
        $profesor_id = $request->input('usuario_id');

        $materias = Subject::where('grado_id', $grado_id)->get();
        if ($materias == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'La Materia no existe.'
                ]
            );
        }

        $newArr = array();
        foreach ($materias as $materia){
            $materiaArray = array();
            if (isset($materia['usuario_id'])){
                $profesor = Usuario::find($materia['usuario_id']);
                if ($profesor != null){
                    if ($profesor->_id == $profesor_id){
                        $materiaArray += [
                          "nombre_asignatura" => $materia['nombre_asignatura'],
                          "asignatura_id" => $materia['_id']
                        ];
                        array_push($newArr, $materiaArray);
                    }
                }
            }

        }

        return response()->json(
            [
                'resultado' => true,
                'materias' => $newArr
            ]
        );
    }
    public function createNotas(Request $request){
        $estudiante_id = $request->input('estudiante_id');
        $grado_id = $request->input('grado_id');
        $materia_id = $request->input('materia_id');
        $profesor_id = $request->input('usuario_id');
        $fecha = $request->input('fecha');
        $descripcion = $request->input('descripcion');
        $quimestre = $request->input('quimestre');

        $dataMatch = [
            'estudiante_id' => $estudiante_id,
            'quimestre' => $quimestre,
            'materia_id' => $materia_id,
            'profesor_id' => $profesor_id,
            'grado_id' => $grado_id
        ];

        $validation =  Score::where($dataMatch)->count();
        if ($validation > 0  ){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'El registro para el Quimestre del estudiante ya existe.',
                    'onj' => $validation
                ]
            );
        }
        if (empty($estudiante_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo estudiante_id requerido.'
                ]
            );
        }
        if (empty($grado_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo grado_id requerido.'
                ]
            );
        }
        if (empty($materia_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo materia_id requerido.'
                ]
            );
        }
        if (empty($profesor_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo profesor_id requerido.'
                ]
            );
        }
        if (empty($fecha)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo fecha requerido.'
                ]
            );
        }
        if (empty($descripcion)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo descripcion requerido.'
                ]
            );
        }
        if (empty($quimestre)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'campo quimestre requerido.'
                ]
            );
        }

        $parcial = [
            "nota_1" => 0,
            "nota_2" => 0,
            "nota_3" => 0,
            "nota_4" => 0,
            "nota_5" => 0,
            "nota_6" => 0,
            "total" => 0,
        ];

        $data = [
            "quimestre" => $quimestre,
            "descripcion" => $descripcion,
            "fecha_registro" => $fecha,
            "profesor_id" => $profesor_id,
            "estudiante_id" => $estudiante_id,
            "materia_id" => $materia_id,
            "grado_id" => $grado_id,
            "primer_parcial" => $parcial,
            "segundo_parcial" => $parcial,
            "tercer_parcial" => $parcial,
            "estado" => 1,
            "nota_final" => 0
        ];

        $registroNotas = Score::create($data);

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Registro de calificaciones creado correctamente.',
                'numero' => $validation
            ]
        );
    }
    public function getSubjectFromTeacher(Request $request){

        $profesor_id = $request->input('usuario_id');

        $materias = Subject::where('usuario_id', $profesor_id)->get();
        if ($materias == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'La Materia no existe.'
                ]
            );
        }

        $newArr = array();
        foreach ($materias as $materia){
            $materiaArray = array();
            if (isset($materia['usuario_id'])){
                $profesor = Usuario::find($materia['usuario_id']);
                if ($profesor != null){
                    if ($profesor->_id == $profesor_id){
                        $materiaArray += [
                            "nombre_asignatura" => $materia['nombre_asignatura'],
                            "asignatura_id" => $materia['_id']
                        ];
                        array_push($newArr, $materiaArray);
                    }
                }
            }

        }

        return response()->json(
            [
                'resultado' => true,
                'materias' => $newArr
            ]
        );
    }
    public function getNotas(Request $request){
        $teacherId = $request->input('profesor_id');
        $materiaId = $request->input('materia_id');
        $estudiante_id = $request->input('estudiante_id');
        $estado = (integer)$request->input('estado');
        $usuario_id = $request->input('usuario_id');
        $grado_id = $request->input('grado_id');

        $dataMatch = array();

        if ($teacherId != 'todos'){
            $dataMatch += [
                "profesor_id" => $teacherId
            ];
        }

        if ($materiaId != 'todos'){
            $dataMatch += [
                "materia_id" => $materiaId
            ];
        }

        if ($estudiante_id != 'todos'){
            $dataMatch += [
                "estudiante_id" => $estudiante_id
            ];
        }

        if ($grado_id != 'todos'){
            $dataMatch += [
                "grado_id" => $grado_id
            ];
        }

        $dataMatch += [
            "estado" => $estado
        ];

        //pendiente para el get padres
//        if ($usuario_id != 'todos'){
//            $dataMatch += [
//                "usuario_id" => $usuario_id
//            ];
//        }
        $newArr = array();
        $calificaciones = Score::where($dataMatch)->get();

        if ($calificaciones != null){
            foreach ($calificaciones as $calificacion){
                $calificacionesArray = array();

                $estudiante = Student::find($calificacion['estudiante_id']);
                if ($estudiante != null){
                    $calificacionesArray += [
                        "nota_estudiante_nombres" => $estudiante->nombres,
                        "nota_estudiante_apellidos" => $estudiante->apellidos,
                        "nota_estudiante_genero" => $estudiante->genero,
                        "nota_estudiante_jornada" => $estudiante->jornada,
                    ];
                }else{
                    $calificacionesArray += [
                        "nota_estudiante_nombres" => 'no existe',
                        "nota_estudiante_apellidos" => 'no existe',
                        "nota_estudiante_genero" => 'no existe',
                        "nota_estudiante_jornada" => 'no existe',
                    ];
                }

                $grado = Grade::find($calificacion['grado_id']);
                if ($grado != null){
                    $calificacionesArray += [
                        "nota_estudiante_grado" => $grado->nombre_grado,
                    ];
                }else{
                    $calificacionesArray += [
                        "nota_estudiante_grado" => 'no existe',
                    ];
                }

                $materia = Subject::find($calificacion['materia_id']);
                if ($materia != null){
                    $calificacionesArray += [
                        "nota_estudiante_nombre_asignatura" => $materia->nombre_asignatura,
                    ];
                }else{
                    $calificacionesArray += [
                        "nota_estudiante_nombre_asignatura" => 'no existe',
                    ];
                }

                $profesor = Usuario::find($calificacion['profesor_id']);
                if ($profesor != null){
                    $calificacionesArray += [
                        "nota_estudiante_nombres_profesor" => $profesor->nombres,
                        "nota_estudiante_apellidos_profesor" => $profesor->apellidos,
                    ];
                }else{
                    $calificacionesArray += [
                        "nota_estudiante_nombres_profesor" => 'no existe',
                        "nota_estudiante_apellidos_profesor" => 'no existe',
                    ];
                }

                $calificacionesArray += [
                    "nota_estudiante_quimestre" => $calificacion['quimestre'],
                    "nota_estudiante_descripcion" => $calificacion['descripcion'],
                    "nota_estudiante_id" => $calificacion['_id'],
                    "parcial1" => $calificacion['primer_parcial'],
                    "parcial2" => $calificacion['segundo_parcial'],
                    "parcial3" => $calificacion['tercer_parcial'],
                    "nota_final" => $calificacion['nota_final'],
                    "fecha" => $calificacion['fecha_registro']

                ];
                array_push($newArr, $calificacionesArray);
            }
        }

        return response()->json(
            [
                'resultado' => true,
                'calificaciones' => $newArr
            ]
        );
    }
    public function deleteNotas(Request $request){
        $nota_id = $request->input('calificacion_id');

        $calificaciones = Score::find($nota_id);
        if ($calificaciones == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'el registro de calificación no existe.'
                ]
            );
        }

        $calificaciones->delete();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'registro borrado correctamente'
            ]
        );
    }

    public function closeNota(Request $request){
        $nota_id = $request->input('calificacion_id');
        $calificaciones = Score::find($nota_id);
        if ($calificaciones == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'el registro de calificación no existe.'
                ]
            );
        }
        //estado 2 cerrado
        $calificaciones->estado = 2;
        $calificaciones->save();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Registro Cerrado con éxito.'
            ]
        );

    }
    public function getNotaByParcial(Request $request){
        $calificacion_id = $request->input('estudiante_id');
        $parcial = $request->input('parcial');


        $calificaciones = Score::find($calificacion_id);
        if ($calificaciones == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'La calificación buscada no existe.'
                ]
            );
        }

        $newArr = array();
        if ($parcial == 'Parcial 1'){
            $newArr = [
                "nota1" => (integer)$calificaciones->primer_parcial['nota_1'],
                "nota2" => (integer)$calificaciones->primer_parcial['nota_2'],
                "nota3" => (integer)$calificaciones->primer_parcial['nota_3'],
                "nota4" => (integer)$calificaciones->primer_parcial['nota_4'],
                "nota5" => (integer)$calificaciones->primer_parcial['nota_5'],
                "nota6" => (integer)$calificaciones->primer_parcial['nota_6'],
                "total" => (integer)$calificaciones->primer_parcial['total'],
            ];
        }

        if ($parcial == 'Parcial 2'){
            $newArr = [
                "nota1" => (integer)$calificaciones->segundo_parcial['nota_1'],
                "nota2" => (integer)$calificaciones->segundo_parcial['nota_2'],
                "nota3" => (integer)$calificaciones->segundo_parcial['nota_3'],
                "nota4" => (integer)$calificaciones->segundo_parcial['nota_4'],
                "nota5" => (integer)$calificaciones->segundo_parcial['nota_5'],
                "nota6" => (integer)$calificaciones->segundo_parcial['nota_6'],
                "total" => (integer)$calificaciones->segundo_parcial['total'],
            ];
        }

        if ($parcial == 'Parcial 3'){
            $newArr = [
                "nota1" => (integer)$calificaciones->tercer_parcial['nota_1'],
                "nota2" => (integer)$calificaciones->tercer_parcial['nota_2'],
                "nota3" => (integer)$calificaciones->tercer_parcial['nota_3'],
                "nota4" => (integer)$calificaciones->tercer_parcial['nota_4'],
                "nota5" => (integer)$calificaciones->tercer_parcial['nota_5'],
                "nota6" => (integer)$calificaciones->tercer_parcial['nota_6'],
                "total" => (integer)$calificaciones->tercer_parcial['total'],
            ];
        }

        return response()->json(
            [
                'resultado' => true,
                'calificaciones' => $newArr
            ]
        );

    }

    public function updateNota(Request $request){
        $calificacion_id = $request->input('calificacion_id');
        $parcial = $request->input('parcial');
        $nota1 = $request->input('nota_1');
        $nota2 = $request->input('nota_2');
        $nota3 = $request->input('nota_3');
        $nota4 = $request->input('nota_4');
        $nota5 = $request->input('nota_5');
        $nota6 = $request->input('nota_6');
        $total = $request->input('total');

        $calificaciones = Score::find($calificacion_id);
        if ($calificaciones == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'La calificación buscada no existe.'
                ]
            );
        }
        if ($parcial == 'Parcial 1'){
            foreach ($calificaciones->primer_parcial as $cal){
                $cal->nota_1 = $nota1;
                $cal->save();
            }
//                $calificaciones->primer_parcial['nota_1'] = $nota1;
//                $calificaciones->primer_parcial['nota_2'] = $nota2;
//                $calificaciones->primer_parcial['nota_3'] = $nota3;
//                $calificaciones->primer_parcial['nota_4'] = $nota4;
//                $calificaciones->primer_parcial['nota_5'] = $nota5;
//                $calificaciones->primer_parcial['nota_6'] = $nota6;
//                $calificaciones->primer_parcial['total'] = $total;
//                $calificaciones->save();
        }
        if ($parcial == 'Parcial 2'){
            $calificaciones->segundo_parcial['nota_1'] = $nota1;
            $calificaciones->segundo_parcial['nota_2'] = $nota2;
            $calificaciones->segundo_parcial['nota_3'] = $nota3;
            $calificaciones->segundo_parcial['nota_4'] = $nota4;
            $calificaciones->segundo_parcial['nota_5'] = $nota5;
            $calificaciones->segundo_parcial['nota_6'] = $nota6;
            $calificaciones->segundo_parcial['total'] = $total;
            $calificaciones->save();
        }
        if ($parcial == 'Parcial 3'){
            $calificaciones->tercer_parcial['nota_1'] = $nota1;
            $calificaciones->tercer_parcial['nota_2'] = $nota2;
            $calificaciones->tercer_parcial['nota_3'] = $nota3;
            $calificaciones->tercer_parcial['nota_4'] = $nota4;
            $calificaciones->tercer_parcial['nota_5'] = $nota5;
            $calificaciones->tercer_parcial['nota_6'] = $nota6;
            $calificaciones->tercer_parcial['total'] = $total;
            $calificaciones->save();
        }

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Notas actualizadas.'
            ]
        );


    }
}
