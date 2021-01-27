<?php

namespace App\Http\Controllers\doc_electronicos;

use App\doc_electronicos\NotificacionDE;
use App\doc_electronicos\TipoDeNotificacionDE;
use App\Http\Controllers\Controller;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

class NotificacionDEController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarFiltroNotificaciones()
    {
        try {
            $arretiquetas = array("options_tipos_notificaciones");
            $options_tipos_notificaciones = TipoDeNotificacionDE::get_options_tipos_notificacion();
            $arrvalores = array($options_tipos_notificaciones);
            return view(
                'doc_electronicos.notificaciones.filtro_notificaciones',
                array_combine($arretiquetas, $arrvalores)
            );
        } catch (Exception $e) {
        }
    }

    public function getNotificaciones(Request $request)
    {
        try {
            $id_cliente = session()->get("id_cliente");
            $id_leida = $request->input("filtro_leida");
            Filtrar($id_leida, "INTEGER", -1);
            $id_tipo = $request->input("filtro_tipo");
            Filtrar($id_tipo, "INTEGER", -1);

            $draw = $request->input('draw');
            $skip = (integer)$request->input('start');
            $take = (integer)$request->input('length');

            switch ($request->input("order")[0]["column"]) {
                case 0:
                {
                    $order_column = "fecha_envio";
                    break;
                }
                case 1:
                {
                    $order_column = "titulo";
                    break;
                }
                case 2:
                {
                    $order_column = "id_tipo";
                    break;
                }
                default:
                {
                    $order_column = "fecha_envio";
                    break;
                }
            }
            $order_dir = $request->input("order")[0]["dir"];

            $notificaciones = NotificacionDE::select("_id", "fecha_envio", "titulo", "id_tipo", "leida")->with(
                [
                    'tipo_notificacion' => function ($query) {
                        $query->select("id_tipo", "tipo");
                    }
                ]
            )->where("id_cliente", $id_cliente);
            if ($id_leida != -1) {
                $notificaciones = $notificaciones->where("leida", ($id_leida == 1) ? true : false);
            }
            if ($id_tipo != -1) {
                $notificaciones = $notificaciones->where("id_tipo", (int)$id_tipo);
            }
            $records_total = $notificaciones->count();
            $notificaciones = $notificaciones->skip($skip)->take($take)->orderBy($order_column, $order_dir)->get();

            $result = array();
            foreach ($notificaciones as $notificacion) {
                $result[] =
                    [
                        "_id" => EncriptarId($notificacion["_id"]),
                        "fecha_envio_mostrar" => FormatearMongoISODate($notificacion["fecha_envio"], "d/m/Y"),
                        "fecha_envio_orden" => FormatearMongoISODate($notificacion["fecha_envio"], "d/m/Y"),
                        "titulo" => $notificacion["titulo"],
                        "tipo" => $notificacion["tipo_notificacion"]["tipo"],
                        "leida" => $notificacion["leida"],
                        "acciones" => ""
                    ];
            }
            return response()->json(
                array(
                    "draw" => $draw,
                    "recordsTotal" => $records_total,
                    "recordsFiltered" => $records_total,
                    "data" => $result
                ),
                200
            );
        } catch (Exception $e) {
        }
    }

    public function MostrarNotificacion(Request $request)
    {
        try {
            $id_notificacion = DesencriptarId($request->input("Valor_1"));
            Filtrar($id_notificacion, "STRING", -1);
            $arretiquetas = array("titulo", "texto", "ruta");
            $notificacion = NotificacionDE::find($id_notificacion);
            $titulo = $notificacion["titulo"];
            $texto = $notificacion["texto"];
            $id_tipo = $notificacion["id_tipo"];
            switch ($id_tipo) {
                case 1:
                {
                    $ruta = '<a href="' . $notificacion["ruta"] . '">Ver invitaciones enviadas</a>';
                    break;
                }
                case 2:
                {
                    $ruta = '<a href="' . $notificacion["ruta"] . '">Ver procesos de firma</a>';
                    break;
                }
                case 3:
                {
                    $ruta = '<a href="' . $notificacion["ruta"] . '">Ver procesos de firma</a>';
                    break;
                }
                case 4:
                {
                    $ruta = '<a href="' . $notificacion["ruta"] . '">Ver procesos simples</a>';
                    break;
                }
                case 5:
                {
                    $ruta = '<a href="' . $notificacion["ruta"] . '">Ver procesos simples</a>';
                    break;
                }
                default:
                {
                    $ruta = '';
                    break;
                }
            }
            $arrvalores = array($titulo, $texto, $ruta);
            return view('doc_electronicos.notificaciones.notificacion', array_combine($arretiquetas, $arrvalores));
        } catch (Exception $e) {
        }
    }

    public function MarcarLeida(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $_id = DesencriptarId($request->input("Valor_1"));
            Filtrar($_id, "STRING");
            if ($Res >= 0) {
                $notificacion = NotificacionDE::find($_id);
                if (!$notificacion) {
                    $Res = -1;
                    $Mensaje = "Notificación inexistente";
                }
            }
            if ($Res >= 0) {
                $notificacion->leida = true;
                $notificacion->fecha_leida = new UTCDateTime(new DateTime());
                $notificacion->save();
                if (!$notificacion) {
                    $Res = -2;
                    $Mensaje = "Ocurrio un error marcando como leída la notificación.";
                } else {
                    $Res = 1;
                    $Mensaje = "La notificación fue marcada como leída.";
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function EliminarNotificacion(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $_id = DesencriptarId($request->input("Valor_1"));
            Filtrar($_id, "STRING");
            if ($Res >= 0) {
                $notificacion = NotificacionDE::find($_id);
                if (!$notificacion) {
                    $Res = -1;
                    $Mensaje = "Notificación inexistente";
                }
            }
            if ($Res >= 0) {
                if (isset($notificacion)) {
                    $notificacion->delete();
                    $Res = 1;
                } else {
                    $Res = -1;
                    $Mensaje = "No se encontró la notificación";
                }
            }
            if ($Res >= 0) {
                $Mensaje = "La notificación fue eliminada.";
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public static function get_item_notificaciones()
    {
        $notificaciones_todas = NotificacionDE::where("id_cliente", session("id_cliente"))->orderBy(
            "fecha_envio",
            "desc"
        )->get();
        $notificaciones_mostrar = NotificacionDE::where("id_cliente", session("id_cliente"))->take(10)->orderBy(
            "fecha_envio",
            "desc"
        )->get();
        $notificaciones_sin_leer = NotificacionDE::where("id_cliente", session("id_cliente"))->where(
            "leida",
            false
        )->get();
        $total_todas = count($notificaciones_todas);
        $total_sin_leer = count($notificaciones_sin_leer);
        $sin_leer = '';
        if ($total_sin_leer > 0) {
            $sin_leer = '<span class="label label-warning" id="SCantNotif">' . $total_sin_leer . '</span>';
        }
        $html = '<a class="dropdown-toggle count-info" data-toggle="dropdown" href="#"><i class="fa fa-bell"></i>' . $sin_leer . '</a><ul class="dropdown-menu dropdown-alerts" id="ULNotificaciones">';
        foreach ($notificaciones_mostrar as $notificacion) {
            $_id = EncriptarId($notificacion["_id"]);
            $titulo = '&nbsp' . substr($notificacion["titulo"], 0, 20);
            $fecha_envio = FormatearMongoISODate($notificacion["fecha_envio"], "d/m/Y");
            $leida = $notificacion["leida"];
            if (!$leida) {
                $titulo = '<b>' . $titulo . '</b>';
            }
            $data_leida = $leida ? 1 : 0;
            $html .= '<li><a style="cursor:pointer"><div data-id="' . $_id . '" data-leida="' . $data_leida . '"><i class="fa fa-arrow-circle-right"></i>' . $titulo . '<span class="pull-right text-muted small">' . $fecha_envio . '</span></div></a></li><li class="divider"></li>';
        }
        if ($total_todas == 0) {
            $html .= '<li><div class="text-center link-block" style="margin-top: 4px;"><strong>No tiene notificaciones &nbsp</strong></div></li></ul>';
        } else {
            $html .= '<li><div class="text-center link-block" style="margin-top: 6px;"><a href="/doc_electronicos/notificaciones"><strong>Ver todas las notificaciones &nbsp</strong><i class="fa fa-angle-right"></i></a></div></li></ul>';
        }
        return $html;
    }

    public function CrearNotificacion($id_cliente, $id_usuario, $titulo, $texto, $id_tipo, $ruta = null)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                if (empty($id_cliente) || empty($id_usuario) || empty($titulo) || empty($texto) || empty($id_tipo)) {
                    $Res = -1;
                    $Mensaje = "Datos incompletos";
                }
            }
            if ($Res >= 0) {
                $id_notificacion = null;
                $data_notificacion =
                    [
                        "id_cliente" => $id_cliente,
                        "id_usuario" => $id_usuario,
                        "titulo" => $titulo,
                        "texto" => $texto,
                        "id_tipo" => $id_tipo,
                        "ruta" => $ruta,
                        "fecha_envio" => new UTCDateTime(new DateTime()),
                        "leida" => false,
                        "fecha_leida" => null
                    ];
                $notificacion = NotificacionDE::create($data_notificacion);
                if (!$notificacion) {
                    $Res = -1;
                    $Mensaje = "No se pudo guardar la notificación.";
                } else {
                    $Res = 1;
                    $Mensaje = "Notificación creada con éxito.";
                    $id_notificacion = $notificacion->_id;
                }
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje, "id_notificacion" => $id_notificacion);
        return response()->json($result, 200);
    }

}