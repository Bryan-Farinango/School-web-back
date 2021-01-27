<?php

namespace App\doc_electronicos;

use App\Cliente;
use App\Http\Controllers\doc_electronicos\ApiController;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class ProcesoBase extends Eloquent
{
    public const STORAGE_LOCAL = 1;
    public const STORAGE_EXTERNO = 2;

    public function cliente_emisor()
    {
        return $this->belongsTo("App\Cliente", 'id_cliente_emisor');
    }

    public function getDocumento($id_documento)
    {
        foreach ($this->documentos as $documento) {
            if ($documento['id_documento'] == $id_documento) {
                return $documento;
            }
        }
        return null;
    }

    public function InvocarWebServiceRetroalimentacion($proceso)
    {
        if (!$proceso) {
            return;
        }
        try {
            Log::info("Invocando servicio de retroalimentacion para " . $proceso->_id);
            $url_respuesta = "";

            $url_respuesta = Preferencia::get_url_respuesta($proceso->id_cliente_emisor);
            $this->enviarRetroAlimentacion($proceso, $url_respuesta);

            if (isset($proceso->ftp_filename) && !empty($proceso->ftp_filename)) {
                $url_respuesta = Config::get('app.retroalimentacion_carga_ftp_firma_de');
                $this->enviarRetroAlimentacion($proceso, $url_respuesta);
            }
        } catch (Exception $e) {
            Log::error("Error en InvocarWebServiceRetroalimentacion($proceso->_id): " . $e->getMessage());
        }
    }

    private function enviarRetroAlimentacion($proceso, $url)
    {
        try {
            if (!empty($url)) {
                Log::info("Enviando retroalimentacion a $url");

                $api_key = Preferencia::get_api_key($proceso->id_cliente_emisor);
                $cliente = Cliente::find($proceso->id_cliente_emisor);

                $request = new Request();
                $request->merge(
                    array(
                        "ruc" => $cliente->identificacion,
                        "api_key" => $api_key,
                        "referencia" => $proceso->_id
                    )
                );
                $api = new ApiController();
                $resultado = $api->VerEstado($request);

                $opciones = array(
                    'http' => array(
                        'method' => "POST",
                        'header' => "Content-Type:application/json\r\n" . "Content-Length:" . strlen(
                                $resultado
                            ) . "\r\n",
                        'content' => $resultado
                    )
                );

                $context = stream_context_create($opciones);

                $fp = fopen($url, 'r', false, $context);
                fclose($fp);
            }
        } catch (Exception $e) {
            Log::error("Error en enviarRetroAlimentacion($proceso->_id, $url): " . $e->getMessage());
        }
    }
}