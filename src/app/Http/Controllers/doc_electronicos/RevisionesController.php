<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\Auditoria;
use App\doc_electronicos\Preferencia;
use App\doc_electronicos\Proceso;
use App\Http\Controllers\Config\ClienteController;
use App\Http\Controllers\Controller;
use App\Usuarios;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

class RevisionesController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarFiltroRevisiones()
    {
        return view("doc_electronicos.revisiones.filtro_revisiones");
    }

    public function ResultadoRevision($historico_revisiones, $id_usuario)
    {
        foreach ($historico_revisiones as $hr) {
            if ($hr["id_usuario_revisor"] == $id_usuario) {
                return (int)$hr["accion"];
            }
        }
        return 0;
    }

    public function GetRevisiones(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $id_usuario_actual = session()->get("id_usuario");
        $filtro_id_participante = $request->input("filtro_id_participante");
        Filtrar($filtro_id_participante, "STRING", "");
        $filtro_titulo = $request->input("filtro_titulo");
        Filtrar($filtro_titulo, "STRING", "");
        $filtro_desde = $request->input("filtro_desde");
        Filtrar($filtro_desde, "STRING", "");
        $filtro_hasta = $request->input("filtro_hasta");
        Filtrar($filtro_hasta, "STRING", "");
        $filtro_sentido = $request->input("filtro_sentido");
        Filtrar($filtro_sentido, "STRING", "-1");

        $draw = $request->input('draw');
        $skip = (integer)$request->input('start');
        $take = (integer)$request->input('length');
        switch ($request->input("order")[0]["column"]) {
            case 0:
            {
                $order_column = "id_cliente_emisor";
                break;
            }
            case 1:
            {
                $order_column = "titulo";
                break;
            }
            case 4:
            {
                $order_column = "momento_emitido";
                break;
            }
            default:
            {
                $order_column = "momento_emitido";
                break;
            }
        }
        $order_dir = $request->input("order")[0]["dir"];

        $procesos = Proceso::select(
            "_id",
            "titulo",
            "momento_emitido",
            "id_cliente_emisor",
            "firmantes",
            "revisiones"
        )->with(
            [
                'cliente_emisor' => function ($query) {
                    $query->select("nombre_identificacion");
                }
            ]
        );
        $procesos = $procesos->where(
            function ($query) use ($id_cliente) {
                $query->where("id_cliente_emisor", $id_cliente)->orWhere("firmantes.id_cliente_receptor", $id_cliente);
            }
        );
        $procesos = $procesos->where(
            function ($query) use ($id_usuario_actual) {
                $query->where(
                    function ($q1) use ($id_usuario_actual) {
                        $q1->where("revisiones.revisores", $id_usuario_actual);
                    }
                )
                    ->orWhere(
                        function ($q2) use ($id_usuario_actual) {
                            $q2->where("firmantes.revisiones.revisores", $id_usuario_actual)->whereNotIn(
                                "revisiones.estado_revision",
                                [0, 2]
                            );
                        }
                    );
            }
        );

        if (!empty($filtro_id_participante)) {
            $procesos = $procesos->where(
                function ($query) use ($filtro_id_participante) {
                    $query->where("firmantes.id_cliente_receptor", $filtro_id_participante)->orWhere(
                        "id_cliente_emisor",
                        $filtro_id_participante
                    );
                }
            );
        }
        if (!empty($filtro_titulo)) {
            $procesos = $procesos->where("titulo", "like", "%$filtro_titulo%");
        }
        if (!empty($filtro_desde)) {
            $procesos = $procesos->where(
                "momento_emitido",
                ">=",
                Carbon::createFromFormat("d/m/Y H:i:s", $filtro_desde . " 00:00:00")
            );
        }
        if (!empty($filtro_hasta)) {
            $procesos = $procesos->where(
                "momento_emitido",
                "<=",
                Carbon::createFromFormat("d/m/Y H:i:s", $filtro_hasta . " 23:59:59")
            );
        }
        
        if ($filtro_sentido != -1) {
            if ($filtro_sentido == "IN") {
                $signo = "<>";
            } else {
                $signo = "=";
            }
            $procesos = $procesos->where("id_cliente_emisor", $signo, $id_cliente);
        }

        $records_total = $procesos->count();
        $procesos = $procesos->skip($skip)->take($take)->orderBy($order_column, $order_dir)->get();

        $result = array();
        foreach ($procesos as $proceso) {
            $ya_reviso = false;
            $participantes = '<li style="margin-left:15px"><b>' . $proceso["cliente_emisor"]["nombre_identificacion"] . '</b></li>';
            foreach ($proceso["firmantes"] as $f) {
                $participantes .= '<li style="margin-left:15px">' . Cliente::find(
                        $f["id_cliente_receptor"]
                    )["nombre_identificacion"] . '</li>';
            }
            $revisores = '';
            if ($proceso->id_cliente_emisor == $id_cliente) {
                foreach ($proceso["revisiones"]["revisores"] as $id_usuario_revisor) {
                    $accion = '';
                    if ($this->ResultadoRevision(
                            $proceso["revisiones"]["historico_revisiones"],
                            $id_usuario_revisor
                        ) == 1) {
                        $accion = '<b style="color:#336600"> (APROBADO)</b>';
                    } else {
                        if ($this->ResultadoRevision(
                                $proceso["revisiones"]["historico_revisiones"],
                                $id_usuario_revisor
                            ) == 2) {
                            $accion = '<b style="color:#CC3333"> (DESAPROBADO)</b>';
                        }
                    }
                    $revisores .= '<li style="margin-left:15px">' . Usuarios::find(
                            $id_usuario_revisor
                        )["nombre"] . $accion . '</li>';
                }
                $ya_reviso = $this->ResultadoRevision(
                        $proceso["revisiones"]["historico_revisiones"],
                        $id_usuario_actual
                    ) != 0;
            } else {
                foreach ($proceso["firmantes"] as $f) {
                    if ($f["id_cliente_receptor"] == $id_cliente) {
                        foreach ($f["revisiones"]["revisores"] as $id_usuario_revisor) {
                            $accion = '';
                            if ($this->ResultadoRevision(
                                    $f["revisiones"]["historico_revisiones"],
                                    $id_usuario_revisor
                                ) == 1) {
                                $accion = '<b style="color:#336600"> (APROBADO)</b>';
                            } else {
                                if ($this->ResultadoRevision(
                                        $f["revisiones"]["historico_revisiones"],
                                        $id_usuario_revisor
                                    ) == 2) {
                                    $accion = '<b style="color:#CC3333"> (DESAPROBADO)</b>';
                                }
                            }
                            $revisores .= '<li style="margin-left:15px">' . Usuarios::find(
                                    $id_usuario_revisor
                                )["nombre"] . $accion . '</li>';
                        }
                        $ya_reviso = $this->ResultadoRevision(
                                $f["revisiones"]["historico_revisiones"],
                                $id_usuario_actual
                            ) != 0;
                    }
                }
            }

            $result[] =
                [
                    "_id" => EncriptarId($proceso["_id"]),
                    "sentido" => ($proceso["id_cliente_emisor"] == $id_cliente) ? "SALIENTE" : "ENTRANTE",
                    "titulo" => $proceso["titulo"],
                    "participantes" => $participantes,
                    "revisores" => $revisores,
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso["momento_emitido"], "d/m/Y"),
                    "fecha_emision_orden" => FormatearMongoISODate($proceso["momento_emitido"], "U"),
                    "acciones" => "",
                    "ya_reviso" => $ya_reviso
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
    }

    public function MostrarAccionesRevision(Request $request)
    {
        $id_proceso = $request->input("Valor_1");
        Filtrar($id_proceso, "STRING");
        $proceso = Proceso::find($id_proceso);
        $arretiquetas = array("titulo_proceso", "tabla_documentos", "logo");
        $titulo_proceso = $proceso->titulo;
        $tabla_documentos = '';
        foreach ($proceso->documentos as $documento) {
            $id_documento = $documento["id_documento"];
            $titulo_documento = $documento["titulo"];

            $boton_pdf = '<img src="/img/iconos/pdf.png" id="IMGDocumento_' . EncriptarId(
                    $id_proceso
                ) . '||' . $id_documento . '" style="cursor:pointer" />';
            $tabla_documentos .= '<tr data-tr="tr"><td>' . $titulo_documento . '</td><td style="text-align:center">' . $boton_pdf . '</td></tr>';
        }
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $logo = ClienteController::getUrlLogo($cliente_emisor);
        $arrvalores = array($titulo_proceso, $tabla_documentos, $logo);
        return view("doc_electronicos.revisiones.acciones_revision", array_combine($arretiquetas, $arrvalores));
    }

    public function DesaprobarProceso(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                $id_proceso = DesencriptarId($request->input("HIdProcesoRevision"));
                Filtrar($id_proceso, "STRING");
                $motivo = $request->input("TAMotivoRevision");
                Filtrar($motivo, "STRING", "");
                $id_usuario_rechaza = session()->get("id_usuario");
                $id_cliente_rechaza = session()->get("id_cliente");
                $proceso = Proceso::find($id_proceso);
                if (!$proceso) {
                    $Res = -1;
                    $Mensaje = "Proceso inexistente";
                }
            }
            if ($Res >= 0) {
                $momento_accion = new UTCDateTime(DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000);
                $historial = $proceso->historial;
                foreach ($proceso->documentos as $documento) {
                    array_push(
                        $historial,
                        array(
                            "id_usuario" => $id_usuario_rechaza,
                            "id_cliente_receptor" => $id_cliente_rechaza,
                            "id_documento" => $documento["id_documento"],
                            "accion" => -1,
                            "motivo" => $motivo,
                            "camino" => $documento["camino_original"],
                            "id_estado_previo_documento" => 1,
                            "id_estado_actual_documento" => 3,
                            "momento_accion" => $momento_accion
                        )
                    );
                }
                $proceso->historial = $historial;
                $proceso->id_estado_actual_proceso = 3;
                $proceso->save();
                if ($Res >= 0) {
                    Auditoria::Registrar(
                        8,
                        $id_usuario_rechaza,
                        null,
                        $id_proceso,
                        1,
                        null,
                        $momento_accion,
                        $proceso->id_cliente_emisor
                    );
                }
            }
            if ($Res >= 0) {
                if ($id_cliente_rechaza == $proceso->id_cliente_emisor) {
                    $revisiones = $proceso["revisiones"];
                    $historico_revisiones = $revisiones["historico_revisiones"];
                    $hito = array(
                        "id_usuario_revisor" => $id_usuario_rechaza,
                        "momento_accion" => $momento_accion,
                        "accion" => (int)2
                    );
                    array_push($historico_revisiones, $hito);
                    $revisiones["historico_revisiones"] = $historico_revisiones;
                    $revisiones["estado_revision"] = (int)2;
                    $proceso["revisiones"] = $revisiones;
                } else {
                    $firmantes = array();
                    foreach ($proceso->firmantes as $firmante) {
                        if ($firmante["id_cliente_receptor"] == $id_cliente_rechaza) {
                            $revisiones = $firmante["revisiones"];
                            $historico_revisiones = $revisiones["historico_revisiones"];
                            $hito = array(
                                "id_usuario_revisor" => $id_usuario_rechaza,
                                "momento_accion" => $momento_accion,
                                "accion" => (int)2
                            );
                            array_push($historico_revisiones, $hito);
                            $revisiones["historico_revisiones"] = $historico_revisiones;
                            $revisiones["estado_revision"] = (int)2;
                            $firmante["revisiones"] = $revisiones;
                        }
                        array_push($firmantes, $firmante);
                    }
                    $proceso->firmantes = $firmantes;
                }
                $proceso->save();
                $arr_res = $this->EnviarCorreoNotificaRechazoRevision(
                    $id_proceso,
                    $id_usuario_rechaza,
                    $id_cliente_rechaza
                );
                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
                if ($Res >= 0) {
                    Auditoria::Registrar(
                        10,
                        $id_usuario_rechaza,
                        null,
                        $id_proceso,
                        null,
                        null,
                        $momento_accion,
                        $proceso->id_cliente_emisor
                    );
                }
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El proceso fue desaprobado. El flujo del proceso ha finalizado.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function EnviarCorreoNotificaRechazoRevision($id_proceso, $id_usuario_rechaza, $id_cliente_rechaza)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $arretiquetas = array(
                "banner_url",
                "compania",
                "titulo_proceso",
                "nombre_usuario_rechaza",
                "nombre_cliente_rechaza"
            );
            $proceso = Proceso::find($id_proceso);
            $id_usuario_emisor = $proceso->id_usuario_emisor;
            $usuario_emisor = Usuarios::find($id_usuario_emisor);
            $email_emisor = $usuario_emisor->email;
            $nombre_emisor = $usuario_emisor->nombre;
            $nombre_usuario_rechaza = Usuarios::find($id_usuario_rechaza)->nombre;
            $nombre_cliente_rechaza = Cliente::find($id_cliente_rechaza)->nombre_identificacion;

            $arr_emails_destinatarios = array($email_emisor);
            $arr_nombres_destinatarios = array($nombre_emisor);
            foreach ($proceso->firmantes as $firmante) {
                if ($id_cliente_rechaza == $firmante["id_cliente_receptor"] && $firmante["id_estado_correo"] == 0) {
                    array_push($arr_emails_destinatarios, $firmante["email"]);
                    array_push($arr_nombres_destinatarios, $firmante["nombre"]);
                    if (isset($firmante["revisiones"])) {
                        foreach ($firmante["revisiones"]["revisores"] as $id_usuario_revisor_receptor) {
                            array_push(
                                $arr_emails_destinatarios,
                                Usuarios::find($id_usuario_revisor_receptor)["email"]
                            );
                            array_push(
                                $arr_nombres_destinatarios,
                                Usuarios::find($id_usuario_revisor_receptor)["nombre"]
                            );
                        }
                    }
                }
            }
            if (isset($proceso->revisiones) && $id_cliente_rechaza == $proceso->id_cliente_emisor) {
                foreach ($proceso["revisiones"]["revisores"] as $id_usuario_revisor_emisor) {
                    array_push($arr_emails_destinatarios, Usuarios::find($id_usuario_revisor_emisor)["email"]);
                    array_push($arr_nombres_destinatarios, Usuarios::find($id_usuario_revisor_emisor)["nombre"]);
                }
            }
            $titulo_proceso = $proceso->titulo;
            $id_cliente_emisor = $proceso->id_cliente_emisor;
            $cliente_emisor = Cliente::find($id_cliente_emisor);
            $compania = $cliente_emisor["nombre_identificacion"];

            $pc = new PreferenciasController();
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            $de = "Stupendo";
            $asunto = "Proceso rechazado durante revisión";

            $arrvalores = array(
                $banner_url,
                $compania,
                $titulo_proceso,
                $nombre_usuario_rechaza,
                $nombre_cliente_rechaza
            );
            $nombreEnmas = (isset($proceso->nombre_enmas) && !empty($proceso->nombre_enmas)) ? $proceso->nombre_enmas : Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                "de_email"
            );
            $correoEnmas = (isset($proceso->correo_enmas) && !empty($proceso->correo_enmas)) ? $proceso->correo_enmas : Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                "de_enmascaramiento"
            );

            $arr_res = EnviarCorreo(
                'emails.doc_electronicos.proceso_rechazado_revision',
                $de,
                $asunto,
                $arr_emails_destinatarios,
                $arr_nombres_destinatarios,
                $arretiquetas,
                $arrvalores,
                null,
                null,
                $nombreEnmas,
                $nombreEnmas
            );
            $Res = $arr_res[0];
            $Mensaje = $arr_res[1];
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return array($Res, $Mensaje);
    }

    private function IdentificaEstadoRevisiones($revisores, $historico_revisiones)
    {
        foreach ($historico_revisiones as $hr) {
            if ((int)$hr["accion"] == (int)2) {
                return (int)2;
            }
        }
        if (count($revisores) == count($historico_revisiones)) {
            return (int)1;
        } else {
            return (int)0;
        }
    }

    public function AprobarProceso(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        $firmantes = array();
        try {
            $pc = new ProcesoController();
            if ($Res >= 0) {
                $id_proceso = DesencriptarId($request->input("HiddenIdProcesoRevision"));
                Filtrar($id_proceso, "STRING");
                $id_usuario_aprueba = session()->get("id_usuario");
                $id_cliente_aprueba = session()->get("id_cliente");
                $momento_accion = new UTCDateTime(DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000);
                $proceso = Proceso::find($id_proceso);
                if (!$proceso) {
                    $Res = -1;
                    $Mensaje = "Proceso inexistente";
                }
            }
            if ($Res >= 0) {
                if ($id_cliente_aprueba == $proceso->id_cliente_emisor) {
                    $revisiones = $proceso["revisiones"];
                } else {
                    foreach ($proceso->firmantes as $firmante) {
                        if ($firmante["id_cliente_receptor"] == $id_cliente_aprueba) {
                            $revisiones = $firmante["revisiones"];
                        }
                    }
                }

                $historico_revisiones = $revisiones["historico_revisiones"];
                $hito = array(
                    "id_usuario_revisor" => $id_usuario_aprueba,
                    "momento_accion" => $momento_accion,
                    "accion" => (int)1
                );
                array_push($historico_revisiones, $hito);
                $revisiones["historico_revisiones"] = $historico_revisiones;
                $revisiones["estado_revision"] = $this->IdentificaEstadoRevisiones(
                    $revisiones["revisores"],
                    $historico_revisiones
                );

                if ($id_cliente_aprueba == $proceso->id_cliente_emisor) {
                    $proceso["revisiones"] = $revisiones;
                } else {
                    foreach ($proceso->firmantes as $firmante) {
                        if ($firmante["id_cliente_receptor"] == $id_cliente_aprueba) {
                            $firmante["revisiones"] = $revisiones;
                        }
                        array_push($firmantes, $firmante);
                    }
                    $proceso->firmantes = $firmantes;
                }
                $proceso->save();
                if ($revisiones["estado_revision"] == 1) {
                    if ($id_cliente_aprueba == $proceso->id_cliente_emisor) {
                        $pc->firmarSalidaYNotificar($proceso);
                    } else {
                        $pc->notificarARevisoresEntradaPendiente($proceso);
                        $pc->enviarEnlaceInvitacion($proceso);
                    }
                }
                if ($Res >= 0) {
                    Auditoria::Registrar(
                        9,
                        $id_usuario_aprueba,
                        null,
                        $id_proceso,
                        null,
                        null,
                        $momento_accion,
                        $proceso->id_cliente_emisor
                    );
                }
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
            \Log::error('Excepción encontrada al aprobar proceso: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
        }
        if ($Res >= 0) {
            $Mensaje = "Proceso aprobado.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

}