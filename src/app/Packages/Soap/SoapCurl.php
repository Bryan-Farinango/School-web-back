<?php

namespace App\Packages\Soap;

use SimpleXMLElement;

abstract class SoapCurl
{

    protected $authentication;

    protected $curlUrl;

    protected $curlConnectTimeout = 30;

    protected $curlTimeout = 30;

    protected $curlPost = true;

    private $curlVerifyPeer = false;

    private $curlVerifyHost = false;

    protected $endpoint;

    protected $errors;

    protected $namespace = 'soap';

    protected $parameters = [];

    protected $pathNamespace = "";

    protected $soapAction = "";

    private $removeNamepaceResponse = false;

    protected $xmlnsInAction = "";

    protected $xmlHeaderTag = "";

    protected $xmlResponse;

    protected $xmlRequest = "";

    public function namespace(string $ns)
    {
        $this->namespace = $ns;

        return $this;
    }

    public function namespaceInAction(string $action)
    {
        $this->xmlnsInAction = $action;

        return $this;
    }

    public function removeNamespaceInResponse(bool $value = true)
    {
        $this->removeNamepaceResponse = $value;

        return $this;
    }

    public function curlVerifyPeer(bool $value = true)
    {
        $this->curlVerifyPeer = $value;

        return $this;
    }

    public function curlVerifyHost(bool $value = true)
    {
        $this->curlVerifyHost = $value;

        return $this;
    }

    public function headerAutenticationTag(string $nametag)
    {
        $this->xmlHeaderTag = $nametag;

        return $this;
    }

    public function header()
    {
        if ($this->authentication && $this->xmlHeaderTag) {
            $this->xmlRequest .= '<' . $this->namespace . ':Header>';
            $ns = $this->xmlnsInAction ? 'xmlns="' . $this->xmlnsInAction . '"' : "";
            $this->xmlRequest .= '<' . $this->xmlHeaderTag . ' ' . $ns . '>';

            foreach ($this->authentication as $key => $value) {
                $this->xmlRequest .= '<' . $key . '>' . trim($value) . '</' . $key . '>';
            }

            $this->xmlRequest .= '</' . $this->xmlHeaderTag . '>';
            $this->xmlRequest .= '</' . $this->namespace . ':Header>';
        }

        return $this;
    }

    public function parameters(array $data = [])
    {
        $this->parameters = $data;

        return $this;
    }

    public function registerXPathNamespace(string $path_namespace)
    {
        $this->pathNamespace = $path_namespace;

        return $this;
    }

    public function action(string $action)
    {
        $this->soapAction = $action;

        return $this;
    }

    public function envelopeHeader()
    {
        $this->xmlRequest .= '<' . $this->namespace . ':Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:' . $this->namespace . '="http://schemas.xmlsoap.org/soap/envelope/">';

        return $this;
    }

    public function envelopeFooter()
    {
        $this->xmlRequest .= '</' . $this->namespace . ':Envelope>';

        return $this;
    }

    public function body()
    {
        $this->xmlRequest .= '<' . $this->namespace . ':Body>';
        $ns = $this->xmlnsInAction ? 'xmlns="' . $this->xmlnsInAction . '"' : "";
        $this->xmlRequest .= '<' . $this->soapAction . ' ' . $ns . '>';

        foreach ($this->parameters as $key => $value) {
            if (is_array($value)) {
                $this->xmlRequest .= '<' . $key . '>';
                $this->xmlRequest .= (isset($value['xmlToText']) && $value['xmlToText']) ? '<![CDATA[' : '';
                $this->xmlRequest .= $this->buildRecursiveParameters($value['data']);
                $this->xmlRequest .= (isset($value['xmlToText']) && $value['xmlToText']) ? ']]>' : '';
                $this->xmlRequest .= '</' . $key . '>';
            } else {
                $this->xmlRequest .= '<' . $key . '>' . trim($value) . '</' . $key . '>';
            }
        }

        $this->xmlRequest .= '</' . $this->soapAction . '>';
        $this->xmlRequest .= '</' . $this->namespace . ':Body>';

        return $this;
    }

    public function buildRequest()
    {
        $this->envelopeHeader()
            ->header()
            ->body()
            ->envelopeFooter();

        return $this;
    }

    public function buildRecursiveParameters($data)
    {
        $fields = '';

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                $fields .= '<' . $key . '>' . trim($value) . '</' . $key . '>';
            } else {
                $fields .= '<' . $key . '>';
                $fields .= $this->buildRecursiveParameters($value);
                $fields .= '</' . $key . '>';
            }
        }

        return $fields;
    }

    public function execute()
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->curlUrl);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curlConnectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->curlTimeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, $this->curlPost);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->curlVerifyPeer);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->curlVerifyHost);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xmlRequest);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=utf-8', 'Content-Length: ' . strlen($this->xmlRequest)));
            $this->xmlResponse = curl_exec($ch);
            curl_close($ch);

            if ($this->xmlResponse) {
                //Remover namespace en Response (selectivo)
                if ($this->removeNamepaceResponse) {
                    $this->xmlResponse = str_replace('xmlns="' . $this->xmlnsInAction . '"', "", $this->xmlResponse);
                }

                libxml_use_internal_errors(true);
                $xml = new SimpleXMLElement(trim($this->xmlResponse));
                $errors = libxml_get_errors();

                $this->errors = [];

                //Errores capturados por libxml_get_errors. Pueden haber casos en que se ejecute el request pero el error venga en la respuesta del mismo y no en la ejecución o parseo de la respuesta a XML
                if ($errors) {
                    $this->errors = $errors;
                } else {
                    //Si no hay errores de conversión, se debe verificar si no existe un Soap:Fault (Exception) en la respuesta. Esto es para evitar que el servicio se detenga por errores del webservices externo
                    if ($xml && $xml->xpath('InnerException')) {
                        $this->errors = $xml->xpath('InnerException');
                    }
                }

                if ($this->pathNamespace) {
                    $xml->registerXPathNamespace('d', $this->pathNamespace);
                }

                $this->xmlResponse = $xml;
            }

            return $this->xmlResponse;
        } catch (Exception $e) {
        }
    }

    public function getXPath(string $query): array
    {
        if ($this->xmlResponse instanceof SimpleXMLElement) {
            if ($query) {
                return $this->xmlResponse->xpath($query);
            }
        } else {
            return [];
        }
    }

    public function hasErrors()
    {
        return ($this->errors) ? true : false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}