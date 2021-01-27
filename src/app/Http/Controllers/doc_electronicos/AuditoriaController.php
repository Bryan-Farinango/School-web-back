<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\Acuerdo;
use App\doc_electronicos\Auditoria;
use App\doc_electronicos\Firma;
use App\doc_electronicos\Proceso;
use App\doc_electronicos\ProcesoSimple;
use App\doc_electronicos\TipoDeAuditoriaDE;
use App\Http\Controllers\Controller;
use App\Usuarios;
use Carbon\Carbon;
use Config;
use Excel;
use Exception;
use Illuminate\Http\Request;


class AuditoriaController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarFiltroAuditoria()
    {
        $arretiquetas = array("opciones_tipo", "desde", "hasta");
        $opciones_tipo = TipoDeAuditoriaDE::get_options_tipos_auditoria();
        $desde = Carbon::now()->format('01/m/Y');
        $hasta = Carbon::now()->format('d/m/Y');
        $arrvalores = array($opciones_tipo, $desde, $hasta);
        return view("doc_electronicos.auditoria.filtro_auditoria", array_combine($arretiquetas, $arrvalores));
    }

    public function MostrarListaRegistrosAuditoria(Request $request)
    {
        $draw = $request->input('draw');
        $skip = (integer)$request->input('start');
        $take = (integer)$request->input('length');

        $registros = $this->ObtenerRegistros($request);

        $result = array();
        $skipCount = 0;
        $takeCount = 0;
        $records_total = 0;
        foreach ($registros as $registro) {
            if (!empty($registro["cliente"]["identificacion"]) && !empty($registro["usuario"]["nombre"])) {
                $skipCount++;
                $records_total++;
                if ($skipCount > $skip && $takeCount < $take) {
                    $takeCount++;
                    $result[] =
                        [
                            "_id" => EncriptarId($registro["_id"]),
                            "momento_mostrar" => FormatearMongoISODate($registro["momento"], "d/m/Y H:i:s"),
                            "momento_orden" => FormatearMongoISODate($registro["momento"], "U"),
                            "cliente" => $registro["cliente"]["nombre_identificacion"],
                            "usuario" => $registro["usuario"]["nombre"] . " - " . $registro["usuario"]["email"],
                            "identificacion" => $registro["cliente"]["identificacion"],
                            "tipo" => $registro["tipo_registro"]["tipo"],
                            "detalles" => ""
                        ];
                }
            }
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

    public function ExportarExcel(Request $request)
    {
        $registros = $this->ObtenerRegistros($request);
        $result = array(
            [
                'MOMENTO',
                'CLIENTE',
                'USUARIO',
                'IDENTIFICACION',
                'TIPO'
            ]
        );

        foreach ($registros as $registro) {
            if (!empty($registro["cliente"]["identificacion"]) && !empty($registro["usuario"]["nombre"])) {
                $result[] =
                    [
                        FormatearMongoISODate($registro["momento"], "d/m/Y H:i:s"),
                        $registro["cliente"]["nombre_identificacion"],
                        $registro["usuario"]["nombre"] . " - " . $registro["usuario"]["email"],
                        $registro["cliente"]["identificacion"],
                        $registro["tipo_registro"]["tipo"]
                    ];
            }
        }

        Excel::create(
            'Registros_Auditoria',
            function ($excel) use ($result) {
                $excel->sheet(
                    'Reporte Facturacion',
                    function ($sheet) use ($result) {
                        $sheet->setOrientation('landscape');
                        $sheet->fromArray($result, null, 'A1', false, false);
                    }
                );
            }
        )->download('xls');
    }

    private function ObtenerRegistros(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $filtro_cliente = $request->input("filtro_cliente");
        Filtrar($filtro_cliente, "STRING", "");
        $filtro_usuario = $request->input("filtro_usuario");
        Filtrar($filtro_usuario, "STRING", "");
        $filtro_identificacion = $request->input("filtro_identificacion");
        Filtrar($filtro_identificacion, "STRING", "");
        $filtro_desde = $request->input("filtro_desde");
        Filtrar($filtro_desde, "STRING", "");
        $filtro_hasta = $request->input("filtro_hasta");
        Filtrar($filtro_hasta, "STRING", "");
        $filtro_tipo = $request->input("filtro_tipo");
        Filtrar($filtro_tipo, "INTEGER", -1);
        $origen_soporte = $request->input("origen_soporte");
        Filtrar($origen_soporte, "INTEGER");

        switch ($request->input("order")[0]["column"]) {
            case 0:
            {
                $order_column = "momento";
                break;
            }
            case 1:
            {
                $order_column = ($origen_soporte == 1) ? "id_cliente" : "id_usuario";
                break;
            }
            case 2:
            {
                $order_column = ($origen_soporte == 1) ? "id_usuario" : "tipo_auditoria";
                break;
            }
            case 3:
            {
                $order_column = "tipo_auditoria";
                break;
            }
            default:
            {
                $order_column = "momento";
                break;
            }
        }
        $order_dir = $request->input("order")[0]["dir"];
        Filtrar($order_dir, "STRING", "desc");

        $registros = Auditoria::select("_id", "momento", "tipo_auditoria", "id_usuario", "id_cliente")
            ->with(
                [
                    'tipo_registro' =>
                        function ($query) use ($filtro_cliente) {
                            if (!empty($filtro_cliente)) {
                                $query->select("id_tipo", "tipo")
                                    ->where("nombre_identificacion", "like", "%$filtro_cliente%");
                            } else {
                                $query->select("id_tipo", "tipo");
                            }
                        },
                    'usuario' =>
                        function ($query) use ($filtro_usuario) {
                            if (!empty($filtro_usuario)) {
                                $query->select("nombre", "email")
                                    ->where("nombre", "like", "%$filtro_usuario%");
                            } else {
                                $query->select("nombre", "email");
                            }
                        },
                    'cliente' =>
                        function ($query) use ($filtro_identificacion) {
                            if (!empty($filtro_identificacion)) {
                                $query->select("nombre_identificacion", "identificacion")
                                    ->where("identificacion", "like", "%$filtro_identificacion%");
                            } else {
                                $query->select("nombre_identificacion", "identificacion");
                            }
                        }
                ]
            );
        if ($origen_soporte != 1) {
            $registros = $registros->where("id_cliente", $id_cliente)->orWhere("referencia_asociado", $id_cliente);
        }
        if (!empty($filtro_desde)) {
            $registros = $registros->where(
                "momento",
                ">=",
                Carbon::createFromFormat("d/m/Y H:i:s", $filtro_desde . " 00:00:00")
            );
        }
        if (!empty($filtro_hasta)) {
            $registros = $registros->where(
                "momento",
                "<=",
                Carbon::createFromFormat("d/m/Y H:i:s", $filtro_hasta . " 23:59:59")
            );
        }
        if ($filtro_tipo != -1) {
            $registros = $registros->where("tipo_auditoria", (int)$filtro_tipo);
        }

        return $registros->orderBy($order_column, $order_dir)->get();
    }

    public function VerDetallesRegistro(Request $request, $esPdf = false)
    {
        $id_auditoria = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_auditoria, "STRING");
        $arretiquetas = array(
            "es_pdf",
            "banner",
            "id_auditoria",
            "id_tipo",
            "tipo",
            "nombre_usuario",
            "representando",
            "momento",
            "ip",
            "sistema_operativo",
            "navegador",
            "agente"
        );
        $registro = Auditoria::find($id_auditoria);
        $id_tipo = (int)$registro->tipo_auditoria;
        $tipo = TipoDeAuditoriaDE::where("id_tipo", $id_tipo)->first(["tipo"])["tipo"];
        $usuario = Usuarios::find($registro->id_usuario);
        $cliente = Cliente::find($registro->id_cliente);

        $nombre_usuario = $usuario->nombre . " (" . $usuario->email . ")";
        $representando = $cliente->nombre_identificacion . " (" . $cliente->identificacion . ")";
        $momento = FormatearMongoISODate($registro->momento, "d/m/Y H:i:s");
        $ip = $registro->ip;
        $sistema_operativo = $registro->sistema_operativo;
        $navegador = isset($registro->navegador) ? $registro->navegador : "";
        $agente = isset($registro->agente) ? $registro->agente : "";
        $referencia_1 = $registro->referencia_1;
        $referencia_2 = $registro->referencia_2;

        $arrvalores = array(
            $esPdf,
            Config::get('app.url') . '/email/img/header2.png',
            $id_auditoria,
            $id_tipo,
            $tipo,
            $nombre_usuario,
            $representando,
            $momento,
            $ip,
            $sistema_operativo,
            $navegador,
            $agente
        );

        if ((int)$id_tipo == 2) {
            $arretiquetas = array_merge($arretiquetas, array("tipo_acuerdo", "texto_acuerdo", "cliente_destino"));
            $acuerdo = Acuerdo::find($referencia_1);
            $cliente_destino = Cliente::find($acuerdo->id_cliente_destino)["nombre_identificacion"];
            switch ($acuerdo->tipo_acuerdo) {
                case 1:
                {
                    $tipo_acuerdo = "Acuerdo de uso de medios electrónicos con Stupendo.";
                    $cliente_destino = "Stupendo";
                    break;
                }
                case 2:
                {
                    $tipo_acuerdo = "Acuerdo de uso de medios electrónicos con cliente emisor.";
                    break;
                }
                case 3:
                {
                    $tipo_acuerdo = "Reconocimiento de persona jurídica.";
                    break;
                }
                case 4:
                {
                    $tipo_acuerdo = "Acuerdo de generación y uso de firma electrónica Stupendo.";
                    $cliente_destino = "Stupendo";
                    break;
                }
                default:
                {
                    $tipo_acuerdo = "Acuerdo no tipificado.";
                    $cliente_destino = "No tipificado";
                    break;
                }
            }
            $texto_acuerdo = $acuerdo->texto;
            $arrvalores = array_merge($arrvalores, array($tipo_acuerdo, $texto_acuerdo, $cliente_destino));
        } else {
            if (in_array((int)$id_tipo, array(3, 4, 5))) {
                $arretiquetas = array_merge($arretiquetas, array("enlace_firma"));
                $firma = Firma::find($referencia_1);
                $enlace_firma = '<a><label id="LMostrarFirma_' . EncriptarId(
                        $firma->_id
                    ) . '" style="cursor:pointer; color:202C52">Mostrar detalles de la firma</label></a>';
                $arrvalores = array_merge($arrvalores, array($enlace_firma));
            } else {
                if ((int)$id_tipo == 6) {
                    $arretiquetas = array_merge(
                        $arretiquetas,
                        array(
                            "titulo",
                            "usuario_emisor",
                            "cliente_emisor",
                            "orden",
                            "firma_emisor",
                           // "firma_stupendo",
                           // "sello_tiempo",
                            "documentos_originales",
                            "signatarios"
                        )
                    );
                    $proceso = Proceso::find($referencia_1);
                    $titulo = $proceso->titulo;
                    $usuario_emisor = Usuarios::find($proceso->id_usuario_emisor);
                    $usuario_emisor = $usuario_emisor->nombre . " (" . $usuario_emisor->email . ")";
                    $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
                    $cliente_emisor = $cliente_emisor->nombre_identificacion . " (" . $cliente_emisor->identificacion . ")";
                    $orden = $proceso->orden == 1 ? "Paralelo" : "Secuencial";
                    switch ($proceso->firma_emisor) {
                        case 0:
                        {
                            $firma_emisor = "No estamparse.";
                            break;
                        }
                        case 1:
                        {
                            $firma_emisor = "Primera en estamparse.";
                            break;
                        }
                        case 2:
                        {
                            $firma_emisor = "Última en estamparse.";
                            break;
                        }
                    }
                    $firma_stupendo = 2; //nuevas
                    $sello_tiempo = 2;  //nuevas
                    //$firma_stupendo = $proceso->firma_stupendo == 0 ? "No estamparse." : "Estamparse al final.";
                    $firma_stupendo == 0 ? "No estamparse." : "Estamparse al final.";

                    $sello_tiempo  == 0 ? "No agregar." : "Agregar al final.";
                    $documentos_originales = '<a><label id="LMostrarOriginales_' . EncriptarId(
                            $proceso->_id
                        ) . '" style="cursor:pointer; color:202C52">Mostrar documentos originales</label></a>';
                    $signatarios = '<a><label id="LMostrarSignatarios_' . EncriptarId(
                            $proceso->_id
                        ) . '" style="cursor:pointer; color:202C52">Mostrar signatarios</label></a>';
                    $arrvalores = array_merge(
                        $arrvalores,
                        array(
                            $titulo,
                            $usuario_emisor,
                            $cliente_emisor,
                            $orden,
                            $firma_emisor,
                            $firma_stupendo,
                            $sello_tiempo,
                            $documentos_originales,
                            $signatarios
                        )
                    );
                } else {
                    if (in_array((int)$id_tipo, array(7, 8))) {
                        $arretiquetas = array_merge(
                            $arretiquetas,
                            array("enlace_documento_antes", "enlace_documento_despues")
                        );
                        $proceso = Proceso::find($referencia_1);
                        $id_documento = $referencia_2;
                        $index = -1;
                        $indice_accion_previa = -1;
                        $indice_accion = -1;
                        foreach ($proceso->historial as $hito) {
                            $index++;
                            if (($hito["id_cliente_receptor"] != $registro->id_cliente && (isset($hito["id_cliente_emisor"])
                                        && $hito["id_cliente_emisor"] != $registro->id_cliente)) && $hito["id_documento"] == $id_documento) {
                                $indice_accion_previa = $index;
                            } else {
                                if (($hito["id_cliente_receptor"] == $registro->id_cliente || (isset($hito["id_cliente_emisor"])
                                            && $hito["id_cliente_emisor"] == $registro->id_cliente)) && $hito["id_documento"] == $id_documento) {
                                    $indice_accion = $index;
                                    break;
                                }
                            }
                        }
                        $pc = new ProcesoController();
                        $enlace_documento_despues = $pc->getAdjuntoFromHito(
                            $proceso->_id,
                            ($proceso->historial)[$indice_accion]
                        );
                        if ($indice_accion_previa > -1) {
                            $enlace_documento_antes = $pc->getAdjuntoFromHito(
                                $proceso->_id,
                                ($proceso->historial)[$indice_accion_previa]
                            );
                        } else {
                            $enlace_documento_antes = $pc->getAdjuntoFromHito($proceso->_id, null, $id_documento);
                        }
                        $arrvalores = array_merge(
                            $arrvalores,
                            array($enlace_documento_antes, $enlace_documento_despues)
                        );
                    } else {
                        if (in_array((int)$id_tipo, array(11, 12, 13))) {
                            $arretiquetas = array_merge(
                                $arretiquetas,
                                array(
                                    "titulo",
                                    "usuario_emisor",
                                    "cliente_emisor",
                                    "orden",
                                    "documentos_originales",
                                    "signatarios",
                                    "variante_aceptacion",
                                    "mensaje_email",
                                    "empresa_notificacion"
                                )
                            );
                            $proceso = ProcesoSimple::find($referencia_1);
                            $titulo = $proceso->titulo;
                            $usuario_emisor = Usuarios::find($proceso->id_usuario_emisor);
                            $usuario_emisor = $usuario_emisor->nombre . " (" . $usuario_emisor->email . ")";
                            $cliente_emisor = Cliente::find($proceso->id_cliente_emisor);
                            $cliente_emisor = $cliente_emisor->nombre_identificacion . " (" . $cliente_emisor->identificacion . ")";
                            $empresa_notificacion = $referencia_2;
                            $orden = $proceso->orden == 1 ? "Paralelo" : "Secuencial";
                            switch ($proceso->variante_aceptacion) {
                                case "EMAIL":
                                {
                                    $variante_aceptacion = "Aceptación vía Correo Electrónico";
                                    break;
                                }
                                case "SMS":
                                {
                                    $variante_aceptacion = "Aceptación vía SMS";
                                    break;
                                }
                                case "AMBAS":
                                {
                                    $variante_aceptacion = "Aceptación vía Correo Electrónico y SMS";
                                    break;
                                }
                            }
                            $mensaje_email = base64_decode($proceso->mensaje_email);
                            $documentos_originales = '<a><label id="LMostrarOriginales_' . EncriptarId(
                                    $proceso->_id
                                ) . '" style="cursor:pointer; color:202C52">Mostrar documentos</label></a>';
                            $signatarios = '<a><label id="LMostrarSignatarios_' . EncriptarId(
                                    $proceso->_id
                                ) . '" style="cursor:pointer; color:202C52">Mostrar participantes</label></a>';
                            $arrvalores = array_merge(
                                $arrvalores,
                                array(
                                    $titulo,
                                    $usuario_emisor,
                                    $cliente_emisor,
                                    $orden,
                                    $documentos_originales,
                                    $signatarios,
                                    $variante_aceptacion,
                                    $mensaje_email,
                                    $empresa_notificacion
                                )
                            );

                            if ($esPdf) {
                                $procesoSimpleCtrl = new ProcesoSimpleController();
                                $arrDetallesDocumentos = $procesoSimpleCtrl->GetDetallesDocumentos($referencia_1);
                                $arretiquetas = array_merge($arretiquetas, array_keys($arrDetallesDocumentos));
                                $arrvalores = array_merge($arrvalores, array_values($arrDetallesDocumentos));

                                if ($id_tipo == 11) {
                                    $arrListaParticipantes = $procesoSimpleCtrl->GetListaParticipantes(
                                        $referencia_1,
                                        false
                                    );
                                    $arretiquetas = array_merge($arretiquetas, array_keys($arrListaParticipantes));
                                    $arrvalores = array_merge($arrvalores, array_values($arrListaParticipantes));
                                }
                            }
                        }
                    }
                }
            }
        }
        return view("doc_electronicos.auditoria.registro", array_combine($arretiquetas, $arrvalores));
    }

    public function VerDetallesRegistroPdf(Request $request)
    {
        return self::VerDetallesRegistro($request, true);
    }

    public function PdfDetallesRegistro(Request $request)
    {
        $pdf = \App::make('snappy.pdf.wrapper');

        $pdf->loadHTML(self::VerDetallesRegistro($request, true))
            ->setPaper('a4')
            ->setOrientation('portrait')
            ->setOption('encoding', 'UTF-8')
            ->setOption('margin-left', 5)
            ->setOption('margin-right', 5)
            ->setOption('footer-font-size', 8)
            ->setOption('footer-center', utf8_decode('[page] de [topage]'));

        $result = null;
        try {
            $result = $pdf->stream('detalle-auditoria.pdf');
        } catch (Exception $exc) {
            \Log::info('Error al crear PDF de registros auditoria');
            \Log::error($exc);
        }
        return $result;
    }
}