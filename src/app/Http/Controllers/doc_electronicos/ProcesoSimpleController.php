<?php

namespace App\Http\Controllers\doc_electronicos;

use App;
use App\Cliente;
use App\Customizacion;
use App\doc_electronicos\Auditoria;
use App\doc_electronicos\EstadoDocumento;
use App\doc_electronicos\EstadoProcesoSimpleEnum;
use App\doc_electronicos\FirmaPorValidar;
use App\doc_electronicos\Plantilla;
use App\doc_electronicos\Preferencia;
use App\doc_electronicos\ProcesoSimple;
use App\Http\Controllers\Config\ClienteController;
use App\Http\Controllers\PerfilesController;
use App\Http\Controllers\SMSController;
use App\Packages\Traits\DocumentoElectronicoTrait;
use App\Usuarios;
use Carbon\Carbon;
use Config;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use MongoDB\BSON\UTCDateTime;


class ProcesoSimpleController extends ProcesoSimplePlantillaBaseController
{
    use \App\Packages\Traits\UserUtilTrait;
    use DocumentoElectronicoTrait;

    public function __construct()
    {
    }

    public function MostrarFiltroEmisionesSimples()
    {
        $arretiquetas = array("opciones_estado");
        $opciones_estado = EstadoProcesoSimpleEnum::getOptionsEstadosProcesosSimples();
        $arrvalores = array($opciones_estado);
        return view(
            "doc_electronicos.emisiones_simples.filtro_emisiones_simples",
            array_combine($arretiquetas, $arrvalores)
        );
    }

