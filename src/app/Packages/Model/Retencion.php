<?php

namespace App\Packages\Model;

use App\Packages\Contracts\DocumentoRepository;
use App\Packages\Traits\{StupendoAdons};
use Carbon\Carbon;
use SimpleXMLElement;

class Retencion extends DocumentoSri implements DocumentoRepository
{

    use StupendoAdons;

    private $dir_establecimiento;

    private $obligado_contabilidad;

    private $tipo_identificacion_sujeto_retenido;

    private $parte_relacionada;

    private $razon_social_sujeto_retenido;

    private $identificacion_sujeto_retenido;

    private $periodo_fiscal;

    public function __construct(SimpleXMLElement $documento = null)
    {
        $this->documento = $documento;


        if ($this->documento) {
            parent::__construct($this->documento);
            $this->dir_establecimiento = (string)$this->documento->infoCompRetencion->dirEstablecimiento;
            $this->obligado_contabilidad = (string)$this->documento->infoCompRetencion->obligadoContabilidad;
            $this->tipo_identificacion_sujeto_retenido = (string)$this->documento->infoCompRetencion->tipoIdentificacionSujetoRetenido;
            $this->parte_relacionada = (string)$this->documento->infoCompRetencion->parteRel;
            $this->razon_social_sujeto_retenido = (string)$this->documento->infoCompRetencion->razonSocialSujetoRetenido;
            $this->identificacion_sujeto_retenido = (string)$this->documento->infoCompRetencion->identificacionSujetoRetenido;
            $this->periodo_fiscal = (string)$this->documento->infoCompRetencion->periodoFiscal;
        } else {
            parent::__construct($documento);
            $this->version = "1.0.0";
        }
    }


    public function getDireccionEstablecimiento()
    {
        return $this->dir_establecimiento;
    }

    public function getObligadoContabilidad()
    {
        return $this->obligado_contabilidad;
    }

    public function getTipoIdentificacionSujetoRetenido()
    {
        return $this->tipo_identificacion_sujeto_retenido;
    }

    public function getParteRelacionada()
    {
        return $this->parte_relacionada;
    }

    public function getRazonSocialSujetoRetenido()
    {
        return $this->razon_social_sujeto_retenido;
    }

    public function getIdentificacionSujetoRetenido()
    {
        return $this->identificacion_sujeto_retenido;
    }

