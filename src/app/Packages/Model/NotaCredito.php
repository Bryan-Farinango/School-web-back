<?php

namespace App\Packages\Model;

use App\Packages\Contracts\DocumentoRepository;
use App\Packages\Traits\{DetallesTrait, StupendoAdons};
use Carbon\Carbon;
use SimpleXMLElement;


class NotaCredito extends DocumentoSri implements DocumentoRepository
{

    use DetallesTrait, StupendoAdons;

    private $dir_establecimiento;

    private $tipo_identificacion_comprador;

    private $razon_social_comprador;

    private $identificacion_comprador;

    private $cod_doc_modificado;

    private $num_doc_modificado;

    private $fecha_emision_doc_sustento;

    private $total_sin_impuestos;

    private $valor_modificacion;

    private $motivo;


    public function __construct(SimpleXMLElement $documento = null)
    {
        $this->documento = $documento;

        if ($this->documento) {
            parent::__construct($this->documento);
            $this->dir_establecimiento = (string)$this->documento->infoNotaCredito->dirEstablecimiento;
            $this->tipo_identificacion_comprador = (string)$this->documento->infoNotaCredito->tipoIdentificacionComprador;
            $this->razon_social_comprador = (string)$this->documento->infoNotaCredito->razonSocialComprador;
            $this->identificacion_comprador = (string)$this->documento->infoNotaCredito->identificacionComprador;
            $this->cod_doc_modificado = (string)$this->documento->infoNotaCredito->codDocModificado;
            $this->num_doc_modificado = (string)$this->documento->infoNotaCredito->numDocModificado;
            $this->fecha_emision_doc_sustento = (string)$this->documento->infoNotaCredito->fechaEmisionDocSustento;
            $this->total_sin_impuestos = (string)$this->documento->infoNotaCredito->totalSinImpuestos;
            $this->valor_modificacion = (string)$this->documento->infoNotaCredito->valorModificacion;
            $this->motivo = (string)$this->documento->infoNotaCredito->motivo;
            $this->moneda = (string)$this->documento->infoNotaCredito->moneda;
        } else {
            parent::__construct($documento);
            $this->version = "1.1.0";
        }
    }

    public function getDireccionEstablecimiento()
    {
        return $this->dir_establecimiento;
    }

    public function getTipoIdentificacionComprador()
    {
        return $this->tipo_identificacion_comprador;
    }

    public function getRazonSocialComprador()
    {
        return $this->razon_social_comprador;
    }

    public function getIdentificacionComprador()
    {
        return $this->identificacion_comprador;
    }

    public function getCodDocModificado()
    {
        return $this->cod_doc_modificado;
    }

    public function getNumDocModificado()
    {
        return $this->num_doc_modificado;
    }

    public function getFechaEmisionDocSustento()
    {
        return $this->fecha_emision_doc_sustento;
    }

    public function getTotalSinImpuestos()
    {
        return $this->total_sin_impuestos;
    }

    public function getValorModificacion()
    {
        return $this->valor_modificacion;
    }

    public function getMotivo()
    {
        return $this->motivo;
    }


    public static function build($xml, $options = [])
    {
        $obj_options = (object)$options;

        if (isset($xml)) {
            if ($obj_options->decode) {
                $documento = new SimpleXMLElement(base64_decode(trim($xml)));
            } else {
                $documento = new SimpleXMLElement(trim($xml));
            }

            $object = (new NotaCredito($documento))->setImpuestos()->setDetalles(4);

            return $object;
        } else {
            $documento = null;

            $object = (new NotaCredito($documento));
        }
    }


    public function armarDocumento($data = [])
    {
        $this->setImpuestos();
        $this->setDescuentos();
        $this->setDetalles(4);

        return $this->data = array_merge(
            $data,
            [
                'xml' => $this->documento,
                'logo' => config('reportes.logo'),
                'tarifa' => $this->tarifa_impuesto,
                'detalles' => $this->detalles,
                'headers' => $this->headers,
                'valoriva2' => $this->valoriva2,
                'valorice' => $this->valorice,
                'valorirb' => $this->valorirb,
                'valorivaNO' => $this->valorivaNO,
                'valorivaEX' => $this->valorivaEX,
                'baseImponible2' => $this->baseImponible2,
                'baseImponible0' => $this->baseImponible0,
                'baseImponibleice' => $this->baseImponibleice,
                'baseImponibleirb' => $this->baseImponibleirb,
                'baseImponibleNO' => $this->baseImponibleNO,
                'baseImponibleEX' => $this->baseImponibleEX,
                'descuentos' => $this->descuentos,
            ]
        );
    }