    public function MostrarListaEmisionesSimples(Request $request)
    {
        $id_cliente_emisor = session()->get("id_cliente");
        $filtro_cliente = $request->input("filtro_cliente");
        Filtrar($filtro_cliente, "STRING", "");
        $filtro_receptor = $request->input("filtro_receptor");
        Filtrar($filtro_receptor, "STRING", "");
        $filtro_titulo = $request->input("filtro_titulo");
        Filtrar($filtro_titulo, "STRING", "");
        $filtro_desde = $request->input("filtro_desde");
        Filtrar($filtro_desde, "STRING", "");
        $filtro_hasta = $request->input("filtro_hasta");
        Filtrar($filtro_hasta, "STRING", "");
        $filtro_estado = $request->input("filtro_estado");
        Filtrar($filtro_estado, "INTEGER", -1);
        $origen_soporte = $request->input("origen_soporte");
        Filtrar($origen_soporte, "INTEGER");

        $draw = $request->input('draw');
        $skip = (integer)$request->input('start');
        $take = (integer)$request->input('length');

        switch ($request->input("order")[0]["column"]) {
            case 0:
            {
                $order_column = ($origen_soporte == 1) ? "id_cliente_emisor" : "titulo";
                break;
            }
            case 1:
            {
                $order_column = ($origen_soporte == 1) ? "titulo" : "id_estado_actual_proceso";
                break;
            }
            case 2:
            {
                $order_column = ($origen_soporte == 1) ? "id_estado_actual_proceso" : "momento_emitido";
                break;
            }
            case 3:
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

        $procesos = ProcesoSimple::select(
            "_id",
            "titulo",
            "id_estado_actual_proceso",
            "momento_emitido",
            "id_cliente_emisor",
            "firmantes",
            "estado_proceso"
        );
        if ($origen_soporte != 1) {
            $procesos = $procesos->where("id_cliente_emisor", $id_cliente_emisor);
        }
        if (!empty($filtro_cliente)) {
            $procesos = $procesos->whereHas(
                "cliente_emisor",
                function ($query) use ($filtro_cliente) {
                    $query->where("nombre_identificacion", "like", "%$filtro_cliente%");
                }
            );
        }
        if (!empty($filtro_receptor)) {
            $procesos = $procesos->where("firmantes.nombre", "like", "%$filtro_receptor%");
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
        if ($filtro_estado != -1) {
            $procesos = $procesos->where("id_estado_actual_proceso", (int)$filtro_estado);
        }
        $records_total = $procesos->count();
        $procesos = $procesos->skip($skip)->take($take)->orderBy($order_column, $order_dir)->get();

        $result = array();
        foreach ($procesos as $proceso) {
            $participantes = $this->getNombresFirmantes($proceso->firmantes);
            $result[] =
                [
                    "_id" => EncriptarId($proceso["_id"]),
                    "cliente" => $proceso["cliente_emisor"]["nombre_identificacion"],
                    "titulo" => $proceso["titulo"],
                    "id_estado_actual_proceso" => $proceso["id_estado_actual_proceso"],
                    "estado_actual_proceso" => EstadoProcesoSimpleEnum::toString($proceso["id_estado_actual_proceso"]),
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso["momento_emitido"], "d/m/Y"),
                    "fecha_emision_orden" => FormatearMongoISODate($proceso["momento_emitido"], "U"),
                    "documentos" => "",
                    "participantes" => $participantes,
                    "historial" => "",
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
    }

    public function MostrarDetallesDocumentos(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");

        $viewData = $this->GetDetallesDocumentos($id_proceso);

        return view("doc_electronicos.emisiones.documentos_originales", $viewData);
    }

    public function GetDetallesDocumentos($id_proceso)
    {
        $pc = new ProcesoController();
        $arretiquetas = array("titulo_proceso", "contenido_tabla_documentos_originales");
        $titulo_proceso = "";
        $contenido_tabla_documentos_originales = "";
        $estado_original = EstadoDocumento::where("id_estado", 0)->first(["estado"]);
        $estado_original = $estado_original ? $estado_original["estado"] : "Original";
        $proceso = ProcesoSimple::find($id_proceso);
        if ($proceso) {
            $titulo_proceso = $proceso["titulo"];
            $momento_emitido = FormatearMongoISODate($proceso["momento_emitido"]);
            foreach ($proceso->documentos as $documento) {
                $clase = $pc->getClaseLineaDocumento(0);
                $titulo_documento = $documento["titulo"];
                $adjunto = $this->getAdjunto($id_proceso, $documento["id_documento"]);
                $contenido_tabla_documentos_originales .= '<tr style="text-align:center" class="' . $clase . '"><td style="text-align:left">' . $titulo_documento . '</td>
                <td>' . $estado_original . '</td><td>' . $momento_emitido . '</td><td>' . $adjunto . '</td></tr>';
            }
        }
        $arrvalores = array($titulo_proceso, $contenido_tabla_documentos_originales);
        return array_combine($arretiquetas, $arrvalores);
    }

    public function mostrarDetallesDocumentosHistorial(Request $request)
    {
        $pc = new ProcesoController();
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $arretiquetas = array("titulo_proceso", "contenido_tabla_historial");
        $titulo_proceso = "";
        $contenido_tabla_historial = "";
        $proceso = ProcesoSimple::find($id_proceso);
        if (count($proceso->historial) == 0) {
            return $this->MostrarDetallesDocumentos($request);
        }
        if ($proceso) {
            $titulo_proceso = $proceso["titulo"];
            foreach ($proceso->historial as $hito) {
                $id_documento = $hito["id_documento"];
                $clase = $pc->getClaseLineaDocumento($hito["id_estado_actual_documento"]);
                $documento = $proceso->getDocumento($id_documento);
                $titulo_documento = $documento["titulo"];
                if ($hito["accion"] == 1) {
                    $accion = "Aceptado";
                } else {
                    if ($hito["accion"] == -1) {
                        $accion = "Rechazado";
                    } else {
                        $accion = "";
                    }
                }
                $momento_accion = FormatearMongoISODate($hito["momento_accion"]);
                $actor = $pc->getNombreActorFromHito($hito);
                $adjunto = $this->getAdjunto($id_proceso, $id_documento);
                $contenido_tabla_historial .= '<tr style="text-align:center" class="' . $clase . '"><td style="text-align:left">' . $titulo_documento . '</td>' .
                    '<td>' . $accion . '</td><td>' . $momento_accion . '</td><td>' . $actor . '</td><td>' . $adjunto . '</td></tr>';
            }
            if ($proceso->id_estado_actual_proceso == 3) {
                $contenido_tabla_historial .= '<tr><td colspan="6"><div align="center"><div class="alert alert-warning alert-dismissible" role="alert" style="text-align: left">
                <p><h4 class="alert-heading">Motivo expuesto por ' . $actor . ':</h4><p>' . $hito["motivo"] . '</p></p></div></div></td></tr>';
            }
        }
        $arrvalores = array($titulo_proceso, $contenido_tabla_historial);
        return view("doc_electronicos.emisiones.documentos_historial", array_combine($arretiquetas, $arrvalores));
    }

    public function MostrarListaParticipantes(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $campo_reenviar = $request->input("Valor_2");
        Filtrar($campo_reenviar, "BOOLEAN", false);
        $viewData = $this->GetListaParticipantes($id_proceso, $campo_reenviar);
        return view("doc_electronicos.emisiones.participantes", $viewData);
    }

    public function GetListaParticipantes($id_proceso, $campo_reenviar)
    {
        $arretiquetas = array(
            "id_proceso",
            "titulo_proceso",
            "contenido_tabla_firmantes",
            "campo_reenviar",
            "variante_aceptacion"
        );
        $titulo_proceso = "";
        $contenido_tabla_firmantes = "";
        $proceso = ProcesoSimple::find($id_proceso);
        $variante_aceptacion = "";
        if ($proceso) {
            $variante_aceptacion = $proceso["variante_aceptacion"];
            $campo_reenviar = $campo_reenviar && $variante_aceptacion == "EMAIL";

            $titulo_proceso = $proceso["titulo"];
            foreach ($proceso->firmantes as $firmante) {
                $identificacion = $firmante["identificacion"];
                $nombre = $firmante["nombre"];
                $email = $firmante["email"];
                $id_usuario = EncriptarId($firmante["id_usuario"]);
                $estado_correo = isset($firmante["estado_correo"]) ? $firmante["estado_correo"] : ProcesoController::getEstadoCorreo(
                    -1
                );
                $telefono = $firmante["telefono"];
                $contenido_tabla_firmantes .= '<tr style="text-align:center"><td>' . $identificacion . '</td><td style="text-align:left">' . $nombre . '</td><td>' . $email . '</td><td>';
                if ($variante_aceptacion == "EMAIL") {
                    $contenido_tabla_firmantes .= $estado_correo . '</td><td>';
                }
                $contenido_tabla_firmantes .= $telefono . '</td>';
                if ($campo_reenviar) {
                    if ($this->ParticipanteYaAcepto(DesencriptarId($id_usuario), $proceso)) {
                        $contenido_tabla_firmantes .= '<td>&nbsp</td>';
                    } else {
                        $contenido_tabla_firmantes .= '<td><i id="IReenviarCorreoInvitacionProcesoSimple_' . $id_usuario . '" class="fa fa-share-square-o text-navy fa-2x" title="Reenviar citación" style="cursor:pointer"></i></td>';
                    }
                }
                $contenido_tabla_firmantes .= '</tr>';
            }
        }
        $id_proceso = EncriptarId($id_proceso);
        $arrvalores = array(
            $id_proceso,
            $titulo_proceso,
            $contenido_tabla_firmantes,
            $campo_reenviar,
            $variante_aceptacion
        );
        return array_combine($arretiquetas, $arrvalores);
    }

    private function ParticipanteYaAcepto($id_usuario, $proceso, $id_documento = null)
    {
        foreach ($proceso->firmantes as $firmante) {
            if ($firmante["id_usuario"] == $id_usuario) {
                $id_cliente = $firmante["id_cliente_receptor"];
                break;
            }
        }
        if (!empty($id_cliente)) {
            foreach ($proceso->historial as $hito) {
                if (!empty($hito["id_cliente_receptor"])) {
                    if (!empty($id_documento)) {
                        if ($hito["id_cliente_receptor"] == $id_cliente && $hito["id_documento"] == $id_documento) {
                            return true;
                        }
                    } else {
                        if ($hito["id_cliente_receptor"] == $id_cliente) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function getAdjunto($id_proceso, $id_documento, $mostrar = 'icono')
    {
        $proceso = ProcesoSimple::find($id_proceso);
        $url = Config::get(
                'app.url'
            ) . '/doc_electronicos/descargar_documento_simple/' . $id_proceso . '/' . $id_documento;
        $link = '<a href="' . $url . '" target="_blank">';
        if ($mostrar == 'url') {
            $link .= $url;
        } else {
            if ($mostrar == 'titulo') {
                $link .= $proceso->getDocumento($id_documento)["titulo"];
            } else {
                $link .= '<img src="' . Config::get('app.url') . '/img/iconos/' . $this->GetIcono(
                        $proceso,
                        $id_documento
                    ) . '">';
            }
        }
        $link .= '</a>';
        return $link;
    }

    public function DescargarDocumentoSimple($id_proceso, $id_documento)
    {
        $id_proceso = DesencriptarId($id_proceso);
        $proceso = ProcesoSimple::find($id_proceso);
        $extension = $this->GetExtension($proceso, $id_documento);

        $camino = $proceso->getCaminoADocumentoOriginal($id_documento);

        $content = $proceso->getContenidoDocumento($id_documento);

        return response(
            $content,
            200,
            [
                'Content-Type' => mime_content_type($camino),
                'Content-Disposition' => 'attachment; filename="' . $this->getTituloDocumento(
                        $proceso,
                        $id_documento
                    ) . '.' . $extension . '"',
            ]
        );
    }

    public function MostrarNuevaEmisionSimple()
    {
        $id_usuario = session()->get("id_usuario");
        $id_cliente = session()->get("id_cliente");
        $cliente = getCliente();
        $opciones_plantillas = Plantilla::get_options_plantillas($id_cliente, 'simple');
        
        $de_email = Preferencia::get_default_email_data($id_cliente, "de_email");
        $de_enmascaramiento = Preferencia::get_default_email_data($id_cliente, "de_enmascaramiento");

        
        $options_orden = '<option selected="selected" value="1">Paralelo (Los participantes son invitados a aceptar simultáneamente)</option>
                          <option value="2">Secuencial (Los participantes son invitados a aceptar según el orden definido)</option>';

        $ambiente = config('app.environment');
        $nombre_empresa_sms = "";

        $options_variante_aceptacion = '<option selected="selected" value="EMAIL">Correo electrónico</option>';

        if ($cliente->hasAceptacionSimplePorSms()) {
            $options_variante_aceptacion .= '<option value="SMS">SMS</option>
                                             <option value="AMBAS">Ambas</option>';
            $nombre_empresa_sms = $cliente->getNombreParaSms();
        }


        $cuerpo_aceptacion_simple = null;
        if (isset($cliente->de_cuerpo_email_aceptacion_simple) && $cliente->de_cuerpo_email_aceptacion_simple != null) {
            $cuerpo_aceptacion_simple = $cliente->de_cuerpo_email_aceptacion_simple;
        }
        $banner_aceptacion_simple = null;
        if (isset($cliente->de_banner_email_aceptacion_simple) && $cliente->de_banner_email_aceptacion_simple != null) {
            $banner_aceptacion_simple = $cliente->de_banner_email_aceptacion_simple;
        }

        $tiene_vista_email_personalizada = $cliente->tieneVistaEmailPersonalizada();

        $arrValues = array(
            "id_usuario" => $id_usuario,
            "id_cliente" => $id_cliente,
            "opciones_plantillas" => $opciones_plantillas,
            "options_orden" => $options_orden,
            "options_variante_aceptacion" => $options_variante_aceptacion,
            "cuerpo_aceptacion_simple" => htmlspecialchars_decode($cuerpo_aceptacion_simple),
            "banner_aceptacion_simple" => $banner_aceptacion_simple,
            "tiene_vista_email_personalizada" => $tiene_vista_email_personalizada,
            "nombre_empresa_sms" => $nombre_empresa_sms,
            "de_email" => empty($de_email) ? Config::get('app.mail_from_name') : $de_email,
            "de_enmascaramiento" => empty($de_enmascaramiento) ? Config::get('app.mail_from_address') : $de_enmascaramiento
        );
        return view("doc_electronicos.emisiones_simples.emision_simple", $arrValues);
    }

    public function GuardarProcesoSimple(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        $id = null;
        try {
            $id_cliente_emisor = empty($request->input("HiddenIdCliente")) ? session()->get(
                "id_cliente"
            ) : $request->input("HiddenIdCliente");
            Filtrar($id_cliente_emisor, "STRING");

            $id_usuario_emisor = empty($request->input("HiddenIdUsuario")) ? session()->get(
                "id_usuario"
            ) : $request->input("HiddenIdUsuario");
            Filtrar($id_usuario_emisor, "STRING");

            $id_propio = 1 + $this->getMaxIdPropioProcesoSimple();
            $titulo_proceso = $request->input("TTituloProceso");
            Filtrar($titulo_proceso, "STRING", "");

            $id_estado_actual_proceso = 0;
            $momento_emitido = new UTCDateTime(DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000);
            $id_documento = 0;
            $documentos = array();

            $arr_documentos = $request->input("HiddenDocumentos");
            Filtrar($arr_documentos, "ARRAY", []);

            $firmantes = array();
            $arr_firmantes = $request->input("HiddenDestinatarios");
            Filtrar($arr_firmantes, "ARRAY", []);

            $historial = array();
            $storage = ProcesoSimple::STORAGE_LOCAL;
            $orden = (int)$request->input("SOrden");
            Filtrar($orden, "INTEGER", 1);

            $variante_aceptacion = $request->input("SViaAceptacion");
            Filtrar($variante_aceptacion, "STRING", "");

            $via = $request->input("HiddenVia");
            if (empty($via)) {
                $via = "WEB";
            }
            Filtrar($via, "STRING", "WEB");

            $ftp_filename = $request->input("ftp_filename");//Solo si viene de FTP
            Filtrar($ftp_filename, "STRING", "");

            if ($variante_aceptacion == "SMS") {
                $nombreEnmas = $request->input("TNombreEmpresa");
                Filtrar($nombreEnmas, "STRING", "");
            } elseif($variante_aceptacion == "EMAIL")  {
                $nombreEnmas = $request->input("TNombreEnmas");
                Filtrar($nombreEnmas, "STRING", "");
                $nombreEnmas = empty($nombreEnmas) ? Preferencia::get_default_email_data(
                    $id_cliente_emisor,
                    "de_email"
                ) : $nombreEnmas;
            } elseif($variante_aceptacion == "AMBAS")  {

                $nombreEnmas = $request->input("TNombreEmpresa");
                Filtrar($nombreEnmas, "STRING", "");

                if($nombreEnmas == "") {
                    $nombreEnmas = $request->input("TNombreEnmas");
                    Filtrar($nombreEnmas, "STRING", "");
                    $nombreEnmas = empty($nombreEnmas) ? Preferencia::get_default_email_data(
                        $id_cliente_emisor,
                        "de_email"
                    ) : $nombreEnmas;
                }
            } else {
                $nombreEnmas ="";
            }

            $correoEnmas = $request->input("TCorreoEnmas");
            Filtrar($correoEnmas, "EMAIL", "");
            $correoEnmas = empty($correoEnmas) ? Preferencia::get_default_email_data(
                $id_cliente_emisor,
                "de_enmascaramiento"
            ) : $correoEnmas;


            $cuerpo_email = null;
            if (isset($_POST['cuerpo_email'])) {
                $cuerpo_email = $_POST['cuerpo_email'];
            }
            if (empty($cuerpo_email)) {
                $cuerpo_parte_1 = '<p><span style="font-size: 12px; font-family: Verdana, Geneva, sans-serif;"><strong>Estimado/a,</strong></span><span style="font-size: 12px;"><span style="font-family: Verdana,Geneva,sans-serif;"></span><br/></span></p><br/><p><span style="font-size: 12px;"><span style="font-family: Verdana,Geneva,sans-serif;">La compañía "NOMBRE_COMPANIA", a través de la plataforma <strong>Stupendo</strong>, te ha invitado a leer y aceptar el contenido descrito en este mensaje y los documentos electrónicos adjuntos, correspondientes al proceso que han denominado "TITULO_PROCESO"</span></span></p> ';
                $cuerpo_parte_2 = '<p><span style="font-size: 12px;"><span style="font-family: Verdana,Geneva,sans-serif;"><strong>Para el proceso requieren la aceptación de:</strong></span></span></p>  <p><span style="font-size: 12px;"><span style="font-family: Verdana,Geneva,sans-serif;">LISTA_PARTICIPANTES</span></span></p>  <p><span style="font-size: 12px;"><span style="font-family: Verdana,Geneva,sans-serif;">Por favor, participa aceptando o rechazando este mensaje.</span></span></p>';
                $cuerpo_email = $cuerpo_parte_1 .
                    '<p style="color: #70706e; display: block; font-family: Helvetica; font-size: 14px; line-height: 30px; text-align: justify; margin: 0 0 15px;" align="justify">' .
                    '<b>Para el proceso requieren la aceptación de los siguientes documentos:</b>' .
                    '</p> LISTA_DOCUMENTOS <p><br/></p>' .
                    $cuerpo_parte_2;
            }
            $cuerpo_email = htmlspecialchars($cuerpo_email);

            if ($Res >= 0) {
                $cliente_emisor = Cliente::find($id_cliente_emisor);
                $usuario_emisor = Usuarios::find($id_usuario_emisor);
                if (!$cliente_emisor || !$usuario_emisor) {
                    $Res = -2;
                    $Mensaje = "Cliente o usuario inexistente";
                }
            }

            if ($Res >= 0) {
                if (empty($titulo_proceso) || 
                        empty($arr_documentos) || 
                        empty($arr_firmantes) || 
                        !in_array($orden, array(1, 2)) || 
                        !in_array($variante_aceptacion, array("EMAIL", "SMS", "AMBAS"))) {
                    $Res = -2;
                    $Mensaje = "Datos incompletos";
                }
            }

            if ($Res >= 0) {
                foreach ($arr_documentos as $documento) {
                    if ($Res >= 0) {
                        $documento = DesunirData($documento);
                        $id_documento++;
                        $titulo = $documento[0];
                        $camino_temporal = $documento[1];
                        $arrd = explode(".", $camino_temporal);
                        $extension = array_pop($arrd);

                        if (empty($titulo)) {
                            $Res = -4;
                            $Mensaje = "Datos (documentos) incompletos.";
                        } else {
                            if (strpos($camino_temporal, 'plantilla') !== false) {
                                $camino_original = $camino_temporal;
                            } else {
                                if (!is_file($camino_temporal)) {
                                    $Res = -2;
                                    $Mensaje = "No se pudo leer el documento.<br/>";
                                } else {
                                    $carpeta_destino = storage_path(
                                        ) . "/doc_electronicos/procesos_simples/cliente_$id_cliente_emisor/$id_propio/documentos";
                                    if (!is_dir($carpeta_destino)) {
                                        mkdir($carpeta_destino, 0777, true);
                                    }
                                    $camino_destino = "$carpeta_destino/$id_documento.$extension";
                                    @unlink($camino_destino);
                                    if (!copy($camino_temporal, $camino_destino)) {
                                        $Res = -1;
                                        $Mensaje = "Ocurrió un error moviendo el documento.";
                                    } else {
                                        $camino_original = "/doc_electronicos/procesos_simples/cliente_$id_cliente_emisor/$id_propio/documentos/$id_documento.$extension";
                                    }
                                }
                            }
                            if ($Res >= 0) {
                                array_push(
                                    $documentos,
                                    array(
                                        "id_documento" => $id_documento,
                                        "titulo" => $titulo,
                                        "camino_original" => $camino_original
                                    )
                                );
                            }
                        }
                    }
                }
            }
            if ($Res >= 0) {
                foreach ($arr_firmantes as $firmante) {
                    if ($Res >= 0) {
                        $firmante = DesunirData($firmante);
                        $identificacion = $firmante[0];
                        $nombre = $firmante[1];
                        $email = $firmante[2];
                        $telefono = $firmante[3];
                        $id_estado_correo = 0;
                        $estado_correo = ProcesoController::getEstadoCorreo($id_estado_correo);

                        if (empty($identificacion) || empty($nombre) || !EMailValido($email) || !CelularEcuadorValido(
                                $telefono
                            )) {
                            $Res = -7;
                            $Mensaje = "Datos incompletos";
                        } else {
                            if ($identificacion == $cliente_emisor->identificacion) {
                                $Res = -5;
                                $Mensaje = "No puede incluirse usted mismo dentro de la lista de participantes.";
                            } else {
                                $arr_res = ProcesoController::prepararClienteUsuarioDE(
                                    $identificacion,
                                    $nombre,
                                    $email,
                                    $telefono,
                                    "Receptor_DE",
                                    session()->get("id_cliente"),
                                    null
                                );
                                $Res = $arr_res["Res"];
                                $Mensaje = $arr_res["Mensaje"];
                                $id_cliente = $arr_res["id_cliente"];
                                $id_usuario = $arr_res["id_usuario"];
                                $nuevo_usuario = $arr_res["nuevo_usuario"];
                            }
                        }
                    }
                    if ($Res >= 0) {
                        array_push(
                            $firmantes,
                            array(
                                "id_usuario" => $id_usuario,
                                "id_cliente_receptor" => $id_cliente,
                                "identificacion" => $identificacion,
                                "nombre" => $nombre,
                                "email" => $email,
                                "telefono" => $telefono,
                                "id_estado_correo" => $id_estado_correo,
                                "estado_correo" => $estado_correo,
                                "creado_en_proceso" => $nuevo_usuario
                            )
                        );
                    }
                }
            }
            if ($Res >= 0) {
                $pc = new ProcesoController();
                $depende_de_clientes = $pc->dependeDeClientes($id_cliente_emisor);
                if (!empty($depende_de_clientes)) {
                    $arreglo_id_clientes_firmantes = array_column($firmantes, "id_cliente_receptor");
                    $coincidencias = array_intersect($depende_de_clientes, $arreglo_id_clientes_firmantes);
                    if (empty($coincidencias)) {
                        $arreglo_nombre_clientes_asociados = array();
                        foreach ($depende_de_clientes as $id_cliente_d) {
                            array_push(
                                $arreglo_nombre_clientes_asociados,
                                Cliente::find($id_cliente_d)->nombre_identificacion
                            );
                        }
                        $clientes_asociados = implode(", ", $arreglo_nombre_clientes_asociados);
                        $Res = -6;
                        $Mensaje = "Solo puedes emitir procesos con la participación de tus clientes asociados. ($clientes_asociados)";
                    }
                }
            }
            if ($Res >= 0) {
                $docs_registrar_auditoria = true;
                if (isset($cliente_emisor->parametros)) {
                    $docs_registrar_auditoria = $cliente_emisor->parametros->TieneDocsAceptacionSimpleConAuditorias();
                }

                $data_proceso_simple = array(
                    "id_propio" => $id_propio,
                    "id_cliente_emisor" => $id_cliente_emisor,
                    "id_usuario_emisor" => $id_usuario_emisor,
                    "titulo" => $titulo_proceso,
                    "id_estado_actual_proceso" => $id_estado_actual_proceso,
                    "momento_emitido" => $momento_emitido,
                    "documentos" => $documentos,
                    "firmantes" => $firmantes,
                    "historial" => $historial,
                    "storage" => $storage,
                    "orden" => $orden,
                    "variante_aceptacion" => $variante_aceptacion,
                    "via" => $via,
                    "nombre_enmas" => $nombreEnmas,
                    "correo_enmas" => $correoEnmas,
                    "cuerpo_email" => $cuerpo_email,
                    "url_banner" => $request->input("url_banner"),
                    "docs_agregar_auditoria" => $docs_registrar_auditoria
                );
                if (!empty($ftp_filename)) {
                    $data_proceso_simple['ftp_filename'] = $ftp_filename;
                }
                $proceso_simple = ProcesoSimple::create($data_proceso_simple);
            }
            if ($Res >= 0) {
                $this->EnviarEnlaceInvitacionSimple($proceso_simple);
            }
            if ($Res >= 0) {
                Auditoria::Registrar(
                    11,
                    $id_usuario_emisor,
                    $id_cliente_emisor,
                    $proceso_simple->_id,
                    isset($proceso_simple->nombre_enmas) ? $proceso_simple->nombre_enmas : null,
                    null,
                    $momento_emitido,
                    $proceso_simple->id_cliente_emisor
                );
            }
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
            \Log::error(
                "Error al guardar el proceso simple: " . $e->getMessage() . " - Stacktrace: " . $e->getTraceAsString()
            );
        }
        if ($Res >= 0) {
            $Res = $id_propio;
            $Mensaje = "El proceso simple fue guardado con éxito.<br/>";
            $id = $proceso_simple->_id;
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "id" => $id), 200);
    }

    public function reenviarEnlaceInvitacionPuntual($id_proceso, $id_usuario_receptor)
    {
        $proceso = ProcesoSimple::find($id_proceso);
        if ($proceso) {
            return $this->EnviarEnlaceInvitacionSimple($proceso, $id_usuario_receptor);
        } else {
            return response()->json(array("Res" => -1, "Mensaje" => "El proceso no existe"), 200);
        }
    }

    public function EnviarEnlaceInvitacionSimple($proceso, $id_usuario_receptor = null)
    {
        $result = array("Res" => 0, "Mensaje" => "");
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);

        if ($proceso->variante_aceptacion == "EMAIL") {
            $result = $this->EnviarEnlaceInvitacionSimpleEmail(
                $proceso,
                $cliente_emisor,
                $id_usuario_receptor,
                htmlspecialchars_decode($proceso->cuerpo_email),
                $proceso->url_banner
            );
            return $result;
        } elseif($proceso->variante_aceptacion == "SMS") {

                $result = $this->EnviarEnlaceInvitacionSimpleSMS(
                    $proceso,
                    $cliente_emisor,
                    $id_usuario_receptor
                );

        } elseif($proceso->variante_aceptacion == "AMBAS") {

            $result = $this->EnviarEnlaceInvitacionSimpleEmail(
                $proceso,
                $cliente_emisor,
                $id_usuario_receptor,
                htmlspecialchars_decode($proceso->cuerpo_email),
                $proceso->url_banner
            );

            $resultSMS = $this->EnviarEnlaceInvitacionSimpleSMS(
                $proceso,
                $cliente_emisor,
                $id_usuario_receptor
            );

            return $result;
        }

        return response()->json($result, 200);
    }

    private function EnviarEnlaceInvitacionSimpleSMS($proceso, $cliente_emisor, $id_usuario_receptor)
    {
        $result = array("Res" => 0, "Mensaje" => "");

        foreach ($proceso->firmantes as $firmante) {
            if ($this->SeDebeNotificar($firmante, $proceso, $id_usuario_receptor)) {
                $url = URL::to(
                    '/doc_electronicos/invitacion_aceptacion_simple_sms',
                    array($proceso->_id, $firmante["id_usuario"])
                );

                \Log::info('URL SMS: ' . $url);

                $shortURL = file_get_contents('http://tinyurl.com/api-create.php?url=' . $url);

                $smsCtrl = new SMSController();
                $result = $smsCtrl->Enviar_SMS_Aceptacion_Simple(
                    $firmante["telefono"],
                    $proceso,
                    $cliente_emisor,
                    $shortURL
                );
            }
        }
        return $result;
    }

    private function EnviarEnlaceInvitacionSimpleEmail(
        $proceso,
        $cliente_emisor,
        $id_usuario_receptor,
        $cuerpo,
        $banner_url
    ) {
        try {
            $Res = 0;
            $Mensaje = "";

            $titulo_proceso = $proceso->titulo;
            $compania = $cliente_emisor["nombre_identificacion"];
            $cliente_identificacion = $cliente_emisor["identificacion"];
            $pc = new PreferenciasController();
            $asunto = Preferencia::get_default_email_data($cliente_emisor->_id, "asunto_email_asimple");
            $asunto = str_replace("TITULO_PROCESO", $titulo_proceso, $asunto);
            $lista_documentos = "";
            $titulos_documentos = array();

            $index = 0;
            foreach ($proceso->documentos as $documento) {
                $index++;
                $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
                $titulos_documentos[] = $documento["titulo"];
            }

            if ($banner_url == "" || $banner_url == null) {
                $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            }

            $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);

            $de = Preferencia::get_default_email_data($proceso->id_cliente_emisor, "de_email");
            $credenciales = "";
            $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
            $lista_documentos = "";
            $index = 0;
            $arr_adjuntos = array();

            $nombreEnmas = $proceso->nombre_enmas;
            $correoEnmas = $proceso->correo_enmas;

            if ($correoEnmas != "") {
                $de = $nombreEnmas;
            }

            foreach ($proceso->documentos as $documento) {
                $index++;
                $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
                array_push($arr_adjuntos, storage_path($documento["camino_original"]));
            }
            $lista_participantes = "";
            foreach ($proceso->firmantes as $firmante) {
                $lista_participantes .= $firmante["nombre"] . "<br/>";
            }
            foreach ($proceso->firmantes as $firmante) {
                if ($this->SeDebeNotificar($firmante, $proceso, $id_usuario_receptor)) {
                    $usuario_receptor = Usuarios::find($firmante["id_usuario"]);

                    $credenciales = $this->CrearCredencialesSiEsNuevo($usuario_receptor, $proceso, $firmante);

                    $fc = new FirmasController();
                    $arr_key_vector = $fc->getArrayKeyVector();
                    $token = $fc->Encriptar(
                        $arr_key_vector["llave"],
                        $arr_key_vector["vector"],
                        $proceso->_id . "_" . $firmante["id_cliente_receptor"] . "_" . $firmante["id_usuario"]
                    );
                    $botones_accion = '<td style="text-align: right; padding: 20px"><a target="_blank" href="' . URL::to(
                            '/doc_electronicos/rechazar_email_simple/' . $token
                        ) . '  "><img alt="RECHAZAR" style="cursor:pointer" src="' . URL::to(
                            '/img/doc_electronicos/boton_rechazar.png'
                        ) . '" /></a></td>
                                   <td style="text-align: left; padding: 20px"><a target="_blank" href="' . URL::to(
                            '/doc_electronicos/aceptar_email_simple/' . $token
                        ) . '  "><img alt="ACEPTAR" style="cursor:pointer" src="' . URL::to(
                            '/img/doc_electronicos/boton_aceptar_todo.png'
                        ) . '" /></a></td>';

                    $cuerpo = $this->reemplazarTags(
                        $cuerpo,
                        $titulo_proceso,
                        $compania,
                        $lista_participantes,
                        $botones_accion,
                        $enlace,
                        $lista_documentos
                    );
                    $arretiquetas = array(
                        "banner_url",
                        "compania",
                        "lista_documentos",
                        "lista_participantes",
                        "titulo_proceso",
                        "enlace",
                        "credenciales",
                        "botones_accion",
                        "cuerpo",
                        'titulos_documentos'
                    );
                    $arrvalores = array(
                        $banner_url,
                        $compania,
                        $lista_documentos,
                        $lista_participantes,
                        $titulo_proceso,
                        $enlace,
                        $credenciales,
                        $botones_accion,
                        $cuerpo,
                        $titulos_documentos
                    );
                    $data_mailgun = array();
                    $data_mailgun["X-Mailgun-Tag"] = "monitoreo_proceso_signatarios_de";
                    $data_mailgun["X-Mailgun-Track"] = "yes";
                    $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $proceso->_id]);

                    $mail_view = "emails.doc_electronicos.$cliente_emisor->identificacion.invitacion_aceptacion_simple";
                    if (!$cliente_emisor->tieneVistaEmailPersonalizada($mail_view)) {
                        $mail_view = 'emails.doc_electronicos.invitacion_aceptacion_simple';
                    }
                    $arr_res = EnviarCorreo(
                        $mail_view,
                        $de,
                        $asunto,
                        $usuario_receptor->email,
                        $usuario_receptor->nombre,
                        $arretiquetas,
                        $arrvalores,
                        $arr_adjuntos,
                        $data_mailgun,
                        $nombreEnmas,
                        $correoEnmas
                    );
                    $Res = $arr_res[0];
                    $Mensaje = $arr_res[1];
                    if ($Res > 0) {
                        $this->actualizarMonitoreoProcesoSignatarios($proceso, $usuario_receptor->email, 1);
                        $Mensaje = "El correo de citación fue enviado correctamente a " . $usuario_receptor->email;
                    }
                }
            }
            if ($Res == 0) {
                $Mensaje = "El usuario será citado cuando corresponda su turno.";
            }
            return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
            Log::info("El error es " . $Mensaje);
            return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 500);
        }
    }

    private function CrearCredencialesSiEsNuevo($usuario, $proceso, $firmante)
    {
        $procesoCtrl = new ProcesoController();
        $credenciales = "";

        if (PerfilesController::EsUsuarioNuevo($firmante["id_usuario"])
            && $procesoCtrl->receptorCreadoEnProceso($proceso, $firmante["id_usuario"])) {
            $password_autogenerado = substr(md5($firmante["id_usuario"]), -8);
            $usuario->password = bcrypt($password_autogenerado);
            $usuario->save();
            $credenciales = "Tus credenciales de acceso, por primera vez, a Stupendo son:<br/>Usuario: <b>" . $usuario->email . "</b><br/>Contraseña: <b>$password_autogenerado</b><br/>Se te requerirá automáticamente a cambiar tus credenciales en el primer ingreso.";
        }
        return $credenciales;
    }

    public function MostrarInvitacionSms($id_proceso = null, $id_usuario = null)
    {
        \Log::info("Ingresando en MostrarInvitacionSms ( id_proceso: $id_proceso , id_usuario: $id_usuario )");
        if ($id_proceso == null || $id_usuario == null) {
            return response()->view('errors.404', [], 404);
        }

        $proceso = ProcesoSimple::find($id_proceso);
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $usuario_actual = Usuarios::find($id_usuario);

        if ($usuario_actual == null) {
            return response()->view('errors.404', [], 404);
        }

        $titulo_proceso = $proceso->titulo;

        if (isset($proceso->nombre_enmas) && !empty($proceso->nombre_enmas)) {
            $compania = $proceso->nombre_enmas;
        } else {
            $compania = $cliente_emisor["nombre_identificacion"];
        }

        if (isset($proceso->url_banner) && !empty(($proceso->url_banner))) {
            $banner_url = $proceso->url_banner;
        } else {
            $pc = new PreferenciasController();
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            if (empty($banner_url)) {
                $banner_url = '/img/email/headers/5d1bb7637e891321fc0062ab.png';
            }
        }

        $credenciales = "";
        $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
        $lista_documentos = "";
        $index = 0;
        foreach ($proceso->documentos as $documento) {
            $index++;
            $id_documento = $documento['id_documento'];
            $lista_documentos .= $index . ' - ' . $this->getAdjunto($id_proceso, $id_documento, 'titulo') . "<br/>";
        }
        $lista_participantes = "";
        $index = 0;
        $token = "";
        foreach ($proceso->firmantes as $firmante) {
            $index++;
            $lista_participantes .= $index . " - " . $firmante["nombre"] . "<br/>";
            if ($firmante["id_usuario"] == $id_usuario) {
                $fc = new FirmasController();
                $arr_key_vector = $fc->getArrayKeyVector();
                $token = $fc->Encriptar(
                    $arr_key_vector["llave"],
                    $arr_key_vector["vector"],
                    $proceso->_id . "_" . $firmante["id_cliente_receptor"] . "_" . $firmante["id_usuario"]
                );

                $credenciales = $this->CrearCredencialesSiEsNuevo($usuario_actual, $proceso, $firmante);
            }
        }

        $botones_accion = '<td style="text-align: right; padding: 20px"><a target="_blank" href="' . URL::to(
                '/doc_electronicos/rechazar_email_simple/' . $token
            ) . '  "><img alt="RECHAZAR" style="cursor:pointer" src="' . URL::to(
                '/img/doc_electronicos/boton_rechazar.png'
            ) . '" /></a></td>
                            <td style="text-align: left; padding: 20px"><a target="_blank" href="' . URL::to(
                '/doc_electronicos/aceptar_email_simple/' . $token
            ) . '  "><img alt="ACEPTAR" style="cursor:pointer" src="' . URL::to(
                '/img/doc_electronicos/boton_aceptar_todo.png'
            ) . '" /></a></td>';

        $cuerpo_final = $this->reemplazarTags(
            $proceso->cuerpo_email,
            $titulo_proceso,
            $compania,
            $lista_participantes,
            $botones_accion,
            $enlace,
            $lista_documentos
        );

        $arr_result = array(
            "banner_url" => $banner_url,
            "compania" => $compania,
            "lista_documentos" => $lista_documentos,
            "lista_participantes" => $lista_participantes,
            "credenciales" => $credenciales,
            "titulo_proceso" => $titulo_proceso,
            "enlace" => $enlace,
            "botones_accion" => $botones_accion,
            "cuerpo" => htmlspecialchars_decode($cuerpo_final)
        );

        return view("doc_electronicos.emisiones_simples.mostrar_invitacion_sms", $arr_result);
    }

