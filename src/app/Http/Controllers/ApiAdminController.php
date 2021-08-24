<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Enums\AccionProcesoEnum;
use App\Models\Enums\EstadoFirmaEnum;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Ruta;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Http\Request;
use App\Models\Proceso;
use App\Models\Enums\TipoProcesoEnum;
use App\Models\Firma;
use Exception;
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
}
