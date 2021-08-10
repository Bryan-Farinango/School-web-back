<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Publicaciones;
use App\Models\Roles;
use App\Models\Subject;
use App\Modulo;
use App\Poliza\Aseguradora;
use App\SolicitudVinculacion;
use App\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ApiPublicationsController extends Controller
{
    public function publications (Request $request)
    {
        $tema = $request-> input('tema');
        $descripcion = $request->input('descripcion');
        $nombreprofesor=$request->input('nombreprofesor');


        if (empty($tema)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'tema de publicacion requerida.'
                ]
            );
        }
        if (empty($descripcion)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'descripcion requerida.'
                ]
            );
        }
        if (empty($nombreprofesor)){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'nombre del profesor requerido.'
                ]
            );
        }

        $publicaciones =[
            'tema'=> $tema,
            'descripcion'=> $descripcion,
            'nombreprofesor'=>$nombreprofesor
        ];

        $cuenta = Publicaciones:: create ($publicaciones);


        return response()-> json(
            [

                'resultado' =>true,
                'objeto' => 'publicacion creada con exito'
            ]
        );
    }
}