    private function SeDebeNotificar($firmante, $proceso, $id_usuario_receptor)
    {
        $se_le_debe_enviar = ($id_usuario_receptor == null) || ($id_usuario_receptor == $firmante["id_usuario"] && $firmante["id_estado_correo"] != 0);
        if (($proceso->orden == 1 && $se_le_debe_enviar) ||
            ($proceso->orden == 2 && $se_le_debe_enviar && $this->esSuTurno(
                    $proceso->_id,
                    $firmante["id_cliente_receptor"]
                ))) {
            return true;
        } else {
            return false;
        }
    }

    private function getMaxIdPropioProcesoSimple()
    {
        $max_id_propio = 0;
        $ultimo_proceso = ProcesoSimple::orderBy("id_propio", "desc")->first(["id_propio"]);
        if ($ultimo_proceso) {
            $max_id_propio = (int)$ultimo_proceso->id_propio;
        }
        return $max_id_propio;
    }

    public function actualizarMonitoreoProcesoSignatarios($proceso, $recipient, $id_estado_correo, $id_proceso = null)
    {
        if (!empty($id_proceso)) {
            $proceso = ProcesoSimple::find($id_proceso);
        }
        if ($proceso) {
            $firmantes_modificado = array();
            foreach ($proceso->firmantes as $firmante) {
                if ($firmante["email"] == $recipient) {
                    $id_estado_anterior = isset($firmante["id_estado_correo"]) ? $firmante["id_estado_correo"] : -1;
                    if ($id_estado_correo != $id_estado_anterior) {
                        $firmante["id_estado_correo"] = $id_estado_correo;
                        $firmante["estado_correo"] = ProcesoController::getEstadoCorreo($id_estado_correo);
                    }
                }
                array_push($firmantes_modificado, $firmante);
            }
            $proceso->firmantes = $firmantes_modificado;
            $proceso->save();
        }
    }

