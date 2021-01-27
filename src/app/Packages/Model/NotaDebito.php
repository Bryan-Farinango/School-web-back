<?php

namespace App\Packages\Model;

use App\Packages\Contracts\DocumentoRepository;
use App\Packages\Traits\{StupendoAdons};
use Carbon\Carbon;
use SimpleXMLElement;


class NotaDebito extends DocumentoSri implements DocumentoRepository
{

    use StupendoAdons;

    private $dir_establecimiento;

    private $contribuyente_especial;

    private $obligado_contabilidad;

    private $tipo_identificacion_comprador;

    private $razon_social_comprador;

    private $identificacion_comprador;

    private $cod_doc_modificado;

    private $num_doc_modificado;

    private $fecha_emision_doc_sustento;

    private $total_sin_impuestos;

    private $valor_total;

    private $motivo_razon;

    private $motivo_valor;

    public function __construct(SimpleXMLElement $documento = null)
    {
        $this->documento = $documento;

        if ($this->documento) {
            parent::__construct($this->documento);
            $this->dir_establecimiento = (string)$this->documento->infoNotaDebito->dirEstablecimiento;
            $this->contribuyente_especial = (string)$this->documento->infoNotaDebito->contribuyenteEspecial;
            $this->obligado_contabilidad = (string)$this->documento->infoNotaDebito->obligadoContabilidad;
            $this->tipo_identificacion_comprador = (string)$this->documento->infoNotaDebito->tipoIdentificacionComprador;
            $this->razon_social_comprador = (string)$this->documento->infoNotaDebito->razonSocialComprador;
            $this->identificacion_comprador = (string)$this->documento->infoNotaDebito->identificacionComprador;
            $this->cod_doc_modificado = (string)$this->documento->infoNotaDebito->codDocModificado;
            $this->num_doc_modificado = (string)$this->documento->infoNotaDebito->numDocModificado;
            $this->fecha_emision_doc_sustento = (string)$this->documento->infoNotaDebito->fechaEmisionDocSustento;
            $this->total_sin_impuestos = (string)$this->documento->infoNotaDebito->totalSinImpuestos;
            $this->valor_total = (string)$this->documento->infoNotaDebito->valorTotal;
            $this->moneda = (string)$this->documento->infoNotaDebito->moneda;
            $this->descripcion_forma_pago = (string)$this->getDescripcionFormaPago();
            $this->setMotivos();
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

    public function getValorTotal()
    {
        return $this->valor_total;
    }

    public function getMotivoRazon()
    {
        return $this->motivo_razon;
    }

    public function getMotivoValor()
    {
        return $this->motivo_valor;
    }

    public function setMotivos()
    {
        $motivos = $this->documento->xpath("//motivos");

        if ($motivos) {
            $this->motivo_razon = (string)$motivos[0]->motivo->razon;
            $this->motivo_valor = (string)$motivos[0]->motivo->valor;
        }
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

            $object = (new NotaDebito($documento))->setImpuestos();

            return $object;
        } else {
            $documento = null;

            $object = (new NotaDebito($documento));
        }
    }


    public function armarDocumento($data = [])
    {
        $this->setImpuestos();

        return $this->data = array_merge(
            $data,
            [
                'xml' => $this->documento,
                'logo' => config('reportes.logo'),
                'tarifa' => $this->tarifa_impuesto,
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
            ]
        );
    }


    public function armarFisico($data = [], $impuestos = [])
    {
        if ($data) {
            $notaDebito = [
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
                'infoNotaDebito' => [
                    'fechaEmision' => Carbon::createFromFormat('Y-m-d', $data['fechaEmision'])->format('d/m/Y'),
                    'dirEstablecimiento' => $data['dirEstablecimiento'],
                    'tipoIdentificacionComprador' => $data['tipoIdentificacionComprador'],
                    'razonSocialComprador' => $data['razonSocialComprador'],
                    'identificacionComprador' => $data['identificacionComprador'],
                    'contribuyenteEspecial' => $data['contribuyenteEspecial'],
                    'obligadoContabilidad' => $data['obligadoContabilidad'],
                    'codDocModificado' => $data['codDocModificado'],
                    'numDocModificado' => $data['numDocModificado'],
                    'fechaEmisionDocSustento' => Carbon::createFromFormat(
                        'Y-m-d',
                        $data['fechaEmisionDocSustento']
                    )->format('d/m/Y'),
                    'totalSinImpuestos' => number_format((float)$data['totalSinImpuestos'], 2, '.', ''),
                    'impuestos' => [
                        'impuesto' => [
                            'codigo' => $data['codigo'],
                            'codigoPorcentaje' => $data['codigoPorcentaje'],
                            'tarifa' => number_format((float)$data['tarifa'], 2, '.', ''),
                            'baseImponible' => number_format((float)$data['baseImponible'], 2, '.', ''),
                            'valor' => number_format((float)$data['valor'], 2, '.', ''),
                        ]
                    ],
                    'valorTotal' => number_format((float)$data['valorTotal'], 2, '.', ''),
                ],
                'motivos' => [
                    'motivo' => [
                        'razon' => $data['razon'],
                        'valor' => number_format((float)$data['valorTotal'], 2, '.', ''),
                    ]
                ],
                'infoAdicional' => []
            ];

            $this->documento = new SimpleXMLElement(
                "<?xml version=\"1.0\" encoding=\"UTF-8\" ?><notaDebito id=\"comprobante\" version=\"" . $this->version . "\"></notaDebito>"
            );

            $this->arrayToXml($notaDebito, $this->documento);
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
            'contribuyenteEspecial' => (string)$this->getContribuyenteEspecial(),
            'obligadoContabilidad' => (string)$this->getObligadoContabilidad(),
            'tipoIdentificacionComprador' => (string)$this->getTipoIdentificacionComprador(),
            'razonSocialComprador' => (string)$this->getRazonSocialComprador(),
            'identificacionComprador' => (string)$this->getIdentificacionComprador(),
            'codDocModificado' => (string)$this->getCodDocModificado(),
            'numDocModificado' => (string)$this->getNumDocModificado(),
            'fechaEmisionDocSustento' => (string)$this->getFechaEmisionDocSustento(),
            'totalSinImpuestos' => (string)$this->getTotalSinImpuestos(),
            'valorTotal' => (string)$this->getValorTotal(),
            'motivoRazon' => (string)$this->getMotivoRazon(),
            'motivoValor' => (string)$this->getMotivoValor(),
            'formaPago' => (string)$this->getFormaPago(),
        ];

        return $this;
    }

}