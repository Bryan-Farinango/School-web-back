<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Enums\AccionProcesoEnum;
use App\Models\Enums\EstadoFirmaEnum;
use App\Models\Grade;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Http\Request;
use App\Models\Proceso;
use App\Models\Enums\TipoProcesoEnum;
use App\Models\Firma;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
                'sector_3' => $r['sector_3'],
                'transportista_id' => $r['transportista_id'],
                'nombre_transportista' => $transportistaName['nombres'],
                'apellido_transportista' => $transportistaName['apellidos'],
                'obj' => $transportistaName
            );
            array_push($getRutas, $rutasArray);
        }

        return response()->json(
            [
                'resultado' => true,
                'rutas' => $getRutas
            ]
        );

    }

}
