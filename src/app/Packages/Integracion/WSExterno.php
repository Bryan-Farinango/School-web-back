<?php

namespace App\Packages\Integracion;

use App\Packages\Model\{Factura, NotaCredito, NotaDebito, Retencion};
use SimpleXMLElement;

class WSExterno
{

    /**
     * Documento del SRI, modelado desde su XML
     * @var Factura|NotaCredito|NotaDebito|Retencion
     */
    private $model;

    /**
     * Documento XML
     * @var xml
     */
    private $documento;

    private $options;

    /**
     * Instancia de la clase que sera usada de forma abstracta desde el controlador
     * @var Clousure
     */
    private $instance;

    private $credenciales;

    private $endpoint;

    /**
     * Constructor. Permite instanciar dinamicamente otra clase previamente definida para determinado usuario, y asi poder hacer uso de misma de forma abstracta
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->options = (object)$config;
        $xml = isset($this->options->xml) ? $this->options->xml : '';

        if (isset($this->options->decode) && ($this->options->decode)) {
            $this->documento = new SimpleXMLElement(base64_decode(trim($xml)));
        } else {
            $this->documento = new SimpleXMLElement(trim($xml));
        }

        $this->generateModel();

        $this->credenciales = isset($this->options->credenciales) ? $this->options->credenciales : null;
        $this->endpoint = isset($this->options->endpoint) ? $this->options->endpoint : null;
        $classname = isset($this->options->classname) ? "App\\Packages\\Integracion\\" . $this->options->classname : null;

        if ($classname) {
            $this->instance = new $classname();
            $this->instance->loadConfig($this->endpoint, $this->credenciales, $this->model);
        }
    }

    public function hasErrors()
    {
        return $this->instance->hasErrors();
    }

    public function getErrors(): array
    {
        return $this->instance->getErrors();
    }

    public function checkSaldoDocumento(): float
    {
        return $this->instance->checkSaldoDocumento();
    }

    public function registrarPagoDocumento($data = []): bool
    {
        return $this->instance->registrarPagoDocumento($data);
    }

    public function anularPagoDocumento($data = []): bool
    {
        return $this->instance->anularPagoDocumento($data);
    }

    public static function make(array $config = []): WSExterno
    {
        return new self($config);
    }

    /**
     * Modelado del documento xml en clase administrable
     * @return self
     */
    public function generateModel()
    {
        if ($this->documento) {
            switch ($this->documento->infoTributaria->codDoc) {
                case '01':

                    $this->model = (new Factura($this->documento))->setImpuestos()->setDetalles(1);
                    break;

                case '04':

                    $this->model = (new NotaCredito($this->documento))->setImpuestos()->setDetalles(4);
                    break;

                case '05':

                    $this->model = (new NotaDebito($this->documento))->setImpuestos();
                    break;

                case '07':

                    $this->model = (new Retencion($this->documento))->setImpuestosRetenciones();
                    break;
            }
        }

        return $this;
    }
}