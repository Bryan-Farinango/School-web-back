<?php

namespace App\Packages\Integracion;

use App\Packages\Soap\SoapCurl;
use App\Packages\Utilities\StupendoCalendar;

class InteraguaWS extends SoapCurl
{

    private $model;

    private const CHECK_SALDO = 'OS_IA_GETSALDOCONTRATO.asmx?wsdl';

    private const PAYMENT_CONTRATO = 'OS_IA_WITHHOLDINGPAYMENTSINRET.asmx?wsdl';

    private const ANULAR_PAGO_CONTRATO = 'OS_IA_PAYMENTSANNULL.asmx?wsdl';


    public function loadConfig(string $url, array $auth = [], $model)
    {
        $this->endpoint = $url;
        $this->authentication = $auth;
        $this->model = $model;

        $this->namespaceInAction("OpenSystems.WebServices.UI")
            ->headerAutenticationTag("AuthenticationHeader")
            ->registerXPathNamespace("urn:schemas-microsoft-com:xml-diffgram-v1")
            ->removeNamespaceInResponse();

        return $this;
    }


    public function checkSaldoDocumento(): float
    {
        $codigo_contrato = $this->model->getCampoAdicional('CONTRATO');

        if ($codigo_contrato) {
            $this->parameters = [
                'INUSUSCCODI' => (int)$codigo_contrato
            ];

            $this->curlUrl = $this->endpoint . static::CHECK_SALDO;

            $response = $this->action('OS_IA_GETSALDOCONTRATO_WS')->buildRequest()->execute();

            if (!$this->hasErrors()) {
                $data = $response->xpath('//OCUDATACURSOR[@d:id="OCUDATACURSOR1"]');

                if ($data) {
                    return (string)$data[0]->SALDO_BANCO;
                } else {
                    return $this->model->getImporteTotal();
                }
            }
        } else {
            return $this->model->getImporteTotal();
        }
    }

    public function registrarPagoDocumento($data = []): bool
    {
        $codigo_contrato = $this->model->getCampoAdicional('CONTRATO');

        if ($codigo_contrato) {
            $paymentBrand = $this->getPaymentBrandCode($data['paymentBrand']);

            $this->parameters = [
                'INUREFTYPE' => 3,
                'ISBXMLREFERENCE' => [
                    'xmlToText' => true,
                    'data' => [
                        'Pago_Contrato' => [
                            'Cod_Contrato' => (int)$codigo_contrato
                        ],
                    ],
                ],
                'ISBXMLPAYMENT' => [
                    'xmlToText' => true,
                    'data' => [
                        'Informacion_Pago' => [
                            'Conciliacion' => [
                                'Cod_Conciliacion' => '',
                                'Entidad_Conciliacion' => 2132,
                                'Fecha_Conciliacion' => (new StupendoCalendar())->nextBusinessDay()->format('d-m-Y')
                            ],
                            'Entidad_Recaudo' => 2132,
                            'Punto_Pago' => 'BPS',
                            'Valor_Pagado' => $data['amount'],
                            'Fecha_Pago' => StupendoCalendar::now()->format('d-m-Y'),
                            'No_Transaccion' => $data['resultDetails']['AuthCode'],
                            'Forma_Pago' => 'TC',
                            'Clase_Documento' => $paymentBrand,
                            'No_Documento' => $data['card']['bin'],
                            'Ent_Exp_Documento' => '',
                            'No_Autorizacion' => $data['resultDetails']['AuthCode'],
                            'No_Meses' => '',
                            'No_Cuenta' => '',
                            'Programa' => 'OS_PAYMENT',
                            'Terminal' => 'APP',
                        ],
                    ],
                ],
            ];

            $this->curlUrl = $this->endpoint . static::PAYMENT_CONTRATO;

            $response = $this->action('OS_IA_WITHHOLDINGPAYMENTSINRET_WS')->buildRequest()->execute();

            if (!$this->hasErrors()) {
                $error = '';
                if (isset($response->xpath('//ONUERRORCODE')[0])) {
                    $error = (string)$response->xpath('//ONUERRORCODE')[0];
                    $mensaje = (string)$response->xpath('//OSBERRORMESSAGE')[0];
                    \Log::info('RetroalimentarPago: Codigo: ' . $error);
                    \Log::info('RetroalimentarPago: Mensaje: ' . $mensaje);
                }

                if ($error != '0') {
                    if ($response->asXML()) {
                        \Log::info($response->asXML());
                    }
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    public function anularPagoDocumento($data = []): bool
    {
        $codigo_contrato = $this->model->getCampoAdicional('CONTRATO');

        if ($codigo_contrato) {
            $this->parameters = [
                'INUREFTYPE' => 2,
                'ISBXMLREFERENCE' => [
                    'xmlToText' => true,
                    'data' => [
                        'Anulacion_Contrato' => [
                            'Contrato' => (int)$codigo_contrato,
                            'Entidad_Recaudo' => 2132,
                            'Punto_Pago' => 'BPS',
                            'Fecha_Pago' => $data['fechaPago'],
                            'Valor_Pagado' => $data['valorPagado'],
                            'Forma_Pago' => 'TC',
                        ],
                    ],
                ],
                'ISBXMLPAYMENT' => [
                    'xmlToText' => true,
                    'data' => [
                        'Inf_Anulacion' => [
                            'Causa_Anulacion' => 'PGD',
                            'Programa' => 'OS_PAYMENT',
                            'Terminal' => 'APP',
                        ],
                    ],
                ],
            ];

            $this->curlUrl = $this->endpoint . static::ANULAR_PAGO_CONTRATO;

            $response = $this->action('OS_IA_PAYMENTSANNULL_WS')->buildRequest()->execute();

            if (!$this->hasErrors()) {
                $error = (string)$response->xpath('//ONUERRORCODE')[0];
                $mensaje = (string)$response->xpath('//OSBERRORMESSAGE')[0];

                if ($error != '0') {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    public function getPaymentBrandCode($brand)
    {
        $paymentBrand = '';

        switch ($brand) {
            case 'VISA':
                $paymentBrand = 51;
                break;

            case 'MASTER':
                $paymentBrand = 52;
                break;

            case 'AMEX':
                $paymentBrand = 41;
                break;

            case 'DINERS':
                $paymentBrand = 42;
                break;

            case 'DISCOVER':
                $paymentBrand = 42;
                break;

            case 'ALIA':
                $paymentBrand = 43;
                break;

            default:
                $paymentBrand = 51;
                break;
        }

        return $paymentBrand;
    }
}
