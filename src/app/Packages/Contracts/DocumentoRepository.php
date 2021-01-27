<?php

namespace App\Packages\Contracts;

use SimpleXMLElement;

/**
 * Interface DocumentoRepository
 *
 * @author Julio Hernandez
 */
interface DocumentoRepository
{
    /**
     * Constructor estatico
     *
     * @param string $xml Documento XML (Codificado o No)
     * @param array $options Opciones para el constructor
     *
     * @return instance class
     */
    public static function build($xml, $options = []);

    /**
     * Genera el XML de un documento fisico
     *
     * @param array $options
     */
    public function armarFisico($data = [], $impuestos = []);

    /**
     * Retorna un string del XML que se genera
     * @return string
     */
    public function getStringXml(): string;

    /**
     * Devuelve el documento
     * @return SimpleXMLElement Documento
     */
    public function getDocumento();

    /**
     * Gestiona la data del XML en dentro de un array
     * @return Array
     */
    public function groupDataFromXml();

}