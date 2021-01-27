<?php

namespace App\Packages\Traits;

use App\Packages\Model\{Factura, NotaCredito, NotaDebito, Retencion};
use App\Packages\Utilities\JsonPrepareXML;
use App\TipoDocumentoEmisionEnum;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;
use SimpleXMLElement;

trait StupendoAdons
{

    public function arrayToXml($array, &$xml_destino)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml_destino->addChild("$key");
                    $this->arrayToXml($value, $subnode);
                } else {
                    $subnode = $xml_destino->addChild(key($value));
                    $this->arrayToXml($value[key($value)], $subnode);
                }
            } else {
                $xml_destino->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }


    public function convertObjectXmlToString(SimpleXMLElement $xml): string
    {
        return str_replace("\n", "", $xml->asXML());
    }


    public function xmlToJson($xml, $data = []): string
    {
        $xml_object = new \SimpleXMLElement(base64_decode($xml));
        $tipo_comprobante = $xml_object->getName();

        $documento = array_merge($data);

        $dom = new DOMDocument();
        $dom->loadXML($xml_object->asXML());

        $this->json_prepare_xml($dom);
        $sxml = simplexml_load_string($dom->saveXML());


        $documento['comprobante'] = [
            "$tipo_comprobante" => $sxml
        ];


        $array_xml = [
            "?xml" => [
                "@version" => "1.0",
                "@encoding" => "UTF-8",
            ],
            "documento" => $documento,
        ];
        return json_encode($array_xml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function json_prepare_xml($domNode)
    {
        foreach ($domNode->childNodes as $node) {
            if ($node->hasChildNodes()) {
                $this->json_prepare_xml($node);
            } else {
                if ($domNode->hasAttributes() && strlen($domNode->nodeValue)) {
                    $domNode->setAttribute("text", $node->textContent);
                    $node->nodeValue = "";
                }
            }
        }
    }

    public function toCollection()
    {
        if (!$this->collection instanceof Collection) {
            $this->collection = collect($this->data);
        }

        return $this;
    }

    public function fieldsFilteredToCollection()
    {
        if (!$this->fields instanceof Collection) {
            return collect($this->fields)->map(
                function ($row) {
                    if (is_array($row)) {
                        return collect($row);
                    }

                    return $row;
                }
            );
        } else {
            return $this->fields;
        }
    }


    public function joinWithFields($data = [], $filters = [])
    {
        if ($data) {
            if (isset($this->data[0]) && (is_array($this->data[0]))) {
                foreach ($this->data as $key => $fields) {
                    if (is_array($fields)) {
                        $this->data[$key] = array_merge($data, $fields);
                    }
                }
            } else {
                $this->data = array_merge($data, $this->data);
            }
        }

        if ($filters) {
            $this->fields = [];

            if (isset($this->data[0]) && (is_array($this->data[0]))) {
                $rows = [];

                foreach ($this->data as $k => $fields) {
                    if (is_array($fields)) {
                        foreach ($filters as $key => $filter) {
                            if (array_key_exists($filter['id'], $fields)) {
                                $rows[$filter['id']] = $fields[$filter['id']];
                            }
                        }

                        $this->fields[] = $rows;
                    }
                }
            } else {
                foreach ($filters as $key => $filter) {
                    if (array_key_exists($filter['id'], $this->data)) {
                        $this->fields[$filter['id']] = $this->data[$filter['id']];
                    }
                }
            }
        }

        return $this;
    }

    public function dinamicParameters($parametros): array
    {
        $filters = (!is_array($parametros) ? (array)json_decode($parametros, true) : $parametros);

        $dataMatch = [];
        foreach ($filters as $filter) {
            switch ($filter['type']) {
                case 'datetime':

                    $desde = explode("/", $filter['value'][0]);
                    $from = Carbon::create($desde[2], $desde[1], $desde[0], 0, 0, 0);
                    $hasta = explode("/", $filter['value'][1]);
                    $to = Carbon::create($hasta[2], $hasta[1], $hasta[0], 23, 59, 59);

                    if ($filter['sign'] == 'range') {
                        $dataMatch += [
                            $filter['field'] => [
                                '$gte' => new UTCDateTime(strtotime($from) * 1000),
                                '$lte' => new UTCDateTime(strtotime($to) * 1000)
                            ]
                        ];
                    } elseif ($filter['sign'] == 'accumulative') {
                        $from = Carbon::now()->startOfMonth();
                        $to = Carbon::now();
                        $dataMatch += [
                            $filter['field'] => [
                                '$gte' => new UTCDateTime(strtotime($from) * 1000),
                                '$lte' => new UTCDateTime(strtotime($to) * 1000)
                            ]
                        ];
                    } elseif ($filter['sign'] == 'monthlyized') {
                        $from = Carbon::now()->subDays(30);
                        $to = Carbon::now();
                        $dataMatch += [
                            $filter['field'] => [
                                '$gte' => new UTCDateTime(strtotime($from) * 1000),
                                '$lte' => new UTCDateTime(strtotime($to) * 1000)
                            ]
                        ];
                    } else {
                        $date_explode = explode("/", $filter['value']);
                        $date = Carbon::create(
                            $date_explode[2],
                            $date_explode[1],
                            $date_explode[0],
                            0,
                            0,
                            0
                        );
                        $dataMatch[$filter['field']] = new UTCDateTime(strtotime($date) * 1000);
                    }
                    break;

                default:

                    $dataMatch[$filter['field']] = $filter['value'];
                    break;
            }
        }

        return $dataMatch;
    }


    public function getInstanceOfTipoDocumento($tipo_documento)
    {
        switch ($tipo_documento) {
            case TipoDocumentoEmisionEnum::FACTURA:
                $instance = Factura::class;
                break;

            case TipoDocumentoEmisionEnum::NOTA_DE_CREDITO:
                $instance = NotaCredito::class;
                break;

            case TipoDocumentoEmisionEnum::NOTA_DE_DEBITO:
                $instance = NotaDebito::class;
                break;

            case TipoDocumentoEmisionEnum::COMPROBANTE_DE_RETENCION:
                $instance = Retencion::class;
                break;
        }

        return $instance;
    }


    public function orderByFields($fields_required = [])
    {
        $ordered_fields = [];

        if (isset($this->fields[0]) && (is_array($this->fields[0]))) {
            foreach ($this->fields as $index => $data) {
                foreach ($fields_required as $field) {
                    $ordered_fields[$index][$field] = $data[$field];
                }
            }
        } else {
            foreach ($fields_required as $field) {
                $ordered_fields[$field] = $this->fields[$field];
            }
        }

        $this->fields = null;
        $this->fields = $ordered_fields;

        return $this;
    }

    public function encriptar($str)
    {
        $PASSWORD = config('app.password_encrypt');
        $CIPHER_METHOD = 'AES-256-CBC';

        $iv_length = openssl_cipher_iv_length($CIPHER_METHOD);

        $iv = random_bytes($iv_length);
        $str = $iv . $str;

        $val = openssl_encrypt($str, $CIPHER_METHOD, $PASSWORD, 0, $iv);

        return str_replace(array('+', '/', '='), array('_', '-', '.'), $val);
    }

    function desencriptar($str)
    {
        $PASSWORD = config('app.password_encrypt');
        $CIPHER_METHOD = 'AES-256-CBC';

        $val = str_replace(array('_', '-', '.'), array('+', '/', '='), $str);
        $data = base64_decode($val);
        $iv_length = openssl_cipher_iv_length($CIPHER_METHOD);
        $body_data = substr($data, $iv_length);
        $iv = substr($data, 0, $iv_length);
        $base64_body_data = base64_encode($body_data);

        return openssl_decrypt($base64_body_data, $CIPHER_METHOD, $PASSWORD, 0, $iv);
    }

    public function getColumnExcel($position)
    {
        $lettersColumns = [
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'H',
            'I',
            'J',
            'K',
            'L',
            'M',
            'N',
            'O',
            'P',
            'Q',
            'R',
            'S',
            'T',
            'U',
            'V',
            'W',
            'X',
            'Y',
            'Z',
            'AA',
            'AB',
            'AC',
            'AD',
            'AE',
            'AF',
            'AG',
            'AH',
            'AI',
            'AJ',
            'AK',
            'AL',
            'AM',
            'AN',
            'AO',
            'AP',
            'AQ',
            'AR',
            'AS',
            'AT',
            'AU',
            'AV',
            'AW',
            'AX',
            'AY',
            'AZ',
            'BA',
            'BB',
            'BC',
            'BD',
            'BE',
            'BF',
            'BG',
            'BH',
            'BI',
            'BJ',
            'BK',
            'BL',
            'BM',
            'BN',
            'BO',
            'BP',
            'BQ',
            'BR',
            'BS',
            'BT',
            'BU',
            'BV',
            'BW',
            'BX',
            'BY',
            'BZ',
            'CA',
            'CB',
            'CC',
            'CD',
            'CE',
            'CF',
            'CG',
            'CH',
            'CI',
            'CJ',
            'CK',
            'CL',
            'CM',
            'CN',
            'CO',
            'CP',
            'CQ',
            'CR',
            'CS',
            'CT',
            'CU',
            'CV',
            'CW',
            'CX',
            'CY',
            'CZ'
        ];

        return $lettersColumns[$position];
    }

}