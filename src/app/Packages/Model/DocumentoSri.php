<?php

namespace App\Packages\Model;

use App\Packages\Traits\StupendoSriTrait;

/**
 * Modelo DocumentoSri
 *
 * @package stupendo-documentosri
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
class DocumentoSri extends Impuestos
{
    use StupendoSriTrait;

    /**
     * Documento del SRI
     * @var SimpleXMLElement
     */
    protected $documento;

    /**
     * Datos de la factura pero en forma de una collection unidimensional, sin sub-collections
     * @var Illuminate\Support\Collection
     */
    protected $collection;

    /**
     * Datos referentes al documento Factura, con informacion de Impuestos y Detalles
     * @var Array
     */
    protected $data;

    /**
     * Conjunto de campos que se pueden utilizar para generar un excel.
     * @var Array
     */
    protected $fields;

    /**
     * Optiones que permiten algunas operaciones al momento de construir el objeto
     * @var Array
     */
    protected $options;

    /**
     * Ambiente del SRI
     * @var string  Valores permitidos: 1 y 2
     */
    protected $ambiente;

    /**
     * Tipo de emisión del SRI
     * @var string  Valores permitidos: 1 y 2
     */
    protected $tipo_emision;

    /**
     * Razón social de quien emite el documento
     * @var string
     */
    protected $razon_social;

    /**
     * Nombre comercial de quien emite el documento
     * @var string
     */
    protected $nombre_comercial;

    /**
     * RUC de quien emite el documento
     * @var string
     */
    protected $ruc;

    /**
     * Clave de acceso generada por el SRI
     * @var string
     */
    protected $clave_acceso;

    /**
     * Tipo de Documento
     * @var string
     */
    protected $cod_doc;

    /**
     * Código del Establecimiento del cliente que emite el documento
     * @var string
     */
    protected $establecimiento;

    /**
     * Código del Punto de Emisión del cliente que emite el documento
     * @var string
     */
    protected $punto_emision;

    /**
     * Código del Secuencial del documento
     * @var string
     */
    protected $secuencial;

    /**
     * Dirección Matriz del establecimiento del cliente que emite el documento
     * @var string
     */
    protected $direccion_matriz;

    /**
     * Versión del XML
     * @var string
     */
    protected $version;

    /**
     * Información Adicional contenida en el documento XML
     * @var object|array
     */
    protected $info_adicional;

    /**
     * Moneda del documento
     * @var string
     */
    protected $moneda;

    /**
     * Forma de pago del documento
     * @var string
     */
    protected $cod_forma_pago;

    /**
     * Descripcion de la forma de pago del documento
     * @var string
     */
    protected $descripcion_forma_pago;

    /**
     * Tipo de retorno para la información adicional. Esta puede ser un 'object' o un 'array'
     * @var boolean
     */
    private $info_adicional_object = false;

    /**
     * Contructor
     *
     * @param \SimpleXMLElement|null $documento
     */
    public function __construct(\SimpleXMLElement $documento = null)
    {
        $this->documento = $documento;
        $this->version = ($documento) ? (string)$this->documento->attributes()['version'] : "";

        if ($this->documento) {
            $this->ambiente = (string)$this->documento->infoTributaria->ambiente;
            $this->tipo_emision = (string)$this->documento->infoTributaria->tipoEmision;
            $this->razon_social = (string)$this->documento->infoTributaria->razonSocial;
            $this->nombre_comercial = (string)$this->documento->infoTributaria->nombreComercial;
            $this->ruc = (string)$this->documento->infoTributaria->ruc;
            $this->clave_acceso = (string)$this->documento->infoTributaria->claveAcceso;
            $this->cod_doc = (string)$this->documento->infoTributaria->codDoc;
            $this->establecimiento = (string)$this->documento->infoTributaria->estab;
            $this->punto_emision = (string)$this->documento->infoTributaria->ptoEmi;
            $this->secuencial = (string)$this->documento->infoTributaria->secuencial;
            $this->direccion_matriz = (string)$this->documento->infoTributaria->dirMatriz;
            $this->setInfoAdicional();
        }

        parent::__construct($this->documento, $this->version);

        return $this;
    }

    /**
     * Devuelve el documento
     * @return SimpleXMLElement Documento
     */
    public function getDocumento()
    {
        return $this->documento;
    }

    /**
     * Devuelve el Ambiente del SRI
     * @return string
     */
    public function getAmbiente()
    {
        return $this->ambiente;
    }

    /**
     * Devuelve el Tipo de Emisión del SRI
     * @return string
     */
    public function getTipoEmision()
    {
        return $this->tipo_emision;
    }

    /**
     * Devuelve la razón social de quien emite el documento
     * @return string
     */
    public function getRazonSocial()
    {
        return $this->razon_social;
    }

    /**
     * Devuelve la razón comercial de quien emite el documento
     * @return string
     */
    public function getRazonComercial()
    {
        return $this->nombre_comercial;
    }

    /**
     * Devuelve el RUC de quien emite el documento
     * @return string
     */
    public function getRuc()
    {
        return $this->ruc;
    }

    /**
     * Devuelve la clave de Acceso del documento
     * @return string
     */
    public function getClaveAcceso()
    {
        return $this->clave_acceso;
    }

    /**
     * Devuelve el Tipo de Documento
     * @return string
     */
    public function getCodDoc()
    {
        return $this->cod_doc;
    }

    /**
     * Retorna el número del establecimiento de un documento, con el formato correcto del SRI
     *
     * @param string $num_documento
     *
     * @return string
     */
    public function getEstablecimiento($num_documento = null)
    {
        return ($num_documento) ? str_pad(substr($num_documento, 0, 3), 3, "0", STR_PAD_LEFT) : $this->establecimiento;
    }

    /**
     * Retorna el número del punto de emisión de un documento, con el formato correcto del SRI
     *
     * @param string $num_documento
     *
     * @return string
     */
    public function getPuntoEmision($num_documento = null)
    {
        return ($num_documento) ? str_pad(substr($num_documento, 3, 3), 3, "0", STR_PAD_LEFT) : $this->punto_emision;
    }

    /**
     * Retorna el número del secuencial de un documento, con el formato correcto del SRI
     *
     * @param string $num_documento
     *
     * @return string
     */
    public function getSecuencial($num_documento = null)
    {
        return ($num_documento) ? str_pad(substr($num_documento, 6), 9, "0", STR_PAD_LEFT) : $this->secuencial;
    }

    /**
     * Retorna el numero del documento con formato del SRI
     * @return string
     */
    public function getNumeroDocumento()
    {
        return str_pad($this->establecimiento, 3, "0", STR_PAD_LEFT) . '-' .
            str_pad($this->punto_emision, 3, "0", STR_PAD_LEFT) . '-' .
            str_pad($this->secuencial, 9, "0", STR_PAD_LEFT);
    }

    /**
     * Retorna la direccion matriz del documento
     * @return string
     */
    public function getDireccionMatriz()
    {
        return $this->direccion_matriz;
    }

    /**
     * Retorna la moneda del documento
     * @return string
     */
    public function getMoneda()
    {
        return $this->moneda;
    }

    /**
     * Retorna la descripcion de la forma de pago
     * @return string
     */
    public function getFormaPago()
    {
        return $this->descripcion_forma_pago;
    }

    /**
     * Retorna la información adicional del documento
     * @return object|array Objeto o un array de la información adicional conseguida en el documento
     */
    public function getInfoAdicional()
    {
        if (is_array($this->info_adicional) && $this->info_adicional_object) {
            return array_map(
                function ($array) {
                    return (object)$array;
                },
                $this->info_adicional
            );
        }

        return $this->info_adicional;
    }

    /**
     * Extra los campos adicionales y los almacena en la clase
     */
    public function setInfoAdicional()
    {
        $info_adicional = [];

        if ($this->documento->infoAdicional->campoAdicional) {
            foreach ($this->documento->infoAdicional->campoAdicional as $campo_adicional) {
                $info_adicional = array_add($info_adicional, trim((string)$campo_adicional['nombre']), trim((string)$campo_adicional));
            }
        }

        $this->info_adicional = $info_adicional;

        return $this;
    }

    /**
     * Obtiene informacion de un campo adicional en particular
     *
     * @param string $campo Nombre del campo
     *
     * @return string|null   Valor del campo adicional
     */
    public function getCampoAdicional($campo)
    {
        return array_get($this->info_adicional, $campo);
    }

    /**
     * Retorna el XML generado en formato string
     * @return string XML formateado
     */
    public function getStringXml(): string
    {
        return $this->convertObjectXmlToString($this->documento);
    }

    /**
     * Verifica si un RUC tiene el tercer digito verificador en 6
     * @return bool
     */
    public function validaRucSectorPublico(): bool
    {
        return (substr($this->ruc, 2, 1) == '6');
    }

    /**
     * Retorna los campos que se filtraron en el proceso de generar reporte personalizado
     * @return array
     */
    public function getFieldsFiltered(): array
    {
        return $this->fields;
    }

    /**
     * Retorna la representacion del documento XML procesado pero en formado de collection
     * @return Illuminate\Support\Collection
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Retorna la forma de pago definida en el XML del documento
     * @return string
     */
    public function getDescripcionFormaPago()
    {
        $descripcion = '';

        switch ($this->cod_doc) {
            case '01':
            case '05':

                $codigo_forma_pago = $this->documento->xpath("//formaPago");

                if ($codigo_forma_pago) {
                    $this->cod_forma_pago = (string)$codigo_forma_pago[0];

                    return $this->descripcionFormaPago($codigo_forma_pago[0]);
                }

                break;
        }

        return $descripcion;
    }

}