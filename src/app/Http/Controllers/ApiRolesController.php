<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Roles;
use App\Modulo;
use App\Poliza\Aseguradora;
use App\SolicitudVinculacion;
use App\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ApiRolesController extends Controller
{
    public function userRol (Request $request)
    {
        $descripcion = $request->input('descripcion');
        $activo = $request->input('activo');


        //validaciones

        if (empty($descripcion)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'descripcion requerida.'
            ]
            );
        }
        if (empty($activo)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'Estado necesario.'
                ]
            );
        }

        $roles = [
            'descripcion' => $descripcion,
            'activo' => $activo
        ];

        $cuenta = Roles:: create ($roles);

        return response()->json(
            [
                'resultado' => true,
                'objeto' => 'rol creado con exito.'
            ]
        );
    }
}
