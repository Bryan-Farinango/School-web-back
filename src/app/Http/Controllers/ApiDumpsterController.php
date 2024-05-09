<?php

namespace App\Http\Controllers;

use App\Models\Driver;

use App\Models\Dumpster;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\In;
use MongoDB\BSON\UTCDateTime;

class ApiDumpsterController extends Controller
{
    public function addDumpster(Request $request)
    {
        $name = $request->input('name');
        $lastname = $request->input('lastname');
        $bintype = $request->input('bintype');
        $binsize = $request->input('binsize');
        $bincondition = $request->input('bincondition');
        $comments = $request->input('comments');
        $ddress = $request->input('address');

        $dumpster = [
            'name' => $name,
            'lastname' => $lastname,
            'bintype' => $bintype,
            'binsize' => $binsize,
            'bincondition' => $bincondition,
            'comments' => $comments,
            'address' => $ddress,
        ];

        try {
            $dumpsterDB = Dumpster::create($dumpster);
        }catch (Exception $e){
            Log::info($e);
            return response()->json(
                [
                    'resultado' => false,
                    'mensaje' => 'No se pudo crear los datos del contenedor de basura.',
                    'error' => $e
                ]
            );
        }
        return response()->json(
            [
                'resultado' => true,
                'mensaje' => 'Información del contenedor creada correctamente.'
            ]
        );

    }
}