    public function armarFisico($data = [], $impuestos = [])
    {
        if ($data) {
            $notaCredito = [
                'infoTributaria' => [
                    'ambiente' => 1,
                    'tipoemision' => 1,
                    'razonSocial' => $data['razonSocial'],
                    'nombreComercial' => $data['nombreComercial'],
                    'ruc' => $data['ruc'],
                    'claveAcceso' => ($data['claveAcceso']) ?: $data['numeroAutorizacion'],
                    'codDoc' => str_pad($data['codDoc'], 2, "0", STR_PAD_LEFT),
                    'estab' => str_pad($data['estab'], 3, "0", STR_PAD_LEFT),
                    'ptoEmi' => str_pad($data['ptoEmi'], 3, "0", STR_PAD_LEFT),
                    'secuencial' => str_pad($data['secuencial'], 9, "0", STR_PAD_LEFT),
                    'dirMatriz' => $data['dirMatriz'],
                ],
                'infoNotaCredito' => [
                    'fechaEmision' => Carbon::createFromFormat('Y-m-d', $data['fechaEmision'])->format('d/m/Y'),
                    'dirEstablecimiento' => $data['dirEstablecimiento'],
                    'tipoIdentificacionComprador' => $data['tipoIdentificacionComprador'],
                    'razonSocialComprador' => $data['razonSocialComprador'],
                    'identificacionComprador' => $data['identificacionComprador'],
                    'codDocModificado' => $data['codDocModificado'],
                    'numDocModificado' => $data['numDocModificado'],
                    'fechaEmisionDocSustento' => Carbon::createFromFormat(
                        'Y-m-d',
                        $data['fechaEmisionDocSustento']
                    )->format('d/m/Y'),
                    'totalSinImpuestos' => number_format((float)$data['totalSinImpuestos'], 2, '.', ''),
                    'valorModificacion' => number_format((float)$data['valorModificacion'], 2, '.', ''),
                    'moneda' => $data['moneda'],
                    'totalConImpuestos' => [
                        'totalImpuesto' => [
                            'codigo' => $data['codigo'],
                            'codigoPorcentaje' => $data['codigoPorcentaje'],
                            'baseImponible' => number_format((float)$data['baseImponible'], 2, '.', ''),
                            'valor' => number_format((float)$data['valor'], 2, '.', ''),
                        ]
                    ],
                    'motivo' => $data['motivo'],
                ],
                'detalles' => [
                    'detalle' => [
                        'codigoInterno' => '',
                        'codigoAdicional' => '',
                        'descripcion' => '',
                        'cantidad' => '',
                        'precioUnitario' => '',
                        'descuento' => '',
                        'precioTotalSinImpuesto' => '',
                        'detallesAdicionales' => [
                            'detAdicional' => ''
                        ],
                        'impuestos' => [
                            'impuesto' => [
                                'codigo' => '',
                                'codigoPorcentaje' => '',
                                'tarifa' => '',
                                'baseImponible' => '',
                                'valor' => '',
                            ]
                        ]
                    ]
                ],
                'infoAdicional' => []
            ];

            $this->documento = new SimpleXMLElement(
                "<?xml version=\"1.0\" encoding=\"UTF-8\" ?><notaCredito id=\"comprobante\" version=\"" . $this->version . "\"></notaCredito>"
            );

            $this->arrayToXml($notaCredito, $this->documento);
        }

        return $this;
    }


    public function groupDataFromXml()
    {
        $this->data = [
            'ambiente' => (string)$this->getAmbiente(),
            'tipoEmision' => (string)$this->getTipoEmision(),
            'razonSocial' => (string)$this->getRazonSocial(),
            'nombreComercial' => (string)$this->getRazonComercial(),
            'ruc' => (string)$this->getRuc(),
            'claveAcceso' => (string)$this->getClaveAcceso(),
            'codDoc' => (string)$this->getCodDoc(),
            'estab' => (string)$this->getEstablecimiento(),
            'ptoEmi' => (string)$this->getPuntoEmision(),
            'secuencial' => (string)$this->getSecuencial(),
            'dirMatriz' => (string)$this->getDireccionMatriz(),
            'baseImponibleDiffCero' => (string)$this->getBaseImponibleDiffCero(),
            'valorIvaDiffCero' => (string)$this->getValorIvaDiffCero(),
            'baseImponibleIgualCero' => (string)$this->getBaseImponibleIgualCero(),
            'valorIvaIgualCero' => (string)$this->getValorIvaIgualCero(),
            'baseImponibleNoObjetoImpuesto' => (string)$this->getBaseImponibleNoObjetoImpuesto(),
            'valorIvaNoObjetoImpuesto' => (string)$this->getValorIvaNoObjetoImpuesto(),
            'baseImponibleExentoIva' => (string)$this->getBaseImponibleExento(),
            'valorIvaExento' => (string)$this->getValorIvaExento(),
            'baseImponibleIvaIce' => (string)$this->getBaseImponibleIce(),
            'valorIvaIce' => (string)$this->getValorIvaIce(),
            'baseImponibleIvaIrbpnr' => (string)$this->getBaseImponibleIrbpnr(),
            'valorIvaIrbpnr' => (string)$this->getValorIvaIrbpnr(),
            'dirEstablecimiento' => (string)$this->getDireccionEstablecimiento(),
            'tipoIdentificacionComprador' => (string)$this->getTipoIdentificacionComprador(),
            'razonSocialComprador' => (string)$this->getRazonSocialComprador(),
            'identificacionComprador' => (string)$this->getIdentificacionComprador(),
            'codDocModificado' => (string)$this->getCodDocModificado(),
            'numDocModificado' => (string)$this->getNumDocModificado(),
            'fechaEmisionDocSustento' => (string)$this->getFechaEmisionDocSustento(),
            'totalSinImpuestos' => (string)$this->getTotalSinImpuestos(),
            'valorModificacion' => (string)$this->getValorModificacion(),
            'motivo' => (string)$this->getMotivo(),
            'moneda' => (string)$this->getMoneda()
        ];

        return $this;
    }

}