<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Grade;
use App\Models\Roles;
use App\Models\Subject;
use App\Modulo;
use App\Poliza\Aseguradora;
use App\SolicitudVinculacion;
use App\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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
                    'mensaje' => 'año escolar requerido'
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
            'grado_id' => $grado_id->_id

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
}
