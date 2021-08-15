<?php

namespace App\Http\Controllers;

use App\Models\Driver;
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
        $nombres = $request->input('nombres');
        $apellidos = $request->input('apellidos');
        $email = $request->input('email');
        $rol = $request->input('rol');
        $telefono = $request->input('telefono');
        $password = $request->input('password');
        $api_key_admin = $request->input('api_key_admin');
        $createdBy = $request->input('origen');
        $temporal_password = $request->input('temporal_password');

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

        if (!empty($temporal_password)){
            $usuarios += [
                'temporal_password' => $temporal_password
            ];
        }

        if (!empty($createdBy)){
            $usuarios += [
                'origen' => $createdBy
            ];
        }

        $userValidation = Usuario::where('email', $email)
            ->orderBy("created_at", "desc")
            ->first();

        if ($userValidation != null) {
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => "El usuario ya existe."
                ]
            );
        }

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

    public function transportistaRegister(Request $request)
    {
        $nombres = $request->input('nombres');
        $apellidos = $request->input('apellidos');
        $email = $request->input('email');
        $capacidad = $request->input('capacidad');
        $rol = $request->input('rol');
        $telefono = $request->input('telefono');
        $experiencia = $request->input('experiencia_laboral');
        $password = $request->input('password');
        $api_key_admin = $request->input('api_key_admin');
        $temporal_password = $request->input('temporal_password');
        //validaciones

        if (empty($email)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'email requerido.'
                ]
            );
        }
        if (empty($capacidad)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Capacidad requerida.'
                ]
            );
        }

        if (empty($experiencia)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Experiencia requerida.'
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

        $transportista = [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'email' => $email,
            'capacidad' => $capacidad,
            'rol' => $rol,
            'telefono' => $telefono,
            'experiencia_laboral' => $experiencia,
            'password' => Hash::make($password),
            'temporal_password' => $temporal_password
        ];

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear transportistas.'
                ]
            );
        }

        $driverValidation = Driver::where('email', $email)
            ->orderBy("created_at", "desc")
            ->first();

        if ($driverValidation != null) {
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => "El transporte ya existe."
                ]
            );
        }
        try {
            $driverAccount = Driver::create($transportista);
        }catch (Exception $e){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear el transportista.'
                ]
            );
        }


        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Transportista creado correctamente.'
            ]
        );
    }

}
