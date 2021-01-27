<?php

namespace App\Http\Controllers\doc_electronicos;

use App;
use App\Cliente;
use App\ConsultaDatofast;
use App\DatoPersona;
use App\doc_electronicos\Auditoria;
use App\doc_electronicos\EstadoDocumento;
use App\doc_electronicos\EstadoProcesoEnum;
use App\doc_electronicos\Firma;
use App\doc_electronicos\Plantilla;
use App\doc_electronicos\Preferencia;
use App\doc_electronicos\Proceso;
use App\doc_electronicos\ProcesoSimple;
use App\Formulario_vinculacion;
use App\Http\Controllers\Config\ClienteController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PerfilesController;
use App\Consultas;
use App\Jobs\Retroalimentacion\Retroalimentacion_formularios;
use App\RetroalimentarUsuario;
use App\Http\Controllers\Poliza\AutoLoginController;
use App\Http\Controllers\SMSController;
use App\Modulo;
use App\Packages\Traits\DocumentoElectronicoTrait;
use App\Packages\Traits\ImageTrait;
use App\Packages\Traits\UserUtilTrait;
use App\Poliza\Broker;
use App\Poliza\NotificaciondeError;
use App\Usuarios;
use App\Constantes;
use Carbon\Carbon;
use DateTime;
use Excel;
use Exception;
use Guzzle\Http\Exception\BadResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use mikehaertl\pdftk\Pdf;
use MongoDB\BSON\UTCDateTime;
use OpenCloud\Rackspace;
use PLOP;
use Throwable;
use ZipArchive;

class ProcesoController extends Controller
{
    use DocumentoElectronicoTrait;
    use ImageTrait;
    use UserUtilTrait;

    public function __construct()
    {
    }

    public function mostrarFiltroEmisiones()
    {
        $arretiquetas = array("opciones_estado");
        $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
        $arrvalores = array($opciones_estado);
        return view("doc_electronicos.emisiones.filtro_emisiones", array_combine($arretiquetas, $arrvalores));
    }

    public function mostrarListaEmisiones(Request $request)
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
        $skip = (int)$request->input('start');
        $take = (int)$request->input('length');

        switch ($request->input("order")[0]["column"]) {
            case 0:
                $order_column = ($origen_soporte == 1) ? "id_cliente_emisor" : "titulo";
                break;
            case 1:
                $order_column = ($origen_soporte == 1) ? "titulo" : "id_estado_actual_proceso";
                break;
            case 2:
                $order_column = ($origen_soporte == 1) ? "id_estado_actual_proceso" : "momento_emitido";
                break;
            case 3:
                $order_column = "momento_emitido";
                break;
            default:
                $order_column = "momento_emitido";
                break;
        }
        $order_dir = $request->input("order")[0]["dir"];

        $procesos = Proceso::select(
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
            $firmantes = $this->getNombresFirmantes($proceso->firmantes);
            $result[] =
                [
                    "_id" => EncriptarId($proceso["_id"]),
                    "cliente" => $proceso["cliente_emisor"]["nombre_identificacion"],
                    "titulo" => $proceso["titulo"],
                    "id_estado_actual_proceso" => $proceso["id_estado_actual_proceso"],
                    "estado_actual_proceso" => EstadoProcesoEnum::toString($proceso["id_estado_actual_proceso"]),
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso["momento_emitido"], "d/m/Y"),
                    "fecha_emision_orden" => FormatearMongoISODate($proceso["momento_emitido"], "U"),
                    "documentos_originales" => "",
                    "documentos_actuales" => "",
                    "firmantes" => $firmantes,
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

    public function getNombresFirmantes($firmantes)
    {
        $result = array();
        foreach ($firmantes as $firmante) {
            $detallesCompleto = $this->detallesIndividualesFirmantes($firmante);
            $result[] = $detallesCompleto['nombre'];
        }
        return $result;
    }

    public function detallesIndividualesFirmantes($firmante)
    {
        return array(
            'identificacion' => $firmante['identificacion'],
            'nombre' => $firmante['nombre'],
            'email' => $firmante['email'],
            'telefono' => $firmante['telefono'],
            'id_usuario' => EncriptarId($firmante["id_usuario"]),
        );
    }

    public function mostrarDetallesDocumentosOriginales(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $arretiquetas = array("titulo_proceso", "contenido_tabla_documentos_originales");
        $titulo_proceso = "";
        $contenido_tabla_documentos_originales = "";
        $estado_original = EstadoDocumento::where("id_estado", 0)->first(["estado"]);
        $estado_original = $estado_original ? $estado_original["estado"] : "Original";
        $proceso = Proceso::find($id_proceso);
        if ($proceso) {
            $titulo_proceso = $proceso["titulo"];
            $momento_emitido = FormatearMongoISODate($proceso["momento_emitido"]);
            foreach ($proceso->documentos as $documento) {
                $clase = $this->getClaseLineaDocumento(0);
                $titulo_documento = $documento["titulo"];
                $adjunto = $this->getAdjuntoFromHito($id_proceso, null, $documento["id_documento"]);
                $contenido_tabla_documentos_originales .= '<tr style="text-align:center" class="' . $clase . '"><td style="text-align:left">' . $titulo_documento . '</td>
                <td>' . $estado_original . '</td><td>' . $momento_emitido . '</td><td>' . $adjunto . '</td></tr>';
            }
        }
        $arrvalores = array($titulo_proceso, $contenido_tabla_documentos_originales);
        return view("doc_electronicos.emisiones.documentos_originales", array_combine($arretiquetas, $arrvalores));
    }

    public function mostrarDetallesDocumentosActuales(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $arretiquetas = array("titulo_proceso", "contenido_tabla_documentos_actuales");
        $titulo_proceso = "";
        $alert = "";
        $contenido_tabla_documentos_actuales = "";
        $proceso = Proceso::find($id_proceso);
        if (count($proceso->historial) == 0) {
            return $this->mostrarDetallesDocumentosOriginales($request);
        }
        if ($proceso) {
            $titulo_proceso = $proceso["titulo"];
            foreach ($proceso->documentos as $documento) {
                $titulo_documento = $documento["titulo"];
                $id_documento = $documento["id_documento"];
                if (!empty($proceso->historial)) {
                    $hito = null;
                    foreach ($proceso->historial as $hito_actual) {
                        if ($hito_actual["id_documento"] == $id_documento) {
                            $hito = $hito_actual;
                        }
                    }

                    $clase = $this->getClaseLineaDocumento($hito["id_estado_actual_documento"]);
                    $estado_actual_documento = EstadoDocumento::where(
                        "id_estado",
                        $hito["id_estado_actual_documento"]
                    )->first(["estado"])["estado"];
                    $momento_ultima_accion = FormatearMongoISODate($hito["momento_accion"]);
                    //Este $actor es el que se toma en base al usuario que firmo.
                    $actor = $this->getNombreActorFromHito($hito);
                    $actorHistory =  $this->getNameFromHitoAndSignature($hito, $proceso->firmantes);
                    $adjunto = $this->getAdjuntoFromHito($id_proceso, $hito);

                    if ($hito["id_estado_actual_documento"] == 3) {
                        $alert = '<tr><td colspan="6"><div align="center"><div class="alert alert-warning alert-dismissible" role="alert" style="text-align: left">
                              <p><h4 class="alert-heading">Motivo expuesto por ' . $actor . ':</h4><p>' . $hito["motivo"] . '</p></p></div></div></td></tr>';
                    }
                }
                $contenido_tabla_documentos_actuales .= '<tr style="text-align:center" class="' . $clase . '"><td style="text-align:left">' . $titulo_documento . '</td>
                <td>' . $estado_actual_documento . '</td><td>' . $momento_ultima_accion . '</td><td>' . $actorHistory . '</td><td>' . $adjunto . '</td></tr>';
            }
            if ($proceso->id_estado_actual_proceso == 3) {
                $contenido_tabla_documentos_actuales .= $alert;
            }
        }
        $arrvalores = array($titulo_proceso, $contenido_tabla_documentos_actuales);
        return view("doc_electronicos.emisiones.documentos_actuales", array_combine($arretiquetas, $arrvalores));
    }

    public function mostrarDetallesDocumentosHistorial(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $arretiquetas = array("titulo_proceso", "contenido_tabla_historial");
        $titulo_proceso = "";
        $contenido_tabla_historial = "";
        $proceso = Proceso::find($id_proceso);
        if (count($proceso->historial) == 0) {
            return $this->mostrarDetallesDocumentosOriginales($request);
        }
        if ($proceso) {
            $titulo_proceso = $proceso["titulo"];
            foreach ($proceso->historial as $hito) {
                $id_documento = $hito["id_documento"];
                $clase = $this->getClaseLineaDocumento($hito["id_estado_actual_documento"]);
                foreach ($proceso->documentos as $documento) {
                    if ($documento["id_documento"] == $id_documento) {
                        break;
                    }
                }
                $titulo_documento = $documento["titulo"];
                if ($hito["accion"] == 1) {
                    $accion = "Firmado";
                } else {
                    if ($hito["accion"] == -1) {
                        $accion = "Rechazado";
                    } else {
                        $accion = "";
                    }
                }
                $momento_accion = FormatearMongoISODate($hito["momento_accion"]);
                //Este $actor es el que se toma en base al usuario que firmo.
                $actor = $this->getNombreActorFromHito($hito);
                $actorHistory =  $this->getNameFromHitoAndSignature($hito, $proceso->firmantes);
                $adjunto = $this->getAdjuntoFromHito($id_proceso, $hito);
                $contenido_tabla_historial .= '<tr style="text-align:center" class="' . $clase . '"><td style="text-align:left">' . $titulo_documento . '</td>' .
                    '<td>' . $accion . '</td><td>' . $momento_accion . '</td><td>' . $actorHistory . '</td><td>' . $adjunto . '</td></tr>';
            }
        }
        $arrvalores = array($titulo_proceso, $contenido_tabla_historial);
        return view("doc_electronicos.emisiones.documentos_historial", array_combine($arretiquetas, $arrvalores));
    }

    public function mostrarListaFirmantes(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $campo_reenviar = $request->input("Valor_2");
        Filtrar($campo_reenviar, "BOOLEAN", false);
        $arretiquetas = array("id_proceso", "titulo_proceso", "contenido_tabla_firmantes", "campo_reenviar");
        $titulo_proceso = "";
        $contenido_tabla_firmantes = "";
        $proceso = Proceso::find($id_proceso);
        if ($proceso) {
            $titulo_proceso = $proceso["titulo"];
            foreach ($proceso->firmantes as $firmante) {
                $identificacion = $firmante["identificacion"];
                $nombre = $firmante["nombre"];
                $email = $firmante["email"];
                $id_usuario = EncriptarId($firmante["id_usuario"]);
                $estado_correo = isset($firmante["estado_correo"]) ? $firmante["estado_correo"] : self::getEstadoCorreo(
                    -1
                );
                $telefono = $firmante["telefono"];
                $contenido_tabla_firmantes .= '<tr style="text-align:center"><td>' . $identificacion . '</td><td style="text-align:left">' . $nombre . '</td><td>' . $email . '</td><td>' . $estado_correo . '</td><td>' . $telefono . '</td>';
                if ($campo_reenviar) {
                    if ($this->signatarioYaFirmo(DesencriptarId($id_usuario), $proceso)) {
                        $contenido_tabla_firmantes .= '<td>&nbsp</td>';
                    } else {
                        $contenido_tabla_firmantes .= '<td><i id="IReenviarCorreoInvitacionProceso_' . $id_usuario . '" class="fa fa-share-square-o text-navy fa-2x" title="Reenviar citación" style="cursor:pointer"></i></td>';
                    }
                }
                $contenido_tabla_firmantes .= '</tr>';
            }
        }
        $id_proceso = EncriptarId($id_proceso);
        $arrvalores = array($id_proceso, $titulo_proceso, $contenido_tabla_firmantes, $campo_reenviar);
        return view("doc_electronicos.emisiones.firmantes", array_combine($arretiquetas, $arrvalores));
    }

    private function signatarioYaFirmo($id_usuario, $proceso, $id_documento = null)
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

    public function getClaseLineaDocumento($id_estado_actual_documento)
    {
        switch ($id_estado_actual_documento) {
            case 0:
            {
                $clase = "documento_inicial";
                break;
            }
            case 1:
            {
                $clase = "documento_en_curso";
                break;
            }
            case 2:
            {
                $clase = "documento_finalizado";
                break;
            }
            case 3:
            {
                $clase = "documento_rechazado";
                break;
            }
            default:
            {
                $clase = "documento_inicial";
                break;
            }
        }
        return $clase;
    }

    public function getNombreActorFromHito($hito)
    {
        if (isset($hito["id_cliente_emisor"])) {
            $id_cliente = $hito["id_cliente_emisor"];
        } else {
            $id_cliente = $hito["id_cliente_receptor"];
        }
        return Cliente::find($id_cliente)["nombre_identificacion"];
    }

    public function getNameFromHitoAndSignature($hito, $firmantes)
    {
        if (isset($hito["id_cliente_emisor"])) {
            $id_cliente = $hito["id_cliente_emisor"];
            return Cliente::find($id_cliente)["nombre_identificacion"];
        } else {
            $id_cliente = $hito["id_cliente_receptor"];
            foreach ($firmantes as $firmante) {
                if($firmante["id_cliente_receptor"] == $hito["id_cliente_receptor"])
                    return $firmante["nombre"];
            }
        }
        return Cliente::find($id_cliente)["nombre_identificacion"];
    }


    public function getLinkFromHito($id_proceso, $hito, $id_documento = null)
    {
        $proceso = Proceso::find($id_proceso);
        $esProcesoFirma = true;
        if (!$proceso) {
            $proceso = ProcesoSimple::find($id_proceso);
            $esProcesoFirma = false;
        }
        if (empty($hito)) {
            $ultima_carpeta = "originales";
        } else {
            $id_estado_actual_documento = $hito["id_estado_actual_documento"];
            $firma_emisor = $proceso->firma_emisor;
            $firma_stupendo = 0;
            $id_documento = $hito["id_documento"];
            $arr_camino = explode("/", $hito["camino"]);
            $cantidad_de_fragmentos = count($arr_camino);
            $posicion_uc = $cantidad_de_fragmentos - 2;
            if (isset($arr_camino[$posicion_uc])) {
                $ultima_carpeta = $arr_camino[$posicion_uc];
            } else {
                $arr_camino = explode("\\", $hito["camino"]);
                $cantidad_de_fragmentos = count($arr_camino);
                $posicion_uc = $cantidad_de_fragmentos - 2;
                if (isset($arr_camino[$posicion_uc])) {
                    $ultima_carpeta = $arr_camino[$posicion_uc];
                } else {
                    $ultima_carpeta = "originales";
                }
            }

            if ($hito["id_estado_actual_documento"] == 2 && $id_estado_actual_documento == 2 && $firma_stupendo != 0) {
                $ultima_carpeta = "Stupendo";
            } else {
                if ($hito["id_estado_actual_documento"] == 2 && $id_estado_actual_documento == 2 && $firma_stupendo == 0 && $firma_emisor == 2) {
                    $ultima_carpeta = "firmado_emisor";
                }
            }
        }
        $id_proceso = EncriptarId($id_proceso);
        if ($esProcesoFirma) {
            return "/doc_electronicos/descargar_documento/$id_proceso/$id_documento/$ultima_carpeta";
        } else {
            return "/doc_electronicos/descargar_documento_simple/$id_proceso/$id_documento";
        }
    }

    public function getAdjuntoFromHito($id_proceso, $hito, $id_documento = null)
    {
        return '<a href="' . $this->getLinkFromHito(
                $id_proceso,
                $hito,
                $id_documento
            ) . '" target="_blank"><img src="/img/iconos/pdf.png"></a>';
    }

    public function descargarDocumento($id_proceso, $id_documento, $ultima_carpeta)
    {
        $id_proceso = DesencriptarId($id_proceso);
        $proceso = Proceso::find($id_proceso);
        $storage = $proceso["storage"];
        $id_cliente_emisor = $proceso["id_cliente_emisor"];
        $id_propio = $proceso["id_propio"];
        if ($storage != Proceso::STORAGE_EXTERNO) {
            $camino = storage_path(
                "doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/$ultima_carpeta/$id_documento.pdf"
            );
            return response()->download($camino, $this->getTituloDocumento($proceso, $id_documento) . ".pdf");
        } else {
            $camino_original = "";
            foreach ($proceso->documentos as $documento) {
                if ($documento["id_documento"] == $id_documento) {
                    $camino_original = $documento["camino_original"];
                    break;
                }
            }
            $posicion = strpos($camino_original, "doc_electronicos/procesos/cliente_");
            $camino = substr(
                    $camino_original,
                    0,
                    $posicion
                ) . "doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/$ultima_carpeta/$id_documento.pdf";
            $response = response(
                file_get_contents($camino),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $this->getTituloDocumento(
                            $proceso,
                            $id_documento
                        ) . '.pdf"',
                ]
            );
            return $response;
        }
    }

    public function mostrarNuevaEmision()
    {
        $id_usuario = session()->get("id_usuario");
        $id_cliente = session()->get("id_cliente");
        
        $opciones_plantillas = Plantilla::get_options_plantillas($id_cliente, 'firma');
        $options_orden = Preferencia::get_options_default($id_cliente, "orden");
        $options_firma_emisor = Preferencia::get_options_default($id_cliente, "firma_emisor");
        $options_firma_stupendo = Preferencia::get_options_default($id_cliente, "firma_stupendo");
        $options_sello_tiempo = Preferencia::get_options_default($id_cliente, "sello_tiempo");
        $options_referencia_paginas = Preferencia::get_options_default($id_cliente, "referencia_paginas");
        $options_origen_exigido = Preferencia::get_options_default($id_cliente, "origen_exigido");

        $de_email = Preferencia::get_default_email_data($id_cliente, "de_email");
        $de_enmascaramiento = Preferencia::get_default_email_data($id_cliente, "de_enmascaramiento");

        $cliente = Cliente::find($id_cliente);

        $tiene_vista_email_personalizada = $cliente->tieneVistaEmailPersonalizada();

        $arrvalores = array(
            "id_usuario" => $id_usuario,
            "id_cliente" => $id_cliente,
            "options_orden" => $options_orden,
            "options_firma_emisor" => $options_firma_emisor,
            "options_firma_stupendo" => $options_firma_stupendo,
            "options_sello_tiempo" => $options_sello_tiempo,
            "options_referencia_paginas" => $options_referencia_paginas,
            "options_origen_exigido" => $options_origen_exigido,
            "opciones_plantillas" => $opciones_plantillas,
            "tiene_vista_email_personalizada" => $tiene_vista_email_personalizada,
            "de_email" => (empty($de_email)) ? Config::get('app.mail_from_name') : $de_email,
            "de_enmascaramiento" => empty($de_enmascaramiento) ? Config::get(
                'app.mail_from_address'
            ) : $de_enmascaramiento
        );

        return view("doc_electronicos.emisiones.emision", $arrvalores);
    }

    private function getSubCarpetaArchivoTemporal()
    {
        return sys_get_temp_dir() . "/doc_electronicos/documentos";
    }

