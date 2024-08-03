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
        $address = $request->input('address');
        $anonymous = $request->input('anonymous');
        $resident = $request->input('resident');
        $country = $request->input('country');
        $bintypetrash = $request->input('bintypetrash');
        $bincondition = $request->input('bincondition');
        $comments = $request->input('comments');
        $binsize = $request->input('binsize');
        $email = $request->input('email');

        if ($resident == true)
        {
            $residentLabel = 'Residente';
        }else{
            $residentLabel = 'Turista';
        }

        if ($bincondition == true){
            $binconditionLabel = 'Dañado';
        }else{
            $binconditionLabel = 'Buena condición';
        }

        $dumpster = [
            'address' => $address,
            'anonymous' => $anonymous,
            'residency_status' => $residentLabel,
            'country' => $country,
            'bintypetrash' => $bintypetrash,
            'bincondition' => $binconditionLabel,
            'comments' => $comments,
            'binsize' => $binsize,
            'email' => $email
        ];

        try {
            $dumpsterDB = Dumpster::create($dumpster);
            return response()->json(
                [
                    'resultado' => true,
                    'mensaje' => 'Información del contenedor creada correctamente.',
                    'object' => $dumpsterDB
                ]
            );
        }catch (Exception $e){
            Log::info($e);
            return response()->json(
                [
                    'result' => false,
                    'message' => 'No se pudo crear los datos del contenedor de basura.',
                    'error' => $e
                ]
            );
        }


    }
}
