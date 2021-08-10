<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Http\Controllers\Auth\LoginController;
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
        $anio_escolar = $request->input('anio_escolar');
        $activo = $request->input('activo');


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
        if (empty($activo)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'activo false'
                ]
            );
        }

        $asignaturas = [
            'nombre_asignatura' => $nombre_asignatura,
            'anio_escolar' => $anio_escolar,
            'activo' => $activo
        ];

        $cuenta = Subject:: create ($asignaturas);

        return response()->json(
            [
                'resultado' => true,
                'objeto' => 'asignatura creada con exito '
            ]
        );
    }
}
