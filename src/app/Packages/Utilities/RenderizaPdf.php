<?php

namespace App\Packages\Utilities;

use App\Packages\Model\Factura;
use App\Packages\Model\NotaCredito;
use App\Packages\Model\NotaDebito;
use App\Packages\Model\Retencion;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Routing\ResponseFactory;
use SimpleXMLElement;

/**
 * Utilidad para renderizar los RIDES, segun el tipo de documento
 *
 * @package -renderizapdf
 * @author Julio Hernandez
 */
class RenderizaPdf
{

    /**
     * Datos del documentos que pueden ser pasados al objetos, previo armado en el controller
     * @var Array
     */
    private $data;

    /**
     * Documento a procesar
     * @var XML
     */
    private $xml;

    /**
     * Datos del documento, armado con el XML
     * @var SimpleXMLElement
     */
    private $documento;

    /**
     * Vista utilizada para renderizar el PDF
     * @var String
     */
    private $view;

    /**
     * Tipo de Documento
     * @var String
     */
    private $tipo_documento;

    /**
     * Constructor que puede ser usado para crear un PDF con un Array de datos previamente armado
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Definicion de vista (View) que sera utilizada para renderizar el PDF
     *
     * @param String $view
     */
    public function setView($view)
    {
        $this->view = $view;
    }

    public function setTipoDocumento()
    {
        $this->tipo_documento = $this->documento->infoFactura ? 'FACTURA' : $this->tipo_documento;
        $this->tipo_documento = $this->documento->infoNotaCredito ? 'NOTA_CREDITO' : $this->tipo_documento;
        $this->tipo_documento = $this->documento->infoNotaDebito ? 'NOTA_DEBITO' : $this->tipo_documento;
        $this->tipo_documento = $this->documento->infoCompRetencion ? 'RETENCION' : $this->tipo_documento;
        $this->tipo_documento = $this->documento->infoGuiaRemision ? 'GUIA_REMISION' : $this->tipo_documento;
    }

    /**
     * Definicion del XML (documento) que contiene los datos
     *
     * @param Strin $xml Documento
     * @param boolean $decode Indica si el documento esta codificado o no en Base64
     */
    public function cargarXml($xml, $decode = false)
    {
        $this->xml = $decode ? base64_decode($xml) : $xml;
        $this->documento = new SimpleXMLElement(trim($this->xml));
    }

    /**
     * Arma el documento que se va a renderizar, basado en el XML. Permite renderizar todos los tipos de documentos conocidos en el SRi.
     * @return JSON Documento
     */
    public function armarDocumento()
    {
        $this->setTipoDocumento();

        switch ($this->tipo_documento) {
            case 'FACTURA':
                $this->setView(config('reportes.rides.views.factura'));
                $factura = new Factura($this->documento);
                $this->data = $factura->armarDocumento($this->data);
                break;

            case 'NOTA_CREDITO':
                $this->setView(config('reportes.rides.views.notacredito'));
                $nota_credito = new NotaCredito($this->documento);
                $this->data = $nota_credito->armarDocumento($this->data);
                break;

            case 'NOTA_DEBITO':
                $this->setView(config('reportes.rides.views.notadebito'));
                $nota_debito = new NotaDebito($this->documento);
                $this->data = $nota_debito->armarDocumento($this->data);
                break;

            case 'RETENCION':
                $this->setView(config('reportes.rides.views.retencion'));
                $retencion = new Retencion($this->documento);
                $this->data = $retencion->armarDocumento($this->data);
                break;

            case 'GUIA_REMISION':
                break;
        }
    }

    public function downloadPDF()
    {
        //Dependiendo del modelo a renderizar, se invoca una clase en particular y arma sus datos
        $this->armarDocumento();
        //Obtenemos el html a renderizar
        $html = view($this->view, $this->data);

        //Y... renderizamos
        $pdf = PDF::loadHTML($html)->setPaper(config('reportes.paper_size'))
            ->setOrientation(config('reportes.orientation'))
            ->setOption('encoding', config('reportes.encoding'))
            ->setOption('margin-left', config('reportes.margin-left'))
            ->setOption('margin-right', config('reportes.margin-right'))
            ->setOption('footer-center', config('reportes.footer-center'));

        return $pdf->stream();
    }
}
