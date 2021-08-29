<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Grade;
use App\Models\Roles;
use App\Models\Ruta;
use App\Models\Subject;
use App\Models\Usuario;
use App\Modulo;
use App\Poliza\Aseguradora;
use App\SolicitudVinculacion;
use App\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use DataTables;

class ApiSubjectsController extends Controller
{
    public function subjects (Request $request)
    {
        $nombre_asignatura = $request->input('nombre_asignatura');
        $descripcion = $request->input('descripcion');
        $anio_escolar = (int)$request->input('anio_escolar');
        $grado = $request->input('grado');

        //validaciones
        if (empty($nombre_asignatura)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'nombre de asignatura requerida.'
                ]
            );
        }
        if (empty($anio_escolar)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'año escolar requerido.'
                ]
            );
        }
        if (empty($descripcion)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'descripcion requerida'
                ]
            );
        }
        if (empty($grado)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'grado requerido'
                ]
            );
        }

        $grado_id = Grade::where('nombre_grado', $grado)
            ->orderBy("created_at", "desc")
            ->first();

        if (empty($grado_id)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'El grado no existe'
                ]
            );
        }

        $asignaturas = [
            'nombre_asignatura' => $nombre_asignatura,
            'descripcion' => $descripcion,
            'anio_escolar' => $anio_escolar,
            'grado_id' => $grado_id->_id,
            'nombre_grado' => $grado_id->nombre_grado,
            'usuario_id' => '',
            'nombre_profesor' => ''

        ];
        $materiaValidacion = Subject::where('nombre_asignatura', $nombre_asignatura)
            ->orderBy("created_at", "desc")
            ->first();

        if ($materiaValidacion != null) {
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => "La Materia ya existe."
                ]
            );
        }


        $subject = Subject::create($asignaturas);

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'asignatura creada con exito'
            ]
        );
    }
    public function getSubjects(Request $request){
        //NEW NEW NEW NEW NEW
        $defaultLengthTake = 10;
        $validateFormat = false;
        $errors = array();
        $apiKey = $request->input('api_key_admin');
        $inputDataFrom = $request->input('desde');
        $inputDateTo = $request->input('hasta');
        $dataTableFormat = $request->input('formato_datatable');
        $dataTableFormat = isset($dataTableFormat) ? (bool)$dataTableFormat : false;

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        if (!isset($inputDataFrom)) {
            $inputDataFrom = '01/12/2020';
        }

        $materia = Subject::all();
        foreach ($materia as $m){
            if ($m->grado_id != null ){
                $grado = Grade::find($m->grado_id);
                if ($grado == null){
                    $m->grado_id = '';
                    $m->nombre_grado = 'Sin Asignar';
                    $m->save();
                }
            }
        }


        $subjects = Subject::select('nombre_asignatura', 'descripcion', 'anio_escolar', 'grado_id', 'nombre_grado', 'usuario_id', 'nombre_profesor')
            ->take(3000)
            ->get();


        $objeto = Datatables::of($subjects)->addIndexColumn()
            ->toJson();
        $objeto = $dataTableFormat ? $objeto : $objeto->original['data'];







        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Consulta realizada existosamente',
                'materias' => $objeto,

            ]
        );

    }
    public function updateSubjects(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $materiaId = $request->input('materia_id');
        $nombre = $request->input('nombre_asignatura');
        $descripcion = $request->input('descripcion');
        $anio = $request->input('anio_escolar');
        $grado = $request->input('grado');
        $profesor_id = $request->input('usuario_id');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $grado = Grade::where('nombre_grado', $grado)->get()->first();

        if ($grado != null ){
            $newGradeID = $grado->_id;
            $newNombreGrado = $grado->nombre_grado;
        }else{
            $newGradeID = '';
            $newNombreGrado = '';
        }

        //profesor
        $teacher = Usuario::find($profesor_id);

        if ($teacher != null ){
            $newTeacherID = $teacher->_id;
            $newNombreProfesor = $teacher->nombres;
        }else{
            $newTeacherID = '';
            $newNombreProfesor = 'Sin Asignar';
        }

        $materia = Subject::where("_id", $materiaId)->get()->first();

        if ($materia == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "La materia no existe."
                ];
        }

        $materia->nombre_asignatura = $nombre;
        $materia->descripcion = $descripcion;
        $materia->anio_escolar = $anio;
        $materia->grado_id = $newGradeID;
        $materia->nombre_grado = $newNombreGrado;


        $materia->usuario_id = $newTeacherID;
        $materia->nombre_profesor = $newNombreProfesor;

        $materia->save();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Datos actualizados.',
            ]
        );
    }
    public function deleteSubjects(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $materiaId = $request->input('materia_id');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $materia = Subject::find($materiaId);
        if ($materiaId == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "La materia no existe."
                ];
        }

        $materia->delete();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Materia Borrada.'
            ]
        );
    }
    //test
    public function getSubjectsSeparate(Request $request){
        $apiKey = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $materias =  Subject::all();
        $getMaterias = array();
        foreach ($materias as $r){
            $gradeName = Grade::where('_id', $r['grado_id'])
                ->orderBy("created_at", "desc")
                ->first();
            $materiasArray = array(
                'nombre_asignatura' => $r['nombre_asignatura'],
                'descripcion' => $r['descripcion'],
                'anio_escolar' => $r['anio_escolar'],
                'nombre_grado' => $gradeName['nombre_grado'],
                'materia_id' => $r['_id']
            );
            $tmpArr = [
                "inscritos" => $materiasArray,
            ];
            array_push($getMaterias, $tmpArr);
        }



        return response()->json(
            [
                'resultado' => true,
                'asignaturas' => $getMaterias
            ]
        );
    }
    public function getTeachers(Request $request){
        $apiKey = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $teachers = Usuario::where('rol', 'Profesor')->get();
        return response()->json(
            [
                'resultado' => true,
                'profesores' => $teachers
            ]
        );
    }

    //get subjects para un determinado profesor
    public function getMySubjects(Request $request){
        $ProfesorId = $request->input('usuario_id');



        $materias =  Subject::where('usuario_id', $ProfesorId)->get();

        return response()->json(
            [
                'resultado' => true,
                'asignaturas' => $materias
            ]
        );
    }
}
