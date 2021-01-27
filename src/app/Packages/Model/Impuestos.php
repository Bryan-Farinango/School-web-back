<?php

namespace App\Packages\Model;

use App\TipoDocumentoEmisionEnum;
use DateTime;
use SimpleXMLElement;

/**
 * Modelo Impuestos
 *
 * @package stupendo-impuestos
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
class Impuestos
{
    private $documento;
    private $version;
    protected $tarifa_impuesto;
    protected $baseImponible2;
    protected $baseImponible0;
    protected $baseImponibleice;
    protected $baseImponibleirb;
    protected $baseImponibleNO;
    protected $baseImponibleEX;
    protected $porcentaje;
    protected $valoriva0;
    protected $valoriva2;
    protected $valorice;
    protected $valorirb;
    protected $valorivaNO;
    protected $valorivaEX;
    protected $descuentos;
    protected $totalRetenido;
    protected $impuestos;
    protected $docs_sustento;

    public function __construct(SimpleXMLElement $documento = null, $version = '')
    {
        $this->documento = $documento;
        $this->version = $version;
        $this->tarifa_impuesto = '0.00';
        $this->baseImponible2 = '0.00';
        $this->baseImponible0 = '0.00';
        $this->baseImponibleice = '0.00';
        $this->baseImponibleirb = '0.00';
        $this->baseImponibleNO = '0.00';
        $this->baseImponibleEX = '0.00';
        $this->porcentaje = '0.00';
        $this->valoriva0 = '0.00';
        $this->valoriva2 = '0.00';
        $this->valorice = '0.00';
        $this->valorirb = '0.00';
        $this->valorivaNO = '0.00';
        $this->valorivaEX = '0.00';
        $this->descuentos = '0.00';
        $this->totalRetenido = '0.00';
        $this->impuestos = [];
        $this->docs_sustento = [];
    }

    /**
     * Tarifa de Impuesto 12%-14% (Codigo de Porcentaje = 2 ó 3)
     * @return float|string
     */
    public function getTarifaImpuesto()
    {
        return $this->tarifa_impuesto;
    }

    /**
     * Valor del IVA diferente de Cero (Codigo de Porcentaje = 2 ó 3)
     * @return float|string
     */
    public function getValorIvaDiffCero()
    {
        return $this->valoriva2;
    }

    /**
     * Base imponible del IVA diferente de Cero (Codigo de porcentaje = 2 ó 3)
     * @return float|string
     */
    public function getBaseImponibleDiffCero()
    {
        return $this->baseImponible2;
    }

    /**
     * Valor del IVA igual a Cero (Codigo de Porcentaje = 0)
     * @return float|string
     */
    public function getValorIvaIgualCero()
    {
        return $this->valoriva0;
    }

    /**
     * Base imponible del IVA igual a Cero (Codigo de porcentaje = 0)
     * @return float|string
     */
    public function getBaseImponibleIgualCero()
    {
        return $this->baseImponible0;
    }

    /**
     * Valor del IVA No Objeto de Impuesto (Codigo de Porcentaje = 6)
     * @return float|string
     */
    public function getValorIvaNoObjetoImpuesto()
    {
        return $this->valorivaNO;
    }

    /**
     * Base imponible del IVA No objeto de Impuesto (Codigo de porcentaje = 6)
     * @return float|string
     */
    public function getBaseImponibleNoObjetoImpuesto()
    {
        return $this->baseImponibleNO;
    }

    /**
     * Valor del IVA Exento de Impuesto (Codigo de Porcentaje = 7)
     * @return float|string
     */
    public function getValorIvaExento()
    {
        return $this->valorivaEX;
    }

    /**
     * Base Imponible del IVA Exento de Impuesto (Codigo de Porcentaje = 7)
     * @return float|string
     */
    public function getBaseImponibleExento()
    {
        return $this->baseImponibleEX;
    }

    /**
     * Valor del IVA ICE (Codigo de Impuesto = 3)
     * @return float|string
     */
    public function getValorIvaIce()
    {
        return $this->valorice;
    }

    /**
     * Base Imponible del IVA ICE (Codigo de Impuesto = 3)
     * @return float|string
     */
    public function getBaseImponibleIce()
    {
        return $this->baseImponibleice;
    }

    /**
     * Valor del IVA IRBPNR (Codigo de Impuesto = 7)
     * @return float|string
     */
    public function getValorIvaIrbpnr()
    {
        return $this->valorirb;
    }

    /**
     * Base Imponible del IVA IRBPNR (Codigo de Impuesto = 7)
     * @return float|string
     */
    public function getBaseImponibleIrbpnr()
    {
        return $this->baseImponibleirb;
    }

    /**
     * Tarifa aplicada
     * @return float|string
     */
    public function getPorcentajeImpuesto()
    {
        return $this->porcentaje;
    }

    /**
     * Determina los montos por cada impuesto asociado al documento
     */
    public function setImpuestos()
    {
        $tipo_documento = $this->documento->infoTributaria->codDoc;

        switch ($tipo_documento) {
            case TipoDocumentoEmisionEnum::FACTURA:
                $impuestos_documentos = $this->documento->infoFactura->totalConImpuestos->totalImpuesto;
                break;

            case TipoDocumentoEmisionEnum::NOTA_DE_CREDITO:
                $impuestos_documentos = $this->documento->infoNotaCredito->totalConImpuestos->totalImpuesto;
                break;

            case TipoDocumentoEmisionEnum::NOTA_DE_DEBITO:
                //Las notas de debito no poseen el tag 'totalConImpuestos'. Por eso se hace esta validacion
                $impuestos_documentos = $this->documento->infoNotaDebito->impuestos->impuesto;
        }

        foreach ($impuestos_documentos as $impuestos) {
            switch ($impuestos->codigo) {
                //IMPUESTO IVA
                case 2:

                    //PORCENTAJE DE IVA 12% (Codigo Nro 2) Y 14% (Codigo Nro 3)
                    if ($impuestos->codigoPorcentaje == '2' || $impuestos->codigoPorcentaje == '3') {
                        $this->valoriva2 = number_format((float)$impuestos->valor, 2, '.', '');
                        $this->baseImponible2 = number_format((float)$impuestos->baseImponible, 2, '.', '');

                        if ($impuestos->tarifa) {
                            $this->porcentaje = $impuestos->tarifa;
                            //Si no esta definida la tarifa en el documento, verificamos el tipo de porcentaje
                        } else {
                            if ($impuestos->codigoPorcentaje == '2') {
                                $this->porcentaje = '12';
                            } else {
                                if ($impuestos->codigoPorcentaje == '3') {
                                    $this->porcentaje = '14';
                                }
                            }
                        }
                    }

                    //PORCENTAJE DE IVA 0%
                    if ($impuestos->codigoPorcentaje == '0') {
                        $this->valoriva0 = number_format((float)$impuestos->valor, 2, '.', '');
                        $this->baseImponible0 = number_format((float)$impuestos->baseImponible, 2, '.', '');
                    }

                    //NO OBJETO DE IMPUESTO
                    if ($impuestos->codigoPorcentaje == '6') {
                        $this->valorivaNO = number_format((float)$impuestos->valor, 2, '.', '');
                        $this->baseImponibleNO = number_format((float)$impuestos->baseImponible, 2, '.', '');
                    }

                    //EXENTO DE IVA
                    if ($impuestos->codigoPorcentaje == '7') {
                        $this->valorivaEX = number_format((float)$impuestos->valor, 2, '.', '');
                        $this->baseImponibleEX = number_format((float)$impuestos->baseImponible, 2, '.', '');
                    }

                    break;

                //IMPUESTO ICE
                case 3:

                    $this->valorice = number_format((float)$impuestos->valor, 2, '.', '');
                    $this->baseImponibleice = number_format((float)$impuestos->baseImponible, 2, '.', '');

                    break;

                //IMPUESTO IRBPNR
                case 5:

                    $this->valorirb = number_format((float)$impuestos->valor, 2, '.', '');
                    $this->baseImponibleirb = number_format((float)$impuestos->baseImponible, 2, '.', '');

                    break;
            }
        }

        //Si aun no se ha definido en porcentaje, hacemos una verificacion de la fecha de emision
        //Con esto, determinamos que tipo de IVA aplicar (Ver reglamentacion del SRI)
        if ($this->porcentaje == '0.00' || $this->porcentaje == '0') {
            $dateIVA = DateTime::createFromFormat('d/m/Y', '01/06/2016');
            $dateIVA2 = DateTime::createFromFormat('d/m/Y', '01/06/2017');

            $fecha_emision = DateTime::createFromFormat('d/m/Y', $this->documento->fechaEmision);

            if ($fecha_emision < $dateIVA || $fecha_emision >= $dateIVA2) {
                $this->tarifa_impuesto = '12';
            } else {
                $this->tarifa_impuesto = '14';
            }
        } else {
            $this->tarifa_impuesto = $this->porcentaje;
        }

        return $this;
    }

    /**
     * Define el array de impuestos del documento, en funcion de la version del mismo
     */
    public function setImpuestosRetenciones()
    {
        //Arreglo que tendrá la información de cada impuesto del documento. Luego será convertido en objeto
        $impuestos = [];

        switch ($this->version) {
            case '1.0.0':

                if ($this->documento) {
                    if ($this->documento->impuestos->impuesto) {
                        foreach ($this->documento->impuestos->impuesto as $impuesto) {
                            $impuestos[] = [
                                'codSustento' => (string)$impuesto->codDocSustento,
                                'descripcionCodSustento' => $this->getDescripcionCodSustento(
                                    (string)$impuesto->codDocSustento
                                ),
                                'numDocSustento' => (string)$impuesto->numDocSustento,
                                'fechaEmisionDocSustento' => (string)$impuesto->fechaEmisionDocSustento,
                                'periodoFiscal' => (string)$this->documento->infoCompRetencion->periodoFiscal,
                                'tipoCodigoImpuesto' => $this->getDescripcionCodigoImpuesto((string)$impuesto->codigo),
                                'codigo' => (string)$impuesto->codigo,
                                'codigoRetencion' => (string)$impuesto->codigoRetencion,
                                'baseImponible' => (string)$impuesto->baseImponible,
                                'porcentajeRetener' => (string)$impuesto->porcentajeRetener,
                                'valorRetenido' => (string)$impuesto->valorRetenido,
                            ];
                        }
                    }
                }

                break;

            case '2.0.0':

                if ($this->documento) {
                    foreach ($this->documento->docsSustento->docSustento as $docSustento) {
                        foreach ($docSustento->retenciones->retencion as $impuesto) {
                            $impuestos[] = [
                                'codSustento' => (string)$docSustento->codDocSustento,
                                'descripcionCodSustento' => $this->getDescripcionCodSustento(
                                    (string)$docSustento->codDocSustento
                                ),
                                'numDocSustento' => (string)$docSustento->numDocSustento,
                                'fechaEmisionDocSustento' => (string)$docSustento->fechaEmisionDocSustento,
                                'periodoFiscal' => (string)$this->documento->infoCompRetencion->periodoFiscal,
                                'fechaRegistroContable' => (string)$docSustento->fechaRegistroContable,
                                'numAutDocSustento' => (string)$docSustento->numAutDocSustento,
                                'pagoLocExt' => (string)$docSustento->pagoLocExt,
                                'totalComprobantesReembolso' => (string)$docSustento->totalComprobantesReembolso,
                                'totalBaseImponibleReembolso' => (string)$docSustento->totalBaseImponibleReembolso,
                                'totalImpuestoReembolso' => (string)$docSustento->totalImpuestoReembolso,
                                'totalSinImpuestos' => (string)$docSustento->totalSinImpuestos,
                                'importeTotal' => (string)$docSustento->importeTotal,
                                'tipoCodigoImpuesto' => $this->getDescripcionCodigoImpuesto((string)$impuesto->codigo),
                                'codigo' => (string)$impuesto->codigo,
                                'codigoRetencion' => (string)$impuesto->codigoRetencion,
                                'baseImponible' => (string)$impuesto->baseImponible,
                                'porcentajeRetener' => (string)$impuesto->porcentajeRetener,
                                'valorRetenido' => (string)$impuesto->valorRetenido,
                            ];
                        }
                    }
                }

                break;
        }

        //Convertimos el array de impuestos en un objeto, para hacer uso del mismo en blade de RIDE
        if ($impuestos) {
            $this->impuestos = array_map(
                function ($array) {
                    return (object)$array;
                },
                $impuestos
            );
        }

        return $this;
    }

    /**
     * Retorna los impuestos de Retencion
     * @return Object
     */
    public function getImpuestosRetenciones()
    {
        if (!$this->impuestos) {
            $this->setImpuestosRetenciones();
        }

        $impuestos = array_map(
            function ($array) {
                return (array)$array;
            },
            $this->impuestos
        );

        return $impuestos;
    }

    /**
     * Compara las retenciones aplicadas al impuesto (IVA 12%) de un documento
     *
     * @param array $impuestos
     *
     * @return bool  Devuelve true si se encuentran coincidencias en condiciones aplicadas en busqueda
     */
    public function compararImpuestoRetencion($impuestos): bool
    {
        foreach ($this->impuestos as $retencion) {
            if ($retencion['codigo'] == $impuestos['codigo']
                && $retencion['codigoRetencion'] == $impuestos['codigoRetencion']
                && $retencion['baseImponible'] == $impuestos['baseImponible']
                && $retencion['numDocSustento'] == $impuestos['numDocSustento']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna la descripcion del Código de Sustento del documento
     *
     * @param string $codigo Código de Sustento en formato 99
     *
     * @return string         Descripció del Código de Sustento
     */
    public function getDescripcionCodSustento($codigo): string
    {
        //Descripción del código de sustento asociado documento al cual se le esta aplicando la retención
        $descripcion_sustento = "";

        //Según codificación del SRi
        switch ($codigo) {
            case '01':
                $descripcion_sustento = "Factura";
                break;
            case '02':
                $descripcion_sustento = "Nota o Boleta de Venta";
                break;
            case '03':
                $descripcion_sustento = "Liquidaci&oacute;n de Compra de Bienes o Prestaci&oacute;n de Servicios";
                break;
            case '04':
                $descripcion_sustento = "Nota de Cr&eacute;dito";
                break;
            case '05':
                $descripcion_sustento = "Nota de D&eacute;bito";
                break;
            case '07':
                $descripcion_sustento = "Retenci&oacute;n";
                break;
            case '11':
                $descripcion_sustento = "Pasajes Expedidos por Empresas de Aviaci&oacute;n";
                break;
            case '12':
                $descripcion_sustento = "Documentos Emitidos por Instituciones Financieras";
                break;
            case '15':
                $descripcion_sustento = "Comprobante de Venta Emitido en el Exterior";
                break;
            case '21':
                $descripcion_sustento = "Carta de Porte Aereo";
                break;
            case '41':
                $descripcion_sustento = "Comprobante de Venta Emitido por Rembolso";
                break;
            case '43':
                $descripcion_sustento = "Liquidaci&oacute;n para Explotaci&oacute;n y Exploraci&oacute;n de Hidrocarburos";
                break;
            case '47':
                $descripcion_sustento = "Nota de Cr&eacute;dito por Rembolso Emirida por Intermediario";
                break;
            case '48':
                $descripcion_sustento = "Nota de D&eacute;bito por Rembolso Emirida por Intermediario";
                break;
        }

        return $descripcion_sustento;
    }

    /**
     * Retorna la descripcion del Código de Impuesto del documento
     *
     * @param string $codigo Código de Impuesto en formato 99
     *
     * @return string         Descripció del Código de Impuesto
     */
    public function getDescripcionCodigoImpuesto($codigo)
    {
        //Descripción del código de impuesto aplicado a la retención
        $descripcion_impuesto = "";

        //Según codificación del SRi
        switch ($codigo) {
            case '1':
                $descripcion_impuesto = "RENTA";
                break;
            case '2':
                $descripcion_impuesto = "IVA";
                break;
            case '6':
                $descripcion_impuesto = "ISD";
                break;
        }

        return $descripcion_impuesto;
    }

    /**
     * Determina el total de valor retenido en impuestos
     * @return float Total Retenido
     */
    public function setTotalRetenido()
    {
        $total_retenido = 0;

        switch ($this->version) {
            case '1.0.0':

                if ($this->documento->impuestos->impuesto) {
                    foreach ($this->documento->impuestos->impuesto as $impuesto) {
                        $total_retenido += (float)$impuesto->valorRetenido;
                    }
                }

                break;

            case '2.0.0':

                foreach ($this->documento->docsSustento->docSustento as $docSustento) {
                    foreach ($docSustento->retenciones->retencion as $retenido) {
                        $total_retenido += (float)$retenido->valorRetenido;
                    }
                }

                break;
        }

        $this->totalRetenido = number_format((float)$total_retenido, 2, '.', '');
    }

    /**
     * Retorna el valor del Total Retenido
     * @return float Total Retenido
     */
    public function getTotalRetenido()
    {
        return $this->totalRetenido;
    }

    /**
     * Determina el total en Descuentos recibidos para el documento
     * @return floar Total Descuentos
     */
    public function setDescuentos()
    {
        $descuentos = 0;
        foreach ($this->documento->detalles as $detalle) {
            $descuentos += (float)$detalle->descuento;
        }
        $this->descuentos = number_format((float)$descuentos, 2, '.', '');
    }

}