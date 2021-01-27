<?php

namespace App\doc_electronicos;

use Illuminate\Support\Facades\Config;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Preferencia extends Eloquent
{
    const DEF_ASUNTO_EMAIL_CITACION = "Proceso de firmas - TITULO_PROCESO.";
    const DEF_ASUNTO_EMAIL_ASIMPLE = "Proceso de aceptación simple - TITULO_PROCESO.";
    const DEF_ASUNTO_EMAIL_FINALIZADO = "Proceso finalizado - TITULO_PROCESO.";
    const DEF_ASUNTO_EMAIL_RECHAZADO = "Proceso rechazado - TITULO_PROCESO.";
    const DEF_ASUNTO_EMAIL_INVITACION = "Invitación a participar.";
    const DEF_ASUNTO_EMAIL_REVISION = "Solicitud de revisión.";
    const DEF_ENVIAR_CORREO_FINALIZACION_EMISOR = true;

    const DEF_ORDEN = OrdenProcesoEnum::PARALELO;
    const DEF_FIRMA_EMISOR = 1;
    const DEF_FIRMA_STUPENDO = 0;
    const DEF_SELLO_TIEMPO = 2;
    const DEF_REFERENCIA_PAGINAS = 0;
    const DEF_ORIGEN_EXIGIDO = 0;

    const DEF_API_KEY = "";
    const DEF_URL_RESPUESTA = "";

    protected $collection = 'de_preferencias';
    protected $fillable = [
        '_id',
        'id_cliente',
        'id_usuario_establece',
        'default_de_email',
        'default_de_enmascaramiento',
        'default_asunto_email_citacion',
        'default_asunto_email_asimple',
        'default_asunto_email_finalizado',
        'default_asunto_email_rechazado',
        'default_asunto_email_invitacion',
        'def_asunto_email_revision',
        'default_enviar_correo_finalizacion_emisor',
        'direcciones_email_finalizacion',
        'api_key',
        'default_orden',
        'default_firma_emisor',
        'default_firma_stupendo',
        'default_sello_tiempo',
        'default_referencia_paginas',
        'default_origen_exigido',
        'url_respuesta'
    ];

    public function cliente()
    {
        return $this->belongsTo("App\Cliente", 'id_cliente');
    }

    public static function get_options_default($id_cliente, $default, $valor_default = null)
    {
        $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
        if (empty($valor_default)) {
            if (!$preferencias) {
                switch ($default) {
                    case "orden":
                    {
                        $valor_default = self::DEF_ORDEN;
                        break;
                    }
                    case "firma_emisor":
                    {
                        $valor_default = self::DEF_FIRMA_EMISOR;
                        break;
                    }
                    case "firma_stupendo":
                    {
                        $valor_default = self::DEF_FIRMA_STUPENDO;
                        break;
                    }
                    case "sello_tiempo":
                    {
                        $valor_default = self::DEF_SELLO_TIEMPO;
                        break;
                    }
                    case "referencia_paginas":
                    {
                        $valor_default = self::DEF_REFERENCIA_PAGINAS;
                        break;
                    }
                    case "origen_exigido":
                    {
                        $valor_default = self::DEF_ORIGEN_EXIGIDO;
                        break;
                    }
                }
            } else {
                $valor_default = $preferencias["default_" . $default];
            }
        }
        $options = '';
        $S = array();
        for ($index = 0; $index <= 2; $index++) {
            $S[$index] = ((int)$valor_default == (int)$index) ? 'selected="selected"' : '';
        }
        switch ($default) {
            case "orden":
            {
                $options .= '<option value="1" ' . $S[1] . '>Paralelo (Los signatarios son invitados a firmar simultáneamente)</option>';
                $options .= '<option value="2" ' . $S[2] . '>Secuencial (Los signatarios son invitados a firmar según el orden definido)</option>';
                break;
            }
            case "firma_emisor":
            {
                $options .= '<option value="1" ' . $S[1] . '>Al iniciar el proceso (Primera firma en estamparse)</option>';
                $options .= '<option value="2" ' . $S[2] . '>Al finalizar el proceso (Última firma en estamparse)</option>';
                $options .= '<option value="0" ' . $S[0] . '>No estampar firma emisora</option>';
                break;
            }
            case "firma_stupendo":
            {
                $options .= '<option value="2" ' . $S[2] . '>Estampar firma de Stupendo como ente garante al finalizar el proceso</option>';
                $options .= '<option value="0" ' . $S[0] . '>No estampar firma garante de Stupendo</option>';
                break;
            }
            case "sello_tiempo":
            {
                $options .= '<option value="2" ' . $S[2] . '>Agregar sello de tiempo desde un TSA</option>';
                $options .= '<option value="0" ' . $S[0] . '>No agregar sello de tiempo</option>';
                break;
            }
            case "referencia_paginas":
            {
                $options .= '<option value="0" ' . $S[0] . '>No agregar referencia Stupendo en páginas originales</option>';
                $options .= '<option value="1" ' . $S[1] . '>Agregar referencia Stupendo en todas las páginas</option>';
                break;
            }
            case "origen_exigido":
            {
                $options .= '<option value="0" ' . $S[0] . '>Admitir Ambas</option>';
                $options .= '<option value="1" ' . $S[1] . '>Admitir Firma Acreditada </option>';
                $options .= '<option value="2" ' . $S[2] . '>Admitir Firma Simple Stupendo</option>';
                break;
            }
        }
        return $options;
    }

    public static function get_api_key($id_cliente)
    {
        $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
        if (!$preferencias || !isset($preferencias["api_key"]) || empty($preferencias["api_key"])) {
            return self::DEF_API_KEY;
        } else {
            return $preferencias["api_key"];
        }
    }

    public static function get_url_respuesta($id_cliente)
    {
        $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
        if (!$preferencias || !isset($preferencias["url_respuesta"]) || empty($preferencias["url_respuesta"])) {
            return self::DEF_URL_RESPUESTA;
        } else {
            return $preferencias["url_respuesta"];
        }
    }

    public static function get_default($id_cliente, $campo)
    {
        $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
        if (!$preferencias || !isset($preferencias["default_" . $campo])) {
            switch ($campo) {
                case "orden":
                {
                    $valor_default = self::DEF_ORDEN;
                    break;
                }
                case "firma_emisor":
                {
                    $valor_default = self::DEF_FIRMA_EMISOR;
                    break;
                }
                case "firma_stupendo":
                {
                    $valor_default = self::DEF_FIRMA_STUPENDO;
                    break;
                }
                case "sello_tiempo":
                {
                    $valor_default = self::DEF_SELLO_TIEMPO;
                    break;
                }
                case "referencia_paginas":
                {
                    $valor_default = self::DEF_REFERENCIA_PAGINAS;
                    break;
                }
                case "origen_exigido":
                {
                    $valor_default = self::DEF_ORIGEN_EXIGIDO;
                    break;
                }
            }
            return (int)$valor_default;
        } else {
            return (int)$preferencias["default_" . $campo];
        }
    }

    public static function get_default_email_data($id_cliente, $campo)
    {
        $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
        if (!$preferencias || !isset($preferencias["default_" . $campo])) {
            switch ($campo) {
                case "de_email":
                {
                    $valor_default = Config::get('app.mail_from_name');
                    break;
                }
                case "de_enmascaramiento":
                {
                    $valor_default = Config::get('app.mail_from_address');
                    break;
                }
                case "asunto_email_citacion":
                {
                    $valor_default = self::DEF_ASUNTO_EMAIL_CITACION;
                    break;
                }
                case "asunto_email_asimple":
                {
                    $valor_default = self::DEF_ASUNTO_EMAIL_ASIMPLE;
                    break;
                }
                case "asunto_email_finalizado":
                {
                    $valor_default = self::DEF_ASUNTO_EMAIL_FINALIZADO;
                    break;
                }
                case "asunto_email_rechazado":
                {
                    $valor_default = self::DEF_ASUNTO_EMAIL_RECHAZADO;
                    break;
                }
                case "asunto_email_invitacion":
                {
                    $valor_default = self::DEF_ASUNTO_EMAIL_INVITACION;
                    break;
                }
                case "asunto_email_revision":
                {
                    $valor_default = self::DEF_ASUNTO_EMAIL_REVISION;
                    break;
                }
                case "enviar_correo_finalizacion_emisor":
                {
                    $valor_default = self::DEF_ENVIAR_CORREO_FINALIZACION_EMISOR;
                    break;
                }
            }
            return $valor_default;
        } else {
            return $preferencias["default_" . $campo];
        }
    }

    public static function get_direcciones_emails_finalizacion($id_cliente)
    {
        $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
        if (!empty($preferencias) && isset($preferencias->direcciones_emails_finalizacion)) {
            return $preferencias->direcciones_emails_finalizacion;
        }
        return '';
    }
}