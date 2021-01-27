<?php

namespace App\doc_electronicos;

use Illuminate\Support\Facades\Log;

class Proceso extends ProcesoBase
{
    protected $collection = 'de_procesos';

    public const CUERPO_EMAIL_POR_DEFECTO = '<p style="color:#70706e;display:block;font-family:Helvetica;font-size:20px;line-height:100%;text-align:justify;margin:0 0 30px" align="justify">Estimado/a,</p><p style="color:#70706e;display:block;font-family:Helvetica;font-size:14px;line-height:26px;text-align:justify;margin:0 0 30px" align="justify">La compañía "NOMBRE_COMPANIA", a través de la plataforma <b>Stupendo</b>, te ha invitado a participar en la revisión y firmado, de los siguientes documentos electrónicos, correspondientes al proceso que han denominado "TITULO_PROCESO":</p><p style="color:#70706e;display:block;font-family:Helvetica;font-size:14px;line-height:2px;text-align:justify;margin:0 0 30px" align="justify"><b>Documentos relacionados</b></p><p>LISTA_DOCUMENTOS</p><br><span class="im"><br><p style="color:#70706e;display:block;font-family:Helvetica;font-size:14px;line-height:2px;text-align:justify;margin:0 0 30px" align="justify"><b>Participantes</b></p>LISTA_PARTICIPANTES<br></br>';

    protected $fillable = [
        '_id',
        'id_propio',
        'id_cliente_emisor',
        'id_usuario_emisor',
        'titulo',
        'id_estado_actual_proceso',
        'momento_emitido',
        'documentos',
        'firmantes',
        'historial',
        'storage',
        'orden',
        'firma_emisor',
        'firma_stupendo',
        'sello_tiempo',
        'referencia_paginas',
        'origen_exigido',
        'bloqueado',
        'via',
        'revisiones',
        'nombre_enmas',
        'correo_enmas',
        'cuerpo_email',
        'url_banner',
        'ftp_filename'
    ];

    public function adjuntos()
    {
        return $this->embedsMany('App\doc_electronicos\AdjuntoProceso');
    }

    public function esFirmanteJuridico($clienteId)
    {
        foreach ($this->firmantes as $firmante) {
            if ($firmante["id_cliente_receptor"] == $clienteId) {
                if (isset($firmante["persona"]) && $firmante["persona"] == 'J') {
                    return true;
                }
            }
        }
        return false;
    }

    public function noTieneFirmaVigente($clienteId)
    {
        foreach ($this->firmantes as $firmante) {
            if ($firmante["id_cliente_receptor"] == $clienteId) {
                $firma = Firma::ultimaFirmaDelCliente($clienteId, true);
                if (!$firma) {
                    return true;
                }
            }
        }
        return false;
    }

    public function estaSiendoValidadaLaFirma($clienteId)
    {
        foreach ($this->firmantes as $firmante) {
            if ($firmante["id_cliente_receptor"] == $clienteId) {
                $firmapv = FirmaPorValidar::ultimaFirmaDelCliente($clienteId);
                if($firmapv && $firmapv->id_estado == FirmaPorValidarEstadoEnum::POR_VALIDAR) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        Log::info("estaSiendoValidadaLaFirma($clienteId): llegó al false del final");
        return false;
    }
}