    public function AceptarTodosSimple(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $actor = $request->input("HiddenActor");
            Filtrar($actor, "STRING");
            $id_proceso = DesencriptarId($request->input("HiddenIdProceso"));
            Filtrar($id_proceso, "STRING");
            $proceso = ProcesoSimple::find($id_proceso);
            if (!$proceso) {
                $Res = -1;
                $Mensaje = "Proceso inexistente";
            } else {

            $id_estado_actual_proceso = $proceso->id_estado_actual_proceso;

                if($id_estado_actual_proceso < 2)
                {
                    foreach ($proceso->documentos as $documento) {
                        if ($Res >= 0) {
                            $new_request = new Request();
                            $new_request->merge(
                                array(
                                    "HiddenActor" => $actor,
                                    "HiddenIdProceso" => EncriptarId($id_proceso),
                                    "HiddenIdDocumento" => $documento["id_documento"],
                                    "HiddenIdUsuario" => $request->input("HiddenIdUsuario"),
                                    "HiddenIdCliente" => $request->input("HiddenIdCliente")
                                )
                            );
                            $resultado = $this->AceptarDocumentoSimple($new_request);
                            $arr_res = json_decode($resultado->getContent());
                            $Res = $arr_res->Res;
                            $Mensaje = $arr_res->Mensaje . " (" . $documento["id_documento"] . ")";
                        }
                    }


                } else
                {
                    $Res = -3;
                    $Mensaje = "El proceso fue anulado por el emisor o rechazado por otro participante";
                }

            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El mismo fue aceptado completamente.";
        }

        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }




