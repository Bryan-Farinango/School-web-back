<?php

namespace App\Http\Controllers;

use App\Models\Enums\AccionProcesoEnum;
use App\Models\Enums\EstadoFirmaEnum;
use App\Models\Usuario;
use Illuminate\Http\Request;
use App\Models\Proceso;
use App\Models\Enums\TipoProcesoEnum;
use App\Models\Firma;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

class ApiRegisterController extends Controller
{
    public function userRegister(Request $request)
    {
        $email = $request->input('email');
        $nombres = $request->input('nombres');
        $apellidos = $request->input('apellidos');
        $rol = $request->input('rol');
        $password = $request->input('password');
        $telefono = $request->input('telefono');
        $api_key_admin = $request->input('api_key_admin');

        //validaciones

        if (empty($email)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'email requerido.'
                ]
            );
        }

        if (empty($nombres)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'nombres requeridos.'
                ]
            );
        }

        if (empty($apellidos)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'apellidos requeridos.'
                ]
            );
        }

        if (empty($rol)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'rol requerido.'
                ]
            );
        }

        if (empty($password)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'contraseña requerida.'
                ]
            );
        }

        if (empty($telefono)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'telefono requerido.'
                ]
            );
        }

        if (empty($api_key_admin)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'api_key_admin requerido.'
                ]
            );
        }

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $usuarios = [
            'email' => $email,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'rol' => $rol,
            'password' => Hash::make($password),
            'telefono' => $telefono
        ];

        try {
            $cuenta = Usuario::create($usuarios);
        }catch (Exception $e){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear el usuario.'
                ]
            );
        }


        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'usuario creado correctamente.'
            ]
        );
    }

}
