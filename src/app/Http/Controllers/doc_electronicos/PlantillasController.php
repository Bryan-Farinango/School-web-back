<?php

namespace App\Http\Controllers\doc_electronicos;

use App;
use App\Cliente;
use App\doc_electronicos\Plantilla;
use App\doc_electronicos\Preferencia;
use App\Http\Controllers\Config\ClienteController;
use App\Packages\Traits\DocumentoElectronicoTrait;
use App\Usuarios;
use Config;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PlantillasController extends ProcesoSimplePlantillaBaseController
{
    use DocumentoElectronicoTrait;

    public function __construct()
    {
    }

    public function MostrarFiltroPlantillas()
    {
        return view("doc_electronicos.plantillas.plantillas");
    }

    public function MostrarListaPlantillas(Request $request)
    {
        $id_cliente = session()->get("id_cliente");

        $draw = $request->input('draw');
        $skip = (integer)$request->input('start');
        $take = (integer)$request->input('length');

        $order_column = "nombre_plantilla";

        $order_dir = $request->input("order")[0]["dir"];

        $plantillas = Plantilla::select("_id", "nombre_plantilla", "id_cliente", "tipo_proceso", "created_at")->where(
            "id_cliente",
            $id_cliente
        );

        $records_total = $plantillas->count();
        $plantillas = $plantillas->skip($skip)->take($take)->orderBy($order_column, $order_dir)->get();

        $result = array();
        foreach ($plantillas as $plantilla) {
            $result[] =
                [
                    "_id" => EncriptarId($plantilla["_id"]),
                    "cliente" => $plantilla["cliente_emisor"]["nombre_identificacion"],
                    "titulo" => $plantilla["nombre_plantilla"],
                    "tipo_proceso" => $plantilla["tipo_proceso"],
                    "fecha_creacion" => FormatearMongoISODate($plantilla["created_at"], "d/m/Y"),
                    "documentos" => "",
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
        $id_plantilla = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_plantilla, "STRING");

        $viewData = $this->GetDetallesDocumentos($id_plantilla);

        return view("doc_electronicos.emisiones.documentos_originales", $viewData);
    }

    public function ConfirmaEliminarPlantilla(Request $request)
    {
        $id_plantilla = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_plantilla, "STRING");

        $plantilla = Plantilla::find($id_plantilla);

        return view(
            "doc_electronicos.plantillas.confirmar_eliminacion_plantilla",
            array(
                'id' => $plantilla->_id,
                'nombre_plantilla' => $plantilla->nombre_plantilla,
                'titulo_proceso' => $plantilla->titulo_proceso
            )
        );
    }

    public function EliminarPlantilla($id_plantilla = null)
    {
        if (empty($id_plantilla)) {
            return response()->view('errors.404', [], 404);
        }
        Plantilla::find($id_plantilla)->delete();
        header("Location: /doc_electronicos/plantillas");
        die();
    }

    public function GetDetallesDocumentos($id_plantilla)
    {
        $pc = new ProcesoController();
        $titulo_proceso = "";
        $contenido_tabla_documentos_originales = "";
        $estado_original = "Original";
        $plantilla = Plantilla::find($id_plantilla);
        if ($plantilla) {
            $titulo_proceso = $plantilla["titulo_proceso"];
            $momento_emitido = FormatearMongoISODate($plantilla["created_at"]);
            foreach ($plantilla->documentos as $documento) {
                $clase = $pc->getClaseLineaDocumento(0);
                $titulo_documento = $documento["titulo"];
                $adjunto = $this->getAdjunto($id_plantilla, $documento["id_documento"]);
                $contenido_tabla_documentos_originales .= '<tr style="text-align:center" class="' . $clase . '"><td style="text-align:left">' . $titulo_documento . '</td>
                <td>' . $estado_original . '</td><td>' . $momento_emitido . '</td><td>' . $adjunto . '</td></tr>';
            }
        }
        $arretiquetas = array("titulo_proceso", "contenido_tabla_documentos_originales");
        $arrvalores = array($titulo_proceso, $contenido_tabla_documentos_originales);
        return array_combine($arretiquetas, $arrvalores);
    }

    public function getAdjunto($id_plantilla, $id_documento)
    {
        $plantilla = Plantilla::find($id_plantilla);
        return '<a href="' . Config::get(
                'app.url'
            ) . '/doc_electronicos/descargar_documento_plantilla/' . $id_plantilla . '/' . $id_documento . '" target="_blank"><img src="' . Config::get(
                'app.url'
            ) . '/img/iconos/' . $this->GetIcono($plantilla, $id_documento) . '"></a>';
    }

    public function DescargarDocumentoPlantilla($id_plantilla, $id_documento)
    {
        $pc = new ProcesoController();
        $id_plantilla = DesencriptarId($id_plantilla);
        $plantilla = Plantilla::find($id_plantilla);
        $extension = $this->GetExtension($plantilla, $id_documento);

        foreach ($plantilla["documentos"] as $documento) {
            if ($documento["id_documento"] == $id_documento) {
                $camino = storage_path($documento["camino_original"]);
                return response()->download(
                    $camino,
                    $this->getTituloDocumento($plantilla, $id_documento) . ".$extension"
                );
            }
        }
    }

    public function MostrarNuevaPlantilla($id_plantilla = null)
    {
        $id_usuario = session()->get("id_usuario");
        $id_cliente = session()->get("id_cliente");
        $cliente = getCliente();

        $opciones_firma_emisor = Preferencia::get_options_default($id_cliente, "firma_emisor");
        $opciones_firma_stupendo = Preferencia::get_options_default($id_cliente, "firma_stupendo");
        $opciones_sello_tiempo = Preferencia::get_options_default($id_cliente, "sello_tiempo");
        $opciones_referencia_paginas = Preferencia::get_options_default($id_cliente, "referencia_paginas");
        $opciones_origen_exigido = Preferencia::get_options_default($id_cliente, "origen_exigido");

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

        $opciones_tipo_proceso = '<option selected="selected" value="false">Seleccione el Tipo de Proceso</option>
                            <option value="firma">Firma</option>
                            <option value="simple">Aceptación Simple</option>';

        $tiene_vista_email_personalizada = $cliente->tieneVistaEmailPersonalizada();

        $arrValues = array(
            "id_usuario" => $id_usuario,
            "id_cliente" => $id_cliente,
            "opciones_tipo_proceso" => $opciones_tipo_proceso,
            "options_orden" => $options_orden,
            "options_variante_aceptacion" => $options_variante_aceptacion,
            "cuerpo_aceptacion_simple" => htmlspecialchars_decode($cuerpo_aceptacion_simple),
            "banner_aceptacion_simple" => $banner_aceptacion_simple,
            "id_plantilla" => $id_plantilla,
            "opciones_firma_emisor" => $opciones_firma_emisor,
            "opciones_firma_stupendo" => $opciones_firma_stupendo,
            "opciones_sello_tiempo" => $opciones_sello_tiempo,
            "opciones_referencia_paginas" => $opciones_referencia_paginas,
            "opciones_origen_exigido" => $opciones_origen_exigido,
            "tiene_vista_email_personalizada" => $tiene_vista_email_personalizada,
            "nombre_empresa_sms" => $nombre_empresa_sms
        );
        return view("doc_electronicos.plantillas.emisiones", $arrValues);
    }

    public function GuardarPlantilla(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        $id = null;
        try {
            $id_cliente = empty($request->input("HiddenIdCliente")) ? session()->get("id_cliente") : $request->input(
                "HiddenIdCliente"
            );
            Filtrar($id_cliente, "STRING");

            $id_usuario = empty($request->input("HiddenIdUsuario")) ? session()->get("id_usuario") : $request->input(
                "HiddenIdUsuario"
            );
            Filtrar($id_usuario, "STRING");

            $id_plantilla = $request->input("id_plantilla");
            Filtrar($id_plantilla, "STRING");

            $nombre_plantilla = $request->input("TNombrePlantilla");
            Filtrar($nombre_plantilla, "STRING", "");

            $titulo_proceso = $request->input("TTituloProceso");
            Filtrar($titulo_proceso, "STRING", "");

            $cantidad_procesos = $request->input("SCantidadProcesos");
            Filtrar($cantidad_procesos, "STRING", "");

            $id_documento = 0;

            $documentos = array();
            $arr_documentos = $request->input("HiddenDocumentos");
            Filtrar($arr_documentos, "ARRAY", []);

            $storage = Plantilla::STORAGE_LOCAL;
            $orden = (int)$request->input("SOrden");
            Filtrar($orden, "INTEGER", 1);

            $variante_aceptacion = $request->input("SViaAceptacion");
            Filtrar($variante_aceptacion, "STRING", "");

            if ($variante_aceptacion == 'SMS') {
                $nombreEnmas = $request->input("TNombreEmpresa");
                Filtrar($nombreEnmas, "STRING", "");
            } else {
                $nombreEnmas = $request->input("TNombreEnmas");
                Filtrar($nombreEnmas, "STRING", null);
            }

            $correoEnmas = $request->input("TCorreoEnmas");
            Filtrar($correoEnmas, "EMAIL", null);

            $tipo_proceso = $request->input("STipoProceso");
            Filtrar($tipo_proceso, "STRING", "");

            $firma_emisor = $request->input("SFirmaEmisor");
            Filtrar($firma_emisor, "STRING", "");

            $referencia_paginas = $request->input("SReferenciaPaginas");
            Filtrar($referencia_paginas, "STRING", "");

            $origen_exigido = $request->input("SOrigenExigido");
            Filtrar($origen_exigido, "STRING", "");

            if ($Res >= 0) {
                $cliente = Cliente::find($id_cliente);
                $usuario = Usuarios::find($id_usuario);
                if (!$cliente || !$usuario) {
                    $Res = -2;
                    $Mensaje = "Cliente o usuario inexistente";
                }
            }
            if ($Res >= 0) {
                if (empty($nombre_plantilla)
                    || !in_array($orden, array(1, 2))
                    || !in_array($variante_aceptacion, array("EMAIL", "SMS", "AMBAS"))) {
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
                            $Mensaje = "$documento Datos (documentos) incompletos.";
                        } else {
                            if (empty($id_plantilla) && !is_file($camino_temporal)) {
                                $Res = -2;
                                $Mensaje = "No se pudo leer el documento.<br/>";
                            } else {
                                if (strpos($camino_temporal, 'plantillas') === false) {
                                    $fecha_hora = date("YmdHis");
                                    $carpeta_destino = "/doc_electronicos/plantillas/cliente_$id_cliente/$fecha_hora/documentos";
                                    $carpeta_destino_completa = storage_path() . $carpeta_destino;
                                    if (!is_dir($carpeta_destino_completa)) {
                                        mkdir($carpeta_destino_completa, 0777, true);
                                    }
                                    $camino_destino = "$carpeta_destino_completa/$id_documento.$extension";
                                    @unlink($camino_destino);
                                    if (!copy($camino_temporal, $camino_destino)) {
                                        $Res = -1;
                                        $Mensaje = "Ocurrió un error moviendo el documento.";
                                    }
                                    $camino_original = "$carpeta_destino/$id_documento.$extension";
                                } else {
                                    $camino_original = $camino_temporal;
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
            }
            if ($Res >= 0) {
                $url_banner = $request->input("url_banner");
                $cuerpo_email = $_POST['cuerpo_email'];

                if (empty($id_plantilla)) {
                    $plantilla = new Plantilla();
                } else {
                    $plantilla = Plantilla::find($id_plantilla);
                }
                $plantilla->id_cliente = $id_cliente;
                $plantilla->id_usuario = $id_usuario;
                $plantilla->nombre_plantilla = $nombre_plantilla;
                $plantilla->titulo_proceso = $titulo_proceso;
                $plantilla->cantidad_procesos = $cantidad_procesos;
                $plantilla->documentos = $documentos;
                $plantilla->storage = $storage;
                $plantilla->orden = $orden;
                $plantilla->variante_aceptacion = $variante_aceptacion;
                $plantilla->nombre_enmas = $nombreEnmas;
                $plantilla->correo_enmas = $correoEnmas;
                $plantilla->url_banner = $url_banner;
                $plantilla->cuerpo_email = $cuerpo_email;
                $plantilla->tipo_proceso = $tipo_proceso;
                $plantilla->firma_emisor = $firma_emisor;
                $plantilla->referencia_paginas = $referencia_paginas;
                $plantilla->origen_exigido = $origen_exigido;

                $plantilla->save();
            }
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
            \Log::error(
                "Error al guardar la plantilla : " . $e->getMessage() . " - Stacktrace: " . $e->getTraceAsString()
            );
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "La plantilla fue guardada con éxito.<br/>";
            $id = $plantilla->_id;
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "id" => $id), 200);
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

    public function GetPlantilla($id_plantilla = null)
    {
        if (empty($id_plantilla)) {
            return response()->view('errors.404', [], 404);
        }
        $plantilla = Plantilla::find($id_plantilla);
        if ($plantilla == null) {
            return response()->view('errors.404', [], 404);
        }
        return response()->json($plantilla, 200);
    }

    public function EditarPlantilla($id_plantilla = null)
    {
        if (empty($id_plantilla)) {
            return response()->view('errors.404', [], 404);
        }
        $plantilla = Plantilla::find($id_plantilla);
        if ($plantilla == null) {
            return response()->view('errors.404', [], 404);
        }
        return $this->MostrarNuevaPlantilla($id_plantilla);
    }
}