    public function AceptarDocumentoSimple(Request $request)
    {
        $actor = $request->input("HiddenActor");
        Filtrar($actor, "STRING");
        $id_proceso = DesencriptarId($request->input("HiddenIdProceso"));
        Filtrar($id_proceso, "STRING");
        $id_documento = $request->input("HiddenIdDocumento");
        Filtrar($id_documento, "INTEGER");
        $momento_accion = new UTCDateTime(DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000);
        $Res = 0;
        $Mensaje = "";

        if ($Res >= 0) {
            $proceso = ProcesoSimple::find($id_proceso);
            if (!$proceso) {
                $Res = -1;
                $Mensaje = "Proceso inexistente";
            } else {
                $id_propio = $proceso->id_propio;
                $id_cliente_emisor = $proceso->id_cliente_emisor;
            }
        }
        if ($Res >= 0) {
            if (!in_array(strtoupper($actor), array("EMISOR", "RECEPTOR", "STUPENDO"))) {
                $Res = -2;
                $Mensaje = "Actor indefinido";
            }
        }
        if ($Res >= 0) {
            $id_usuario = $request->input("HiddenIdUsuario") ?: session()->get("id_usuario");
            Filtrar($id_usuario, "STRING");
            $id_cliente_receptor = $request->input("HiddenIdCliente") ?: session()->get("id_cliente");
            Filtrar($id_cliente_receptor, "STRING");
            $id_estado_previo_documento = 1;
            $camino = "/doc_electronicos/procesos_simples/cliente_$id_cliente_emisor/$id_propio/documentos/$id_documento." . $this->GetExtension(
                    $proceso,
                    $id_documento
                );
        }
        if ($Res >= 0) {
            $accion = 1;
            $historial = $proceso->historial;
            $cantidad_firmas_receptores_reales = 0;
            foreach ($historial as $hito) {
                if ((int)$hito["id_documento"] == (int)$id_documento && !empty($hito["id_cliente_receptor"])) {
                    $cantidad_firmas_receptores_reales++;
                }
            }
            $id_estado_actual_documento = ($cantidad_firmas_receptores_reales == (count(
                        $proceso->firmantes
                    ) - 1) && strtoupper($actor) == "RECEPTOR") ? 2 : 1;
            $hito = array(
                "id_usuario" => $id_usuario,
                "id_cliente_receptor" => $id_cliente_receptor,
                "id_documento" => (int)$id_documento,
                "accion" => $accion,
                "camino" => $camino,
                "id_estado_previo_documento" => $id_estado_previo_documento,
                "id_estado_actual_documento" => $id_estado_actual_documento,
                "momento_accion" => $momento_accion
            );
            array_push($historial, $hito);
            $proceso->id_estado_actual_proceso = 1;
            $proceso->historial = $historial;
            $proceso->save();
        }
        if ($Res >= 0) {
            Auditoria::Registrar(
                12,
                $id_usuario,
                $id_cliente_receptor,
                $proceso->_id,
                isset($proceso->nombre_enmas) ? $proceso->nombre_enmas : null,
                $id_documento,
                $momento_accion,
                $proceso->id_cliente_emisor
            );
        }
        if ($Res >= 0) {
            if ($Res >= 0 && $proceso->orden == 2
                && !$this->HayAceptacionesReceptoresPendientes($id_proceso, null, $id_cliente_receptor)
                && $this->HayAceptacionesReceptoresPendientes($id_proceso)) {
                $this->EnviarEnlaceInvitacionSimple($proceso);
            }
            if ($Res >= 0 && !$this->HayAceptacionesReceptoresPendientes($id_proceso)) {
                $proceso->id_estado_actual_proceso = 2;
                $proceso->save();
                $arr_res = $this->enviarCorreoNotificaFinalizacion($id_proceso);
                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
                $proceso->InvocarWebServiceRetroalimentacion($proceso);
            }
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El documento fue aceptado.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function getIdClienteActualInvitado($id_proceso)
    {
        $proceso = ProcesoSimple::find($id_proceso);
        if ($proceso) {
            $cantidad_documentos_total = count($proceso->documentos);
            foreach ($proceso->firmantes as $firmante) {
                $id_cliente = $firmante["id_cliente_receptor"];
                $cant_aceptados = 0;
                foreach ($proceso->historial as $hito) {
                    if ($hito["accion"] == -1) {
                        return false;
                    } else {
                        if ($hito["id_cliente_receptor"] == $id_cliente && $hito["accion"] == 1) {
                            $cant_aceptados++;
                        }
                    }
                }
                if ($cant_aceptados < $cantidad_documentos_total) {
                    return $id_cliente;
                }
            }
            return false;
        }
    }

    public function MostrarFiltroProcesosSimples()
    {
        $arretiquetas = array("opciones_estado");
        $opciones_estado = EstadoProcesoSimpleEnum::getOptionsEstadosProcesosSimples();
        $arrvalores = array($opciones_estado);
        return view(
            "doc_electronicos.emisiones_simples.filtro_procesos_simples",
            array_combine($arretiquetas, $arrvalores)
        );
    }

    public function MostrarListaProcesosSimples(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $id_usuario_actual = session()->get("id_usuario");
        $filtro_id_cliente = $request->input("filtro_id_cliente");
        Filtrar($filtro_id_cliente, "STRING", "");
        $filtro_titulo = $request->input("filtro_titulo");
        Filtrar($filtro_titulo, "STRING", "");
        $filtro_desde = $request->input("filtro_desde");
        Filtrar($filtro_desde, "STRING", "");
        $filtro_hasta = $request->input("filtro_hasta");
        Filtrar($filtro_hasta, "STRING", "");
        $filtro_estado = $request->input("filtro_estado");
        Filtrar($filtro_estado, "INTEGER", -1);

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
            case 2:
            {
                $order_column = "id_estado_actual_proceso";
                break;
            }
            case 3:
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

        $procesos = ProcesoSimple::select(
            "_id",
            "titulo",
            "id_estado_actual_proceso",
            "momento_emitido",
            "id_cliente_emisor",
            "firmantes",
            "estado_proceso"
        )->with(
            [
                'cliente_emisor' => function ($query) {
                    $query->select("nombre_identificacion");
                }
            ]
        )->where("firmantes.id_cliente_receptor", $id_cliente);

        if (!empty($filtro_id_cliente)) {
            $procesos = $procesos->where("id_cliente_emisor", $filtro_id_cliente);
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
        if ($filtro_estado != -1) {
            $procesos = $procesos->where("id_estado_actual_proceso", (int)$filtro_estado);
        }

        $records_total = $procesos->count();
        $procesos = $procesos->skip($skip)->take($take)->orderBy($order_column, $order_dir)->get();

        $result = array();
        foreach ($procesos as $proceso) {
            $result[] =
                [
                    "_id" => EncriptarId($proceso["_id"]),
                    "id_cliente_emisor" => EncriptarId($proceso["id_cliente_emisor"]),
                    "emisor" => $proceso["cliente_emisor"]["nombre_identificacion"],
                    "titulo" => $proceso["titulo"],
                    "id_estado_actual_proceso" => $proceso["id_estado_actual_proceso"],
                    "estado_actual_proceso" => EstadoProcesoSimpleEnum::toString($proceso["id_estado_actual_proceso"]),
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso["momento_emitido"], "d/m/Y"),
                    "fecha_emision_orden" => FormatearMongoISODate($proceso["momento_emitido"], "U"),
                    "documentos" => "",
                    "participantes" => "",
                    "acciones" => "",
                    "puede_aceptar" => $this->esSuTurno($proceso["_id"], $id_cliente)
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

    public function esSuTurno($id_proceso, $id_cliente_receptor)
    {
        $proceso = ProcesoSimple::find($id_proceso);
        if ($proceso->orden == 1 && $this->HayAceptacionesReceptoresPendientes(
                $id_proceso,
                null,
                $id_cliente_receptor
            )) {
            return true;
        } else {
            return ($this->getIdClienteActualInvitado($id_proceso) == $id_cliente_receptor);
        }
    }

    public function MostrarAccionesSimples($id_proceso)
    {
        Filtrar($id_proceso, "STRING", null);
        $id_cliente_receptor = session()->get("id_cliente");
        $proceso = ProcesoSimple::where("_id", $id_proceso)->where(
            "firmantes.id_cliente_receptor",
            $id_cliente_receptor
        )->first();
        if (!$proceso) {
            header("Location: /doc_electronicos/aceptacion_simple");
            die();
        } else {
            $arretiquetas = array("titulo_proceso", "tabla_documentos", "logo", "cant_documentos");
            $titulo_proceso = $proceso->titulo;
            $tabla_documentos = '';
            foreach ($proceso->documentos as $documento) {
                $id_documento = $documento["id_documento"];
                $titulo_documento = $documento["titulo"];
                $accion = $this->getEstadoIdUsuario($id_proceso, $id_documento, $id_cliente_receptor);
                $extension = $this->GetExtension($proceso, $id_documento);
                $url_publico = URL::to("/doc_electronicos/mostrar_documento_en_marco/$id_proceso/$id_documento");
                $boton = '<img data-url="' . $url_publico . '" src="/img/iconos/' . $this->GetIcono(
                        $proceso,
                        $id_documento
                    ) . '" id="IMGDocumento_' . EncriptarId(
                        $id_proceso
                    ) . '||' . $id_documento . '" style="cursor:pointer" data-extension="' . $extension . '" data-accion="' . $accion . '" />';
                $tabla_documentos .= '<tr data-tr="tr"><td>' . $titulo_documento . '</td><td style="text-align:center">' . $boton . '</td></tr>';
            }
            $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
            $logo = ClienteController::getUrlLogo($cliente_emisor);
            $cant_documentos = count($proceso->documentos);
            $arrvalores = array($titulo_proceso, $tabla_documentos, $logo, $cant_documentos);
            return view(
                "doc_electronicos.emisiones_simples.acciones_simples",
                array_combine($arretiquetas, $arrvalores)
            );
        }
    }

    function getEstadoIdUsuario($id_proceso, $id_documento, $id_cliente_receptor)
    {
        $proceso = ProcesoSimple::find($id_proceso);
        $accion = 0;
        foreach ($proceso->historial as $hito) {
            if ($hito['id_documento'] == $id_documento && !empty($hito["id_cliente_receptor"]) && $hito['id_cliente_receptor'] == $id_cliente_receptor) {
                $accion = $hito["accion"];
            }
        }
        return $accion;
    }

    function MostrarDocumentoEnMarco($id_proceso, $id_documento)
    {
        $id_proceso = DesencriptarId($id_proceso);
        $proceso = ProcesoSimple::find($id_proceso);
        $camino_documento = '';
        $documento = $proceso->getDocumento($id_documento);
        $camino_documento = storage_path($documento["camino_original"]);
        $extension = $this->GetExtension($proceso, $id_documento);

        return Response::make(
            file_get_contents($camino_documento),
            200,
            [
                'Content-Type' => mime_content_type($camino_documento),
                'Content-Disposition' => 'inline; filename="' . $id_documento . '.' . $extension . '"'
            ]
        );
    }

    public function enviarCorreoNotificaFinalizacion($id_proceso)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $arretiquetas = array("banner_url", "compania", "titulo_proceso");
            $proceso = ProcesoSimple::find($id_proceso);
            $id_cliente_emisor = $proceso->id_cliente_emisor;
            $id_usuario_emisor = $proceso->id_usuario_emisor;
            $usuario_emisor = Usuarios::find($id_usuario_emisor);
            $email_emisor = $usuario_emisor->email;
            $nombre_emisor = $usuario_emisor->nombre;
            $id_propio = $proceso->id_propio;
            $titulo_proceso = $proceso->titulo;
            $cliente_emisor = Cliente::find($id_cliente_emisor);
            $compania = $cliente_emisor["nombre_identificacion"];
            $enviar_notificacion_a_emisor = Preferencia::get_default_email_data(
                $id_cliente_emisor,
                'enviar_correo_finalizacion_emisor'
            );
            $de = Preferencia::get_default_email_data($id_cliente_emisor, "de_email");
            $asunto = Preferencia::get_default_email_data($id_cliente_emisor, "asunto_email_finalizado");
            $asunto = str_replace("TITULO_PROCESO", $titulo_proceso, $asunto);

            $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);

            $arr_emails_destinatarios = array();
            $arr_nombres_destinatarios = array();
            if ($enviar_notificacion_a_emisor) {
                if ($proceso->via == 'WEB') {
                    $arr_emails_destinatarios = array($email_emisor);
                    $arr_nombres_destinatarios = array($nombre_emisor);
                } else {
                    $direcciones = Preferencia::get_direcciones_emails_finalizacion($id_cliente_emisor);
                    if (!empty($direcciones)) {
                        $emails_fin = explode(',', $direcciones);
                        foreach ($emails_fin as $email) {
                            $arr_emails_destinatarios[] = $email;
                            $arr_nombres_destinatarios[] = '';
                        }
                    } else {
                        $admins = $this->ObtenerAdministradoresDocElectronicos($id_cliente_emisor);
                        foreach ($admins as $admin) {
                            $arr_emails_destinatarios[] = $admin->email;
                            $arr_nombres_destinatarios[] = $admin->nombre;
                        }
                    }
                }
            }

            $pc = new PreferenciasController();
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            foreach ($proceso->firmantes as $firmante) {
                array_push($arr_emails_destinatarios, $firmante["email"]);
                array_push($arr_nombres_destinatarios, $firmante["nombre"]);
            }
            $arr_adjuntos = array();
            foreach ($proceso->documentos as $documento) {
                $id_documento = $documento['id_documento'];
                @array_push($arr_adjuntos, $proceso->getCaminoADocumentoActual($id_documento));
            }
            $arrvalores = array($banner_url, $compania, $titulo_proceso);

            $mail_view = "emails.doc_electronicos.$cliente_emisor->identificacion.proceso_finalizado";
            if (!$cliente_emisor->tieneVistaEmailPersonalizada($mail_view)) {
                $mail_view = 'emails.doc_electronicos.proceso_finalizado';
            }

            $nombreEnmas = (isset($proceso->nombre_enmas) && !empty($proceso->nombre_enmas)) ? $proceso->nombre_enmas : Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                "de_email"
            );
            $correoEnmas = (isset($proceso->correo_enmas) && !empty($proceso->correo_enmas)) ? $proceso->correo_enmas : Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                "de_enmascaramiento"
            );

            $arr_res = EnviarCorreo(
                $mail_view,
                $de,
                $asunto,
                $arr_emails_destinatarios,
                $arr_nombres_destinatarios,
                $arretiquetas,
                $arrvalores,
                $arr_adjuntos,
                null,
                $nombreEnmas,
                $correoEnmas
            );
            $Res = $arr_res[0];
            $Mensaje = $arr_res[1];
            if ($Res >= 0) {
                $texto = "El flujo de Documentos Electrónicos relativo al proceso denominado $titulo_proceso, emitido por la compañía $compania, a través de la plataforma Stupendo, ha finalizado completamente, con la aprobación de todos sus participantes.";
                $ruta = "/doc_electronicos/aceptacion_simple";
                $nc = new NotificacionDEController();
                foreach ($proceso->firmantes as $firmante) {
                    @$nc->CrearNotificacion(
                        $firmante["id_cliente_receptor"],
                        $firmante["id_usuario"],
                        "$titulo_proceso finalizado",
                        $texto,
                        4,
                        $ruta
                    );
                }
                $ruta = "/doc_electronicos/emisiones_simples";
                if ($proceso->via == 'WEB') {
                    $response = $nc->CrearNotificacion(
                        $id_cliente_emisor,
                        $id_usuario_emisor,
                        "$titulo_proceso finalizado",
                        $texto,
                        4,
                        $ruta
                    );
                    $arr_res = json_decode($response->getContent(), true);
                    $Res = $arr_res["Res"];
                    $Mensaje = $arr_res["Mensaje"];
                }
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return array($Res, $Mensaje);
    }

    public function enviarCorreoNotificaRechazo($id_proceso, $id_documento, $id_usuario_rechaza, $camino = null)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $arretiquetas = array("banner_url", "compania", "titulo_proceso", "titulo_documento", "rechazante");
            $proceso = ProcesoSimple::find($id_proceso);
            $id_usuario_emisor = $proceso->id_usuario_emisor;
            $usuario_emisor = Usuarios::find($id_usuario_emisor);
            $email_emisor = $usuario_emisor->email;
            $nombre_emisor = $usuario_emisor->nombre;
            $titulo_documento = "";

            $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);

            foreach ($proceso->documentos as $documento) {
                if ($documento["id_documento"] == $id_documento) {
                    $titulo_documento = $documento["titulo"];
                    break;
                }
            }


            $rechazante = null;
            $enviar_notificacion_a_emisor = Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                'enviar_correo_finalizacion_emisor'
            );
            $arr_emails_destinatarios = array();
            $arr_nombres_destinatarios = array();
            if ($enviar_notificacion_a_emisor) {
                if ($proceso->via == 'WEB') {
                    $arr_emails_destinatarios = array($email_emisor);
                    $arr_nombres_destinatarios = array($nombre_emisor);
                } else {
                    $admins = $this->ObtenerAdministradoresDocElectronicos($proceso->id_cliente_emisor);
                    foreach ($admins as $admin) {
                        $arr_emails_destinatarios[] = $admin->email;
                        $arr_nombres_destinatarios[] = $admin->nombre;
                    }
                }
            }
            foreach ($proceso->firmantes as $firmante) {
                array_push($arr_emails_destinatarios, $firmante["email"]);
                array_push($arr_nombres_destinatarios, $firmante["nombre"]);

                if($firmante["id_usuario"] == $id_usuario_rechaza)
                {
                    $rechazante = $firmante["nombre"];
                }

            }

