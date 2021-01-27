<?php

namespace App\Packages\Model;

use App\Packages\Contracts\DocumentoRepository;
use App\Packages\Traits\{DetallesTrait, StupendoAdons};
use Carbon\Carbon;
use SimpleXMLElement;


class Factura extends DocumentoSri implements DocumentoRepository
{

    use DetallesTrait, StupendoAdons;

    private $dir_establecimiento;

    private $contribuyente_especial;

    private $obligado_contabilidad;

    private $tipo_identificacion_comprador;

    private $razon_social_comprador;

    private $identificacion_comprador;

    private $direccion_comprador;

    private $total_sin_impuestos;

    private $total_descuentos;

    private $propina;

    private $importe_total;


    public function __construct(SimpleXMLElement $documento = null)
    {
        $this->documento = $documento;

        if ($this->documento) {
            parent::__construct($this->documento);
            $this->dir_establecimiento = (string)$this->documento->infoFactura->dirEstablecimiento;
            $this->contribuyente_especial = (string)$this->documento->infoFactura->contribuyenteEspecial;
            $this->obligado_contabilidad = (string)$this->documento->infoFactura->obligadoContabilidad;
            $this->tipo_identificacion_comprador = (string)$this->documento->infoFactura->tipoIdentificacionComprador;
            $this->razon_social_comprador = (string)$this->documento->infoFactura->razonSocialComprador;
            $this->identificacion_comprador = (string)$this->documento->infoFactura->identificacionComprador;
            $this->direccion_comprador = (string)$this->documento->infoFactura->direccionComprador;
            $this->total_sin_impuestos = (string)$this->documento->infoFactura->totalSinImpuestos;
            $this->total_descuentos = (string)$this->documento->infoFactura->totalDescuento;
            $this->propina = (string)$this->documento->infoFactura->propina;
            $this->importe_total = (string)$this->documento->infoFactura->importeTotal;
            $this->moneda = (string)$this->documento->infoFactura->moneda;
            $this->descripcion_forma_pago = (string)$this->getDescripcionFormaPago();
        } else {
            parent::__construct($documento);
            $this->version = "1.0.0";
        }
    }

    public function getDireccionEstablecimiento()
    {
        return $this->dir_establecimiento;
    }

    public function getContribuyenteEspecial()
    {
        return $this->contribuyente_especial;
    }

    public function getObligadoContabilidad()
    {
        return $this->obligado_contabilidad;
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

    public function getDireccionComprador()
    {
        return $this->direccion_comprador;
    }

    public function getTotalSinImpuestos()
    {
        return $this->total_sin_impuestos;
    }

    public function getTotalDescuentos()
    {
        return $this->total_descuentos;
    }

    public function getPropina()
    {
        return $this->propina;
    }

    public function getImporteTotal()
    {
        return $this->importe_total;
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

            $object = (new Factura($documento))->setImpuestos()->setDetalles(1);
        } else {
            $documento = null;

            $object = (new Factura($documento));
        }

        return $object;
    }


    public function armarDocumento($data = [])
    {
        $this->setImpuestos();
        $this->setDescuentos();
        $this->setDetalles(1);

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
            $factura = [
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
                'infoFactura' => [
                    'fechaEmision' => Carbon::createFromFormat('Y-m-d', $data['fechaEmision'])->format('d/m/Y'),
                    'dirEstablecimiento' => $data['dirEstablecimiento'],
                    'contribuyenteEspecial' => $data['contribuyenteEspecial'],
                    'obligadoContabilidad' => $data['obligadoContabilidad'],
                    'tipoIdentificacionComprador' => ($data['tipoIdentificacionComprador']) ?: '04',
                    'razonSocialComprador' => $data['razonSocialComprador'],
                    'identificacionComprador' => $data['identificacionComprador'],
                    'totalSinImpuestos' => number_format((float)$data['totalSinImpuestos'], 2, '.', ''),
                    'totalDescuento' => number_format((float)$data['totalDescuento'], 2, '.', ''),
                    'totalConImpuestos' => [
                        'totalImpuesto' => [
                            'codigo' => $data['codigo'],
                            'codigoPorcentaje' => $data['codigoPorcentaje'],
                            'baseImponible' => number_format((float)$data['baseImponible'], 2, '.', ''),
                            'tarifa' => number_format((float)$data['tarifa'], 2, '.', ''),
                            'valor' => number_format((float)$data['valor'], 2, '.', ''),
                        ]
                    ],
                    'propina' => number_format((float)$data['propina'], 2, '.', ''),
                    'importeTotal' => number_format((float)$data['importeTotal'], 2, '.', ''),
                    'moneda' => $data['moneda'],
                    'pagos' => [
                        'pago' => [
                            'formaPago' => '',
                            'total' => '',
                            'plazo' => '',
                            'unidadTiempo' => 'dias'
                        ]
                    ]
                ],
                'detalles' => [
                    'detalle' => [
                        'codigoPrincipal' => '',
                        'codigoAuxiliar' => '',
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
                "<?xml version=\"1.0\" encoding=\"UTF-8\" ?><factura id=\"comprobante\" version=\"" . $this->version . "\"></factura>"
            );

            $this->arrayToXml($factura, $this->documento);
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
            'tarifa' => (string)$this->getPorcentajeImpuesto(),
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
            'contribuyenteEspecial' => (string)$this->getContribuyenteEspecial(),
            'obligadoContabilidad' => (string)$this->getObligadoContabilidad(),
            'tipoIdentificacionComprador' => (string)$this->getTipoIdentificacionComprador(),
            'razonSocialComprador' => (string)$this->getRazonSocialComprador(),
            'identificacionComprador' => (string)$this->getIdentificacionComprador(),
            'direccionComprador' => (string)$this->getDireccionComprador(),
            'totalSinImpuestos' => (string)$this->getTotalSinImpuestos(),
            'totalDescuento' => (string)$this->getTotalDescuentos(),
            'propina' => (string)$this->getPropina(),
            'importeTotal' => (string)$this->getImporteTotal(),
            'moneda' => (string)$this->getMoneda(),
            'formaPago' => (string)$this->getFormaPago(),
        ];

        return $this;
    }
}