    public function getPeriodoFiscal()
    {
        return $this->periodo_fiscal;
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

            $object = (new Retencion($documento))->setImpuestosRetenciones();

            return $object;
        } else {
            $documento = null;

            $object = (new Retencion($documento));
        }
    }


    public function armarDocumento($data = [])
    {
        $this->setImpuestosRetenciones();
        $this->setTotalRetenido();

        return $this->data = array_merge(
            $data,
            [
                'xml' => $this->documento,
                'logo' => config('reportes.logo'),
                'totalRetencion' => $this->totalRetenido,
                'impuestos' => $this->impuestos,
            ]
        );
    }


    public function armarFisico($data = [], $impuestos = [])
    {
        if ($data) {
            $arrayImpuestos = [];
            foreach ($impuestos as $impuesto) {
                $imp = [
                    'impuesto' => [
                        'codigo' => $impuesto['codigo'],
                        'codigoRetencion' => $impuesto['codigoRetencion'],
                        'baseImponible' => number_format((float)$impuesto['baseImponible'], 2, '.', ''),
                        'porcentajeRetener' => number_format((float)$impuesto['porcentajeRetener'], 2, '.', ''),
                        'valorRetenido' => number_format((float)$impuesto['valorRetenido'], 2, '.', ''),
                        'codDocSustento' => $impuesto['codDocSustento'],
                        'numDocSustento' => $impuesto['numDocSustento'],
                        'fechaEmisionDocSustento' => Carbon::createFromFormat(
                            'Y-m-d',
                            $impuesto['fechaEmisionDocSustento']
                        )->format('d/m/Y'),
                    ]
                ];
                array_push($arrayImpuestos, $imp);
            }

            $comprobanteRetencion = [
                'infoTributaria' => [
                    'ambiente' => 1,
                    'tipoemision' => 1,
                    'razonSocial' => $data['razonSocial'],
                    'ruc' => $data['ruc'],
                    'claveAcceso' => ($data['claveAcceso']) ?: $data['numeroAutorizacion'],
                    'codDoc' => str_pad($data['codDoc'], 2, "0", STR_PAD_LEFT),
                    'estab' => str_pad($data['estab'], 3, "0", STR_PAD_LEFT),
                    'ptoEmi' => str_pad($data['ptoEmi'], 3, "0", STR_PAD_LEFT),
                    'secuencial' => str_pad($data['secuencial'], 9, "0", STR_PAD_LEFT),
                    'dirMatriz' => $data['dirMatriz'],
                ],
                'infoCompRetencion' => [
                    'fechaEmision' => Carbon::createFromFormat('Y-m-d', $data['fechaEmision'])->format('d/m/Y'),
                    'contribuyenteEspecial' => $data['contribuyenteEspecial'],
                    'obligadoContabilidad' => $data['obligadoContabilidad'],
                    'tipoIdentificacionSujetoRetenido' => $data['tipoIdentificacionSujetoRetenido'],
                    'razonSocialSujetoRetenido' => $data['razonSocialSujetoRetenido'],
                    'identificacionSujetoRetenido' => $data['identificacionSujetoRetenido'],
                    'periodoFiscal' => $data['periodoFiscal'],
                ],
                'impuestos' => $arrayImpuestos,
                'infoAdicional' => []
            ];

            $this->documento = new SimpleXMLElement(
                "<?xml version=\"1.0\" encoding=\"UTF-8\" ?><comprobanteRetencion id=\"comprobante\" version=\"" . $this->version . "\"></comprobanteRetencion>"
            );

            $this->arrayToXml($comprobanteRetencion, $this->documento);
        }

        return $this;
    }


    public function getNumDocSustentos(): array
    {
        $num_docs_sustento = [];

        switch ($this->version) {
            case '1.0.0':

                if ($this->documento) {
                    foreach ($this->documento->impuestos->impuesto as $impuesto) {
                        if (!in_array(
                            [
                                'codSustento' => (string)$impuesto->codDocSustento,
                                'numDocSustento' => (string)$impuesto->numDocSustento
                            ],
                            $num_docs_sustento
                        )) {
                            $num_docs_sustento[] = [
                                'codSustento' => (string)$impuesto->codDocSustento,
                                'numDocSustento' => (string)$impuesto->numDocSustento,
                            ];
                        }
                    }
                }
                break;

            case '2.0.0':

                if ($this->documento) {
                    foreach ($this->documento->docsSustento->docSustento as $docSustento) {
                        foreach ($docSustento->retenciones->retencion as $impuesto) {
                            if (!in_array(
                                [
                                    'codSustento' => (string)$docSustento->codDocSustento,
                                    'numDocSustento' => (string)$docSustento->numDocSustento
                                ],
                                $num_docs_sustento
                            )) {
                                $num_docs_sustento[] = [
                                    'codSustento' => (string)$docSustento->codDocSustento,
                                    'numDocSustento' => (string)$docSustento->numDocSustento,
                                ];
                            }
                        }
                    }
                }
                break;
        }

        return $num_docs_sustento;
    }


    public function groupDataFromXml()
    {
        $data = [];

        foreach ($this->impuestos as $doc_sustento) {
            $data = [
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
                'dirEstablecimiento' => (string)$this->getDireccionEstablecimiento(),
                'obligadoContabilidad' => (string)$this->getObligadoContabilidad(),
                'tipoIdentificacionSujetoRetenido' => (string)$this->getTipoIdentificacionSujetoRetenido(),
                'parteRel' => (string)$this->getParteRelacionada(),
                'razonSocialSujetoRetenido' => (string)$this->getRazonSocialSujetoRetenido(),
                'identificacionSujetoRetenido' => (string)$this->getIdentificacionSujetoRetenido(),
                'periodoFiscal' => (string)$this->getPeriodoFiscal(),
                'codSustento' => (string)$doc_sustento->codSustento,
                'descripcionCodSustento' => (string)$doc_sustento->descripcionCodSustento,
                'numDocSustento' => (string)$doc_sustento->numDocSustento,
                'fechaEmisionDocSustento' => (string)$doc_sustento->fechaEmisionDocSustento,
                'tipoCodigoImpuesto' => (string)$doc_sustento->tipoCodigoImpuesto,
                'codigo' => (string)$doc_sustento->codigo,
                'codigoRetencion' => (string)$doc_sustento->codigoRetencion,
                'baseImponible' => (string)$doc_sustento->baseImponible,
                'porcentajeRetener' => (string)$doc_sustento->porcentajeRetener,
                'valorRetenido' => (string)$doc_sustento->valorRetenido,
                'fechaRegistroContable' => '',
                'numAutDocSustento' => '',
                'pagoLocExt' => '',
                'totalComprobantesReembolso' => '',
                'totalBaseImponibleReembolso' => '',
                'totalImpuestoReembolso' => '',
                'totalSinImpuestos' => '',
                'importeTotal' => '',
            ];

            switch ($this->version) {
                case '2.0.0':

                    $data['fechaRegistroContable'] = (string)$doc_sustento->fechaRegistroContable;
                    $data['numAutDocSustento'] = (string)$doc_sustento->numAutDocSustento;
                    $data['pagoLocExt'] = (string)$doc_sustento->pagoLocExt;
                    $data['totalComprobantesReembolso'] = (string)$doc_sustento->totalComprobantesReembolso;
                    $data['totalBaseImponibleReembolso'] = (string)$doc_sustento->totalBaseImponibleReembolso;
                    $data['totalImpuestoReembolso'] = (string)$doc_sustento->totalImpuestoReembolso;
                    $data['totalSinImpuestos'] = (string)$doc_sustento->totalSinImpuestos;
                    $data['importeTotal'] = (string)$doc_sustento->importeTotal;
                    break;

                default:

                    break;
            }

            $this->data[] = $data;
        }

        return $this;
    }

}