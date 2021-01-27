<?php

namespace App\Packages\Traits;

use SimpleXMLElement;

/**
 * Trait que contiene metodos para armar los detalles de un documento
 *
 * @package stupendo-detalles
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
trait DetallesTrait
{
    protected $headers;
    protected $detalles;
    protected $destinatarios;

    public function setDetalles($tipo_documento)
    {
        $this->detalles = [];
        $this->headers = [];
        $this->destinatarios = [];

        switch ($tipo_documento) { //ToDo: chequear si está bien que sea un entero en vez de string "01"
            case 1:
            case 4: //FACTURAS y NOTAS DE CREDITO
                $this->getDetalles($this->documento->detalles->detalle, $tipo_documento);
                break;

            case 6: //GUIAS DE REMISION

                break;
        }
        return $this;
    }

    /**
     * Agregar un campo de cabecera al detalles
     *
     * @param String $name
     */
    public function setFieldHeader($name)
    {
        if (!in_array($name, $this->headers)) {
            $this->headers[] = $name;
        }
    }

    /**
     * Obtiene los detalles de un documento. Reutilizable para Facturas, NC y Guias Remision
     *
     * @param SimpleXmlElement $detalles_documento
     * @param integer $tipo_documento
     *
     * @return Array
     */
    public function getDetalles($detalles_documento, $tipo_documento)
    {
        foreach ($detalles_documento as $detalle) {
            //Inicializacion de variables
            $detalle1 = null;
            $detalle2 = null;
            $detalle3 = null;
            $codigo_adicional = null;
            $codigo_adicional_valor = null;

            //Codigo Principal (Factura) o Interno (Nota de Credito)
            if ($tipo_documento == 4) { //ToDo: chequear si está bien que sea entero y no el string "04"
                $codigo = 'codigoInterno';
                $codigo_valor = trim($detalle->codigoInterno);
                $this->setFieldHeader('codigoInterno');

                //Si existe el tag codigoAdicional, lo agregamos al detalle del documento
                if ($detalle->codigoAdicional) {
                    $codigo_adicional = 'codigoAdicional';
                    $codigo_adicional_valor = trim($detalle->codigoAdicional);
                    $this->setFieldHeader('codigoAdicional');
                }
            } else {
                $codigo = 'codigoPrincipal';
                $codigo_valor = trim($detalle->codigoPrincipal);
                $this->setFieldHeader('codigoPrincipal');

                //Si existe el tag codigoAuxiliar, lo agregamos al detalle del documento
                if ($detalle->codigoAuxiliar) {
                    $codigo_adicional = 'codigoAuxiliar';
                    $codigo_adicional_valor = trim($detalle->codigoAuxiliar);
                    $this->setFieldHeader('codigoAuxiliar');
                }
            }

            //Descripcion
            $this->setFieldHeader('descripcion');
            $descripcion = trim($detalle->descripcion);

            //Cantidad
            $this->setFieldHeader('cantidad');
            $cantidad = trim($detalle->cantidad);

            //Formateo de dos decimales, en caso de que tenga 4 decimales
            if (((strlen($cantidad) - strpos($cantidad, '.')) - 1) > 2) {
                $cantidad = number_format((float)$cantidad, 2, '.', '');
            }

            //Precio Unitario
            $this->setFieldHeader('precioUnitario');
            $precioUnitario = trim($detalle->precioUnitario);

            //Formateo de dos decimales, en caso de que tenga 4 decimales
            if (((strlen($precioUnitario) - strpos($precioUnitario, '.')) - 1) > 2) {
                $precioUnitario = number_format((float)$precioUnitario, 2, '.', '');
            }

            //Se definen los campos restantes por defecto para estos tipos de documentos
            $this->setFieldHeader('descuento');
            $this->setFieldHeader('precioTotalSinImpuesto');

            //Datos preliminares
            $data = [
                $codigo => $codigo_valor,
                "descripcion" => $descripcion,
                "cantidad" => $cantidad,
                "precioUnitario" => $precioUnitario,
                "descuento" => number_format((float)trim($detalle->descuento), 2, '.', ''),
                "precioTotalSinImpuesto" => number_format((float)trim($detalle->precioTotalSinImpuesto), 2, '.', ''),
            ];

            //Si se conseguieron productos con codigos adicionales, lo agregamos a la data preliminar
            if (isset($codigo_adicional) && ($codigo_adicional)) {
                $data[$codigo_adicional] = $codigo_adicional_valor;
            }

            //Por ultimo, verificamos que tenga detalles adicionales
            if ($detalle->detallesAdicionales) {
                $index_detalle = 1;
                foreach ($detalle->detallesAdicionales->detAdicional as $detalleAdicional) {
                    $descripcion .= '<br>' . str_replace('|', '<br>', $detalleAdicional->attributes()[1]);
                    switch ($index_detalle) {
                        case 1:
                            $detalle1 = str_replace('|', '<br>', $detalleAdicional->attributes()[1]);
                            break;
                        case 2:
                            $detalle2 = str_replace('|', '<br>', $detalleAdicional->attributes()[1]);
                            break;
                        case 3;
                            $detalle3 = str_replace('|', '<br>', $detalleAdicional->attributes()[1]);
                            break;
                    }

                    ++$index_detalle;
                }
            }

            $data['descripcion'] = $descripcion;

            //Si se consiguieron detalles adicionales, lo adicionamos al array de datos definitivo
            if (isset($detalle1) && ($detalle1)) {
                $data['detalle1'] = $detalle1;
                $this->setFieldHeader('detalleAdicional1');
            }

            if (isset($detalle2) && ($detalle2)) {
                $data['detalle2'] = $detalle2;
                $this->setFieldHeader('detalleAdicional2');
            }

            if (isset($detalle3) && ($detalle3)) {
                $data['detalle3'] = $detalle3;
                $this->setFieldHeader('detalleAdicional3');
            }

            $this->detalles[] = $data;
        }
    }

}