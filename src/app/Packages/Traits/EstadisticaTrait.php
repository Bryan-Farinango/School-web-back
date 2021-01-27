<?php

namespace App\Packages\Traits;

use App\Cliente;
use App\Http\Controllers\Doc\DocumentoController;
use App\Race\DocumentoRec;
use App\SubTipoDocumentoEmisionEnum;
use Auth;
use Carbon\Carbon;
use Config;
use DB;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use Session;

/**
 * Trait que contiene los métodos necesarios para subir una imágen como Avatar, banner o logo.
 *
 */
trait EstadisticaTrait
{

    function actualizarEstadisticas(Request $request)
    {
        $cliente = getCliente();

        $opcion = $request->input('opcion');
        Filtrar($opcion, "INTEGER", 1);

        $modulo = $request->input('modulo');
        Filtrar($modulo, "STRING", "");
        $pantalla_actual = $request->input('pantalla_actual');
        Filtrar($pantalla_actual, "STRING", "");

        //asignar el código para cada tipo de documento
        switch ($pantalla_actual) {
            case 'facturas':
                $tipoDocumento = '01';
                break;
            case 'notas-credito':
                $tipoDocumento = '04';
                break;
            case 'notas-debito':
                $tipoDocumento = '05';
                break;
            case 'retenciones':
                $tipoDocumento = '07';
                break;
            case 'guias-remision':
                $tipoDocumento = '06';
                break;
            case 'liquidacion-compras':
                $tipoDocumento = '03';
                break;
            default:
                $tipoDocumento = '00';
                break;
        }

        $estadoDocumento = $request->input('tipo_doc_actual');
        Filtrar($estadoDocumento, "STRING", "");
        $inputFechaInicio = $request->input('fecha_inicio');
        Filtrar($inputFechaInicio, "STRING", date("d/m/Y"));
        $inputFechaFin = $request->input('fecha_fin');
        Filtrar($inputFechaFin, "STRING", date("d/m/Y"));

        switch ($opcion) {
            case 1: //el boton de buscar ha sido activado
                switch ($modulo) {
                    case 'emision':
                        return $this->datosDocsEmitidos($inputFechaInicio, $inputFechaFin, $cliente, $tipoDocumento, $estadoDocumento);
                        break;
                    case 'recepcion':
                        return $this->datosDocsRecibidos($inputFechaInicio, $inputFechaFin, $cliente, $tipoDocumento, $estadoDocumento);
                        break;
                    default:
                        break;
                }
                break;
            default: //se ha ingresado al modulo pero aun no se ha hecho la busqueda
                //return default
                break;
        }

        //Se regresa un array vacio en caso de que no se haya buscado nada o que haya algún error en los parametros de busqueda.
        return response()->json(
            array(
                'categorias' => [],
            )
        );
    }

