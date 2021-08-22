<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Driver;
use App\Models\Enums\AccionProcesoEnum;
use App\Models\Enums\EstadoFirmaEnum;
use App\Models\Grade;
use App\Models\Ruta;
use App\Models\Student;
use App\Models\Usuario;
use Illuminate\Http\Request;
use App\Models\Proceso;
use App\Models\Enums\TipoProcesoEnum;
use App\Models\Firma;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;
use DataTables;

class ApiRegisterController extends Controller
{
    public function getUserInfo(Request $request){
        $id = $request->input('usuario_id');
        $api_key_admin = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $userLogin =  Usuario::find($id)->get()->first();

        if ($userLogin == null){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'El usuario no existe.'
                ]
            );
        }

        $objeto = [
            'user_id' =>  $userLogin['_id'],
            'email' => $userLogin['email'],
            'firebase_uid' => $userLogin['firebase_uid'],
            'telefono' => $userLogin['telefono'],
            'rol' => $userLogin['rol'],
            'nombres' => $userLogin['nombres'],
            'apellidos' => $userLogin['apellidos'],

        ];

        $estadoAux = false;
        if ($userLogin != null){

            $student = Student::where('usuario_id', $userLogin['_id'])->orderBy("created_at", "desc")->get();
            if ($student != null){
                foreach ($student as $s){
                    if ($s['estado'] == 1){
                        $estadoAux = true;
                    }
                }
            }
        }

        if ($estadoAux == true){
            $objeto += [
                'matricula' => true
            ];
        }else{
            $objeto += [
                'matricula' => false
            ];
        }




        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Consulta de datos correcta',
                'objeto' => $objeto,
            ]
        );
    }
    public function loginUser(Request $request){
       $email = $request->input('email');
       $api_key_admin = $request->input('api_key_admin');

       if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

       $userLogin =  Usuario::where('email', $email)->get()->first();

       if ($userLogin == null){
           return response()->json(
               [
                   'resultado' => false,
                   'mensaje' => 'El usuario no existe.'
               ]
           );
       }

        $objeto = [
            'user_id' =>  $userLogin['_id'],
            'email' => $userLogin['email'],
            'firebase_uid' => $userLogin['firebase_uid'],
            'telefono' => $userLogin['telefono'],
            'rol' => $userLogin['rol'],
            'nombres' => $userLogin['nombres'],
            'apellidos' => $userLogin['apellidos'],

        ];

       $estadoAux = false;
        if ($userLogin != null){

            $student = Student::where('usuario_id', $userLogin->_id)->orderBy("created_at", "desc")->get();
            if ($student != null){
                foreach ($student as $s){
                    if ($s['estado'] == 1){
                        $estadoAux = true;
                    }
                }
            }
        }

        if ($estadoAux == true){
            $objeto += [
                'matricula' => true
            ];
        }else{
            $objeto += [
                'matricula' => false
            ];
        }




        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Login correcto, datos correctos',
                'objeto' => $objeto,
            ]
        );

    }
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
        $firebaseUid   = $request->input('firebase_uid');
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
            'telefono' => $telefono,
            'firebase_uid' => $firebaseUid
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
    public function getUsers(Request $request){


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

        $usuarios = Usuario::select('nombres', 'apellidos', 'email', 'telefono', 'rol')
            ->take(3000)
            ->get();

        $objeto = Datatables::of($usuarios)->addIndexColumn()
            ->toJson();
        $objeto = $dataTableFormat ? $objeto : $objeto->original['data'];
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Consulta realizada existosamente',
                'usuarios' => $objeto,
            ]
        );



    }
    public function updateUsers(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $cuentaId = $request->input('cuenta_id');
        $nombres = $request->input('nombres');
        $apellidos = $request->input('apellidos');
        $telefono = $request->input('telefono');
        $rol = $request->input('rol');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $cuenta = Usuario::where("_id", $cuentaId)->get()->first();

        if ($cuenta == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "Campo 'cuenta_id' no válido."
                ];
        }

        $cuenta->nombres = $nombres;
        $cuenta->apellidos = $apellidos;
        $cuenta->telefono = $telefono;
        $cuenta->rol = $rol;
        $cuenta->save();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Datos actualizados.',
            ]
        );
    }
    public function deleteUsers(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $cuentaId = $request->input('cuenta_id');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $cuenta = Usuario::find($cuentaId);
        if ($cuenta == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "Campo 'cuenta_id' no válido."
                ];
        }

        $cuenta->delete();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Cuenta Borrada.'
            ]
        );
    }
    //transportistas
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
        $firebaseUid = $request->input('firebase_uid');
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
            'temporal_password' => $temporal_password,
            'firebase_uid' => $firebaseUid
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
    public function getTransportistas(Request $request){
        $apiKey = $request->input('api_key_admin');

        if ( config('app.api_key_admin') != $apiKey){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para consultar los grados.'
                ]
            );
        }

        $drivers =  Driver::all();
        $getDrivers = array();
        foreach ($drivers as $r){
            $driversArray = array(
                'id' => $r['_id'],
                'nombres' => $r['nombres'],
                'apellidos' => $r['apellidos'],
                'email' => $r['email'],
                'capacidad' => $r['capacidad'],
                'telefono' => $r['telefono'],
                'experiencia_laboral' => $r['experiencia_laboral'],
            );
            array_push($getDrivers, $driversArray);
        }

        return response()->json(
            [
                'resultado' => true,
                'transportistas' => $getDrivers
            ]
        );

    }
    public function updateTransportistas(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $cuentaId = $request->input('cuenta_id');
        $nombres = $request->input('nombres');
        $apellidos = $request->input('apellidos');
        $capacidad = $request->input('capacidad');
        $telefono = $request->input('telefono');
        $experiencia = $request->input('experiencia_laboral');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $cuenta = Driver::where("_id", $cuentaId)->get()->first();

        if ($cuenta == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "El transportista no existe."
                ];
        }

        $cuenta->nombres = $nombres;
        $cuenta->apellidos = $apellidos;
        $cuenta->capacidad = $capacidad;
        $cuenta->telefono = $telefono;
        $cuenta->experiencia_laboral = $experiencia;
        $cuenta->save();

        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Datos actualizados.',
            ]
        );
    }
    public function deleteTransportista(Request $request){
        $api_key_admin = $request->input('api_key_admin');
        $cuentaId = $request->input('cuenta_id');

        if ( config('app.api_key_admin') != $api_key_admin){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No tienes permisos de administrador para crear usuarios.'
                ]
            );
        }

        $cuenta = Driver::find($cuentaId);
        if ($cuenta == null) {
            return
                [
                    'resultado' => false,
                    'mensaje' => "El transportista no existe."
                ];
        }

        $cuenta->delete();
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Transportista Eliminado.'
            ]
        );
    }
    public function getTransportistaTable(Request $request){
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

        $usuarios = Driver::select('nombres', 'apellidos', 'capacidad', 'telefono', 'experiencia_laboral')
            ->take(3000)
            ->get();

        $objeto = Datatables::of($usuarios)->addIndexColumn()
            ->toJson();
        $objeto = $dataTableFormat ? $objeto : $objeto->original['data'];
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Consulta realizada existosamente',
                'usuarios' => $objeto,
            ]
        );
    }

    //estudiantes
    public function estudiantes(Request $request){


        $nombres = $request->input('nombres');
        $apellidos = $request->input('apellidos');
        $identificacion = $request->input('identificacion');
        $edad = (int)$request->input('edad');
        $genero = $request->input('genero');
        $grado = $request->input('nombre_grado');
        $jornada = $request->input('jornada');
        $usuario_id = $request->input('usuario_id');

        //validaciones
        if (empty($identificacion)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'Identificacion requerida.'
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

        if (empty($edad)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'edad requerida.'
                ]
            );
        }

        if (empty($genero)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'genero requerido.'
                ]
            );
        }

        if (empty($grado)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'grado requerido.'
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

        if (empty($usuario_id)){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'usuario_id requerido.'
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

        $userLogged = Usuario::find($usuario_id);
        if ($userLogged == null){
            return response()-> json(
                [

                    'resultado' => false,
                    'mensaje' => 'El usuario no existe'
                ]
            );
        }

        $usuarios = [

            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'identificacion' => $identificacion,
            'edad' => $edad,
            'genero' => $genero,
            'grado_id' => $grado_id->_id,
            'nombre_grado' => $grado_id->nombre_grado,
            'jornada' => $jornada,
            'usuario_id' => $userLogged->_id,
            'estado' => 0
        ];



        $userValidation = Student::where('identificacion', $identificacion)
            ->orderBy("created_at", "desc")
            ->first();

        if ($userValidation != null) {
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => "El estudiante ya existe."
                ]
            );
        }

        try {
            $cuenta = Student::create($usuarios);
        }catch (Exception $e){
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear el estudiante.'
                ]
            );
        }


        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Inscripción correcta.'
            ]
        );
    }
}