            if($rechazante == null)
            {
                $rechazante = Usuarios::find($id_usuario_rechaza)->nombre;
            }


            $titulo_proceso = $proceso->titulo;
            $id_cliente_emisor = $proceso->id_cliente_emisor;
            $cliente_emisor = Cliente::find($id_cliente_emisor);
            $compania = $cliente_emisor["nombre_identificacion"];
            $pc = new PreferenciasController();
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            $de = Preferencia::get_default_email_data($id_cliente_emisor, "de_email");
            $asunto = Preferencia::get_default_email_data($id_cliente_emisor, "asunto_email_rechazado");
            $asunto = str_replace("TITULO_PROCESO", $titulo_proceso, $asunto);

            $arrvalores = array($banner_url, $compania, $titulo_proceso, $titulo_documento, $rechazante);
            if (!empty($camino)) {
                $adjuntos = array($camino);
            } else {
                $adjuntos = null;
            }

            $mail_view = "emails.doc_electronicos.$cliente_emisor->identificacion.proceso_rechazado";
            if (!$cliente_emisor->tieneVistaEmailPersonalizada($mail_view)) {
                $mail_view = 'emails.doc_electronicos.proceso_rechazado';
            }
            $nombreEnmas = (isset($proceso->nombre_enmas) && !empty($proceso->nombre_enmas)) ? $proceso->nombre_enmas : Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                "de_email"
            );
            $correoEnmas = (isset($proceso->correo_enmas) && !empty($proceso->correo_enmas)) ? $proceso->correo_enmas : Preferencia::get_default_email_data(
                $proceso->id_cliente_emisor,
                "de_enmascaramiento"
            );

            $arr_res = EnviarCorreo(
                $mail_view,
                $de,
                $asunto,
                $arr_emails_destinatarios,
                $arr_nombres_destinatarios,
                $arretiquetas,
                $arrvalores,
                $adjuntos,
                null,
                $nombreEnmas,
                $correoEnmas
            );
            $Res = $arr_res[0];
            $Mensaje = $arr_res[1];
            if ($Res >= 0) {
                $texto = "El proceso simple $titulo_proceso, emitido por la compañía $compania, a través de la plataforma Stupendo, ha sido rechazado por el participante $rechazante.";
                $ruta = "/doc_electronicos/aceptacion_simple";
                $nc = new NotificacionDEController();
                foreach ($proceso->firmantes as $firmante) {
                    @$nc->CrearNotificacion(
                        $firmante["id_cliente_receptor"],
                        $firmante["id_usuario"],
                        "$titulo_proceso rechazado",
                        $texto,
                        5,
                        $ruta
                    );
                }
                if ($proceso->via == 'WEB') {
                    $ruta = "/doc_electronicos/emisiones_simples";
                    $response = $nc->CrearNotificacion(
                        $id_cliente_emisor,
                        $id_usuario_emisor,
                        "$titulo_proceso rechazado",
                        $texto,
                        5,
                        $ruta
                    );
                    $arr_res = json_decode($response->getContent(), true);
                    $Res = $arr_res["Res"];
                    $Mensaje = $arr_res["Mensaje"];
                }
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return array($Res, $Mensaje);
    }

    public function HayAceptacionesReceptoresPendientes($id_proceso, $id_documento = null, $id_cliente_receptor = null)
    {
        $proceso = ProcesoSimple::find($id_proceso);
        $cantidad_documentos = count($proceso->documentos);
        $cantidad_firmantes = count($proceso->firmantes);
        $cantidad_aceptaciones_esperadas = 0;
        $cantidad_aceptaciones_reales = 0;
        if ($proceso->id_estado_actual_proceso == 2 || $proceso->id_estado_actual_proceso == 3) {
            return false;
        } else {
            if (empty($id_documento) && empty($id_cliente_receptor)) {
                $cantidad_aceptaciones_esperadas = $cantidad_documentos * $cantidad_firmantes;
                $cantidad_aceptaciones_reales = count($proceso->historial);
            } else {
                if (!empty($id_documento) && empty($id_cliente_receptor)) {
                    $cantidad_aceptaciones_esperadas = $cantidad_firmantes;
                    foreach ($proceso->historial as $hito) {
                        if ($hito["id_documento"] == $id_documento) {
                            $cantidad_aceptaciones_reales++;
                        }
                    }
                } else {
                    if (empty($id_documento) && !empty($id_cliente_receptor)) {
                        $cantidad_aceptaciones_esperadas = $cantidad_documentos;
                        foreach ($proceso->historial as $hito) {
                            if ($hito["id_cliente_receptor"] == $id_cliente_receptor) {
                                $cantidad_aceptaciones_reales++;
                            }
                        }
                    } else {
                        if (!empty($id_documento) && !empty($id_cliente_receptor)) {
                            $cantidad_aceptaciones_esperadas = 1;
                            foreach ($proceso->historial as $hito) {
                                if ($hito["id_cliente_receptor"] == $id_cliente_receptor && $hito["id_documento"] == $id_documento) {
                                    $cantidad_aceptaciones_reales++;
                                }
                            }
                        }
                    }
                }
            }
            return ($cantidad_aceptaciones_esperadas > $cantidad_aceptaciones_reales);
        }
    }

    public function RechazarDocumentoSimple(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                $id_proceso = DesencriptarId($request->input("HIdProceso"));
                Filtrar($id_proceso, "STRING");
                $id_documento = (int)$request->input("HIdDocumento");
                Filtrar($id_documento, "INTEGER");
                $motivo = $request->input("TAMotivo");
                Filtrar($motivo, "STRING", "");
                $id_usuario = $request->input("HIdUsuario") ?: session()->get("id_usuario");
                $id_cliente = $request->input("HIdCliente") ?: session()->get("id_cliente");
                $proceso = ProcesoSimple::find($id_proceso);
                if (!$proceso) {
                    $Res = -1;
                    $Mensaje = "Proceso inexistente";
                }
            }

            if ($proceso) {
                $id_estado_actual_proceso = $proceso->id_estado_actual_proceso;
                    if($id_estado_actual_proceso < 2)
                    {
                        if ($Res >= 0) {
                        $momento_accion = new UTCDateTime(
                            DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000
                        );
                        foreach ($proceso->documentos as $documento) {
                            if ($documento["id_documento"] == $id_documento) {
                                $camino = $documento["camino_original"];
                            }
                        }
                        $historial = $proceso->historial;
                        array_push(
                            $historial,
                            array(
                                "id_usuario" => $id_usuario,
                                "id_cliente_receptor" => $id_cliente,
                                "id_documento" => $id_documento,
                                "accion" => -1,
                                "motivo" => $motivo,
                                "id_estado_previo_documento" => 1,
                                "id_estado_actual_documento" => 3,
                                "momento_accion" => $momento_accion
                            )
                        );
                        $proceso->historial = $historial;
                        $proceso->id_estado_actual_proceso = 3;
                        $proceso->save();
                        $arr_res = $this->enviarCorreoNotificaRechazo(
                            $id_proceso,
                            $id_documento,
                            $id_usuario,
                            storage_path($camino)
                        );
                        $Res = $arr_res[0];
                        $Mensaje = $arr_res[1];
                        $proceso->InvocarWebServiceRetroalimentacion($proceso);
                    }
                    if ($Res >= 0) {
                        Auditoria::Registrar(
                            13,
                            $id_usuario,
                            $id_cliente,
                            $proceso->_id,
                            isset($proceso->nombre_enmas) ? $proceso->nombre_enmas : null,
                            $id_documento,
                            $momento_accion,
                            $proceso->id_cliente_emisor
                        );
                    }
                } else
                {
                    $Res = -3;
                    $Mensaje = "El proceso fue cancelado por el emisor o rechazado por otro participante";
                }
            } else
            {
                $Res = -1;
                $Mensaje = "Proceso inexistente";
            }

        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "Documento(s) rechazado(s).<br/>El flujo del proceso ha finalizado.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    private function ValidarToken($token)
    {
        $Res = 0;
        $Mensaje = "";
        $id_proceso = null;
        $id_cliente_receptor = null;
        $id_usuario_receptor = null;

        try {
            if ($Res >= 0) {
                $fc = new FirmasController();
                $arr_key_vector = $fc->getArrayKeyVector();
                $cadena = $fc->Desencriptar($arr_key_vector["llave"], $arr_key_vector["vector"], $token);
                $arr = explode("_", $cadena);
                if (count($arr) != 3) {
                    $Res = -1;
                    $Mensaje = "Token incorrecto";
                } else {
                    $id_proceso = $arr[0];
                    $id_cliente_receptor = $arr[1];
                    $id_usuario_receptor = $arr[2];
                }
            }
            if ($Res >= 0) {
                $proceso = ProcesoSimple::find($id_proceso);
                if (!$proceso) {
                    $Res = -2;
                    $Mensaje = "Proceso inexistente";
                }
                if ($proceso) {
                    $id_cliente_emisor = $proceso->id_cliente_emisor;
                }
            }
            if ($Res >= 0) {
                $cliente_receptor = Cliente::find($id_cliente_receptor);
                if (!$cliente_receptor) {
                    $Res = -3;
                    $Mensaje = "Cliente inexistente";
                }
            }
            if ($Res >= 0) {
                $usuario_receptor = Usuarios::find($id_usuario_receptor);
                if (!$usuario_receptor) {
                    $Res = -4;
                    $Mensaje = "Usuario inexistente.";
                }
            }
            if ($Res >= 0) {
                $esta_incluido = false;
                foreach ($proceso->firmantes as $firmante) {
                    if ($firmante["id_cliente_receptor"] == $id_cliente_receptor) {
                        $esta_incluido = true;
                        break;
                    }
                }
                if (!$esta_incluido) {
                    $Res = -4;
                    $Mensaje = "Proceso ajeno.";
                }
            }
            if ($Res >= 0) {
                if ($proceso->id_estado_actual_proceso == 2) {
                    $Res = -5;
                    $Mensaje = "Este proceso ya fue aceptado con anterioridad.";
                } else {
                    if ($proceso->id_estado_actual_proceso == 3) {
                        $Res = -6;
                        $Mensaje = "Este proceso ya fue rechazado con anterioridad.";
                    } else {
                        if ($this->ParticipanteYaAcepto($id_usuario_receptor, $proceso)) {
                            $Res = -7;
                            $Mensaje = "Ya su participación fue registrada en este proceso.";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -8;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Token correcto.";
        }
        return array(
            "Res" => $Res,
            "Mensaje" => $Mensaje,
            "id_proceso" => $id_proceso,
            "id_cliente_receptor" => $id_cliente_receptor,
            "id_usuario_receptor" => $id_usuario_receptor,
            "id_cliente_emisor" => $id_cliente_emisor
        );
    }




    public function RechazarEMailSimple($token)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                $arr_res = $this->ValidarToken($token);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
                $id_cliente_emisor = $arr_res["id_cliente_emisor"];
            }
            if ($Res >= 0) {
                $data = array(
                    "HIdProceso" => $arr_res["id_proceso"],
                    "HIdDocumento" => 1,
                    "TAMotivo" => "",
                    "HIdUsuario" => $arr_res["id_usuario_receptor"],
                    "HIdCliente" => $arr_res["id_cliente_receptor"]
                );
                $new_request = new Request();
                $new_request->merge($data);
                $json_resultado_rechazo = $this->RechazarDocumentoSimple($new_request);
                $respuesta = $json_resultado_rechazo->getData();
                $Mensaje = $respuesta->Mensaje;
                $Res = $respuesta->Res;
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return $this->MostrarResultadoEmail(-1, $Res, $Mensaje, $id_cliente_emisor);
    }

    public function AceptarEMailSimple($token)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                $arr_res = $this->ValidarToken($token);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
                $id_cliente_emisor = $arr_res["id_cliente_emisor"];
            }
            if ($Res >= 0) {
                $data = array(
                    "HiddenIdProceso" => $arr_res["id_proceso"],
                    "HiddenActor" => "RECEPTOR",
                    "HiddenIdUsuario" => $arr_res["id_usuario_receptor"],
                    "HiddenIdCliente" => $arr_res["id_cliente_receptor"]
                );
                $new_request = new Request();
                $new_request->merge($data);
                $json_resultado_rechazo = $this->AceptarTodosSimple($new_request);
                $respuesta = $json_resultado_rechazo->getData();
                $Mensaje = $respuesta->Mensaje;
                $Res = $respuesta->Res;

            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return $this->MostrarResultadoEmail(1, $Res, $Mensaje, $id_cliente_emisor);
    }



    public function MostrarResultadoEmail($accion, $Res, $Mensaje, $id_cliente_emisor)
    {
        if ($id_cliente_emisor) {
            $cliente = Cliente::where('_id', $id_cliente_emisor)->first();
            if ($cliente) {
                if (!isset($cliente->parametros->text_aceptacion_simple)) {
                    $text_aceptacion_simple = "Stupendo le agradece por completar el proceso.";
                } else {
                    $text_aceptacion_simple = $cliente->parametros->text_aceptacion_simple;
                }
                if (!isset($cliente->parametros->logo_aceptacion_simple)) {
                    $logo_aceptacion_simple = "stupendo.png";
                } else {
                    $logo_aceptacion_simple = $cliente->parametros->logo_aceptacion_simple;
                }
            }
        }


        if ($Res >= 0 && $accion == 1) {
            $icono = 'success.png';
        } elseif($Res == -3) {
            $icono = 'stop.png';
            $text_aceptacion_simple = "Stupendo le informa";
        } else {
            if ($Res >= 0 && $accion == -1) {
                $icono = 'stop.png';
            } else {
                $icono = 'error.png';
            }
        }


        $dominio_personalizado = "";
        $customizacion = Customizacion::where('cliente_id', '=', $id_cliente_emisor)->first();
        if (isset($customizacion) && !empty($customizacion) && isset($customizacion->dominio)) {
            $dominio_personalizado = $customizacion->dominio;
        }

        return view(
            "doc_electronicos.emisiones_simples.accion_email",
            array(
                "icono" => $icono,
                "mensaje" => $Mensaje,
                "text_aceptacion_simple" => $text_aceptacion_simple,
                "logo_aceptacion_simple" => $logo_aceptacion_simple,
                "dominio_personalizado" => $dominio_personalizado
            )
        );
    }



    public function AnexoAuditoriaDocumentoSimple($proceso_id, $documento_id)
    {
        $proceso = ProcesoSimple::find($proceso_id);
        $documento = $proceso->getDocumento($documento_id);
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $usuario_emisor = Usuarios::find($proceso->id_usuario_emisor);

        $registro_inicial = Auditoria::where(
            [
                'tipo_auditoria' => 11,
                'referencia_1' => $proceso_id
            ]
        )->first();
        $registro_inicial->momento_cad = FormatearMongoISODate($registro_inicial->momento);

        $registros = Auditoria::whereIn('tipo_auditoria', [12, 13])
            ->where(
                [
                    'referencia_1' => $proceso_id,
                    'referencia_3' => (int)$documento_id
                ]
            )->get();

        foreach ($registros as $reg) {
            foreach ($proceso->firmantes as $firmante) {
                if ($firmante["id_usuario"] == $reg->usuario->_id) {
                    $reg->firmante = $firmante;
                    $reg->momento_cad = FormatearMongoISODate($reg->momento);
                }
            }
        }

        return view(
            "doc_electronicos.emisiones_simples.anexo_auditoria_documento",
            array(
                'banner' => Config::get('app.url') . '/email/img/header2.png',
                'cliente_emisor_nombre' => $cliente_emisor->nombre_identificacion,
                'usuario_emisor_nombre' => $usuario_emisor->nombre,
                'titulo_proceso' => $proceso->titulo,
                'variante_aceptacion' => $proceso->variante_aceptacion,
                'identificacion' => $cliente_emisor->identificacion,
                'id_sistema' => $proceso->_id,
                'fecha_emision' => FormatearMongoISODate($proceso->momento_emitido),
                'registro_inicial' => $registro_inicial,
                'registros' => $registros,
                'link_documento' => $this->getAdjunto($proceso_id, $documento_id, 'url')
            )
        );
    }

}