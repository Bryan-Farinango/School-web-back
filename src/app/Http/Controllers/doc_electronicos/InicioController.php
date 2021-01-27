<?php

namespace App\Http\Controllers\doc_electronicos;

use App\doc_electronicos\Proceso;
use App\doc_electronicos\ProcesoSimple;
use App\Http\Controllers\Controller;
use App\Modulo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use MongoDB\BSON\UTCDateTime;

class InicioController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarInicioComun()
    {
        $arretiquetas = array();
        $arrvalores = array();
        return view('doc_electronicos.inicio_comun', array_combine($arretiquetas, $arrvalores));
    }

    public function MostrarInicio($tipo_proceso = 'firma')
    {
        $user = Auth::user();
        $cliente = $user->getCliente();
        $nombre_perfil = Modulo::get_perfil_by_user_cliente_modulo($user, $cliente, "DocumentosElectronicos");
        $modulo = Modulo::where("id_modulo", 7)->first();
        $id_perfil = Modulo::get_id_perfil_by_name($nombre_perfil, $modulo, $cliente);
        if (in_array((int)$id_perfil, array(1, 3, 4))) {
            return $this->MostrarDashboard("EMISOR", $tipo_proceso);
        } else {
            if ((int)$id_perfil == 2) {
                return $this->MostrarDashboard("RECEPTOR", $tipo_proceso);
            } else {
                if (in_array(6, (session()->get("menus"))[7])) {
                    return $this->MostrarDashboard("RECEPTOR", $tipo_proceso);
                } else {
                    return $this->MostrarInicioComun();
                }
            }
        }
    }

    public function MostrarDashboard($actor, $tipo_proceso)
    {
        if (strtoupper($actor) == "EMISOR") {
            $id_cliente_emisor = session()->get("id_cliente");
            $participio_actor = "emitidos";
        } else {
            if (strtoupper($actor) == "RECEPTOR") {
                $id_usuario = session()->get("id_usuario");
                $participio_actor = "recibidos";
            }
        }
        $arr_nombre_meses = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
        $arretiquetas = array("participio_actor");
        $arrvalores = array($participio_actor);

        $arretiquetas = array_merge($arretiquetas, array("g1_labels", "g1_data"));

        $hasta_mes = date("m");
        $hasta_anho = date("Y");
        $desde_mes = $hasta_mes - 5;
        $desde_anho = date("Y");
        if ($desde_mes <= 0) {
            $desde_mes += 12;
            $desde_anho--;
        }
        $g1_labels = array();
        $g1_data = array();
        $mes = $desde_mes;
        $anho = $desde_anho;

        $desde = Carbon::create($desde_anho, $desde_mes, 1, 0, 0, 0);
        $hasta = Carbon::now();
        $project = [
            '_id' => 0,
            'mes' => '$_id.month',
            'anho' => '$_id.year',
            'cantidad' => '$cantidad'
        ];
        if (strtoupper($actor) == "EMISOR") {
            $match = [
                'id_cliente_emisor' => $id_cliente_emisor,
                'momento_emitido' => [
                    '$gte' => new UTCDateTime(strtotime($desde) * 1000),
                    '$lte' => new UTCDateTime(strtotime($hasta) * 1000)
                ]
            ];
        } else {
            if (strtoupper($actor) == "RECEPTOR") {
                $match = [
                    'firmantes.id_usuario' => $id_usuario,
                    'momento_emitido' => [
                        '$gte' => new UTCDateTime(strtotime($desde) * 1000),
                        '$lte' => new UTCDateTime(strtotime($hasta) * 1000)
                    ]
                ];
            }
        }
        $group = [
            '_id' => [
                'year' => ['$year' => '$momento_emitido'],
                'month' => ['$month' => '$momento_emitido']
            ],
            'cantidad' => ['$sum' => 1]
        ];
        $sort = ['mes' => -1, 'anho' => -1];
        if ($tipo_proceso == 'firma') {
            $cursor = Proceso::raw()->aggregate(
                [
                    ['$match' => $match],
                    ['$group' => $group],
                    ['$project' => $project],
                    ['$sort' => $sort]
                ]
            );
        } else {
            $cursor = ProcesoSimple::raw()->aggregate(
                [
                    ['$match' => $match],
                    ['$group' => $group],
                    ['$project' => $project],
                    ['$sort' => $sort]
                ]
            );
        }

        $data = $cursor->toArray();

        for ($index = 1; $index <= 6; $index++) {
            $cantidad = 0;
            foreach ($data as $item) {
                if ($item["mes"] == $mes && $item["anho"] == $anho) {
                    $cantidad = $item["cantidad"];
                }
            }
            array_push($g1_data, $cantidad);
            array_push($g1_labels, $arr_nombre_meses[$mes] . " " . $anho);
            $mes++;
            if ($mes > 12) {
                $mes = 1;
                $anho++;
            }
        }
        $arrvalores = array_merge($arrvalores, array($g1_labels, $g1_data));

        $arretiquetas = array_merge($arretiquetas, array("encurso", "finalizados", "rechazados"));
        if ($tipo_proceso == 'firma') {
            if (strtoupper($actor) == "EMISOR") {
                $encurso = Proceso::where("id_cliente_emisor", $id_cliente_emisor)->where(
                    "id_estado_actual_proceso",
                    1
                )->get()->count();
                $finalizados = Proceso::where("id_cliente_emisor", $id_cliente_emisor)->where(
                    "id_estado_actual_proceso",
                    2
                )->get()->count();
                $rechazados = Proceso::where("id_cliente_emisor", $id_cliente_emisor)->where(
                    "id_estado_actual_proceso",3
                )->get()->count();
            } else {
                if (strtoupper($actor) == "RECEPTOR") {
                    $encurso = Proceso::where("firmantes.id_usuario", $id_usuario)->where(
                        "id_estado_actual_proceso",
                        1
                    )->get()->count();
                    $finalizados = Proceso::where("firmantes.id_usuario", $id_usuario)->where(
                        "id_estado_actual_proceso",
                        2
                    )->get()->count();
                    $rechazados = Proceso::where("firmantes.id_usuario", $id_usuario)->where(
                        "id_estado_actual_proceso",3
                    )->get()->count();
                }
            }
        } else {
            if (strtoupper($actor) == "EMISOR") {
                $encurso = ProcesoSimple::where("id_cliente_emisor", $id_cliente_emisor)->where(
                    "id_estado_actual_proceso",
                    1
                )->get()->count();
                $finalizados = ProcesoSimple::where("id_cliente_emisor", $id_cliente_emisor)->where(
                    "id_estado_actual_proceso",
                    2
                )->get()->count();
                $rechazados = ProcesoSimple::where("id_cliente_emisor", $id_cliente_emisor)->where(
                    "id_estado_actual_proceso",
                    3
                )->get()->count();
            } else {
                if (strtoupper($actor) == "RECEPTOR") {
                    $encurso = ProcesoSimple::where("firmantes.id_usuario", $id_usuario)->where(
                        "id_estado_actual_proceso",
                        1
                    )->get()->count();
                    $finalizados = ProcesoSimple::where("firmantes.id_usuario", $id_usuario)->where(
                        "id_estado_actual_proceso",
                        2
                    )->get()->count();
                    $rechazados = ProcesoSimple::where("firmantes.id_usuario", $id_usuario)->where(
                        "id_estado_actual_proceso",
                        3
                    )->get()->count();
                }
            }
        }

        $arrvalores = array_merge($arrvalores, array($encurso, $finalizados, $rechazados));

        return view('doc_electronicos.inicio', array_combine($arretiquetas, $arrvalores));
    }

}