    function datosDocsEmitidos($inputFechaInicio, $inputFechaFin, $cliente, $tipoDocumento, $estadoDocumento)
    {
        $numero_docs = 0;
        $valor_total = 0;
        $valor_iva = 0;
        $dataVentas = array();
        $valor_subtotal = 0;
        $docs_normal = 0;
        $docs_reembolso = 0;
        $docs_exportacion = 0;
        $docs_negociable = 0;
        $datos_normal = 0;
        $datos_reembolso = 0;
        $datos_exportacion = 0;
        $datos_negociable = 0;
        $documento_afectado_string = '';

        $datos_match = array();


        if ($tipoDocumento == '00') {
            //hubo un problema con el tipo de documento
            return response()->json(array());
        }
        // RANGO DE FECHAS
        $i = explode("/", $inputFechaInicio);
        $f = explode("/", $inputFechaFin);
        $year = $i[2];
        $fechaInicio = Carbon::create($i[2], $i[1], $i[0], 0, 0, 0);
        $fechaFin = Carbon::create($f[2], $f[1], $f[0], 23, 59, 59);

        //tipos de documentos a los cuales se necesitara match(de momento solo nota de credito/debito)
        $requiereMatch = (($tipoDocumento == '04') || ($tipoDocumento == '05'));
        //asignar el código para cada tipo de documento
        switch ($estadoDocumento) {
            case 'docs_emitidos':
                //emitidos devolvera todos los documentos por lo que no hace falta asignar estado
                break;
            case 'docs_pendientes':
                //Se juntan los estados Pendiente(0), En procesamiento(5) y Regularizando (50)
                break;
            case 'docs_autorizadas':
                $estadoDocumento = 1;
                break;
            case 'docs_rechazadas':
                $estadoDocumento = 2;
                break;
            case 'docs_erradas':
                $estadoDocumento = 4; //se debe buscar en coleccion Documento_log
                break;
            case 'docs_anuladas':
                $estadoDocumento = 3;
                break;
            case 'docs_pendientes_anu':
                $estadoDocumento = 8;
                break;

            default:
                $tipoDocumento = -1;
                break;
        }
        $groupEmision = [
            '_id' => [
                'year' => ['$year' => '$fecha_emision'],
                'month' => ['$month' => '$fecha_emision'],
                'subtipo' => ['$ifNull' => ['$sub_tipo_documento', SubTipoDocumentoEmisionEnum::FACTURA_NORMAL]]
            ],
            'cantidad' => ['$sum' => 1],
            'valor_total' => ['$sum' => '$valor_total'],
            'valor_subtotal' => ['$sum' => '$valor_subtotal'],
            'valor_iva' => ['$sum' => '$valor_iva'],
            'valor_nota_credito' => ['$sum' => '$valor_nota_credito']
        ];

        $projectEmision = [
            '_id' => 0,
            'year' => '$_id.year',
            'month' => '$_id.month',
            'subtipo' => '$_id.subtipo',
            'cantidad' => '$cantidad',
            'valor_total' => '$valor_total',
            'valor_iva' => '$valor_iva',
            'valor_subtotal' => ['$sum' => '$valor_subtotal'],
            'valor_nota_credito' => '$valor_nota_credito',
        ];

        $sort = ['month' => -1, 'year' => -1];

        $projectMatch = [
            '_id' => '$_id',
            'emisor' => '$emisor_id',
            'receptor' => '$receptor_id',
            'nombre_receptor' => '$nomb_rec',
            'num_doc' => '$num_doc',
            'valor_total' => '$valor_total',
            'tipo_documento' => '$tipo_documento',
        ];
        // Ventas
        if ($estadoDocumento == 'docs_emitidos') { //Si se buscan documentos con todos los estados
            $cursor = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
                [
                    [
                        '$match' =>
                            [
                                'emisor_id' => $cliente->id,
                                'fecha_emision' => [
                                    '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                    '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                ],
                                'tipo_documento' => $tipoDocumento,
                            ]
                    ],
                    ['$group' => $groupEmision],
                    ['$project' => $projectEmision],
                    ['$sort' => $sort],
                ]
            );

            if ($requiereMatch) {
                $cursorDocs = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
                    [
                        [
                            '$match' =>
                                [
                                    'emisor_id' => $cliente->id,
                                    'fecha_emision' => [
                                        '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                        '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                    ],
                                    'tipo_documento' => $tipoDocumento,
                                ]
                        ],
                        ['$project' => $projectMatch],
                    ]
                );
            }
        } else {
            if ($estadoDocumento == 'docs_pendientes') { //si se necesitan filtrar por Pendientes/En procesamiento/ Regularizando
                $cursor = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
                    [
                        [
                            '$match' =>
                                [
                                    'emisor_id' => $cliente->id,
                                    'fecha_emision' => [
                                        '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                        '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                    ],
                                    'tipo_documento' => $tipoDocumento,
                                    '$or' => [
                                        ['estado' => 0],
                                        ['estado' => 5],
                                        ['estado' => 50]
                                    ]
                                ]
                        ],
                        ['$group' => $groupEmision],
                        ['$project' => $projectEmision],
                        ['$sort' => $sort],
                    ]
                );
                if ($requiereMatch) {
                    $cursorDocs = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
                        [
                            [
                                '$match' =>
                                    [
                                        'emisor_id' => $cliente->id,
                                        'fecha_emision' => [
                                            '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                            '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                        ],
                                        'tipo_documento' => $tipoDocumento,
                                        '$or' => [
                                            ['estado' => 0],
                                            ['estado' => 5],
                                            ['estado' => 50]
                                        ],
                                    ]
                            ],
                            ['$project' => $projectMatch],
                        ]
                    );
                }
            } else {
                if ($estadoDocumento == '04') { //erradas buscar en documento_log
                    $cursor = Documento_Log::raw()->aggregate(
                        [
                            [
                                '$match' =>
                                    [
                                        'emisor_id' => $cliente->id,
                                        'fecha_emision' => [
                                            '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                            '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                        ],
                                        'tipo_documento' => $tipoDocumento,
                                        'estado' => $estadoDocumento
                                    ]
                            ],
                            ['$group' => $groupEmision],
                            ['$project' => $projectEmision],
                            ['$sort' => $sort],
                        ]
                    );
                    if ($requiereMatch) {
                        $cursorDocs = Documento_Log::raw()->aggregate(
                            [
                                [
                                    '$match' =>
                                        [
                                            'emisor_id' => $cliente->id,
                                            'fecha_emision' => [
                                                '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                                '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                            ],
                                            'tipo_documento' => $tipoDocumento,
                                            'estado' => $estadoDocumento,
                                        ]
                                ],
                                ['$project' => $projectMatch],
                            ]
                        );
                    }
                } else {
                    $cursor = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
                        [
                            [
                                '$match' =>
                                    [
                                        'emisor_id' => $cliente->id,
                                        'fecha_emision' => [
                                            '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                            '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                        ],
                                        'tipo_documento' => $tipoDocumento,
                                        'estado' => $estadoDocumento
                                    ]
                            ],
                            ['$group' => $groupEmision],
                            ['$project' => $projectEmision],
                            ['$sort' => $sort],
                        ]
                    );
                    if ($requiereMatch) {
                        $cursorDocs = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
                            [
                                [
                                    '$match' =>
                                        [
                                            'emisor_id' => $cliente->id,
                                            'fecha_emision' => [
                                                '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                                '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                            ],
                                            'tipo_documento' => $tipoDocumento,
                                            'estado' => $estadoDocumento,
                                        ]
                                ],
                                ['$project' => $projectMatch],
                            ]
                        );
                    }
                }
            }
        }

        if ($requiereMatch) {
            $allDocs = $cursorDocs->toArray();
            $documentosIndividuales = function ($arr) {
                $result = $this->docAsociado($arr, $year);
                if (count($result) == 1) {
                    return $result[0];
                } else {
                    return null;
                }
            };
            $datos_match = array_map($documentosIndividuales, $allDocs);
            //chequeo si esque hubo algun error por el que me hayan devuelto un null value
            if (in_array(null, $datos_match, true)) {
                //elimino elementos con valor null
                $datos_match = array_filter($datos_match);
            }
        }

        $ventas = $cursor->toArray();
        foreach ($ventas as $venta) {
            $numero_docs += ($venta['cantidad']);
            $valor_total += ($venta['valor_total']);
            $valor_subtotal += ($venta['valor_subtotal']);
            $valor_iva += ($venta['valor_iva']);

            switch ($venta['subtipo']) {
                case '01':
                    $docs_normal += ($venta['cantidad']);
                    break;
                case '01-E': //Exportacion
                    $docs_exportacion += ($venta['cantidad']);
                    break;
                case '01-R': //Reembolso
                    $docs_reembolso += ($venta['cantidad']);
                    break;
                case '01-N': //Negociable
                    $docs_negociable += ($venta['cantidad']);
                    break;
                default:
                    // $docs_normal+=($venta['cantidad']);
                    break;
            }
        }
        (float)$otrosValores = (double)$valor_total - (double)$valor_subtotal - (double)$valor_iva;
        if ($otrosValores < 0.01) {
            $otrosValores = 0;
        }
        return response()->json(
            array(
                'categorias' => [
                    'Normal' => [$docs_normal, $datos_normal],
                    'Reembolso' => [$docs_reembolso, $datos_reembolso],
                    'Exportacion' => [$docs_exportacion, $datos_exportacion],
                    'Negociable' => [$docs_negociable, $datos_negociable]
                ],
                'numero_docs' => $numero_docs,
                'valor_total' => $valor_total,
                'valor_subtotal' => $valor_subtotal,
                'valor_iva' => $valor_iva,
                'otros_valores' => $otrosValores,
                'docs_match' => $datos_match
            )
        );
    }

    function docAsociado($arr, $year)
    {
        $numeroDocAsociado = $arr['documento_afectado'];
        $emisor = $arr['emisor'];
        $receptor = $arr['receptor'];

        $cursorDocs = (DocumentoController::getDocumentCollection($year))::raw()->aggregate(
            [
                [
                    '$match' =>
                        [
                            'num_doc' => $numeroDocAsociado,
                            'emisor_id' => $emisor,
                            'receptor_id' => $receptor
                        ]
                ],
                [
                    '$project' =>
                        [
                            'num_doc' => '$num_doc',
                            'valor_total' => '$valor_total',
                        ]
                ],
            ]
        );
        $docs = $cursorDocs->toArray();
        $result = array();
        foreach ($docs as $doc) {
            $result[] = array(
                'nombre_receptor' => $arr['nombre_receptor'],
                'num_doc' => $arr['num_doc'],
                'valor_doc' => $arr['valor_total'],
                'num_doc_asociado' => $doc['num_doc'],
                'valor_doc_asociado' => $doc['valor_total']
            );
        }
        return $result;
    }

    function datosDocsRecibidos($inputFechaInicio, $inputFechaFin, $cliente, $tipoDocumento, $estadoDocumento = null)
    {
        // RANGO DE FECHAS
        $i = explode("/", $inputFechaInicio);
        $f = explode("/", $inputFechaFin);
        $fechaInicio = Carbon::create($i[2], $i[1], $i[0], 0, 0, 0);
        $fechaFin = Carbon::create($f[2], $f[1], $f[0], 23, 59, 59);
        $groupRecepcion = [
            '_id' => [
                'emisor_id' => '$emisor_id',
                'year' => ['$year' => '$fecha_emision'],
                'month' => ['$month' => '$fecha_emision'],
                'valor' => '$valor_total'
            ],
            'cantidad' => ['$sum' => 1],
        ];

        $projectRecepcion = [
            '_id' => 0,
            'emisor_id' => '$_id.emisor_id',
            'valor' => '$_id.valor',
            'year' => '$_id.year',
            'month' => '$_id.month',
            'cantidad' => '$cantidad',
        ];

        $sort = ['month' => -1, 'year' => -1];
        $estadoSRI = 1; //estados Autorizados en SRI 
        if ($cliente->getWorkflows()) {
            //asignar el código para cada tipo de documento
            switch ($estadoDocumento) {
                case 'docs_recibidos':
                    $estadoDocumento = 0;
                    break;
                case 'docs_proceso':
                    $estadoDocumento = 1;
                    break;
                case 'docs_aprobadas':
                    $estadoDocumento = 2;
                    break;
                case 'docs_rechazadas':
                    $estadoDocumento = 3;
                    break;
                case 'docs_anuladas':
                    $estadoSRI = 3;
                    break;

                default:
                    $estadoDocumento = -1;
                    break;
            }
            if ($estadoSRI == 1) {
                $cursor = DocumentoRec::raw()->aggregate(
                    [
                        [
                            '$match' =>
                                [
                                    'receptor_id' => $cliente->id,
                                    'fecha_emision' => [
                                        '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                        '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                    ],
                                    'tipo_documento' => $tipoDocumento,
                                    'estado' => $estadoDocumento,
                                    'estado_sri' => $estadoSRI
                                ]
                        ],
                        ['$group' => $groupRecepcion],
                        ['$project' => $projectRecepcion],
                        ['$sort' => $sort]
                    ]
                );
            } else {
                $cursor = DocumentoRec::raw()->aggregate(
                    [
                        [
                            '$match' =>
                                [
                                    'receptor_id' => $cliente->id,
                                    'fecha_emision' => [
                                        '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                        '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                    ],
                                    'tipo_documento' => $tipoDocumento,
                                    'estado_sri' => $estadoSRI
                                ]
                        ],
                        ['$group' => $groupRecepcion],
                        ['$project' => $projectRecepcion],
                        ['$sort' => $sort]
                    ]
                );
            }
        } else {
            switch ($estadoDocumento) {
                case 'docs_anuladas':
                    $estadoSRI = 3;
                    break;
                default:
                    break;
            }

            $cursor = DocumentoRec::raw()->aggregate(
                [
                    [
                        '$match' =>
                            [
                                'receptor_id' => $cliente->id,
                                'fecha_emision' => [
                                    '$gte' => new UTCDateTime(strtotime($fechaInicio) * 1000),
                                    '$lte' => new UTCDateTime(strtotime($fechaFin) * 1000)
                                ],
                                'tipo_documento' => $tipoDocumento,
                                'estado_sri' => $estadoSRI
                            ]
                    ],
                    ['$group' => $groupRecepcion],
                    ['$project' => $projectRecepcion],
                    ['$sort' => $sort]
                ]
            );
        }
        //


        $proveedores = $cursor->toArray();
        foreach ($proveedores as $proveedor) {
            $proveedor['valor'] = floatval($proveedor['valor']);
        }

        $emisor = array_column($proveedores, 'emisor_id');
        $sort = array_multisort($emisor, SORT_DESC, $proveedores);
        $proveedoresArr = $this->getValuesArr('emisor_id', $proveedores, 'valor');

        $valor = array_column($proveedoresArr, 'y');
        $sort = array_multisort($valor, SORT_DESC, $proveedoresArr);


        $proveedores_importantes = array();
        $documentosRecibidos = 0;
        $counter = 0;
        $valorTotalRecibidos = 0;
        foreach ($proveedoresArr as $proveedores) {
            $client = Cliente::where('_id', $proveedores['id'])->get(["nombre_identificacion"])->first()->nombre_identificacion;
            if ($counter < 5) {
                $proveedores_importantes[] = array(
                    'name' => $client,
                    'y' => number_format($proveedores['y'], 2, '.', '') * 1,
                );
            }
            $documentosRecibidos += $proveedores['docs'];
            $valorTotalRecibidos += $proveedores['y'];
            $counter++;
        }

        return response()->json(
            array(
                'numero_docs' => $documentosRecibidos,
                'valor_total' => $valorTotalRecibidos,
                'proveedores_importantes' => $proveedores_importantes,
                'valorTotalRecibidos' => $valorTotalRecibidos,
            )
        );
    }

    function getValuesArr($id, $arr, $valor)
    {
        $result = array();
        $keyAnterior = "";
        $counter = 0;
        $numeroDocs = 0;

        foreach ($arr as $val) {
            if ($val[$id] != $keyAnterior) {
                $counter = 0;
                $numeroDocs = 0;
                $keyAnterior = $val[$id];
            }

            if (array_key_exists($id, $val)) {
                $numeroDocs += 1;
                $counter = $counter + $val[$valor];
                $result[$val[$id]] = array('id' => $val[$id], 'y' => $counter, 'docs' => $numeroDocs);
            } else {
                $counter = $counter + $val[$valor];
                $result[$id] = array('id' => $val[$id], 'y' => $counter, 'docs' => $numeroDocs);
            }
        }

        return $result;
    }

}  