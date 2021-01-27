<?php

namespace App\Packages\Traits;

/**
 * Trait que contiene métodos que permiten obtener información del SRI, como por ejemplo descripciones a Tarifas de IVA, Tipos de Retenciones, ICE, Códigos de Sustentos, y otras informaciones que solo el SRI posee.
 *
 * @package stupendo-adons-sri
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
trait StupendoSriTrait
{

    /**
     * Descripción del tipo de comprobante
     *
     * @param string $codigo Código del Comprobante
     *
     * @return string
     */
    public function descripcionTipoComprobante($codigo)
    {
        $nombre_tipo_comprobante = '';

        switch ((int)$codigo) {
            case 1:
                $nombre_tipo_comprobante = 'FACTURA';
                break;

            case 4:
                $nombre_tipo_comprobante = 'NOTA DE CREDITO';
                break;

            case 5:
                $nombre_tipo_comprobante = 'NOTA DE DEBITO';
                break;

            case 6:
                $nombre_tipo_comprobante = 'GUIA DE REMISION';
                break;

            case 7:
                $nombre_tipo_comprobante = 'COMPROBANTE DE RETENCION';
                break;
        }

        return $nombre_tipo_comprobante;
    }

    /**
     * Determina el porcentaje de IVA a aplicar al documento, en funcion del codigo del iva del mismo
     *
     * @param string $codigo_iva Codigo del IVA, segun ficha Tecnica del SRI
     *
     * @return string Porcentaje de IVA aplicado
     */
    public function getPorcentajeIva($codigo_iva)
    {
        switch ($codigo_iva) {
            case "0": //Porcentaje de IVA 0%
                $porcentaje = "0";
                break;

            case "2": //Porcentaje de IVA 12%
                $porcentaje = "12";
                break;

            case "3": //Porcentaje de IVA 14%
                $porcentaje = "14";
                break;

            case "6": //No objeto de Impuesto
            case "7": //Exento de Iva
                $porcentaje = null;
                break;
        }

        return $porcentaje;
    }

    /**
     * Descripción del Documento de Sustento
     *
     * @param string $codigo
     *
     * @return string Descripción del código de documento sustento
     */
    public function descripcionDocSustento($codigo)
    {
        $nombre_cod_doc_sustento = '';

        switch ($codigo) {
            case '01':
                $nombre_cod_doc_sustento = 'FACTURA';
                break;

            case '04':
                $nombre_cod_doc_sustento = 'NOTA DE CREDITO';
                break;

            case '07':
                $nombre_cod_doc_sustento = 'RETENCION';
                break;

            case '02':
                $nombre_cod_doc_sustento = 'NOTA O BOLETA DE VENTA';
                break;

            case '03':
                $nombre_cod_doc_sustento = 'LIQUIDACION DE COMPRA DE BIENES O PRESTACION DE SERVICIOS';
                break;

            case '05':
                $nombre_cod_doc_sustento = 'NOTA DE DEBITO';
                break;

            case '11':
                $nombre_cod_doc_sustento = 'PASAJES EXPEDIDOS POR EMPRESAS DE AVIACION';
                break;

            case '12':
                $nombre_cod_doc_sustento = 'DOCUMENTOS EMITIDOS POR INSTITUCIONES FINANCIERAS';
                break;

            case '15':
                $nombre_cod_doc_sustento = 'COMPROBANTE DE VENTA EMITIDO EN EL EXTERIOR';
                break;

            case '21':
                $nombre_cod_doc_sustento = 'CARTA DE PORTE AEREO';
                break;

            case '41':
                $nombre_cod_doc_sustento = 'COMPROBANTE DE VENTA EMITIDO POR REMBOLSO';
                break;

            case '43':
                $nombre_cod_doc_sustento = 'LIQUIDACION PARA EXPLOTACION Y EXPLORACION DE HIDROCARBUROS';
                break;

            case '45':
                $nombre_cod_doc_sustento = 'LIQUIDACION POR RECLAMOS DE ASEGURADORAS';
                break;

            case '47':
                $nombre_cod_doc_sustento = 'NOTA DE CREDITO POR REMBOLSO EMITIDA POR INTERMEDIARIO';
                break;

            case '48':
                $nombre_cod_doc_sustento = 'NOTA DE DEBITO POR REMBOLSO EMITIDA POR INTERMEDIARIO';
                break;
        }

        return $nombre_cod_doc_sustento;
    }

    /**
     * Descripción del tipo de Impuesto aplicado al documento
     *
     * @param string $codigo código del Impuesto en el XML
     *
     * @return string         descripción del Impuesto
     */
    public function descripcionTipoImpuesto($codigo)
    {
        $nombre_tipo_impuesto = '';

        switch ($codigo) {
            case '1':
                $nombre_tipo_impuesto = 'RENTA';
                break;

            case '2':
                $nombre_tipo_impuesto = 'IVA';
                break;
        }

        return $nombre_tipo_impuesto;
    }

    /**
     * Descripción de forma de Pago contenida en XML
     *
     * @param string $codigo Codigo del SRI asignado a la forma de pago
     *
     * @return stirng         Descripción de Forma de Pago
     */
    public function descripcionFormaPago($codigo)
    {
        $formaPago = "";

        switch ($codigo) {
            case '01':
                $formaPago = 'Sin utilización del sistema financiero';
                break;
            case '15':
                $formaPago = 'Compensación de deudas';
                break;
            case '16':
                $formaPago = 'Tarjeta de Débito';
                break;
            case '17':
                $formaPago = 'Dinero Electrónico';
                break;
            case '18':
                $formaPago = 'Tarjeta Prepago';
                break;
            case '19':
                $formaPago = 'Tarjeta de Crédito';
                break;
            case '20':
                $formaPago = 'Otros con utilización del sistema financiero';
                break;
            case '21':
                $formaPago = 'Endoso de títulos';
                break;
        }
        return $formaPago;
    }

    /**
     * Devuelve el documento XML con la cabecera del SRI
     *
     * @param array $data Datos del documento
     *
     * @return string      XML con cabecera
     */
    public function getDocCabeceraSri($data = [])
    {
        $xml = '<autorizacion><estado>' . strtoupper($data['estado']) . '</estado><numeroAutorizacion>' . $data['numero_autorizacion'] . '</numeroAutorizacion>';

        if (isset($data['fecha_autorizacion']) && $data['fecha_autorizacion'] != '') {
            $xml .= '<fechaAutorizacion>' . $data['fecha_autorizacion'] . '</fechaAutorizacion>';
        }

        $xml .= '<ambiente>' . $data['ambiente'] . '</ambiente><comprobante><![CDATA[' . base64_decode($data['xml']) . ']]></comprobante></autorizacion>';

        return $xml;
    }

    public function getXmlSinCabecera(\SimpleXMLElement $xml)
    {
        if (!empty($xml->xpath("/autorizacion/comprobante"))) {
            return simplexml_load_string($xml->comprobante);
        }
        return $xml;
    }

}