    private function getNombreArchivoTemporal($nombre = null)
    {
        return "Temp_Archivo_" . session()->get("id_usuario") . "_" . $nombre;
    }

    public function validarPDF($camino_pdf)
    {
        Log::info("Inicio ProcesoController->validarPDF " . microtime(true));
        $Res = 1;
        $Mensaje = "Documento correcto";
        $plop = new PLOP();
        if (Config::get('app.usar_licencia_plop') == true) {
            $plop->set_option("license=" . Config::get('app.plop_license'));
        }
        try {
            if ($Res >= 0) {
                try {
                    $pdf = $plop->open_document($camino_pdf, "");
                    if ($pdf == 0) {
                        $Res = -1;
                        $Mensaje = $this->getErrorPlop($plop);
                    }
                } catch (PLOPException $e) {
                    $Res = -1;
                    $Mensaje = $this->getErrorPlop($plop);
                }
            }
            if ($Res >= 0) {
                try {
                    $cp = $plop->pcos_get_number($pdf, "length:pages");
                } catch (PLOPException $e) {
                    $Res = -1;
                    $Mensaje = "Archivo no soportado. " . $e->getMessage();
                } catch (Exception $e) {
                    $Res = -1;
                    $Mensaje = "Archivo no soportado. " . $e->getMessage();
                } catch (Throwable $t) {
                    $Res = -1;
                    $Mensaje = "Archivo no soportado. " . $e->getMessage();
                }
            }
            if ($Res >= 0) {
                $arr_data = $this->getInfoPaginasPDF($camino_pdf);
                $Res = $arr_data["Res"];
                $Mensaje = $arr_data["Mensaje"];
                $arr_info_pagina = $arr_data["arr_info_pagina"];
            }
            if ($Res >= 0) {
                foreach ($arr_info_pagina as $pagina => $info_pagina) {
                    $pagina_real = $pagina + 1;
                    if ($info_pagina["width_mm"] < 120 || $info_pagina["height_mm"] < 120) {
                        $Res = -3;
                        $Mensaje = "El documento tiene dimensiones demasiado pequeñas (página $pagina_real) y no podrá ser procesado.";
                        break;
                    }
                }
            }
            if ($Res >= 0) {
                $resultado_cierre = $plop->close_document($pdf, "");
                if ($resultado_cierre == 0) {
                    $Res = -4;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
        } catch (PLOPException $e) {
            $Res = -200;
            $Mensaje = $this->getErrorPlop($plop);
        } catch (Exception $e) {
            $Res = -100;
            $Mensaje = $e->getMessage();
        }
        Log::info("Fin ProcesoController->validarPDF " . microtime(true));
        return array($Res, $Mensaje);
    }

    public function getErrorPlop($plop)
    {
        switch ($plop->get_errnum()) {
            case 4534:
            {
                $Mensaje = "No se pueden adjuntar documentos protegidos por contraseña.";
                break;
            }
            default:
            {
                $Mensaje = $plop->get_errnum() . ": " . $plop->get_errmsg();
                break;
            }
        }
        return $Mensaje;
    }

    public function cargarArchivoExcel(Request $request)
    {
        $file = $request->file('file');
        if (!$file->isFile()) {
            AuditoriaController::log($request->user(), "No existe el archivo", "Carga masiva de destinatarios");
            return response()->json('No existe el archivo', 400);
        }

        $extension = $file->getClientOriginalExtension();

        if ($extension != "xlsx") {
            AuditoriaController::log(
                $request->user(),
                "El archivo no está en formato xlsx, nombre de archivo => " . $file->getClientOriginalName(),
                "Carga masiva de destinatarios"
            );
            return response()->json('El archivo no está en formato xlsx', 400);
        }

        $results = Excel::load(
            $file,
            function ($reader) {
            }
        )->get();
        $count = $results->count();

        if ($count > 10000) {
            AuditoriaController::log(
                $request->user(),
                "El archivo tiene mas de 10000 registros tiene " . $count . ", nombre de archivo => " . $file->getClientOriginalName(
                ),
                "Carga masiva de destinatarios"
            );
            return response()->json('El archivo tiene mas de 10.000 registros', 400);
        }

        $destinatarios_array = array();
        $destinatarios_contados = 0;
        $productos_modificados = 0;

        try {
            foreach ($results as $fila) {
                $destinatarios_contados++;
                $fila_array = array(
                    'cedula_destinatarios' => $fila["cedula_del_destinatario"],
                    'Nombres_y_Apellidos' => $fila["nombres_y_apellidos"],
                    'Correo_electronico' => $fila["correo_electronico"],
                    'Telefono_movil' => $fila["telefono_movil"],
                    'error' => ""
                );
                if (strlen("=\"" . $fila['cedula_del_destinatario'] . "\"") > 20) {
                    $fila_array["error"] = "El número de identificación debe tener máximo 20 carácteres en la fila " . $destinatarios_contados;
                    array_push($destinatarios_array, $fila_array);
                } elseif (strlen($fila["nombres_y_apellidos"]) > 300) {
                    $fila_array["error"] = "El nombre del destinatario debe tener máximo 300 carácteres en la fila " . $destinatarios_contados;
                    array_push($destinatarios_array, $fila_array);
                } elseif (strlen($fila["correo_electronico"]) > 300) {
                    $fila_array["error"] = "El correo electrónico del destinatario debe tener máximo 300 carácteres en la fila " . $destinatarios_contados;
                    array_push($destinatarios_array, $fila_array);
                } elseif (strlen("=\"" . $fila["telefono_movil"] . "\"") > 20) {
                    $fila_array["error"] = "El teléfono del destinatario debe tener máximo 20 carácteres en la fila " . $destinatarios_contados;
                    array_push($destinatarios_array, $fila_array);
                } elseif (!is_numeric($fila["cedula_del_destinatario"])) {
                    $fila_array["error"] = "La cédula contiene carácteres alfanuméricos en la fila " . $destinatarios_contados;
                    array_push($destinatarios_array, $fila_array);
                } elseif (!is_numeric($fila["telefono_movil"])) {
                    $fila_array["error"] = "El teléfono contiene carácteres alfanuméricos en la fila " . $destinatarios_contados;
                    array_push($destinatarios_array, $fila_array);
                } elseif (is_null($fila["cedula_del_destinatario"]) == true || is_null(
                        $fila["nombres_y_apellidos"]
                    ) == true || is_null($fila["correo_electronico"]) == true || is_null(
                        $fila["telefono_movil"]
                    ) == true) {
                    $fila_array["error"] = "Faltan campos obligatorios en el archivo.";
                    array_push($destinatarios_array, $fila_array);
                } else {
                    array_push($destinatarios_array, $fila_array);
                }
            }
            return response()->json($destinatarios_array, 200);
        } catch (\Exception $e) {
            Log::info("Error por  " . $e->getMessage() . " en el archivo " . $results);
            return response()->json('El archivo contiene un error de estructura', 400);
        }
    }

    public function subirDocumentoTemporal(Request $request)
    {
        $resultado = true;
        $camino_temporal = null;
        $archivo_destino = null;
        $extension = null;
        $mensaje = "El archivo se subió con éxito";
        $status = 200;
        try {
            $file = $request->file('file');
            if (!$file->isFile()) {
                $resultado = false;
                $mensaje = 'No existe archivo';
                $status = 400;
            } else {
                $extension = strtolower($file->getClientOriginalExtension());
                $carpeta_destino_temporal = $this->getSubCarpetaArchivoTemporal();
                if (!file_exists($carpeta_destino_temporal)) {
                    mkdir($carpeta_destino_temporal, 0777, true);
                }
                $archivo_destino = $this->getNombreArchivoTemporal(uniqid("Ymd") . "." . $extension);
                $upload_success = $file->move($carpeta_destino_temporal, $archivo_destino);
                if (!$upload_success->isFile()) {
                    $resultado = false;
                    $mensaje = 'Ocurrió un error cargando el archivo.';
                    $status = 400;
                } else {
                    $camino_temporal = $carpeta_destino_temporal . "/" . $archivo_destino;
                }
            }
            if ($extension == "pdf" && $request->input("origen_upload_file") != "emision_simple") {
                $arr_res = $this->validarPDF($camino_temporal);
                $resultado = ($arr_res[0] >= 0);
                $mensaje = $arr_res[1];
            }
            $status = $resultado ? 200 : 400;
        } catch (Exception $e) {
            $resultado = false;
            $mensaje = $e->getMessage();
            $status = 400;
        } catch (Exception $e) {
            $resultado = false;
            $mensaje = $e->getMessage();
            $status = 400;
        }
        return response()->json(
            array(
                "resultado" => $resultado,
                "mensaje" => $mensaje,
                "camino_temporal" => $camino_temporal,
                "nombre_temporal" => $archivo_destino,
                "extension" => $extension
            ),
            $status
        );
    }

    public function descargarDocumentoTemporal($nombre_temporal, $titulo_documento)
    {
        return response()->download(
            $this->getSubCarpetaArchivoTemporal() . "/" . $nombre_temporal,
            $titulo_documento . ".pdf"
        );
    }

    public function subirImagenTemporal(Request $request)
    {
        $resultado = true;
        $camino_temporal = null;
        $nombre_temporal = null;
        $extension = null;
        $mensaje = "El archivo se subió con éxito";
        $status = 200;
        try {
            $file = $request->file('file');
            if (!$file->isFile()) {
                $resultado = false;
                $mensaje = 'No existe archivo';
                $status = 400;
            } else {
                $extension = strtolower($file->getClientOriginalExtension());

                $carpeta_destino_temporal = public_path() . "/email/img";
                if (!file_exists($carpeta_destino_temporal)) {
                    mkdir($carpeta_destino_temporal, 0777, true);
                }
                $archivo_destino = $this->getNombreArchivoTemporal(date("U") . "." . $extension);
                $upload_success = $file->move($carpeta_destino_temporal, $archivo_destino);
                if (!$upload_success->isFile()) {
                    $resultado = false;
                    $mensaje = 'Ocurrió un error cargando el archivo.';
                    $status = 400;
                } else {
                    $camino_temporal = $carpeta_destino_temporal . "/" . $archivo_destino;
                }
            }
            if ($extension == "pdf" && $request->input("origen_upload_file") != "emision_simple") {
                $arr_res = $this->validarPDF($camino_temporal);
                $resultado = ($arr_res[0] >= 0);
                $mensaje = $arr_res[1];
            }
            $status = $resultado ? 200 : 400;
        } catch (Exception $e) {
            $resultado = false;
            $mensaje = $e->getMessage();
            $status = 400;
        }
        return response()->json(
            array(
                "resultado" => $resultado,
                "mensaje" => $mensaje,
                "camino_temporal" => $camino_temporal,
                "nombre_temporal" => $archivo_destino,
                "extension" => $extension
            ),
            $status
        );
    }

    public function buscarPorCedula(Request $request)
    {
        $resultado = false;
        $variante = 0;
        $nombre = "";
        $email = "";
        $telefono = "";
        $mensaje = "";
        $identificacion = $request->input("Valor_1");
        Filtrar($identificacion, "STRING");
        if (empty($identificacion)) {
            $mensaje = "No se recibió una identificación.<br/>";
        } else {
            if (!CedulaValida($identificacion)) {
                $mensaje = "No se recibió una cédula válida.<br/>";
            } else {
                $cliente = Cliente::where("identificacion", $identificacion)->first(
                    ["_id", "nombre_identificacion", "email", "telefono"]
                );
                if ($cliente) {
                    $resultado = true;
                    $variante = 1;
                    $id_cliente = $cliente["_id"];
                    $nombre = $cliente["nombre_identificacion"];
                    $email = $cliente["email"];
                    $telefono = $cliente["telefono"];
                    $mensaje = "Un cliente con identificación $identificacion fue encontrado en el sistema.<br/>";
                    if (empty($nombre) || empty($email)) {
                        $usuario = Usuarios::where("clientes.cliente_id", $id_cliente)->first(
                            ["_id", "nombre", "email"]
                        );
                        if ($usuario) {
                            if (empty($nombre)) {
                                $nombre = $usuario["nombre"];
                            }
                            if (empty($email)) {
                                $email = $usuario["email"];
                            }
                        }
                    }
                } else {
                    $consultaDb = $this->consultarRegistroDB($identificacion);
                    if ($consultaDb == 0) {
                        $activado = Config::get("app.datofast_active");
                        if ($activado == true) {
                            $apikey = Config::get('app.datofast_apikey');
                            $url = 'https://esdinamico.datofast.com:5454/consulta/persona_individual/' . $identificacion . '/json?apikey=' . Config::get("app.datofast_apikey");
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                            curl_setopt($ch, CURLOPT_HTTPGET, 1);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            $response = curl_exec($ch);

                            if (curl_errno($ch)) {
                                $mensaje = "DatoFast no arrojó resultados (1).<br/>";
                            } else {
                                if (empty($response)) {
                                    $mensaje = "DatoFast no arrojó resultados (2).<br/>";
                                } else {
                                    $data = json_decode($response, 1);
                                    if (empty($data["persona"]["nombre"])) {
                                        $mensaje = "DatoFast no arrojó resultados (3).<br/>";
                                    } else {
                                        $resultado = true;
                                        $variante = 2;
                                        $nombre = $data["persona"]["nombre"];
                                        if (isset($data["persona"]["email"])) {
                                            $arr_emails = explode(",", $data["persona"]["email"]);
                                            $email = trim(strtolower($arr_emails[0]));
                                        }
                                        if (isset($data["persona"]["telefonos"])) {
                                            $arr_telefonos = explode(",", $data["persona"]["celulares"]);
                                            $telefono = trim(str_ireplace("-", "", $arr_telefonos[0]));
                                        }
                                        $mensaje = "Información resuelta por Datofast";

                                        $this->guardarRegistroDB($identificacion, $data);
                                        $origen = "DatoFast";
                                        $credenciales = "592d8039476a2b05326b9afa";
                                        $apiToken = "F3iqWLDo5yRsNteSbUVBV6FNyJiFR9iJ";
                                        $guarda_peticion_recibida = "personal";
                                        $this->guardarConsulta(1, $identificacion, $credenciales, $origen, $guarda_peticion_recibida);
                                        $this->guardarConsultaDatofast($apiToken, 1, $identificacion, $credenciales, $origen, $guarda_peticion_recibida);
                                    }
                                }
                            }
                            curl_close($ch);
                        } else {
                            $resultado = true;
                            $variante = 0;
                            $mensaje = "No encontramos datos con la cédula buscada. Por favor ingrese los datos manualmente";
                        }
                    } else {
                        $consulta = $consultaDb;
                        $nombre = $consulta['persona']['nombre'];

                        $arr_emails = explode(",", $consulta["persona"]["email"]);
                        $email = trim(strtolower($arr_emails[0]));

                        $arr_telefonos = explode(',', $consulta['persona']['celulares']);
                        $telefono = trim(str_ireplace("-", "", $arr_telefonos[0]));

                        $resultado = true;
                        $variante = 2;
                        $mensaje = "Información resuelta por Base de Datos";
                    }
                }
            }
        }
        return response()->json(
            array(
                "resultado" => $resultado,
                "variante" => $variante,
                "nombre" => $nombre,
                "email" => $email,
                "telefono" => $telefono,
                "mensaje" => $mensaje
            ),
            200
        );
    }

    public function buscarPorRUCListo(Request $request)
    {
        $resultado = false;
        $Res = 0;
        $variante = 0;
        $razon_social = "";
        $email = "";
        $telefono = "";
        $mensaje = "";

        $identificacion = $request->input("Valor_1");


        Filtrar($identificacion, "STRING", "");
        if (!empty(session()->get("id_cliente"))) {
            $ruc_propio = Cliente::find(session()->get("id_cliente"))["identificacion"];
        } else {
            $ruc_propio = "";
        }
        if (empty($identificacion)) {
            $Res = -1;
            $mensaje = "No se recibió una identificación.<br/>";
        } else {

            if (!RUCValido($identificacion)) {
                $Res = -2;
                $mensaje = "No se recibió un RUC válido.<br/>";
            } else {
                if ($ruc_propio == $identificacion) {
                    $Res = -3;
                    $mensaje = "Usted no puede invitarse a usted mismo.<br/>";
                } else {

                       $cliente_existente = Cliente::where("identificacion", $identificacion)->first();


                        if (!$cliente_existente) {
                            $Res = -4;
                            $mensaje = "No se recibió información del RUC ingresado, puede ingresar las datos de la persona jurídica.<br/>";
                        } else {
                            $id_cliente_existente = $cliente_existente->_id;
                            $firma = Firma::ultimaFirmaDelCliente($id_cliente_existente, false);
                            if ($firma) {
                                $variante = 1;
                                $razon_social = $cliente_existente["nombre_identificacion"];
                                $email = $firma->email;
                                $telefono = $firma->telefono;
                                $mensaje = "Un cliente con identificación $identificacion fue encontrado en el sistema de firma.<br/>";
                            } else {
                                $razon_social = $cliente_existente->nombre_identificacion;
                                $email = $cliente_existente->email;
                                $telefono = $cliente_existente->telefono;
                                $mensaje = "Un cliente con identificación $identificacion fue encontrado en el sistema de clientes.<br/>";
                            }

                            $resultado = true;
                            $Res = 1;

                        }
                }
            }
        }
        return response()->json(
            array(
                "resultado" => $resultado,
                "Res" => $Res,
                "variante" => $variante,
                "ruc" => $identificacion,
                "razon_social" => $razon_social,
                "email" => $email,
                "telefono" => $telefono,
                "mensaje" => $mensaje
            ),
            200
        );
    }

    function consultarCedulasClientesLocales(Request $request)
    {
        $cadena = $request->input("Valor_1");
        Filtrar($cadena, "STRING");
        $cedula_propia = Cliente::find(session()->get("id_cliente"))["identificacion"];
        $project = [
            "identificacion" => '$identificacion',
            "length" => ['$strLenCP' => '$identificacion'],
            "tipo_identificacion" => '$tipo_identificacion'
        ];
        $match = [
            'length' => 10,
            'identificacion' => ['$regex' => ".*$cadena.*", '$ne' => $cedula_propia],
            'tipo_identificacion' => "05"
        ];
        $cursor = Cliente::raw()->aggregate([['$project' => $project], ['$match' => $match], ['$limit' => 10]]);
        $data = array_column($cursor->toArray(), 'identificacion');
        $data_final = array("cedulas" => $data);
        return json_encode($data_final);
    }


    function consultarRUCLocalesListos(Request $request)
    {
        $cadena = $request->input("Valor_1");
        Filtrar($cadena, "STRING", "");

        $ruc_propio = Cliente::find(session()->get("id_cliente"))["identificacion"];

        if ($ruc_propio) {
            $firmas = Firma::select("identificacion_cliente")
                                ->where("figura_legal", "J")
                                ->where("id_estado", 1)
                                ->where("identificacion_cliente", "<>",  $ruc_propio)
                                ->where("identificacion_cliente", "like",  "%$cadena%");

            $firmas = $firmas->groupBy("id_cliente")->take(Constantes::AUTOCOMPLETAR_MAXIMO_MOSTRAR)->get();
            $data = array_column($firmas->toArray(), "identificacion_cliente");
            sort($data);
            $data_final = array("rucs" => $data);
        } else {
            $data = "" ;
            $data_final = array("rucs" => $data);
        }

        return json_encode($data_final);
    }

    function consultarClientesLocales(Request $request)
    {
        $cadena = $request->input("Valor_1");
        Filtrar($cadena, "STRING", "");
        $clientes = Cliente::where("identificacion", "like", "%$cadena%")
                                ->orWhere("nombre_identificacion", "like", "%$cadena%")
                                ->take(8)
                                ->get(["_id", "identificacion", "nombre_identificacion"]);
        $clientes = $clientes->toArray();
        foreach ($clientes as &$cliente) {
            $cliente["ruc_nombre"] = $cliente["identificacion"] . " - " . $cliente["nombre_identificacion"];
        }
        $data_final = array("clientes" => $clientes);
        return json_encode($data_final);
    }

    public function dependeDeClientes($id_cliente_dependiente)
    {
        $cliente_dependiente = Cliente::find($id_cliente_dependiente);
        if (!$cliente_dependiente) {
            return false;
        } else {
            if (empty($cliente_dependiente->clientes_dependientes_de)) {
                return false;
            } else {
                return $cliente_dependiente->clientes_dependientes_de;
            }
        }
    }



    public function preCargarCliente(Request $request)
    {
        $identificacion = $request->input("TRUC");
        $razon_social_nombre = $request->input("TRazonSocial");
        $tipo_identificacion ="04";
        $email = $request->input("TEMailPJ");
        $telefono = $request->input("TTelefonoPJ");
        $id_modulo = 7;
        $modulos = array(array('id_modulo' => $id_modulo));

        $cliente_existente = Cliente::where("identificacion", $identificacion)->first();
        if(!$cliente_existente)
        {
            if (!empty($identificacion) || (!empty($razon_social_nombre)) || (!empty($email)) || (!empty($telefono))) {
                $cliente = Cliente::crear(
                    $email,
                    $razon_social_nombre,
                    $identificacion,
                    $tipo_identificacion,
                    "",
                    $telefono,
                    5,
                    ['DocumentosElectronicos'],
                    $modulos
                );
            }
        }
    }

    public function guardarProceso(Request $request)
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
            $id_propio = 1 + $this->getMaxIdPropioProceso();
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
            $storage = Proceso::STORAGE_LOCAL;
            $orden = (int)$request->input("SOrden");
            Filtrar($orden, "INTEGER", 1);
            $firma_emisor = (int)$request->input("SFirmaEmisor");
            Filtrar($firma_emisor, "INTEGER", 1);


            $referencia_paginas = (int)$request->input("SReferenciaPaginas");
            Filtrar($referencia_paginas, "INTEGER", 0);
            $origen_exigido = (int)$request->input("SOrigenExigido");
            Filtrar($origen_exigido, "INTEGER", 0);
            $via = $request->input("HiddenVia");
            if (empty($via)) {
                $via = "WEB";
            }
            Filtrar($via, "STRING", "WEB");

            $ftp_filename = $request->input("ftp_filename");
            Filtrar($ftp_filename, "STRING", "");

            $bloqueado = false;

            $nombreEnmas = $request->input("TNombreEnmas");
            Filtrar($nombreEnmas, "STRING", "");

            $correoEnmas = $request->input("TCorreoEnmas");
            Filtrar($correoEnmas, "EMAIL", "");

            $nombreEnmas = empty($nombreEnmas) ? Preferencia::get_default_email_data(
                $id_cliente_emisor,
                "de_email"
            ) : $nombreEnmas;
            $correoEnmas = empty($correoEnmas) ? Preferencia::get_default_email_data(
                $id_cliente_emisor,
                "de_enmascaramiento"
            ) : $correoEnmas;

            $cuerpo_email = null;
            if (isset($_POST['Valor_1'])) {
                $cuerpo_email = $_POST['Valor_1'];
            }
            if (isset($_POST['cuerpo_email'])) {
                $cuerpo_email = $_POST['cuerpo_email'];
            }

            if (empty($cuerpo_email)) {
                $cuerpo_email = Proceso::CUERPO_EMAIL_POR_DEFECTO;
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
                if (empty($titulo_proceso) || empty($arr_documentos) || empty($arr_firmantes) ||
                    !in_array($orden, array(1, 2)) || !in_array($firma_emisor, array(0, 1, 2))) {
                    $Res = -2;
                    $Mensaje = "Datos incompletos";
                }
            }


            $fc = new FirmasController();
            if ($Res >= 0) {
                if ((int)$firma_emisor != 0) {
                    $arr_estado_firma_emisor = json_decode($fc->GetEstadoFirma($id_cliente_emisor), true);
                    $id_estado_firma_emisor = (int)$arr_estado_firma_emisor["id_estado"];
                    switch ($id_estado_firma_emisor) {
                        case 0:    
                            $Res = -1;
                            $Mensaje = "Tu compañía no tiene una firma electrónica registrada en Stupendo.<br/>";
                            break;
                        case 1:
                            $Res = 1;
                            $Mensaje = "Tu firma electrónica está activa.<br/>";
                            break;
                        case 2:
                            $Res = -2;
                            $Mensaje = "Tu firma electrónica registrada en Stupendo se encuentra cancelada.<br/>";
                            break;
                        case 3:
                            $Res = -3;
                            $Mensaje = "Tu firma electrónica registrada en Stupendo ha caducado.<br/>";
                            break;
                        default:
                            $Res = -4;
                            $Mensaje = "Tu compañía no tiene una firma electrónica registrada en Stupendo.<br/>";
                            break;
                    }
                }
            }
            if ($Res >= 0) {
                $origen_firma_propia = $fc->GetOrigenFirma($id_cliente_emisor);
                if ((int)$firma_emisor != 0 && (int)$origen_exigido == 1 && (int)$origen_firma_propia != 1) {
                    $Res = -5;
                    $Mensaje = "Definiste el proceso para solamente usar firmas Acreditada  y tu firma actual incumple el requisito.";
                } else {
                    if ((int)$firma_emisor != 0 && (int)$origen_exigido == 2 && (int)$origen_firma_propia != 2) {
                        $Res = -6;
                        $Mensaje = "Definiste el proceso para solamente usar firma simple Stupendo y tu firma actual incumple el requisito.";
                    }
                }
            }
            if ($Res >= 0) {
                foreach ($arr_documentos as $documento) {
                    if ($Res >= 0) {
                        $documento = DesunirData($documento);
                        $id_documento++;
                        $titulo = $documento[0];
                        $camino_temporal = $documento[1];
                        $viene_de_plantilla = strpos($camino_temporal, 'plantilla') !== false;
                        if (empty($titulo)) {
                            $Res = -4;
                            $Mensaje = "$documento Datos (documentos) incompletos.";
                        } else {
                            if (($viene_de_plantilla && !is_file(storage_path() . $camino_temporal))
                                && !is_file($camino_temporal)) {
                                $Res = -2;
                                $Mensaje = "No se pudo leer el documento.<br/>";
                            } else {
                                $carpeta_destino = storage_path(
                                    ) . "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/originales";
                                if (!is_dir($carpeta_destino)) {
                                    mkdir($carpeta_destino, 0777, true);
                                }
                                $archivo_destino = $id_documento . ".pdf";
                                $camino_destino = $carpeta_destino . "/" . $archivo_destino;

                                @unlink($camino_destino);
                                if ($viene_de_plantilla) {
                                    $camino_temporal = storage_path() . $camino_temporal;
                                }
                                $arr_res = $this->optimizarPDF($camino_temporal, $camino_destino);
                                $Res = $arr_res[0];
                                $Mensaje = $arr_res[1];
                                
                                if ($Res < 0) {
                                    if(file_exists($camino_destino) || copy($camino_temporal, $camino_destino)) {
                                        $Res = 0;
                                    } else {
                                        $Res = -1;
                                        $Mensaje = "Ocurrió un error optimizando/moviendo el documento.";
                                    }
                                }
                                if ($Res >= 0) {
                                    //ToDo: si se quiere cambiar el nombre del adjunto en el email se debe hacer aquí (y chequear que no se dañe el resto del flujo)
                                    $camino_original = "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/originales/$id_documento.pdf";
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
            }
            if ($Res >= 0) {
                foreach ($arr_firmantes as $firmante) {
                    if ($Res >= 0) {
                        $firmante = DesunirData($firmante);
                        $persona = $firmante[0];
                        $identificacion = $firmante[1];
                        $nombre = $firmante[2];
                        $email = $firmante[3];
                        $telefono = $firmante[4];
                        $id_estado_correo = 0;
                        $estado_correo = self::getEstadoCorreo($id_estado_correo);

                        if ($persona != 'N' && $persona != 'J') {
                            $persona = 'N';
                        } 

                        if ((($persona == "J" && !RUCValido($identificacion))
                                || ($persona == "N" && !CedulaValida($identificacion)))
                                || empty($nombre) || !EMailValido($email) || empty($telefono)) {
                            $Res = -7;
                            $Mensaje = "Datos incompletos";
                        } else {
                            if ($identificacion == $cliente_emisor->identificacion) {
                                $Res = -5;
                                $Mensaje = "No puede incluirse usted mismo dentro de la lista de signatarios.";
                            } else {
                                $arr_res = Self::prepararClienteUsuarioDE(
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
                        $arr_f = array(
                            "id_usuario" => $id_usuario,
                            "id_cliente_receptor" => $id_cliente,
                            "persona" => $persona,
                            "identificacion" => $identificacion,
                            "nombre" => $nombre,
                            "email" => $email,
                            "telefono" => $telefono,
                            "id_estado_correo" => $id_estado_correo,
                            "estado_correo" => $estado_correo,
                            "creado_en_proceso" => $nuevo_usuario
                        );
                        $wc = new WorkflowController();
                        $workflow_in = $wc->GetWorkflowIn($id_cliente, $id_cliente_emisor);
                        if (!empty($workflow_in)) {
                            $arr_f["revisiones"] = array(
                                "id_workflow_in" => $workflow_in["_id"],
                                "permite_lectura" => ($workflow_in["permite_lectura"] == 1) ? true : false,
                                "revisores" => $workflow_in["revisores"],
                                "historico_revisiones" => array(),
                                "estado_revision" => (int)0
                            );
                        }
                        array_push($firmantes, $arr_f);
                    }
                }
            }
            if ($Res >= 0) {
                $depende_de_clientes = $this->dependeDeClientes($id_cliente_emisor);
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
                $data_proceso = array(
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
                    "firma_emisor" => $firma_emisor,
                    "referencia_paginas" => $referencia_paginas,
                    "origen_exigido" => $origen_exigido,
                    "bloqueado" => $bloqueado,
                    "via" => $via,
                    "nombre_enmas" => $nombreEnmas,
                    "correo_enmas" => $correoEnmas,
                    "cuerpo_email" => $cuerpo_email,
                    "url_banner" => $request->input("url_banner")
                );

                if ($via == "FV") {
                    $fv_id = $request->input("fv_id");
                    $fv = Formulario_vinculacion::find($fv_id);
                    $documentos_adjuntos = $this->getAdjuntosFV($fv, $cliente_emisor);
                    if ($documentos_adjuntos && count($documentos_adjuntos) > 0) {
                        $data_proceso['adjuntos'] = $documentos_adjuntos;
                    }
                }

                if (!empty($ftp_filename)) {
                    $data_proceso['ftp_filename'] = $ftp_filename;
                }
                $wc = new WorkflowController();
                
                $workflow_out = $wc->GetWorkflowOut(
                    $id_cliente_emisor,
                    array_column($firmantes, "id_cliente_receptor")
                );
                if (!empty($workflow_out)) {
                    $data_proceso["revisiones"] = array(
                        "id_workflow_out" => $workflow_out["_id"],
                        "revisores" => $workflow_out["revisores"],
                        "historico_revisiones" => array(),
                        "estado_revision" => (int)0
                    );
                }
                $proceso = Proceso::create($data_proceso);

                if (isset($documentos_adjuntos)) {
                    $proceso->adjuntos = $documentos_adjuntos;
                    $proceso->save();
                }

                if (empty($workflow_out)) {
                    $arr_res = $this->firmarSalidaYNotificar($proceso);
                    $Res = $arr_res["Res"];
                    $Mensaje = $arr_res["Mensaje"];
                    if ($Res >= 0) {
                        Auditoria::Registrar(
                            6,
                            $id_usuario_emisor,
                            $id_cliente_emisor,
                            $proceso->_id,
                            null,
                            null,
                            $momento_emitido
                        );
                    }
                } else {
                    $arr_res = $this->notificarARevisoresSalidaPendiente($proceso);
                    $Res = $arr_res["Res"];
                    $Mensaje = $arr_res["Mensaje"];
                }
            }
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage() . " Stacktrace: " . $e->getTraceAsString();
            Log::error("Error al Guardar Proceso (DE): " . $e->getMessage() . " Stacktrace: " . $e->getTraceAsString());
        }
        if ($Res >= 0) {
            $Res = $id_propio;
            $Mensaje = "El proceso fue guardado con éxito.<br/>";
            $id = $proceso->_id;
            foreach ($arr_documentos as $documento) {
                $documento = DesunirData($documento);
                $viene_de_plantilla = strpos($documento[1], 'plantilla') !== false;
                if (!$viene_de_plantilla) {
                    @unlink($documento[3]);
                }
            }
        }

        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "id" => $id), 200);
    }

    private function getAdjuntosFV($formulario, $cliente)
    {
        if ($formulario == null || $cliente == null) {
            return null;
        }

        $campos_adjuntos = $cliente->getCamposAdjuntosFVaDE();
        $doc_adjuntos = array();
        foreach ($campos_adjuntos as $campo) {
            $nombre_campo = $campo['campo'];
            if (isset($nombre_campo) && isset($formulario[$nombre_campo])
                && $formulario[$nombre_campo] != null) {
                $adjunto = array(
                    'nombre_doc' => $campo['nombre_doc'],
                    'url' => $formulario[$nombre_campo]
                );
                $doc_adjuntos[] = $adjunto;
            }
        }
        return $doc_adjuntos;
    }

    public function firmarSalidaYNotificar($proceso)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                if ($proceso->firma_emisor == 1) {
                    foreach ($proceso->documentos as $documento) {
                        if ($Res >= 0) {
                            $request = new Request();
                            $request->merge(
                                array(
                                    "HiddenActor" => "EMISOR",
                                    "HiddenIdProceso" => EncriptarId($proceso->_id),
                                    "HiddenIdDocumento" => $documento["id_documento"]
                                )
                            );
                            $resultado = $this->firmarDocumento($request);
                            $arr_res = json_decode($resultado->getContent());
                            $Res = $arr_res->Res;
                            $Mensaje = $arr_res->Mensaje;
                        }
                    }
                }
                if ($Res < 0) {
                    EliminarDirectorio(
                        storage_path(
                            "doc_electronicos/procesos/cliente_" . $proceso->id_cliente_emisor . "/" . $proceso->id_propio
                        )
                    );
                    $proceso->delete();
                }
            }
            if ($Res >= 0) {
                $this->notificarARevisoresEntradaPendiente($proceso);
                $this->enviarEnlaceInvitacion($proceso);
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El proceso fue iniciado correctamente y los receptores fueron notificados.";
        }
        return array("Res" => $Res, "Mensaje" => $Mensaje);
    }

    public function notificarARevisoresEntradaPendiente($proceso)
    {
        $Res = 0;
        $Mensaje = "";
        $arretiquetas = array("banner_url", "razon_social_receptor", "razon_social_emisor", "titulo_proceso", "enlace");
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $razon_social_emisor = $cliente_emisor["nombre_identificacion"];
        $titulo_proceso = $proceso->titulo;
        $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
        $pc = new PreferenciasController();
        $banner_url = $pc->getURLEmailBanner($cliente_emisor);
        $de = "Stupendo";
        $asunto = "Proceso pendiente de revisión";


        $nombreEnmas = (isset($proceso->nombre_enmas) && !empty($proceso->nombre_enmas)) ? $proceso->nombre_enmas : Preferencia::get_default_email_data(
            $proceso->id_cliente_emisor,
            "de_email"
        );
        $correoEnmas = (isset($proceso->correo_enmas) && !empty($proceso->correo_enmas)) ? $proceso->correo_enmas : Preferencia::get_default_email_data(
            $proceso->id_cliente_emisor,
            "de_enmascaramiento"
        );

        $arreglo_adjuntos = array();
        foreach ($proceso->documentos as $documento) {
            @array_push($arreglo_adjuntos, storage_path($documento["camino_original"]));
        }

        foreach ($proceso->firmantes as $firmante) {
            if (($proceso->orden == 1 && isset($firmante["revisiones"]["revisores"]) && isset($firmante["revisiones"]["estado_revision"]) && $firmante["revisiones"]["estado_revision"] == 0)
                || ($proceso->orden == 2 && isset($firmante["revisiones"]["revisores"]) && isset($firmante["revisiones"]["estado_revision"]) && $firmante["revisiones"]["estado_revision"] == 0
                    && $this->esSuTurno($proceso->_id, $firmante["id_cliente_receptor"]))) {
                $arreglo_emails = array();
                $arreglo_nombres = array();
                $razon_social_receptor = Cliente::find($firmante["id_cliente_receptor"])["nombre_identificacion"];
                foreach ($firmante["revisiones"]["revisores"] as $id_usuario_revisor) {
                    $usuario_revisor = Usuarios::find($id_usuario_revisor);
                    array_push($arreglo_emails, $usuario_revisor["email"]);
                    array_push($arreglo_nombres, $usuario_revisor["nombre"]);
                }
                $arrvalores = array(
                    $banner_url,
                    $razon_social_receptor,
                    $razon_social_emisor,
                    $titulo_proceso,
                    $enlace
                );
                EnviarCorreo(
                    'emails.doc_electronicos.notifica_revisores_entrada_pendiente',
                    $de,
                    $asunto,
                    $arreglo_emails,
                    $arreglo_nombres,
                    $arretiquetas,
                    $arrvalores,
                    $arreglo_adjuntos,
                    null,
                    $nombreEnmas,
                    $correoEnmas
                );
            }
        }
    }

    public function reenviarEnlaceInvitacionPuntual($id_proceso, $id_usuario_receptor)
    {
        $proceso = Proceso::find($id_proceso);
        if ($proceso) {
            return $this->enviarEnlaceInvitacion($proceso, $id_usuario_receptor);
        } else {
            return response()->json(array("Res" => -1, "Mensaje" => "El proceso no existe"), 200);
        }
    }

    public function enviarEnlaceInvitacion($proceso, $id_usuario_receptor = null)
    {
        $Res = 0;
        $Mensaje = "";
        $titulo_proceso = $proceso->titulo;
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $cliente_identificacion = $cliente_emisor["identificacion"];
        $compania = $cliente_emisor["nombre_identificacion"];
        $pc = new PreferenciasController();
        if (isset($proceso->url_banner) && !empty($proceso->url_banner)) {
            $banner_url = $proceso->url_banner;
        } else {
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
        }
        if (isset($proceso->cuerpo_email) && !empty($proceso->cuerpo_email)) {
            $cuerpo_email = $proceso->cuerpo_email;
        } else {
            $cuerpo_email = Proceso::CUERPO_EMAIL_POR_DEFECTO;
        }

        $de = Preferencia::get_default_email_data($proceso->id_cliente_emisor, "de_email");
        $asunto = Preferencia::get_default_email_data($proceso->id_cliente_emisor, "asunto_email_citacion");
        $asunto = str_replace("TITULO_PROCESO", $titulo_proceso, $asunto);
        $mostrar_credenciales = false;
        $password_autogenerado = "";
        $sms = new SMSController();

        $lista_documentos = "";
        $titulos_documentos = array();
        $index = 0;

        $nombreEnmas = $proceso->nombre_enmas;
        $correoEnmas = $proceso->correo_enmas;

        if ($correoEnmas != "") {
            $de = $nombreEnmas;
        }

        foreach ($proceso->documentos as $documento) {
            $index++;
            $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
            $titulos_documentos[] = $documento["titulo"];
        }

        $lista_signatarios = "";
        $index = 0;
        foreach ($proceso->firmantes as $firmante) {
            $index++;
            $lista_signatarios .= $index . " - " . $firmante["nombre"] . "<br/>";
        }

        $cuerpo_email = htmlspecialchars_decode($cuerpo_email);
        $cuerpo_email = $this->reemplazarTags(
            $cuerpo_email,
            $titulo_proceso,
            $compania,
            $lista_signatarios,
            $lista_documentos
        );

        $dias_validez_firma_personal = Config::get('app.dias_validez_firma_personal_stupendo');
        foreach ($proceso->firmantes as $firmante) {
            $se_le_debe_enviar = $this->seDebeEnviarInvitacionAFirmar($proceso, $firmante, $id_usuario_receptor);


            if (
                (
                    $proceso->orden == 1 && $se_le_debe_enviar &&
                    (
                        (isset($proceso["revisiones"]) && $proceso["revisiones"]["estado_revision"] == 1)
                        ||
                        !isset($proceso["revisiones"])
                    ) && (
                        (isset($firmante["revisiones"]) && $firmante["revisiones"]["estado_revision"] == 1)
                        || !isset($firmante["revisiones"])
                    )
                )
                ||
                (
                    $proceso->orden == 2 && $se_le_debe_enviar &&
                    (
                        (isset($proceso["revisiones"]) && $proceso["revisiones"]["estado_revision"] == 1)
                        ||
                        !isset($proceso["revisiones"])
                    ) && (
                        (isset($firmante["revisiones"]) && $firmante["revisiones"]["estado_revision"] == 1)
                        ||
                        !isset($firmante["revisiones"])
                    ) && $this->esSuTurno($proceso->_id, $firmante["id_cliente_receptor"])
                )
            ) {
                $telefono = $firmante["telefono"];
                $usuario_receptor = Usuarios::find($firmante["id_usuario"]);

                if (PerfilesController::EsUsuarioNuevo($firmante["id_usuario"])) {
                    $password_autogenerado = substr(md5($firmante["id_usuario"]), -8);
                    $password_autologin = $password_autogenerado;
                    $usuario_receptor->password = bcrypt($password_autogenerado);
                    $mostrar_credenciales = true;
                    $usuario_receptor->save();
                    
                    if (Config::get('app.sms_anyway_active')) {
                        try {
                            $sms->Enviar_SMS_Invitacion_Firmar(
                                $telefono,
                                count($proceso->documentos),
                                true,
                                "",
                                $usuario_receptor->email,
                                $password_autogenerado,
                                $cliente_identificacion,
                                $cliente_identificacion
                            );
                        } catch (Exception $e) {
                            Log::error("No se pudo enviar el SMS a: $telefono." . $e->getMessage());
                        }
                    }
                } else {
                    if (Config::get('app.sms_anyway_active')) {
                        try {
                            $password_autogenerado = $usuario_receptor->password;
                            $password_autologin = $password_autogenerado;
                            $sms->Enviar_SMS_Invitacion_Firmar(
                                $telefono,
                                count($proceso->documentos),
                                false,
                                $compania,
                                "",
                                "",
                                $cliente_identificacion,
                                $cliente_identificacion
                            );
                        } catch (Exception $e) {
                            Log::error("No se pudo enviar el SMS a: $telefono." . $e->getMessage());
                        }
                    } else {
                        $password_autologin = $usuario_receptor->password;
                    }
                }

                $inicio_vigencia_enlace = new UTCDateTime(Carbon::now()->getTimestamp() * 1000);
                
                $id_proceso = $proceso->_id;
                $alc = new AutoLoginController();
                $dataUrl = array(
                    'id_cliente' => $firmante["id_cliente_receptor"],
                    'id_proceso' => $id_proceso,
                    'pass' => $password_autologin,
                    'email' => $usuario_receptor->email,
                    'inicio_vigencia_enlace' => $inicio_vigencia_enlace
                );
                $dataUrl_encypt = $alc->encriptDatosAutologin($dataUrl);
                $enlace = (Config::get('app.url') . '/docs_electronicos/autologin/' . $dataUrl_encypt);

                $arretiquetas = array(
                    "banner_url",
                    "cuerpo_email",
                    "compania",
                    "lista_documentos",
                    "lista_signatarios",
                    "titulo_proceso",
                    "enlace",
                    "mostrar_credenciales",
                    "email",
                    "password",
                    "dias_validez_firma_personal",
                    'es_invitacion',
                    'titulos_documentos'
                );
                $arrvalores = array(
                    $banner_url,
                    $cuerpo_email,
                    $compania,
                    $lista_documentos,
                    $lista_signatarios,
                    $titulo_proceso,
                    $enlace,
                    $mostrar_credenciales,
                    $usuario_receptor->email,
                    $password_autogenerado,
                    $dias_validez_firma_personal,
                    true,
                    $titulos_documentos
                );

                $data_mailgun = array();
                $data_mailgun["X-Mailgun-Tag"] = "monitoreo_proceso_signatarios_de";
                $data_mailgun["X-Mailgun-Track"] = "yes";
                $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $proceso->_id]);

                $mail_view = "emails.doc_electronicos.$cliente_emisor->identificacion.invitacion";
                if (!$cliente_emisor->tieneVistaEmailPersonalizada($mail_view)) {
                    $mail_view = 'emails.doc_electronicos.invitacion';
                }
                $arr_res = EnviarCorreo(
                    $mail_view,
                    $de,
                    $asunto,
                    $usuario_receptor->email,
                    $usuario_receptor->nombre,
                    $arretiquetas,
                    $arrvalores,
                    null,
                    $data_mailgun,
                    $nombreEnmas,
                    $correoEnmas
                );

                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
                if ($Res > 0) {
                    $this->actualizarMonitoreoProcesoSignatarios($proceso, $usuario_receptor->email, 1);
                    $Mensaje = "El correo de citación fue enviado correctamente a " . $usuario_receptor->email;
                    Log::info(
                        "Resultado enviarEnlaceInvitacion() correo en Proceso " . $proceso->_id . ": " . $Mensaje
                    );
                } else {
                    Log::info(
                        "Resultado enviarEnlaceInvitacion() correo en Proceso " . $proceso->_id . ": " . $Mensaje
                    );
                }
            }
        }
        if ($Res == 0) {
            Log::error("enviarEnlaceInvitacion(): $Res : $Mensaje ");
            $Mensaje = "El usuario será citado cuando corresponda su turno.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    private function seDebeEnviarInvitacionAFirmar($proceso, $firmante, $id_usuario_receptor)
    {
        $se_debe = ($id_usuario_receptor == null && $firmante["id_estado_correo"] == 0)
            || ($id_usuario_receptor == $firmante["id_usuario"] && $firmante["id_estado_correo"] != 0);


        if ($se_debe && $proceso->via == "FV") {
            $identificacion_firmante = $firmante['identificacion'];
            $email_firmante = $firmante['email'];
            $brokerfirmador = Broker::where("identificacion", $identificacion_firmante)->where('activo', true)->first(
            );
            if ($brokerfirmador) {
                $se_debe = true;
            } else {
                $brokerfirmador = Broker::where("email", $email_firmante)->where('activo', true)->first();
                if ($brokerfirmador) {
                    $se_debe = true;
                } else {
                    $se_debe = false;
                }
            }
            Log::info("Se debe enviar invitando a firmar a $email_firmante : $se_debe");
        }
        return $se_debe;
    }


    private function reemplazarTags($cuerpo, $titulo_proceso, $compania, $lista_participantes, $lista_documentos)
    {
        try {
            $cuerpo = str_replace("NOMBRE_COMPANIA", $compania, $cuerpo);
            $cuerpo = str_replace("TITULO_PROCESO", $titulo_proceso, $cuerpo);
            $cuerpo = str_replace("LISTA_PARTICIPANTES", $lista_participantes, $cuerpo);
            if ($lista_documentos) {
                $cuerpo = str_replace("LISTA_DOCUMENTOS", $lista_documentos, $cuerpo);
            }
            return $cuerpo;
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
            Log::info("El error es " . $Mensaje . " el cuerpo que llega es este " . $cuerpo);
            return $cuerpo;
        }
    }

    private function getMaxIdPropioProceso()
    {
        $max_id_propio = 0;
        $ultimo_proceso = Proceso::orderBy("id_propio", "desc")->first(["id_propio"]);
        if ($ultimo_proceso) {
            $max_id_propio = (int)$ultimo_proceso->id_propio;
        }
        return $max_id_propio;
    }

    public static function getEstadoCorreo($id_estado_correo)
    {
        $estados_correos = array();
        $estados_correos[-1] = "No identificado";
        $estados_correos[0] = "Sin enviar";
        $estados_correos[1] = "Enviado";
        $estados_correos[2] = "Recibido";
        $estados_correos[3] = "Leído";
        $estados_correos[4] = "Leído (Link accedido)";
        $estados_correos[5] = "Marcado como spam";
        $estados_correos[10] = "Error en la entrega";
        return $estados_correos[$id_estado_correo] ?: "No identificado";
    }

    public function actualizarMonitoreoProcesoSignatarios($proceso, $recipient, $id_estado_correo, $id_proceso = null)
    {
        if (!empty($id_proceso)) {
            $proceso = Proceso::find($id_proceso);
        }
        if ($proceso) {
            $firmantes_modificado = array();
            foreach ($proceso->firmantes as $firmante) {
                if ($firmante["email"] == $recipient) {
                    $id_estado_anterior = isset($firmante["id_estado_correo"]) ? $firmante["id_estado_correo"] : -1;
                    if ($id_estado_correo != $id_estado_anterior) {
                        $firmante["id_estado_correo"] = $id_estado_correo;
                        $firmante["estado_correo"] = self::getEstadoCorreo($id_estado_correo);
                    }
                }
                array_push($firmantes_modificado, $firmante);
            }
            $proceso->firmantes = $firmantes_modificado;
            $proceso->save();
        }
    }

    public function getClienteReceptorFromProceso($proceso, $id_usuario)
    {
        foreach ($proceso->firmantes as $firmante) {
            if ($firmante["id_usuario"] == $id_usuario) {
                return Cliente::find($firmante["id_cliente_receptor"]);
            }
        }
    }

    public function receptorCreadoEnProceso($proceso, $id_usuario)
    {
        foreach ($proceso->firmantes as $firmante) {
            if ($firmante["id_usuario"] == $id_usuario && $firmante["creado_en_proceso"] == true) {
                return true;
            }
        }
        return false;
    }

    public function notificarARevisoresSalidaPendiente($proceso)
    {
        $arretiquetas = array("banner_url", "razon_social_emisor", "titulo_proceso", "nombre_usuario_emisor", "enlace");
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $razon_social_emisor = $cliente_emisor["nombre_identificacion"];
        $titulo_proceso = $proceso->titulo;
        $nombre_usuario_emisor = Usuarios::find($proceso->id_usuario_emisor)["nombre"];
        $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
        $pc = new PreferenciasController();
        $banner_url = $pc->getURLEmailBanner($cliente_emisor);
        $de = "Stupendo";
        $asunto = "Proceso pendiente de revisión";
        $arreglo_emails = array();
        $arreglo_nombres = array();

        $nombreEnmas = (isset($proceso->nombre_enmas) && !empty($proceso->nombre_enmas)) ? $proceso->nombre_enmas : Preferencia::get_default_email_data(
            $proceso->id_cliente_emisor,
            "de_email"
        );
        $correoEnmas = (isset($proceso->correo_enmas) && !empty($proceso->correo_enmas)) ? $proceso->correo_enmas : Preferencia::get_default_email_data(
            $proceso->id_cliente_emisor,
            "de_enmascaramiento"
        );


        foreach (($proceso->revisiones)["revisores"] as $id_usuario_revisor) {
            $usuario_revisor = Usuarios::find($id_usuario_revisor);
            array_push($arreglo_emails, $usuario_revisor["email"]);
            array_push($arreglo_nombres, $usuario_revisor["nombre"]);
        }
        $arreglo_adjuntos = array();
        foreach ($proceso->documentos as $documento) {
            @array_push($arreglo_adjuntos, storage_path($documento["camino_original"]));
        }
        $arrvalores = array($banner_url, $razon_social_emisor, $titulo_proceso, $nombre_usuario_emisor, $enlace);
        $arr_res = EnviarCorreo(
            'emails.doc_electronicos.notifica_revisores_salida_pendiente',
            $de,
            $asunto,
            $arreglo_emails,
            $arreglo_nombres,
            $arretiquetas,
            $arrvalores,
            $arreglo_adjuntos,
            null,
            $nombreEnmas,
            $correoEnmas
        );
        $Res = $arr_res[0];
        $Mensaje = $arr_res[1];
        return array("Res" => $Res, "Mensaje" => $Mensaje);
    }

    public function getIdUsuarioActualInvitado($id_proceso)
    {
        $proceso = Proceso::find($id_proceso);
        if ($proceso) {
            $cantidad_documentos_total = count($proceso->documentos);
            foreach ($proceso->firmantes as $firmante) {
                $id_usuario = $firmante["id_usuario"];
                $cant_firmados = 0;
                foreach ($proceso->historial as $hito) {
                    if ($hito["accion"] == -1) {
                        return false;
                    } else {
                        if ($hito["id_usuario"] == $id_usuario && $hito["accion"] == 1) {
                            $cant_firmados++;
                        }
                    }
                }
                if ($cant_firmados < $cantidad_documentos_total) {
                    return $id_usuario;
                }
            }
            return false;
        }
    }

    public function firmarTodos(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $actor = $request->input("HiddenActor");
            Filtrar($actor, "STRING");
            $id_proceso = DesencriptarId($request->input("HiddenIdProceso"));
            Filtrar($id_proceso, "STRING");
            $proceso = Proceso::find($id_proceso);
            if (!$proceso) {
                $Res = -1;
                $Mensaje = "Proceso inexistente";
            } else {
                foreach ($proceso->documentos as $documento) {
                    if ($Res >= 0) {
                        $new_request = new Request();
                        $new_request->merge(
                            array(
                                "HiddenActor" => $actor,
                                "HiddenIdProceso" => EncriptarId($id_proceso),
                                "HiddenIdDocumento" => $documento["id_documento"]
                            )
                        );
                        $resultado = $this->firmarDocumento($new_request);
                        $arr_res = json_decode($resultado->getContent());
                        $Res = $arr_res->Res;
                        $Mensaje = $arr_res->Mensaje . " (" . $documento["id_documento"] . ")";
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "Todos los documentos fueron firmados con éxito.";
            $proceso->InvocarWebServiceRetroalimentacion($proceso);
        }

        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function firmarDocumento(Request $request)
    {
        try {
            Log::info("Inicio firmarDocumento(). " . microtime(true));
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
                $proceso = Proceso::find($id_proceso);
                if (!$proceso) {
                    $Res = -1;
                    $Mensaje = "Proceso inexistente";
                } else {
                    $id_estado_actual_proceso = $proceso->id_estado_actual_proceso;
                    if($id_estado_actual_proceso < 2) {
                        $id_propio = $proceso->id_propio;
                        $id_cliente_emisor = $proceso->id_cliente_emisor;
                        $firma_emisor = $proceso->firma_emisor;
                        $firma_stupendo = 0;
                        $referencia_paginas = $proceso->referencia_paginas ?: 0;
                        $origen_exigido = $proceso->origen_exigido ?: 0;
                    } else
                    {
                        $Res = -1;
                        $Mensaje = "El proceso fue anulado por el emisor o rechazado por otro participante";
                    }
                }
            }


            if ($Res >= 0) {
                if (!in_array(strtoupper($actor), array("EMISOR", "RECEPTOR", "STUPENDO"))) {
                    $Res = -2;
                    $Mensaje = "Actor indefinido";
                }
            }
            if ($Res >= 0 && strtoupper($actor) == "RECEPTOR") {
                $fc = new FirmasController();
                $id_cliente_receptor = empty($request->input("HiddenIdCliente")) ? session()->get(
                    "id_cliente"
                ) : $request->input("HiddenIdCliente");
                Filtrar($id_cliente_receptor, "STRING");
                $origen_firma_propia = $fc->GetOrigenFirma($id_cliente_receptor);
                if ((int)$origen_exigido == 1 && (int)$origen_firma_propia != 1) {
                    $Res = -3;
                    $Mensaje = "No puede firmar. El proceso fue definido para solamente usar firmas Acreditada y tu firma actual incumple el requisito.";
                } else {
                    if ((int)$origen_exigido == 2 && (int)$origen_firma_propia != 2) {
                        $Res = -4;
                        $Mensaje = "No puede firmar. El proceso fue definido para solamente usar firma simple Stupendo y tu firma actual incumple el requisito.";
                    }
                }
            }
            if ($Res >= 0 && $proceso->bloqueado == true && $actor == "RECEPTOR") {
                $Res = -1;
                $Mensaje = "El proceso se encuentra bloqueado por otro signatario en este momento. Por favor, espera unos segundos.";
            }
            if ($Res >= 0) {
                $proceso->bloqueado = true;
                $proceso->save();
            }
            if ($Res >= 0) {
                $ya_fueron_agregadas_paginas_en_blanco = false;
                foreach ($proceso->historial as $hito) {
                    if ((int)$hito["id_documento"] == (int)$id_documento) {
                        $ya_fueron_agregadas_paginas_en_blanco = true;
                        break;
                    }
                }
            }

            if ($Res >= 0 && !$ya_fueron_agregadas_paginas_en_blanco) {
                $arr_res = $this->agregarPaginasFirma($id_proceso, $id_documento, $referencia_paginas);
                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
                $camino_pdf_a_firmar = $arr_res[2]["camino_temporal_con_paginas"];
                $camino_es_temporal = true;
            }
            if ($Res >= 0) {
                foreach ($proceso->historial as $hito) {
                    if ($hito["accion"] == 1 && $hito["id_documento"] == $id_documento) {
                        $camino_pdf_a_firmar = storage_path($hito["camino"]);
                        $camino_es_temporal = false;
                    }
                }
            }
            if ($Res >= 0 && strtoupper($actor) == "EMISOR") {
                $id_usuario = $proceso->id_usuario_emisor;
                $id_cliente_receptor = null;
                $id_estado_previo_documento = 0;
                $camino = "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/firmado_emisor/$id_documento.pdf";
                $id_cliente = $id_cliente_emisor;
            } else {
                if ($Res >= 0 && strtoupper($actor) == "RECEPTOR") {
                    $id_usuario = empty($request->input("HiddenIdUsuario")) ? session()->get(
                        "id_usuario"
                    ) : $request->input("HiddenIdUsuario");
                    Filtrar($id_usuario, "STRING");
                    $id_cliente_receptor = empty($request->input("HiddenIdCliente")) ? session()->get(
                        "id_cliente"
                    ) : $request->input("HiddenIdCliente");
                    Filtrar($id_cliente_receptor, "STRING");
                    $id_estado_previo_documento = 1;
                    $camino = "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/$id_cliente_receptor/$id_documento.pdf";
                    $id_cliente = $id_cliente_receptor;
                    if (self::documentoYaFirmado($proceso, $id_usuario, $id_documento)) {
                        $proceso->bloqueado = false;
                        $proceso->save();
                        return response()->json(
                            array("Res" => 0, "Mensaje" => "Ya el documento $id_documento ha sido firmado usted"),
                            200
                        );
                    }
                }
            }
            if ($Res >= 0) {
                $arr_res = $this->incrustarFirmaPLOP(
                    $id_proceso,
                    $id_documento,
                    $actor,
                    $camino_pdf_a_firmar,
                    FormatearMongoISODate($momento_accion),
                    $id_cliente
                );
                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
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
                if (strtoupper($actor) == "EMISOR") {
                    $hito["id_cliente_emisor"] = $id_cliente_emisor;
                }
                array_push($historial, $hito);
                $proceso->id_estado_actual_proceso = 1;
                $proceso->historial = $historial;
                $proceso->bloqueado = false;
                $proceso->save();
            }
            if ($Res >= 0 && $actor = "RECEPTOR") {
                if ($Res >= 0 && $proceso->orden == 2
                    && !$this->hayFirmasReceptoresPendientes($id_proceso, null, $id_cliente_receptor)
                    && $this->hayFirmasReceptoresPendientes($id_proceso)) {
                    $this->notificarARevisoresEntradaPendiente($proceso);
                    $this->enviarEnlaceInvitacion($proceso);
                }
                if ($Res >= 0 && !$this->hayFirmasReceptoresPendientes($id_proceso, $id_documento)) {
                    if ($firma_emisor == 2) {
                        $arr_res = $this->incrustarFirmaPLOP(
                            $id_proceso,
                            $id_documento,
                            "EMISOR",
                            storage_path($camino),
                            FormatearMongoISODate($momento_accion),
                            $proceso->id_usuario_emisor
                        );
                        $Res = $arr_res[0];
                        $Mensaje = $arr_res[1];
                        $camino = "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/firmado_emisor/$id_documento.pdf";
                        $accion = 1;
                        $historial = $proceso->historial;
                        $id_estado_actual_documento = 2;
                        $hito = array(
                            "id_usuario" => $id_usuario,
                            "id_cliente_receptor" => null,
                            "id_documento" => (int)$id_documento,
                            "accion" => $accion,
                            "camino" => $camino,
                            "id_estado_previo_documento" => $id_estado_previo_documento,
                            "id_estado_actual_documento" => $id_estado_actual_documento,
                            "momento_accion" => $momento_accion,
                            "id_cliente_emisor" => $id_cliente_emisor
                        );
                        array_push($historial, $hito);
                        $proceso->historial = $historial;
                        $proceso->save();
                    }
                  if ($firma_stupendo == 2) {
                        $arr_res = $this->incrustarFirmaPLOP(
                            $id_proceso,
                            $id_documento,
                            "STUPENDO",
                            storage_path($camino),
                            FormatearMongoISODate($momento_accion),
                            $id_usuario
                        );
                        $Res = $arr_res[0];
                        $Mensaje = $arr_res[1];
                    }
                }
                if ($Res >= 0 && !$this->hayFirmasReceptoresPendientes($id_proceso)) {
                    $proceso->id_estado_actual_proceso = 2;
                    $proceso->save();
                    $arr_res = $this->enviarCorreoNotificaFinalizacion($id_proceso);
                    $Res = $arr_res[0];
                    $Mensaje = $arr_res[1];
                }
            }
            Log::info("Después de Incrustación de Firma. " . microtime(true));
            if ($Res >= 0 && $proceso->bloqueado == true) {
                $proceso->bloqueado = false;
                $proceso->save();
                $arr_res = $this->enviarCorreoNotificaFinalizacion($id_proceso);
                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
                $proceso->InvocarWebServiceRetroalimentacion($proceso);
            }

            if ($Res >= 0 && $proceso->bloqueado == true) {
                $proceso->bloqueado = false;
                $proceso->save();
            }
            if ($Res >= 0) {
                Auditoria::Registrar(
                    7,
                    $id_usuario,
                    $id_cliente,
                    $id_proceso,
                    $id_documento,
                    null,
                    $momento_accion,
                    $proceso->id_cliente_emisor
                );
                $procesoIdFormulario= $proceso->_id;
                $formulario = Formulario_vinculacion::where('id_proceso', $procesoIdFormulario)->first();
                if(isset($formulario)) {
                    $cliente_aseguradora = Clientes::select("parametros")->where(
                        "identificacion",
                        $formulario->ruc_aseguradora
                    )->first();
                    if ($cliente_aseguradora->isFeedBackPersonalizada()) {
                        Log::info("ingresJob gv" . $formulario->_id);
                        $job = (new Retroalimentacion_formularios($formulario->_id, "Poliza"))->onQueue('ReportePersonalizado');
                        $this->dispatch($job);
                    }
                }
            }
            if ($Res >= 0) {
                $Res = 1;
                $Mensaje = "El documento fue firmado con éxito.";
                Session::put("de_intentos_envio_sms", 0);
                if ($camino_es_temporal) {
                    @unlink($camino_pdf_a_firmar);
                }
                Log::info("Fin firmarDocumento(). " . microtime(true));
            }
        } catch (Exception $e) {
            $Res = -50;
            $Mensaje = $e->getMessage();
            Log::error("Exception en firmarDocumento(): " . $e->getMessage() . " - " . $e->getTraceAsString());
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    function agregarPaginasFirma($id_proceso, $id_documento, $referencia_paginas = 0)
    {
        $Res = 0;
        $Mensaje = "";
        $data = null;
        try {
            if ($Res >= 0) {
                $proceso = Proceso::find($id_proceso);
                $firma_emisor = $proceso->firma_emisor;
                $firma_stupendo = 0;
                $camino_original = null;
                foreach ($proceso->documentos as $documento) {
                    if ($id_documento == $documento["id_documento"]) {
                        $camino_original = storage_path($documento["camino_original"]);
                    }
                }
                if (empty($camino_original)) {
                    $Res = -1;
                    $Mensaje = "No se pudo leer el documento original.<br/>";
                } else {
                    $camino_fuente = $camino_original;
                }
            }

            if ($Res >= 0) {
                $carpeta_temporal = sys_get_temp_dir() . "/doc_electronicos/" . session()->get("id_usuario");
                if (!is_dir($carpeta_temporal)) {
                    mkdir($carpeta_temporal, 0777, true);
                }
                $camino_temporal_con_referencia = $carpeta_temporal . "/WM_" . date("U") . ".pdf";
                $camino_temporal_con_paginas = $carpeta_temporal . "/P_" . date("U") . ".pdf";
            }
            if ($Res >= 0) {
                $arr_info = $this->getInfoPDFOriginal($id_proceso, $id_documento);
                $Res = $arr_info["Res"];
                $Mensaje = $arr_info["Mensaje"];
                $info = $arr_info["info"];
            }
            if ($Res >= 0 && $referencia_paginas == 1) {
                $camino_pagina_watermark = $this->getCaminoPaginaWatermark(
                    $id_proceso,
                    $id_documento,
                    $info["menor_ancho_mm"],
                    $info["mayor_altura_mm"]
                );

                $pdf = new Pdf($camino_fuente);
                $pdf->background($camino_pagina_watermark);
                $resultado = $pdf->saveAs($camino_temporal_con_referencia);
                if (!$resultado) {
                    $Res = -4;
                    $Mensaje = "Ocurrió un error adicionando la referencia a las páginas del documento $id_documento";
                } else {
                    $camino_fuente = $camino_temporal_con_referencia;
                }
            }
            if ($Res >= 0) {
                $camino_pagina_firma = $this->getCaminoPaginaFirma(
                    $id_proceso,
                    $id_documento,
                    $info["width_ultima_pagina_mm"],
                    $info["height_ultima_pagina_mm"]
                );
                if (empty($camino_pagina_firma)) {
                    $Res = -2;
                    $Mensaje = "No se pudo cargar la página establecida de firmas.<br/>";
                }
            }
            if ($Res >= 0) {
                $cantidad_firmantes_fijos_adicionales = 0;
                if ($firma_emisor != 0) {
                    $cantidad_firmantes_fijos_adicionales++;
                }
                if ($firma_stupendo != 0) {
                    $cantidad_firmantes_fijos_adicionales++;
                }
                $cantidad_firmantes = $this->getCantidadFirmantes($id_proceso);
                $cantidad_de_firmas_por_pagina = $info["cantidad_firmas_por_pagina"];
                $cantidad_de_paginas_por_agregar = ceil(
                    ($cantidad_firmantes + $cantidad_firmantes_fijos_adicionales) / $cantidad_de_firmas_por_pagina
                );
            }
            if ($Res >= 0) {
                try {
                    $arreglo_documentos["ZZZ"] = $camino_fuente;

                    for ($index = 0; $index < $cantidad_de_paginas_por_agregar; $index++) {
                        $arreglo_documentos[EnteroEnLetrasPersonal($index)] = $camino_pagina_firma;
                    }

                    $pdf = new Pdf($arreglo_documentos);

                    foreach ($arreglo_documentos as $letra => $camino) {
                        $pdf->cat(1, "end", $letra);
                    }
                    $resultado = $pdf->saveAs($camino_temporal_con_paginas);

                    if (!$resultado) {
                        $Res = -5;
                        $Mensaje = "Ocurrió un error adicionando la(s) página(s) de firmas al documento. " . $pdf->getError(
                            );
                        Log::info("-5 : $Mensaje.");
                    } else {
                        $Res = $cantidad_de_paginas_por_agregar;
                        $data = array(
                            "cantidad_paginas_agregadas" => $cantidad_de_paginas_por_agregar,
                            "camino_temporal_con_paginas" => $camino_temporal_con_paginas
                        );
                    }
                } catch (Exception $e) {
                    $Res = -6;
                    $Mensaje = $e->getMessage();
                }
            }
            if ($Res >= 0) {
                @unlink($camino_pagina_watermark);
                @unlink($camino_pagina_firma);
                @unlink($camino_temporal_con_referencia);
            } else {
                Log::error("ProcesoController->agregarPaginasFirma $Res : $Mensaje");
            }
        } catch (Exception $e) {
            $Res = -7;
            $Mensaje = "Ocurrió un error adicionando la(s) página(s) de firmas al documento o la referencia en las páginas. " . $e->getMessage(
                );
            Log::error("Exception $Res : $Mensaje. " . $e->getMessage());
        } catch (Throwable $t) {
            $Res = -8;
            $Mensaje = "Ocurrió un error adicionando la(s) página(s) de firmas al documento o la referencia en las páginas. " . $t->getMessage(
                );
            Log::error("$Res : $Mensaje. " . $t->getMessage());
        }
        return array($Res, $Mensaje, $data);
    }

    public function optimizarPDF($input, $output)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $plop = new PLOP();
            if (Config::get('app.usar_licencia_plop') == true) {
                $plop->set_option("license=" . Config::get('app.plop_license'));
            }
            if ($Res >= 0) {
                if (!file_exists($input)) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error optimizando el PDF. El archivo origen no existe.";
                }
            }
            if ($Res >= 0) {
                $pdf_origen = $plop->open_document($input, "");
                if ($pdf_origen == 0) {
                    $Res = -2;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $nuevo_pdf = $plop->create_document($output, "optimize=all linearize=true input=" . $pdf_origen);
                if ($nuevo_pdf == 0) {
                    $Res = -3;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $resultado_cierre = $plop->close_document($pdf_origen, "");
                if ($resultado_cierre == 0) {
                    $Res = -4;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
        } catch (Exception $e) {
            $Res = -5;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El PDF fue optimizado correctamente.";
        } else {
            Log::error("optimizarPDF $Res : $Mensaje.");
        }
        return array($Res, $Mensaje);
    }

    private function getInfoPaginasPDF($camino_pdf)
    {
        $Res = 0;
        $Mensaje = "";
        $arr_info_pagina = array();
        try {
            if ($Res >= 0) {
                $plop = new PLOP();
                if (Config::get('app.usar_licencia_plop') == true) {
                    $plop->set_option("license=" . Config::get('app.plop_license'));
                }
                $pdf_original = $plop->open_document($camino_pdf, "");
                if ($pdf_original == 0) {
                    $Res = -1;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $cantidad_paginas_originales = $plop->pcos_get_number($pdf_original, "length:pages");
                if (empty($cantidad_paginas_originales)) {
                    $Res = -2;
                    $Mensaje = "No se pudo leer la cantidad de páginas.<br/>";
                }
            }
            if ($Res >= 0) {
                for ($pagina = 0; $pagina < $cantidad_paginas_originales; $pagina++) {
                    $arr_info_pagina[$pagina]["width_ptos"] = round(
                        $plop->pcos_get_number($pdf_original, "pages[$pagina]/width")
                    );
                    $arr_info_pagina[$pagina]["height_ptos"] = round(
                        $plop->pcos_get_number($pdf_original, "pages[$pagina]/height")
                    );
                    if (empty($arr_info_pagina[$pagina]["width_ptos"])) {
                        $Res = -4;
                        $Mensaje = "No se pudo determinar el width de la página $pagina.<br/>";
                    } else {
                        $arr_info_pagina[$pagina]["width_mm"] = round(
                            $arr_info_pagina[$pagina]["width_ptos"] * 0.352778
                        );
                    }
                    if (empty($arr_info_pagina[$pagina]["height_ptos"])) {
                        $Res = -5;
                        $Mensaje = "No se pudo determinar el height de la página $pagina.<br/>";
                    } else {
                        $arr_info_pagina[$pagina]["height_mm"] = round(
                            $arr_info_pagina[$pagina]["height_ptos"] * 0.352778
                        );
                    }
                }
            }
            if ($Res >= 0) {
                $resultado_cierre = $plop->close_document($pdf_original, "");
                if ($resultado_cierre == 0) {
                    $Res = -3;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = $cantidad_paginas_originales;
            $Mensaje = "La información fue leida con éxito";
        }
        return array("Res" => $Res, "Mensaje" => $Mensaje, "arr_info_pagina" => $arr_info_pagina);
    }

    private function getInfoPDFOriginal($id_proceso, $id_documento)
    {
        Log::info("Inicio getInfoPDFOriginal" . microtime(true));
        $Res = 0;
        $Mensaje = "";
        $info = null;
        $arr_info_pagina = array();
        try {
            if ($Res >= 0) {
                $proceso = Proceso::find($id_proceso);
                $camino_original = null;
                foreach ($proceso->documentos as $documento) {
                    if ($id_documento == $documento["id_documento"]) {
                        $camino_original = storage_path($documento["camino_original"]);
                    }
                }
                if (empty($camino_original)) {
                    $Res = -1;
                    $Mensaje = "No se pudo leer el documento original.<br/>";
                }
            }
            if ($Res >= 0) {
                $plop = new PLOP();
                if (Config::get('app.usar_licencia_plop') == true) {
                    $plop->set_option("license=" . Config::get('app.plop_license'));
                }
                $pdf_original = $plop->open_document($camino_original, "");
                if ($pdf_original == 0) {
                    $Res = -2;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $cantidad_paginas_originales = $plop->pcos_get_number($pdf_original, "length:pages");
                if (empty($cantidad_paginas_originales)) {
                    $Res = -3;
                    $Mensaje = "No se pudo leer la cantidad de páginas.<br/>";
                } else {
                    $indice_ultima_pagina = $cantidad_paginas_originales - 1;
                }
            }
            if ($Res >= 0) {
                $arr_data = $this->getInfoPaginasPDF($camino_original);
                $Res = $arr_data["Res"];
                $Mensaje = $arr_data["Mensaje"];
                $arr_info_pagina = $arr_data["arr_info_pagina"];
            }
            if ($Res >= 0) {
                $mayor_ancho_mm = 0;
                $mayor_altura_mm = 0;
                $menor_ancho_mm = 1000000;
                $menor_altura_mm = 1000000;
                foreach ($arr_info_pagina as $pagina => $arreglo) {
                    if ($arreglo["width_mm"] > $mayor_ancho_mm) {
                        $mayor_ancho_mm = $arreglo["width_mm"];
                    }
                    if ($arreglo["height_mm"] > $mayor_altura_mm) {
                        $mayor_altura_mm = $arreglo["height_mm"];
                    }
                    if ($arreglo["width_mm"] < $menor_ancho_mm) {
                        $menor_ancho_mm = $arreglo["width_mm"];
                    }
                    if ($arreglo["height_mm"] < $menor_altura_mm) {
                        $menor_altura_mm = $arreglo["height_mm"];
                    }
                }
            }
            if ($Res >= 0) {
                $width_primera_pagina_ptos = $arr_info_pagina[0]["width_ptos"];
                $width_primera_pagina_mm = $arr_info_pagina[0]["width_mm"];
                $height_primera_pagina_ptos = $arr_info_pagina[0]["height_ptos"];
                $height_primera_pagina_mm = $arr_info_pagina[0]["height_mm"];
                $width_ultima_pagina_ptos = $arr_info_pagina[$indice_ultima_pagina]["width_ptos"];
                $width_ultima_pagina_mm = $arr_info_pagina[$indice_ultima_pagina]["width_mm"];
                $height_ultima_pagina_ptos = $arr_info_pagina[$indice_ultima_pagina]["height_ptos"];
                $height_ultima_pagina_mm = $arr_info_pagina[$indice_ultima_pagina]["height_mm"];
            }
            if ($Res >= 0) {
                $resultado_cierre = $plop->close_document($pdf_original, "");
                if ($resultado_cierre == 0) {
                    $Res = -5;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $height_fijo_cabecera = 75;
                $height_disponible = $height_ultima_pagina_mm - $height_fijo_cabecera;
                $height_estandar_firma_margen = 40;
                $cantidad_firmas_por_pagina = floor($height_disponible / $height_estandar_firma_margen);
                if ($cantidad_firmas_por_pagina < 1) {
                    $Res = -6;
                    $Mensaje = "Página demasiado pequeña.<br/>";
                }
            }
        } catch (Exception $e) {
            $Res = -6;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "Se obtuvo toda la información del PDF Original.";
            $info = array(
                "cantidad_paginas_originales" => $cantidad_paginas_originales,
                "cantidad_firmas_por_pagina" => $cantidad_firmas_por_pagina,
                "width_primera_pagina_ptos" => $width_primera_pagina_ptos,
                "width_primera_pagina_mm" => $width_primera_pagina_mm,
                "height_primera_pagina_ptos" => $height_primera_pagina_ptos,
                "height_primera_pagina_mm" => $height_primera_pagina_mm,
                "width_ultima_pagina_ptos" => $width_ultima_pagina_ptos,
                "width_ultima_pagina_mm" => $width_ultima_pagina_mm,
                "height_ultima_pagina_ptos" => $height_ultima_pagina_ptos,
                "height_ultima_pagina_mm" => $height_ultima_pagina_mm,
                "arr_info_pagina" => $arr_info_pagina,
                "mayor_ancho_mm" => $mayor_ancho_mm,
                "mayor_altura_mm" => $mayor_altura_mm,
                "menor_ancho_mm" => $menor_ancho_mm,
                "menor_altura_mm" => $menor_altura_mm
            );
        }
        Log::info("Inicio getInfoPDFOriginal" . microtime(true));
        return array("Res" => $Res, "Mensaje" => $Mensaje, "info" => $info);
    }

    public function getCantidadFirmantes($id_proceso)
    {
        $proceso = Proceso::find($id_proceso);
        if ($proceso) {
            return (int)count($proceso->firmantes);
        }
        return false;
    }

    public function getIdClienteActualInvitado($id_proceso)
    {
        $proceso = Proceso::find($id_proceso);
        if ($proceso) {
            $cantidad_documentos_total = count($proceso->documentos);
            foreach ($proceso->firmantes as $firmante) {
                $id_cliente = $firmante["id_cliente_receptor"];
                $cant_firmados = 0;
                foreach ($proceso->historial as $hito) {
                    if ($hito["accion"] == -1) {
                        return false;
                    } else {
                        if ($hito["id_cliente_receptor"] == $id_cliente && $hito["accion"] == 1) {
                            $cant_firmados++;
                        }
                    }
                }
                if ($cant_firmados < $cantidad_documentos_total) {
                    return $id_cliente;
                }
            }
            return false;
        }
    }

    public function mostrarFiltroProcesos()
    {
        $arretiquetas = array("opciones_estado");
        $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
        $arrvalores = array($opciones_estado);
        return view("doc_electronicos.emisiones.filtro_procesos", array_combine($arretiquetas, $arrvalores));
    }

    public function mostrarListaProcesos(Request $request)
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
        $skip = (int)$request->input('start');
        $take = (int)$request->input('length');
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

        $procesos = Proceso::select(
            "_id",
            "titulo",
            "id_estado_actual_proceso",
            "momento_emitido",
            "id_cliente_emisor",
            "firmantes",
            "revisiones",
            "estado_proceso"
        )->with(
            [
                'cliente_emisor' => function ($query) {
                    $query->select("nombre_identificacion");
                }
            ]
        )->where("firmantes.id_cliente_receptor", $id_cliente);
        $procesos = $procesos->whereNotIn("revisiones.estado_revision", [0, 2]);

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
            $permite_lectura = true;
            $estado_revision = 1;
            foreach ($proceso["firmantes"] as $firmante) {
                if ($firmante["id_cliente_receptor"] == $id_cliente && isset($firmante["revisiones"])) {
                    $permite_lectura = $firmante["revisiones"]["permite_lectura"];
                    $estado_revision = $firmante["revisiones"]["estado_revision"];
                }
            }

            $result[] =
                [
                    "_id" => EncriptarId($proceso["_id"]),
                    "id_cliente_emisor" => EncriptarId($proceso["id_cliente_emisor"]),
                    "emisor" => $proceso["cliente_emisor"]["nombre_identificacion"],
                    "titulo" => $proceso["titulo"],
                    "id_estado_actual_proceso" => $proceso["id_estado_actual_proceso"],
                    "estado_actual_proceso" => EstadoProcesoEnum::toString($proceso["id_estado_actual_proceso"]),
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso["momento_emitido"], "d/m/Y"),
                    "fecha_emision_orden" => FormatearMongoISODate($proceso["momento_emitido"], "U"),
                    "documentos_actuales" => "",
                    "firmantes" => "",
                    "acciones" => "",
                    "puede_firmar" => $this->esSuTurno($proceso["_id"], $id_cliente),
                    "estado_revision" => $estado_revision,
                    "permite_lectura" => $permite_lectura
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
        $proceso = Proceso::find($id_proceso);
        if ($proceso->orden == 1 && $this->hayFirmasReceptoresPendientes($id_proceso, null, $id_cliente_receptor)) {
            return true;
        } else {
            return ($this->getIdClienteActualInvitado($id_proceso) == $id_cliente_receptor);
        }
    }

    public function mostrarAcciones(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $id_cliente_receptor = session()->get("id_cliente");
        $proceso = Proceso::find($id_proceso);
        $tiene_adjuntos = isset($proceso->adjuntos) && count($proceso->adjuntos) > 0;
        $titulo_proceso = $proceso->titulo;
        $tabla_documentos = '';
        foreach ($proceso->documentos as $documento) {
            $id_documento = $documento["id_documento"];
            $titulo_documento = $documento["titulo"];
            $accion = $this->getEstadoIdUsuario($id_proceso, $id_documento, $id_cliente_receptor);
            $boton_pdf = '<img src="/img/iconos/pdf.png" id="IMGDocumento_' . 
                            EncriptarId($id_proceso) . '||' . $id_documento . 
                            '" style="cursor:pointer" data-accion="' . $accion . '" />';
            switch ($accion) {
                case 1:
                    $texto_accion = '<span style="color:green">Firmado</span>';
                    break;
                case -1:
                    $texto_accion = '<span style="color:red">Rechazado</span>';
                    break;
                default:
                    $texto_accion = '<span style="color:#888">En espera</span>';
                    break;
            }
            $tabla_documentos .= '<tr data-tr="tr">' .
                '<td>' . $titulo_documento . '</td>' .
                '<td style="text-align:center">' . $boton_pdf . '</td>' .
                '<td style="text-align:center">' . $texto_accion . '</td>' .
                ($tiene_adjuntos ?
                    '<td style="text-align:center"><a href="#" class="ver-adjuntos-de" data-proceso="' . $id_proceso . '">Ver</a></td>' : 
                    '') .
                '</tr>';
        }
        $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
        $logo = ClienteController::getUrlLogo($cliente_emisor);
        $cant_documentos = count($proceso->documentos);
        
        $clienteActual = getCliente();
        $botonesParaMostrar = 'firmar';

        $esJuridico = $proceso->esFirmanteJuridico($clienteActual->_id);

        $noTieneFirmaVigente = $proceso->noTieneFirmaVigente($clienteActual->_id);

        if ($esJuridico && $noTieneFirmaVigente) {
            $estaSiendoValidadaLaFirma = $proceso->estaSiendoValidadaLaFirma($clienteActual->_id);
            if ($estaSiendoValidadaLaFirma) {
                $botonesParaMostrar = 'validando';
            } else {
                $botonesParaMostrar = 'crearJ';
            }
        } elseif (!$esJuridico && $noTieneFirmaVigente) {
            $botonesParaMostrar = 'crearN';
        }

        return view(
            "doc_electronicos.emisiones.acciones",
            array(
                "id_proceso" => $id_proceso,
                "tiene_adjuntos" => $tiene_adjuntos,
                "botonesParaMostrar" => $botonesParaMostrar,
                "titulo_proceso" => $titulo_proceso,
                "tabla_documentos" => $tabla_documentos,
                "logo" => $logo,
                "cant_documentos" => $cant_documentos,
                "cliente_emisor_id" => $cliente_emisor->identificacion
            )
        );
    }

    public function mostrarAdjuntos(Request $request)
    {
        $id_proceso = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_proceso, "STRING");
        $proceso = Proceso::find($id_proceso);
        $titulo_proceso = $proceso->titulo;
        $lista_adjuntos = [];
        foreach ($proceso->adjuntos as $adjunto) {
            $nombre = $adjunto["nombre_doc"];
            $url = $adjunto["url"];
            $lista_adjuntos[] = array('url' => $url, 'nombre' => $nombre);
        }

        return view(
            "doc_electronicos.emisiones.adjuntos",
            array(
                "id_proceso" => $id_proceso,
                "titulo_proceso" => $titulo_proceso,
                "lista_adjuntos" => $lista_adjuntos
            )
        );
    }

    public function descargarAdjuntos($id_proceso)
    {
        Filtrar($id_proceso, "STRING");
        $proceso = Proceso::find($id_proceso);
        $nombre_comprimido = 'Adjuntos_' . str_replace(' ', '_', $proceso->titulo . '.zip');

        $zip = new ZipArchive();
        $carpeta = sys_get_temp_dir() . "/AdjuntosDE/" . date("Ymd") . "/" . session_id() . "/";
        if (!file_exists($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        $path = $carpeta . $nombre_comprimido;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($proceso->adjuntos as $adjunto) {
            $nombre = $adjunto["nombre_doc"];
            $url = $adjunto["url"];
            $ext = pathinfo($url, PATHINFO_EXTENSION);
            $zip->addFromString($nombre . ".$ext", file_get_contents($url));
        }
        $zip->close();

        return response()->download($path);
    }

    function getEstadoIdUsuario($id_proceso, $id_documento, $id_cliente_receptor)
    {
        $proceso = Proceso::find($id_proceso);
        $accion = 0;
        foreach ($proceso->historial as $hito) {
            if ($hito['id_documento'] == $id_documento && !empty($hito["id_cliente_receptor"]) && $hito['id_cliente_receptor'] == $id_cliente_receptor) {
                $accion = $hito["accion"];
            }
        }
        return $accion;
    }

    function mostrarPDFinMarco($id_proceso, $id_documento)
    {
        $id_proceso = DesencriptarId($id_proceso);
        $proceso = Proceso::find($id_proceso);
        $storage = $proceso["storage"];
        $camino_pdf = '';
        foreach ($proceso->documentos as $documento) {
            if ($documento['id_documento'] == $id_documento) {
                if($storage == Proceso::STORAGE_LOCAL) {
                    $camino_pdf = storage_path($documento["camino_original"]);
                } else {
                    $camino_pdf = $documento["camino_original"];
                }
            }
        }
        return Response::make(
            file_get_contents($camino_pdf),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="$id_documento.pdf"'
            ]
        );
    }

    public function enviarCorreoNotificaFinalizacion($id_proceso)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $arretiquetas = array("banner_url", "compania", "titulo_proceso");
            $proceso = Proceso::find($id_proceso);
            $id_cliente_emisor = $proceso->id_cliente_emisor;
            $id_usuario_emisor = $proceso->id_usuario_emisor;
            $usuario_emisor = Usuarios::find($id_usuario_emisor);
            $email_emisor = $usuario_emisor->email;
            $nombre_emisor = $usuario_emisor->nombre;
            $id_propio = $proceso->id_propio;
            $titulo_proceso = $proceso->titulo;
            $cliente_emisor = Cliente::find($id_cliente_emisor);
            $compania = $cliente_emisor["nombre_identificacion"];
            $firma_stupendo = 0;
            $pc = new PreferenciasController();
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            $de = Preferencia::get_default_email_data($id_cliente_emisor, "de_email");
            $asunto = Preferencia::get_default_email_data($id_cliente_emisor, "asunto_email_finalizado");
            $asunto = str_replace("TITULO_PROCESO", $titulo_proceso, $asunto);
            $enviar_notificacion_a_emisor = Preferencia::get_default_email_data(
                $id_cliente_emisor,
                'enviar_correo_finalizacion_emisor'
            );

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

            foreach ($proceso->firmantes as $firmante) {
                array_push($arr_emails_destinatarios, $firmante["email"]);
                array_push($arr_nombres_destinatarios, $firmante["nombre"]);
            }
            $arr_adjuntos = array();
            foreach ($proceso->documentos as $documento) {
                if ($firma_stupendo != 0) {
                    array_push(
                        $arr_adjuntos,
                        storage_path(
                            "doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/Stupendo/" . $documento["id_documento"] . ".pdf"
                        )
                    );
                } else {
                    $camino = "";
                    foreach ($proceso->historial as $hito) {
                        if ((int)$hito["id_documento"] == (int)$documento["id_documento"]) {
                            $camino = $hito["camino"];
                        }
                    }
                    @array_push($arr_adjuntos, storage_path($camino));
                }
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


            foreach ($proceso->firmantes as $firmante) {
                $this->enviarCredencialesSiEsNecesario($firmante, $cliente_emisor, $proceso);
            }


            if ($Res >= 0) {
                $texto = "El flujo de Documentos Electrónicos relativo al proceso denominado $titulo_proceso, emitido por la compañía $compania, a través de la plataforma Stupendo, ha finalizado completamente, con la aprobación de todos sus participantes.";
                $ruta = "/doc_electronicos/documentos";
                $nc = new NotificacionDEController();
                foreach ($proceso->firmantes as $firmante) {
                    @$nc->CrearNotificacion(
                        $firmante["id_cliente_receptor"],
                        $firmante["id_usuario"],
                        "$titulo_proceso finalizado",
                        $texto,
                        2,
                        $ruta
                    );
                }
                $ruta = "/doc_electronicos/emisiones";
                $response = $nc->CrearNotificacion(
                    $id_cliente_emisor,
                    $id_usuario_emisor,
                    "$titulo_proceso finalizado",
                    $texto,
                    2,
                    $ruta
                );
                $arr_res = json_decode($response->getContent(), true);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return array($Res, $Mensaje);
    }

    public function enviarCredencialesSiEsNecesario($firmante, $cliente_emisor, $proceso)
    {
        try {
            $usuario_retroalimentar = RetroalimentarUsuario::where("id_usuario", $firmante["id_usuario"])
                ->where('hecho', false)->first();
            if ($usuario_retroalimentar) {
                $es_nuevo = PerfilesController::EsUsuarioNuevo($firmante["id_usuario"]);

                if ($es_nuevo) {
                    $usuario_retroalimentar->hecho = true;
                    $url = Config::get('app.url');
                    $banner_url = self::getDefaultPath('banner');

                    $arretiquetas = array(
                        'email',
                        'nombre',
                        'nombre_emisor',
                        'titulo_proceso',
                        'autogenerado',
                        'url',
                        'banner_url'
                    );
                    $arrvalores = array(
                        $firmante["email"],
                        $firmante["nombre"],
                        $cliente_emisor->nombre_identificacion,
                        $proceso->titulo,
                        $usuario_retroalimentar->autogenerado,
                        $url,
                        $banner_url
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
                        'emails.doc_electronicos.credenciales_final_proceso',
                        'Stupendo',
                        "Sus credenciales para acceder a Stupendo",
                        array($firmante["email"]),
                        array($firmante["nombre"]),
                        $arretiquetas,
                        $arrvalores,
                        null,
                        null,
                        $nombreEnmas,
                        $correoEnmas
                    );
                    $Res = $arr_res[0];
                    if ($Res >= 0) {
                        $usuario_retroalimentar->save();
                    }
                }
            }
        } catch (Exception $e) {
            Log::error(
                "Error enviarCredencialesSiEsNecesario()" . $e->getMessage() . " Stacktrace: " . $e->getTraceAsString()
            );
        }
    }

    public function enviarCorreoNotificaRechazo(
        $id_proceso,
        $id_documento,
        $id_usuario_rechaza,
        $camino = null,
        $motivo = null
    ) {
        $Res = 0;
        $Mensaje = "";
        try {
            $arretiquetas = array(
                "banner_url",
                "compania",
                "titulo_proceso",
                "titulo_documento",
                "rechazante",
                "motivo"
            );
            $proceso = Proceso::find($id_proceso);
            $id_usuario_emisor = $proceso->id_usuario_emisor;
            $usuario_emisor = Usuarios::find($id_usuario_emisor);
            $email_emisor = $usuario_emisor->email;
            $nombre_emisor = $usuario_emisor->nombre;
            $titulo_documento = "";
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
                $arr_emails_destinatarios = array($email_emisor);
                $arr_nombres_destinatarios = array($nombre_emisor);
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
            $arrvalores = array($banner_url, $compania, $titulo_proceso, $titulo_documento, $rechazante, $motivo);
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
                $texto = "El Documento Electrónico $titulo_documento, perteneciente al proceso $titulo_proceso, emitido por la compañía $compania, a través de la plataforma Stupendo, ha sido rechazado por el signatario $rechazante.";
                $ruta = "/doc_electronicos/documentos";
                $nc = new NotificacionDEController();
                foreach ($proceso->firmantes as $firmante) {
                    @$nc->CrearNotificacion(
                        $firmante["id_cliente_receptor"],
                        $firmante["id_usuario"],
                        "$titulo_proceso rechazado",
                        $texto,
                        3,
                        $ruta
                    );
                }
                $ruta = "/doc_electronicos/emisiones";
                $response = $nc->CrearNotificacion(
                    $id_cliente_emisor,
                    $id_usuario_emisor,
                    "$titulo_proceso rechazado",
                    $texto,
                    3,
                    $ruta
                );
                $arr_res = json_decode($response->getContent(), true);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return array($Res, $Mensaje);
    }

    public function hayFirmasReceptoresPendientes($id_proceso, $id_documento = null, $id_cliente_receptor = null)
    {
        $proceso = Proceso::find($id_proceso);
        $cantidad_documentos = count($proceso->documentos);
        $cantidad_firmantes = count($proceso->firmantes);
        $cantidad_firmas_reales = 0;

        $firma_emisor = $proceso->firma_emisor;
        $cantidad_firmas_fijas_esperadas = 0;

        if ($proceso->id_estado_actual_proceso == 2 || $proceso->id_estado_actual_proceso == 3) {
            return false;
        } else {
            if (empty($id_documento) && empty($id_cliente_receptor)) {
                $cantidad_firmas_esperadas = $cantidad_documentos * ($cantidad_firmantes + $cantidad_firmas_fijas_esperadas);
                foreach ($proceso->historial as $hito) {
                    if (!empty($hito["id_cliente_receptor"])) {
                        $cantidad_firmas_reales++;
                    }
                }
            } else {
                if (!empty($id_documento) && empty($id_cliente_receptor)) {
                    $cantidad_firmas_esperadas = $cantidad_firmantes + $cantidad_firmas_fijas_esperadas;
                    foreach ($proceso->historial as $hito) {
                        if (!empty(!empty($hito["id_cliente_receptor"])) && $hito["id_documento"] == $id_documento) {
                            $cantidad_firmas_reales++;
                        }
                    }
                } else {
                    if (empty($id_documento) && !empty($id_cliente_receptor)) {
                        $cantidad_firmas_esperadas = $cantidad_documentos;
                        foreach ($proceso->historial as $hito) {
                            if ($hito["id_cliente_receptor"] == $id_cliente_receptor) {
                                $cantidad_firmas_reales++;
                            }
                        }
                    } else {
                        if (!empty($id_documento) && !empty($id_cliente_receptor)) {
                            $cantidad_firmas_esperadas = $cantidad_firmas_fijas_esperadas;
                            foreach ($proceso->historial as $hito) {
                                if ($hito["id_cliente_receptor"] == $id_cliente_receptor && $hito["id_documento"] == $id_documento) {
                                    $cantidad_firmas_reales++;
                                }
                            }
                        }
                    }
                }
            }
            return ($cantidad_firmas_esperadas > $cantidad_firmas_reales);
        }
    }

    public function rechazarDocumento(Request $request)
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
                $id_usuario = session()->get("id_usuario");
                $id_cliente = session()->get("id_cliente");
                $proceso = Proceso::find($id_proceso);
                if (!$proceso) {
                    $Res = -1;
                    $Mensaje = "Proceso inexistente";
                }
            }
            if ($Res >= 0) {
                $momento_accion = new UTCDateTime(DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000);
                $camino = "";
                foreach ($proceso->historial as $hito) {
                    if ($hito["id_documento"] == $id_documento) {
                        $camino = $hito["camino"];
                    }
                }
                if (empty($camino)) {
                    foreach ($proceso->documentos as $documento) {
                        if ($documento["id_documento"] == $id_documento) {
                            $camino = $documento["camino_original"];
                        }
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
                        "camino" => $camino,
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
                    storage_path($camino),
                    $motivo
                );
                $Res = $arr_res[0];
                $Mensaje = $arr_res[1];
                if ($Res >= 0) {
                    Auditoria::Registrar(
                        8,
                        $id_usuario,
                        null,
                        $id_proceso,
                        $id_documento,
                        null,
                        $momento_accion,
                        $proceso->id_cliente_emisor
                    );
                }
                $proceso->InvocarWebServiceRetroalimentacion($proceso);
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El documento fue rechazado. El flujo del proceso ha finalizado.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function incrustarFirmaPLOP(
        $id_proceso,
        $id_documento,
        $actor,
        $camino_origen,
        $momento = null,
        $id_cliente_signatario = null
    ) {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                $arr_res = $this->getInfoPDFOriginal($id_proceso, $id_documento);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
                $info = $arr_res["info"];
                if ($Res >= 0) {
                    $width = $info["width_ultima_pagina_mm"];
                }
            }
            if ($Res >= 0) {
                $proceso = Proceso::find($id_proceso);
                $id_cliente_actual = $id_cliente_signatario;
                $id_cliente_emisor = $proceso->id_cliente_emisor;
                $id_propio = $proceso->id_propio;
                $firma_emisor = $proceso->firma_emisor;
                $firma_stupendo = 0;
                $sello_tiempo = 2;
                $timeserver = Config::get('app.tsa_url');
                $doctimestamp = "";
                $optimize = "";
                $update = "update=true";
                switch (strtoupper($actor)) {
                    case "EMISOR":
                        $id_referencial = $id_cliente_emisor;
                        $carpeta_pdf_firmado_plop_final = storage_path(
                            "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/firmado_emisor"
                        );
                        $firma = Firma::ultimaFirmaDelCliente($id_cliente_emisor);
                        $camino_pfx = storage_path($firma["camino_pfx"]);
                        $certification = "certification=none";
                        if ($sello_tiempo != 0 && $firma_stupendo == 0 && $firma_emisor == 2) {
                            $doctimestamp = "doctimestamp={source={url={$timeserver}}}";
                        }

                        break;
                    case "RECEPTOR":
                        $id_referencial = $id_cliente_actual;
                        $carpeta_pdf_firmado_plop_final = storage_path(
                            "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/$id_cliente_actual"
                        );
                        $firma = Firma::ultimaFirmaDelCliente($id_cliente_signatario);
                        $camino_pfx = storage_path($firma["camino_pfx"]);
                        $certification = "certification=none";
                        $cant_firmas_receptores_en_documento = 0;
                        foreach ($proceso->historial as $hito) {
                            if ((int)$hito["id_documento"] == (int)$id_documento) {
                                $cant_firmas_receptores_en_documento++;
                            }
                        }
                        if ($sello_tiempo != 0 && $firma_emisor != 2 && $firma_stupendo == 0 &&
                                count($proceso->firmantes) == $cant_firmas_receptores_en_documento + 1) {
                            $doctimestamp = "doctimestamp={source={url={$timeserver}}}";
                        }

                        break;
                    case "STUPENDO":
                        $id_referencial = $id_cliente_actual;
                        $carpeta_pdf_firmado_plop_final = storage_path(
                            "/doc_electronicos/procesos/cliente_$id_cliente_emisor/$id_propio/Stupendo"
                        );
                        $camino_pfx = storage_path(Config::get("app.path_firma_stupendo"));
                        $certification = "certification=none";
                        if ($sello_tiempo != 0) {
                            $doctimestamp = "doctimestamp={source={url={$timeserver}}}";
                        }

                        break;
                }
                $fc = new FirmasController();
                $clave_pfx = $fc->getPasswordPlanoFirma($actor, $id_referencial);
                $engine = "engine=builtin";
                $digitalid = "digitalid={filename=$camino_pfx}";
                $password = "password={$clave_pfx}";
                $ltv = "ltv=try";
                $sigtype = "sigtype=cades";
                $timestamp = "timestamp={critical source={url={$timeserver}}}";

                $crl = "";
                $validate = "";

                $camino_pdf_representacion_visual = $this->getCaminoRepresentacionVisualFirma(
                    $actor,
                    $id_referencial,
                    $width,
                    $momento
                );
                if (!$camino_pdf_representacion_visual) {
                    $Res = -1;
                    $Mensaje = "No se pudo generar la representación visual de la firma.<br/>";
                }
            }
            if ($Res >= 0) {
                if (!is_dir($carpeta_pdf_firmado_plop_final)) {
                    mkdir($carpeta_pdf_firmado_plop_final, 0777, true);
                }
                $camino_pdf_firmado_plop_final = "$carpeta_pdf_firmado_plop_final/$id_documento.pdf";
                if (file_exists($camino_pdf_firmado_plop_final)) {
                    @unlink($camino_pdf_firmado_plop_final);
                }
            }
            if ($Res >= 0) {
                $plop = new PLOP();
                if (Config::get('app.usar_licencia_plop') == true) {
                    $plop->set_option("license=" . Config::get('app.plop_license'));
                }
                Log::info("Camino PDF representacion visual: $camino_pdf_representacion_visual");
                $visdoc = $plop->open_document($camino_pdf_representacion_visual, "");
                if ($visdoc == 0) {
                    $field = "";
                    $Res = -1;
                    $Mensaje = "Error incrustarFirmaPLOP: " . $plop->get_apiname() . ": " . $plop->get_errmsg();
                } else {
                    $arr_posicion = $this->getPosicionRepresentacionVisual($id_proceso, $id_documento, $actor);
                    $pagina_representacion_visual = $arr_posicion["pagina_representacion_visual"];

                    $x_inicial = $arr_posicion["x_inicial"];
                    $y_inicial = $arr_posicion["y_inicial"];
                    $x_final = $arr_posicion["x_final"];
                    $y_final = $arr_posicion["y_final"];
                    $field = "field={visdoc=$visdoc rect={" . "$x_inicial $y_inicial $x_final $y_final} page=$pagina_representacion_visual}";
                }
            }
            if ($Res >= 0) {
                $pdf_origen = $plop->open_document($camino_origen, "");
                if ($pdf_origen == 0) {
                    $Res = -1;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $firma_plop = "$engine $digitalid $password $ltv $sigtype $timestamp $certification $update $field $doctimestamp $crl $validate";
                $ps = $plop->prepare_signature($firma_plop);
                if ($ps == 0) {
                    $Res = -1;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $nuevo_pdf = $plop->create_document($camino_pdf_firmado_plop_final, "input=" . $pdf_origen);
                if ($nuevo_pdf == 0) {
                    $Res = -1;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
            if ($Res >= 0) {
                $resultado_cierre = $plop->close_document($pdf_origen, "") && $plop->close_document($visdoc, "");
                if ($resultado_cierre == 0) {
                    $Res = -1;
                    $Mensaje = $plop->get_apiname() . ": " . $plop->get_errmsg();
                }
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El documento fue firmado con PLOP con éxito.<br/>";
            @unlink($camino_pdf_representacion_visual);
        } else {
            Log::error("Fin de incrustarFirmaPLOP $Res : $Mensaje");
        }

        if (isset($camino_pdf_firmado_plop_final)) {
            return array($Res, $Mensaje, $camino_pdf_firmado_plop_final);
        } else {
            return array($Res, $Mensaje);
        }
    }

    public function getPosicionRepresentacionVisual($id_proceso, $id_documento)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $arr_info = $this->getInfoPDFOriginal($id_proceso, $id_documento);
            $Res = $arr_info["Res"];
            $Mensaje = $arr_info["Mensaje"];
            $info = $arr_info["info"];
        }
        if ($Res >= 0) {
            $cantidad_paginas_originales = $info["cantidad_paginas_originales"];
            $cantidad_firmas_por_pagina = $info["cantidad_firmas_por_pagina"];
            $cantidad_firmas_colocadas = 0;
            $proceso = Proceso::find($id_proceso);
            foreach ($proceso->historial as $hito) {
                if ($hito["id_documento"] == $id_documento && $hito["accion"] == 1) {
                    $cantidad_firmas_colocadas++;
                }
            }
            $index = $cantidad_firmas_colocadas + 1;
            $indice_pagina_firma = ceil($index / $cantidad_firmas_por_pagina);
            $pagina_representacion_visual = $cantidad_paginas_originales + $indice_pagina_firma;
            $posicion = $index - (($indice_pagina_firma - 1) * $cantidad_firmas_por_pagina);
            $x_inicial = 25;
            $x_final = $info["width_ultima_pagina_ptos"] - $x_inicial;
            $y_inicial = $info["height_ultima_pagina_ptos"] - 75 - ($posicion * 100);
            $y_final = $y_inicial - 94;
        }
        return array(
            "pagina_representacion_visual" => $pagina_representacion_visual,
            "x_inicial" => $x_inicial,
            "y_inicial" => $y_inicial,
            "x_final" => $x_final,
            "y_final" => $y_final
        );
    }

    private function getCaminoPDFAdjunto(
        $html,
        $nombre_temporal,
        $width,
        $heigth,
        $top = 5,
        $bottom = 5,
        $left = 5,
        $right = 5
    ) {
        $camino = sys_get_temp_dir() . "/doc_electronicos/$nombre_temporal" . date("U") . ".pdf";
        if (file_exists($camino)) {
            @unlink($camino);
        }
        $pdf_firma = App::make('snappy.pdf.wrapper');
        $pdf_firma->loadHTML($html)->setOption('encoding', 'UTF-8')->setOption('page-width', $width)->setOption(
            'page-height',
            $heigth
        )->setOption('margin-top', $top)->setOption(
            'margin-bottom',
            $bottom
        )->setOption('margin-left', $left)->setOption('margin-right', $right)->save($camino);
        if ($pdf_firma) {
            return $camino;
        } else {
            return false;
        }
    }

    public function getCaminoPaginaFirma($id_proceso, $id_documento, $width, $heigth)
    {
        try {
            $arretiquetas = array("id_referencia");
            $id_referencia = "$id_proceso-$id_documento";
            $arrvalores = array($id_referencia);
            return $this->getCaminoPDFAdjunto(
                view("doc_electronicos.firmas.pagina_firma", array_combine($arretiquetas, $arrvalores)),
                "pagina_firma_" . $id_proceso,
                $width,
                $heigth
            );
        } catch (Exception $e) {
            Log::error($e->getMessage() . " Stacktrace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function getCaminoPaginaWatermark($id_proceso, $id_documento, $width, $heigth)
    {
        try {
            $arretiquetas = array("info_bar");
            $info_bar = "$id_proceso - $id_documento";
            $arrvalores = array($info_bar);
            return $this->getCaminoPDFAdjunto(
                view("doc_electronicos.firmas.pagina_watermark", array_combine($arretiquetas, $arrvalores)),
                "pagina_referencia_$id_proceso",
                $width,
                $heigth,
                2,
                2,
                2,
                2
            );
        } catch (Exception $e) {
            Log::error($e->getMessage() . " Stacktrace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function getCaminoRepresentacionVisualFirma($actor, $id, $width, $momento = null)
    {
        try {
            $heigth = 35;
            $arretiquetas = array("id_firma", "nombre", "identificacion", "email", "momento", "info_qr", "hash");
            if (strtoupper($actor) == "STUPENDO") {
                $fc = new FirmasController();
                $array_key_vector = $fc->getArrayKeyVector();
                $password_plano = $fc->Desencriptar(
                    $array_key_vector["llave"],
                    $array_key_vector["vector"],
                    Config::get("app.password_firma_stupendo")
                );
                $data = $fc->getInfoCertificado(storage_path(Config::get("app.path_firma_stupendo")), $password_plano);
                $id_firma = EncriptarId($data["info"]["serial_number"]);
                $nombre = "($actor) " . $data["info"]["nombre"];
                $identificacion = $data["info"]["identificacion"];
                $email = $data["info"]["email"];
                $hash = $data["info"]["public_key"];
            } else {
                $firma = Firma::ultimaFirmaDelCliente($id);
                $id_firma = $firma->_id;
                $nombre = "($actor) " . $firma->nombre;
                $identificacion = $firma->identificacion;
                $email = $firma->email;
                $hash = $firma->public_key;
            }
            $momento = date("d/m/Y H:i:s");
            $info_qr = "Nombre / Razón Social: $nombre \015\012 ID Sistema: $id_firma \015\012 Identificación: $identificacion \015\012 Correo electrónico: $email \015\012 Fecha y hora de firmado: $momento";
            $arrvalores = array($id_firma, $nombre, $identificacion, $email, $momento, $info_qr, $hash);
            return $this->getCaminoPDFAdjunto(
                        view(
                            "doc_electronicos.firmas.representacion_visual",
                            array_combine($arretiquetas, $arrvalores)
                        ),
                        "representacion_visual_firma_$id",
                        $width,
                        $heigth,
                        0,
                        0,
                        0,
                        0
            );
        } catch (Exception $e) {
            Log::error($e->getMessage() . " Stacktrace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function existeContenedor($store, $nombre_contenedor)
    {
        try {
            $store->getContainer($nombre_contenedor);
            return true;
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    public function moverARackspace()
    {
        $nombre_contenedor = Config::get('app.container_rackspace_de');
        $client = new Rackspace(
            Rackspace::US_IDENTITY_ENDPOINT,
            [
                'username' => Config::get('app.username_rackspace_de'),
                'apiKey' => Config::get('app.apikey_rackspace_de')
            ],
            [
                Rackspace::SSL_CERT_AUTHORITY => 'system',
                Rackspace::CURL_OPTIONS =>
                    [
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                    ],
            ]
        );

        $store = $client->objectStoreService(null, 'ORD');
        if (!$this->existeContenedor($store, $nombre_contenedor)) {
            $container = $store->createContainer($nombre_contenedor);
        } else {
            $container = $store->getContainer($nombre_contenedor);
        }
        $container->enableCdn();

        $procesos = Proceso::whereIn("id_estado_actual_proceso", [2, 3])->where(
            "storage",
            "<>",
            Proceso::STORAGE_EXTERNO
        )->get();
        foreach ($procesos as $proceso) {
            try {
                $documentos = $proceso->documentos;
                foreach ($documentos as $indice => $documento) {
                    $camino_original = storage_path($documento["camino_original"]);
                    $camino_rackspace = $documento["camino_original"];
                    $fichero = fopen($camino_original, 'r+');
                    $objeto = $container->uploadObject($camino_rackspace, $fichero);
                    fclose($fichero);
                    $documentos[$indice]["camino_original"] = (string)$objeto->getPublicUrl();

                    $camino_stupendo = storage_path(
                        str_ireplace("originales", "Stupendo", $documento["camino_original"])
                    );
                    $camino_rackspace = str_ireplace("originales", "Stupendo", $documento["camino_original"]);
                    if (file_exists($camino_stupendo)) {
                        $fichero = fopen($camino_stupendo, 'r+');
                        $container->uploadObject($camino_rackspace, $fichero);
                        @fclose($fichero);
                    }
                }
                $historial = $proceso->historial;
                foreach ($historial as $indice => $hito) {
                    $camino = storage_path($hito["camino"]);
                    $camino_rackspace = $hito["camino"];
                    $fichero = fopen($camino, 'r+');
                    $objeto = $container->uploadObject($camino_rackspace, $fichero);
                    fclose($fichero);
                    $historial[$indice]["camino"] = (string)$objeto->getPublicUrl();
                }
                $carpeta_proceso_a_borrar = storage_path(
                    "/doc_electronicos/procesos/cliente_" . $proceso->id_cliente_emisor . "/" . $proceso->id_propio
                );
                EliminarContenidoDirectorio($carpeta_proceso_a_borrar);
                rmdir($carpeta_proceso_a_borrar);
                $proceso->documentos = $documentos;
                $proceso->historial = $historial;
                $proceso->storage = Proceso::STORAGE_EXTERNO;
                $proceso->save();
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    public static function prepararClienteUsuarioDE(
        $identificacion,
        $razon_social_nombre,
        $email,
        $telefono = "",
        $perfil_predeterminado = "Receptor_DE",
        $id_cliente_crea = null,
        $id_cliente_facturable = null
    ) {
        $Res = 0;
        $Mensaje = "";
        $id_cliente = null;
        $id_usuario = null;
        $id_modulo = 7;
        $modulo_old_name = "DocumentosElectronicos";
        $nombre_modulo = "Documentos Electrónicos";
        $nuevo_cliente = false;
        $nuevo_usuario = false;
        $nuevo_cliente_de = false;
        try {
            if ($Res >= 0) {
                $cliente = Cliente::where("identificacion", $identificacion)->first();
                if ($cliente) {
                    $id_cliente = $cliente->_id;
                }
                if (empty($id_cliente)) {
                    $nuevo_cliente = true;
                    $nuevo_cliente_de = true;
                    $modulos = array(array('id_modulo' => $id_modulo));
                    if (RUCValido($identificacion)) {
                        $tipo_identificacion = "04";
                    } else {
                        if (CedulaValida($identificacion)) {
                            $tipo_identificacion = "05";
                        } else {
                            $tipo_identificacion = "07";
                        }
                    }
                    $cliente = Cliente::crear(
                        $email,
                        $razon_social_nombre,
                        $identificacion,
                        $tipo_identificacion,
                        "",
                        $telefono,
                        5,
                        [$modulo_old_name],
                        $modulos
                    );
                    $cliente["modulos"] = $modulos;
                } else {
                    if (!Modulo::ClientHasAccessModule($id_cliente, $id_modulo)) {
                        $nuevo_cliente_de = true;
                        if (isset($cliente->modulos)) {
                            $modulos = $cliente->modulos;
                        } else {
                            $modulos = array();
                        }
                        if (isset($cliente->roles)) {
                            $roles = $cliente->roles;
                        } else {
                            $roles = array();
                        }
                        array_push($modulos, array("id_modulo" => $id_modulo));
                        array_push($roles, $modulo_old_name);
                        $cliente["modulos"] = $modulos;
                        $cliente["roles"] = $roles;
                    }
                }
                if (!empty($id_cliente_facturable)) {
                    if (isset($cliente->clientes_dependientes_de)) {
                        $clientes_dependientes_de = $cliente->clientes_dependientes_de;
                    } else {
                        $clientes_dependientes_de = array();
                    }
                    if (!in_array($id_cliente_facturable, $clientes_dependientes_de)) {
                        array_push($clientes_dependientes_de, $id_cliente_facturable);
                    }
                    $cliente["clientes_dependientes_de"] = $clientes_dependientes_de;
                }
                $resultado = $cliente->save();
                if (!$resultado) {
                    $Res = -1;
                    $Mensaje = "No se pudo crear / preparar el cliente para $nombre_modulo.";
                    $id_cliente = null;
                } else {
                    $id_cliente = $cliente->_id;
                }
            }
            if ($Res >= 0) {
                $usuario = Usuarios::where("email", $email)->first();
                if (!$usuario) {
                    $nuevo_usuario = true;
                    $fc = new FirmasController();
                    $password_autogenerado = bcrypt($fc->ObtenerCodigoNuevo());
                    $creado_por = empty($id_cliente_crea) ? $id_cliente : $id_cliente_crea;
                    $perfil = $perfil_predeterminado;
                    $perfiles_rol = array(array("perfil" => $perfil));
                    $perfiles = array(array('rol_cliente' => $modulo_old_name, 'perfiles_rol' => $perfiles_rol));
                    $clientes = array(array('cliente_id' => $id_cliente, 'perfiles' => $perfiles));
                    $data_usuario = array(
                        "email" => $email,
                        "nombre" => $razon_social_nombre,
                        "password" => $password_autogenerado,
                        "activo" => true,
                        "clientes" => $clientes,
                        "creado_por" => $creado_por
                    );
                    $usuario = Usuarios::create($data_usuario);
                    if (!$usuario) {
                        $Res = -2;
                        $Mensaje = "No se pudo crear el usuario $email para $nombre_modulo.";
                    } else {
                        $id_usuario = $usuario->_id;
                    }
                } else {
                    if (!Modulo::UserHasAccessModuleInClient($usuario, $id_cliente, $modulo_old_name)) {
                        $clientes_final = array();
                        $clientes = $usuario->clientes;
                        $cliente_existente_en_usuario = false;
                        foreach ($clientes as $cliente_usuario) {
                            if ($cliente_usuario["cliente_id"] == $id_cliente) {
                                $cliente_existente_en_usuario = true;
                                if (!empty($cliente_usuario["perfiles"])) {
                                    $perfiles = $cliente_usuario["perfiles"];
                                } else {
                                    $perfiles = array();
                                }
                                $perfil_nuevo = array(
                                    "rol_cliente" => $modulo_old_name,
                                    "perfiles_rol" => array(array("perfil" => $perfil_predeterminado))
                                );
                                array_push($perfiles, $perfil_nuevo);
                                $cliente_usuario["perfiles"] = $perfiles;
                            }
                            array_push($clientes_final, $cliente_usuario);
                        }
                        if (!$cliente_existente_en_usuario) {
                            $perfil = $perfil_predeterminado;
                            $perfiles_rol = array(array("perfil" => $perfil));
                            $perfiles = array(
                                array(
                                    'rol_cliente' => $modulo_old_name,
                                    'perfiles_rol' => $perfiles_rol
                                )
                            );
                            $cliente_usuario = array('cliente_id' => $id_cliente, 'perfiles' => $perfiles);
                            array_push($clientes_final, $cliente_usuario);
                        }
                        $usuario["clientes"] = $clientes_final;
                        $resultado = $usuario->save();
                        if (!$resultado) {
                            $Res = -3;
                            $Mensaje = "No se pudo preparar el usuario $email para $nombre_modulo.";
                            $id_usuario = null;
                        } else {
                            $id_usuario = $usuario->_id;
                        }
                    } else {
                        $perfil_anterior = Modulo::get_perfil_by_user_cliente_modulo(
                            $usuario,
                            $cliente,
                            "DocumentosElectronicos"
                        );
                        if (!in_array(
                                $perfil_anterior,
                                array("Administrador_DE", "Emisor_DE", "Emisor_Dependiente")
                            ) && !empty($id_cliente_facturable)) {
                            $arr_res = PerfilesController::CambiarPerfil(
                                $usuario,
                                $id_cliente,
                                $modulo_old_name,
                                $perfil_predeterminado
                            );
                            $Res = $arr_res["Res"];
                            $Mensaje = $arr_res["Mensaje"];
                            $id_usuario = $usuario->_id;
                        } else {
                            $id_usuario = $usuario->_id;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -4;
            $Mensaje = $e->getMessage() . " - Stacktrace: " . $e->getTraceAsString();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El cliente y usuario fueron preparados correctamente para $nombre_modulo.";
        } else {
            Log::error("Error en prepararClienteUsuarioDE ($Res): $Mensaje");
        }
        return array(
            "Res" => $Res,
            "Mensaje" => $Mensaje,
            "id_cliente" => $id_cliente,
            "id_usuario" => $id_usuario,
            "nuevo_cliente" => $nuevo_cliente,
            "nuevo_usuario" => $nuevo_usuario,
            "nuevo_cliente_de" => $nuevo_cliente_de
        );
    }

    public function mostrarAyudaSeleccionDocumento()
    {
        $arretiquetas = array();
        $arrvalores = array();
        return view("doc_electronicos.emisiones.ayuda_seleccion_documento", array_combine($arretiquetas, $arrvalores));
    }

    #region  FUNCION VISUALIZAR Y ANULAR UNA EMISIÓN QUE NO HA SIDO FIRMADA, -eliminarEmisiones
    public function eliminarEmisiones($id, Request $request)
    {
        if ($id) {
            $proceso = Proceso::where('_id', $id)->first();

            if ($proceso) {
                $id_estado_actual_proceso = $proceso->id_estado_actual_proceso;
                $proceso_factible = Proceso::where('id_estado_actual_proceso', '<', 2)->where('_id', $id)->first();

                if ($proceso) {
                    $id_emisor = $proceso->id_cliente_emisor;
                    $id_receptor = $proceso->id_cliente_emisor;

                    if ($id_emisor) {
                        $clientes = Cliente::where("_id", $id_emisor)->first();
                        if ($clientes) {
                            $nombre_identificacion = $clientes->nombre_identificacion;
                            $identificacion = $clientes->identificacion;
                        }
                    }
                }

                $data = [
                    'proceso' => $proceso,
                    'id_proceso' => $id,
                    'emisor' => $nombre_identificacion,
                    'identificacion' => $identificacion,
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso->momento_emitido, "d/m/Y"),
                    'firmantesdocs' => $proceso->firmantes,
                    'estado_actual_proceso' => $id_estado_actual_proceso,
                    'css' => true,
                    'pdf' => true,
                    'class' => 'xs'
                ];
                return view('doc_electronicos.emisiones.VerParaAnularDocElect', $data);
            } else {
                $arretiquetas = array("opciones_estado");
                $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
                $arrvalores = array($opciones_estado);
                return view("doc_electronicos.emisiones.filtro_emisiones", array_combine($arretiquetas, $arrvalores));
            }
        } else {
            $arretiquetas = array("opciones_estado");
            $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
            $arrvalores = array($opciones_estado);
            return view("doc_electronicos.emisiones.filtro_emisiones", array_combine($arretiquetas, $arrvalores));
        }
    }
    #endregion

    #region  FUNCION VISUALIZAR Y ANULAR UNA EMISIÓN QUE NO HA SIDO FIRMADA, -eliminarEmisiones
    public function eliminarEmisionesDef(Request $request)
    {
        $id = $request->input('txt_id_AnulaDocElect');

        if ($id) {
            $proceso = Proceso::where('id_estado_actual_proceso', '<', 2)->where('_id', $id)->first();

            if ($proceso) {
                $dataupdate =
                    [
                        "id_estado_actual_proceso" => 4
                    ];
                $proceso_update = $proceso->update($dataupdate);
            }

            $arretiquetas = array("opciones_estado");
            $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
            $arrvalores = array($opciones_estado);
            return view("doc_electronicos.emisiones.filtro_emisiones", array_combine($arretiquetas, $arrvalores));
        }
    }
    #endregion

    #region  FUNCION PARA VISUALIZAR Y ANULAR UNA EMISIÓN SIMPLE QUE NO HA SIDO FIRMADA, -EliminarEmisionesSimples
    public function eliminarEmisionesSimple($id, Request $request)
    {
        if ($id) {
            $proceso = ProcesoSimple::where('_id', $id)->first();

            if ($proceso) {
                $id_estado_actual_proceso = $proceso->id_estado_actual_proceso;
                $proceso_factible = ProcesoSimple::where('id_estado_actual_proceso', '<', 2)->where('_id', $id)->first(
                );

                if ($proceso) {
                    $id_emisor = $proceso->id_cliente_emisor;
                    $id_receptor = $proceso->id_cliente_emisor;

                    if ($id_emisor) {
                        $clientes = Cliente::where("_id", $id_emisor)->first();
                        if ($clientes) {
                            $nombre_identificacion = $clientes->nombre_identificacion;
                            $identificacion = $clientes->identificacion;
                        }
                    }
                }

                $data = [
                    'proceso' => $proceso,
                    'id_proceso_simple' => $id,
                    'emisor' => $nombre_identificacion,
                    'identificacion' => $identificacion,
                    "fecha_emision_mostrar" => FormatearMongoISODate($proceso->momento_emitido, "d/m/Y"),
                    'firmantesdocs' => $proceso->firmantes,
                    'estado_actual_proceso' => $id_estado_actual_proceso,
                    'css' => true,
                    'pdf' => true,
                    'class' => 'xs'
                ];
                return view('doc_electronicos.emisiones_simples.VerParaAnularDocElectSimple', $data);
            } else {
                $arretiquetas = array("opciones_estado");
                $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
                $arrvalores = array($opciones_estado);
                return view(
                    "doc_electronicos.emisiones_simples.filtro_emisiones_simples",
                    array_combine($arretiquetas, $arrvalores)
                );
            }
        } else {
            $arretiquetas = array("opciones_estado");
            $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
            $arrvalores = array($opciones_estado);
            return view(
                "doc_electronicos.emisiones_simples.filtro_emisiones_simples",
                array_combine($arretiquetas, $arrvalores)
            );
        }
    }
    #endregion

    #region  FUNCION ANULAR UNA EMISIÓN SIMPLE QUE NO HA SIDO FIRMADA, -eliminarEmisionesSimple
    public function eliminarEmisionesSimpleDef(Request $request)
    {
        $id = $request->input('txt_id_AnulaDocElectSimple');

        if ($id) {
            $proceso = ProcesoSimple::where('id_estado_actual_proceso', '<', 2)->where('_id', $id)->first();

            if ($proceso) {
                $dataupdate =
                    [
                        "id_estado_actual_proceso" => 4
                    ];
                $proceso_update = $proceso->update($dataupdate);
            }

            $arretiquetas = array("opciones_estado");
            $opciones_estado = EstadoProcesoEnum::getOptionsEstadosProcesos();
            $arrvalores = array($opciones_estado);
            return view(
                "doc_electronicos.emisiones_simples.filtro_emisiones_simples",
                array_combine($arretiquetas, $arrvalores)
            );
        }
    }

    #endregion

    public function enviarEmisionSinFirmar($proceso)
    {
        try {
            $notificacionController = new NotificacionDEController();

            $id_cliente_emisor = $proceso['id_cliente_emisor'];
            $id_usuario_emisor = $proceso['id_usuario_emisor'];
            $titulo = $proceso['titulo'];
            $id_estado_actual_proceso = $proceso['id_estado_actual_proceso'];
            $id = $proceso['_id'];

            $titulo_proceso = $proceso['titulo'];
            $cliente_emisor = Cliente::find($id_cliente_emisor);
            $cliente_identificacion = $cliente_emisor["identificacion"];
            $compania = $cliente_emisor["nombre_identificacion"];
            $pc = new PreferenciasController();
            $banner_url = $pc->getURLEmailBanner($cliente_emisor);
            $de = Preferencia::get_default_email_data($id_cliente_emisor, "de_email");
            $asunto = Preferencia::get_default_email_data($id_cliente_emisor, "asunto_email_citacion");
            $asunto = str_replace("TITULO_PROCESO", $titulo_proceso, $asunto);
            $mostrar_credenciales = false;
            $password_autogenerado = "";
            $enlace = URL::to('/force_logout');
            $lista_documentos = array();

            $dias_de_atraso = 15;
            $dias_adicional = 15;
            $dias_darbaja = 30;
            $recordatorio = false;

            $procesofir = Proceso::where('_id', $id)->first();

            $lista_signatarios = "";
            $index = 0;
            foreach ($procesofir->firmantes as $firmante) {
                $index++;
                $lista_signatarios .= $index . " - " . $firmante["nombre"] . "<br/>";
            }

            $usuario_receptor = Usuarios::find($firmante["id_usuario"]);


            $dias_validez_firma_personal = Config::get('app.dias_validez_firma_personal_stupendo');

            $arretiquetas = array(
                "banner_url",
                "compania",
                "lista_documentos",
                "lista_signatarios",
                "titulo_proceso",
                "enlace",
                "mostrar_credenciales",
                "email",
                "password",
                "dias_validez_firma_personal",
                'es_invitacion'
            );
            $arrvalores = array(
                $banner_url,
                $compania,
                $lista_documentos,
                $lista_signatarios,
                $titulo_proceso,
                $enlace,
                $mostrar_credenciales,
                $usuario_receptor->email,
                $password_autogenerado,
                $dias_validez_firma_personal,
                true
            );

            $data_mailgun = array();
            $data_mailgun["X-Mailgun-Tag"] = "monitoreo_proceso_signatarios_de";
            $data_mailgun["X-Mailgun-Track"] = "yes";
            $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $id]);


            $clientes_parametros = Cliente::where("identificacion", $cliente_identificacion)->first();
            if ($clientes_parametros) {
                if (isset($clientes_parametros->parametros->dias_emision_docelct)) {
                    $dias_de_atraso = $clientes_parametros->parametros->dias_emision_docelct;
                }

                if (isset($clientes_parametros->parametros->recordatorio_emision_docelct)) {
                    $recordatorio = $clientes_parametros->parametros->recordatorio_emision_docelct;
                } else {
                    $recordatorio = false;
                }
            }

            $fecha_referencia = Carbon::now()->subDays($dias_de_atraso);


            $fecha_referenciaSegRecor = Carbon::now()->subDays($dias_adicional);

            $dias_dar_bajaEmision = $dias_de_atraso + $dias_darbaja;
            $fecha_referenciaDardebaja = Carbon::now()->subDays($dias_dar_bajaEmision);

            $emision_sinfirmarDarBaja = Proceso::where('_id', $id)->where(
                'created_at',
                '<',
                $fecha_referenciaDardebaja
            )->get();


            if ($emision_sinfirmarDarBaja->count()) {
                if ($recordatorio == true) {
                    $procesoDelate = Proceso::where("_id", $id)->first();

                    if ($procesoDelate) {
                        $dataupdate =
                            [
                                "id_estado_actual_proceso" => 4
                            ];
                        $proceso_update = $procesoDelate->update($dataupdate);
                    }
                }
            } else {
                $notificacion_proceso = NotificaciondeError::where("id_proceso", $id)->get();

                if ($notificacion_proceso->count()) {
                    $emision_sinfirmar = NotificaciondeError::where("id_proceso", $id)->where(
                        'created_at',
                        '<',
                        $fecha_referenciaSegRecor
                    )->get();
                } else {
                    $emision_sinfirmar = Proceso::where('_id', $id)->where('created_at', '<', $fecha_referencia)->get();
                }

                if ($recordatorio == true) {
                    if ($procesofir) {
                        if ($emision_sinfirmar->count()) {
                            $notificacion_procesoFin = NotificaciondeError::where("id_proceso", $id)->first();

                            if ($notificacion_procesoFin) {
                                $fin_notificacion = $notificacion_procesoFin->fin_notificacion;
                            } else {
                                $fin_notificacion = false;
                            }

                            if ($fin_notificacion == false) {
                                $mail_view = "emails.doc_electronicos.$cliente_identificacion.invitacionRecordatorio";
                                if (!$cliente_emisor->tieneVistaEmailPersonalizada($mail_view)) {
                                    $mail_view = 'emails.doc_electronicos.invitacionRecordatorio';
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
                                    $usuario_receptor->email,
                                    $usuario_receptor->nombre,
                                    $arretiquetas,
                                    $arrvalores,
                                    null,
                                    $data_mailgun,
                                    $nombreEnmas,
                                    $correoEnmas
                                );

                                $Res = $arr_res[0];
                                $Mensaje = $arr_res[1];

                                if ($Res > 0) {
                                    if ($notificacion_proceso->count()) {
                                        $Mensaje = "El correo de citación fue enviado correctamente a " . $usuario_receptor->email;
                                        $notificacion_procesoUpdate = NotificaciondeError::where("id_proceso", $id)->first(
                                        );
                                        $dataupdate = [
                                            "fin_notificacion" => true
                                        ];

                                        $fin_notificacionupdate = $notificacion_procesoUpdate->update($dataupdate);
                                    } else {
                                        $Mensaje = "El correo de citación fue enviado correctamente a " . $usuario_receptor->email;
                                        $titulo = "Notificación de firmado de documento electrónico de" . $titulo_proceso;
                                        $texto = $Res . " " . $Mensaje;
                                        $id_tipo = 1;
                                        $data_notificacion = [
                                            "id_usuario" => $id_usuario_emisor,
                                            "titulo" => $titulo,
                                            "texto" => $texto,
                                            "id_tipo" => $id_tipo,
                                            "id_proceso" => $id,
                                            "fecha_envio" => new \MongoDB\BSON\UTCDateTime(new \DateTime()),
                                            "leida" => false,
                                            "fin_notificacion" => false,
                                            "fecha_leida" => null
                                        ];
                                        $notificacion = NotificaciondeError::create($data_notificacion);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $Mensaje = "Error al recordar a clientes sobre emisiones sin firmar";
        }
    }

    public static function documentoYaFirmado($proceso, $id_usuario, $id_documento)
    {
        if (isset($proceso) && $proceso != null) {
            foreach ($proceso->historial as $hito_actual) {
                if ($hito_actual["id_documento"] == $id_documento
                    && $hito_actual["id_usuario"] == $id_usuario) {
                    return true;
                }
            }
        }
        return false;
    }

    public function consultarRegistroDB($identificacion)
    {
        $persona = DatoPersona::where('identificacion', $identificacion)->first();
        if ($persona) {
            return $persona->datos;
        }
        return 0;
    }

    public function guardarRegistroDB($cedula, $array)
    {
        $persona = DatoPersona::where('identificacion', $cedula)->first();
        if ($persona) {
            $persona->datos = $array;
            $persona->save();
        } else {
            DatoPersona::create(
                [
                    'identificacion' => $cedula,
                    'datos' => $array
                ]
            );
        }
    }

    public function guardarConsultaDatofast($apiToken, $estado, $parametros, $credenciales, $origen, $guarda_peticion_recibida)
    {
        ConsultaDatofast::create(
            [
                'apiToken' => $apiToken,
                'id_cliente' => $credenciales,
                'estado' => $estado,
                'identificacion' => $parametros,
                'servicio' => "Servicio de Envio de Documentos Electronicos",
                'origen' => $origen,
                'peticion_recibida' => $guarda_peticion_recibida
            ]
        );
    }

    public function guardarConsulta($estado, $parametros, $credenciales, $origen, $guarda_peticion_recibida)
    {
        Consultas::create(
            [
                'id_cliente' => $credenciales,
                'estado' => $estado,
                'parametros' => $parametros,
                'servicio' => "Formularios de Vinculacion",
                'origen' => $origen,
                'peticion_recibida' => $guarda_peticion_recibida
            ]
        );
    }


}
