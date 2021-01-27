<?php

namespace App\Packages\Traits;

use App\TipoDocumentoEmisionEnum;
use SimpleXMLElement;

/**
 * Trait que contiene metodos que sirven para manipular algunas propiedades del modelo
 *
 * @package stupendo-model
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
trait StupendoModelTrait
{
    /**
     * Arma un arreglo con informacion del modelo, basandose en las propiedades o metodos configurados por el cliente para un reporte personalizado
     *
     * @param array $fields Campos seleccionados por el cliente
     *
     * @return array          Arreglo con data del documento
     */
    public function getDataFromModelFields($fields = [])
    {
        $data = [];

        foreach ($fields as $field) {
            switch ($field['origin']) {
                case 'db':
                    $data[$field['id']] = (string)$this->{$field['field']};
                    break;

                case 'model':
                    $data[$field['id']] = (string)$this->{$field['function']}();
                    break;
            }
        }

        return $data;
    }

    /**
     * Retorna la forma de pago definida en el XML del documento
     * @return string
     */
    public function formaPago()
    {
        $xml = ($this->xml) ? new \SimpleXMLElement(base64_decode(trim($this->xml))) : null;

        if ($xml && ($this->tipo_documento == TipoDocumentoEmisionEnum::FACTURA)) {
            $codigo_forma_pago = $xml->xpath("//formaPago");

            if ($codigo_forma_pago) {
                return $this->descripcionFormaPago($codigo_forma_pago[0]);
            }
        } else {
            return '';
        }
    }
}