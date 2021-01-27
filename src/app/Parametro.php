<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Parametro extends Eloquent
{    
    protected $collection = 'parametros';

    protected $fillable = [
        'not_cobranzas',
        'inventario',
        'cobranzas',
        'fecha_inventario',
        'cantidad_documentos',
        'plan_expiracion',
        'mensaje_personal',
        'saldo_partner',
        'email_nombre',
        'email_enmascarimiento',
        'email_embeber',
        'boton_pagos_esdinamico',
        'boton_pagos_id_publico',
        'boton_pagos_id_privado',
        'contrasena_sri',
        'ci_adicional',
        'tipo_documento',
        'recepcion_automatica',
        'recepcion_automatica_race',
        'recepcion_automatica_old',
        'mostrar_moneda',
        'mostrar_codigo_auxiliar',
        'mostrar_detalle_adicional',
        'notificacion_errados',
        'email_notificacion_errados',
        'editar_secuenciales',
        'anulacion_automatica',
        'financiamiento',
        'notificaciones_off',
        'intentos_sri',
        'error_login_sri',
        'contrasena_segura',
        'tiempo_caducidad',
        'tamano_clave',
        'incluir_mayusculas',
        'incluir_especiales',
        'incluir_numeros',
        'expiracion_plan',
        'integracion_excel',
        'descuento_porcentaje',
        'robot_fecha_proc',
        'race_workflows',
        'responsable',
        'web_services_nipro',
        'user_api',
        'password_api',
        'empresa_adm',
        'empresa_adm_nombre',
        'empresa_adm_pendiente_aprob',
        'segmentos',
        'segmento_campo',
        'segmento_cod_def',
        'feedback_email',
        'feedback_ftp',
        'feedback_json',
        'feedback_xml',
        'feedback_tipo_doc',
        'feedback_solo_aprobados',
        'feedback_xml_cabecera',
        'genera_xml_docs_fisicos',
        'calculos',
        'valor_tolerancia',
        'adjuntar_pdf',
        'adjuntar_xml',
        'zipear_xml',
        'tag_email',
        'email_defecto',
        'nombre_personalizado',
        'obtener_datos_archivo',
        'est_i',
        'pto_i',
        'sec_i',
        'email_notificacion_recepcion',
        'boton_pagos_tag',
        'boton_pagos_tag_name',
        'boton_pagos_terminos_condiciones',
        'boton_pagos_terminos_condiciones_file_id',
        'boton_pagos_terminos_condiciones_mimetype',
        'boton_pagos_politicas',
        'boton_pagos_politicas_file_id',
        'boton_pagos_politicas_mimetype',
        'boton_pagos_texto_informativo',
        'recordatorio_emision_docelct',
        'dias_emision_docelct',
        'opcion_precargar',
        'condicion_contrato1',
        'condicion_contrato2',
        'condicion_contrato3',
        'condicion_contrato4',
        'condicion_contrato5_fvpj',
        'validaEAN13',
        'cargaMasiva',
        'banner_inferior_notificacion',
        'superciasInspection',
        'docs_simples_auditoria',
        'omitir_en_procesamiento_cer',
        'scheduled_reports',
        'amount_scheduled_reports_available',
        'scheduling_ftp',
        'scheduling_ftp_server',
        'scheduling_ftp_user',
        'scheduling_ftp_password',
        'scheduling_ftp_port',
        'scheduling_ftp_ssl',
        'scheduling_ftp_tls',
        'scheduling_ftp_root',
        'scheduling_ftp_timeout',
        'scheduling_ftp_ssl_verify_peer',
        'scheduling_ftp_ssl_verify_host',
        'scheduling_ftp_passive',
        'scheduling_ftp_ignore_passive_address',
        'scheduling_ftp_tree',
        'maximum_value',
        'retroalimentacion_personalizada',
        'send_dispatcher_notification',
        'integration_type_feedback',
        'authorizationDateFormatV3'
    ];

    protected $dates = ['robot_fecha_proc'];

    public function getAnulacionAutomatica()
    {
        return isset($this->anulacion_automatica) ? $this->anulacion_automatica : false;
    }

    public function hasFeedBackPersonalizada()
    {
        $retroalimentacion_personalizada = $this->retroalimentacion_personalizada;
        return isset($retroalimentacion_personalizada) ? $retroalimentacion_personalizada : false;
    }

    public function hasFeedBackEmail()
    {
        $feedback_email = $this->feedback_email;
        return isset($feedback_email) ? $feedback_email : false;
    }

    public function hasAuthorizationDateFormat()
    {
        $authorizationDateFormatV3 = $this->authorizationDateFormatV3;
        return isset($authorizationDateFormatV3) ? $authorizationDateFormatV3 : 'd/m/Y H:i:s';
    }

    public function getNodeScheludeFTP()
    {
        $scheduling_ftp_root = $this->scheduling_ftp_root;
        return isset($scheduling_ftp_root) ? $scheduling_ftp_root : null;
    }

    public function hasFeedBackFTP()
    {
        return isset($this->feedback_ftp) ? $this->feedback_ftp : false;
    }

    public function hasScheduledBackFTP()
    {
        return isset($this->scheduling_ftp) ? $this->scheduling_ftp : false;
    }

    public function isAllowedScheduledReports()
    {
        return isset($this->scheduled_reports) ? $this->scheduled_reports : false;
    }

    public function amountScheduledReportsAvailable()
    {
        $amount = (int)$this->amount_scheduled_reports_available;
        return isset($amount) ? $amount : 0;
    }

    public function decreaseAmountScheduledReportsAvailable($quantity = 1)
    {
        $this->amount_scheduled_reports_available -= $quantity;

        $this->save();
    }

    public function isSupercias()
    {
        $superciasInspection = $this->superciasInspection;
        return isset($superciasInspection) ? $superciasInspection : false;
    }

    public function tieneInventario()
    {
        if (isset($this->inventario)) {
            if ($this->inventario != null) {
                if ($this->inventario) {
                    return true;
                }
            }
        }
        return false;
    }

    public function tieneCobranzas()
    {
        if (isset($this->cobranzas)) {
            if ($this->cobranzas) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    public function tieneSegmentos()
    {
        if (isset($this->segmentos)) {
            if ($this->segmentos) {
                return true;
            }
        }
        return false;
    }

    public function mostrarMoneda()
    {
        if (isset($this->mostrar_moneda)) {
            if ($this->mostrar_moneda) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function mostrarCodigoAuxiliar()
    {
        if (isset($this->mostrar_codigo_auxiliar)) {
            if ($this->mostrar_codigo_auxiliar) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function tieneDescuentoPorcentaje()
    {
        if (isset($this->descuento_porcentaje)) {
            if ($this->descuento_porcentaje) {
                return true;
            }
        }
        return false;
    }

    public function mostrarDetalleAdicional()
    {
        if (isset($this->mostrar_detalle_adicional)) {
            if ($this->mostrar_detalle_adicional) {
                return $this->mostrar_detalle_adicional;
            } else {
                return 0;
            }
        }

        return 0;
    }

    public function estaActivadoNotCobranzas()
    {
        if ($this->not_cobranzas) {
            return true;
        } else {
            return false;
        }
    }

    public function adjuntarPdf()
    {
        if (isset($this->adjuntar_pdf)) {
            return $this->adjuntar_pdf;
        } else {
            return true;
        }
    }

    public function adjuntarXml()
    {
        if (isset($this->adjuntar_xml)) {
            return $this->adjuntar_xml;
        } else {
            return true;
        }
    }

    public function getEmailNotRecepcion()
    {
        if ($this->email_notificacion_recepcion) {
            return explode(",", $this->email_notificacion_recepcion);
        } else {
            return false;
        }
    }

    public function getSimpleEmailNotRecepcion()
    {
        if ($this->email_notificacion_recepcion) {
            return $this->email_notificacion_recepcion;
        } else {
            return false;
        }
    }

    public function tagEmails()
    {
        if ($this->tag_email) {
            return explode(",", $this->tag_email);
        } else {
            return false;
        }
    }

    public function simpleTagEmails()
    {
        if ($this->tag_email) {
            return $this->tag_email;
        } else {
            return false;
        }
    }

    public function emailDefecto()
    {
        if ($this->email_defecto) {
            return $this->email_defecto;
        } else {
            return false;
        }
    }

    public function zipearXml()
    {
        if (isset($this->zipear_xml)) {
            return $this->zipear_xml;
        } else {
            return false;
        }
    }

    public function tieneDocsDisponibles()
    {
        if ($this->cantidad_documentos <= 0) {
            return false;
        }
        return true;
    }

    public function documentosParaDescargar()
    {
        if ($this->tipo_documento) {
            return $this->tipo_documento;
        } else {
            return '0';
        }
    }

    public function editarSecuenciales()
    {
        if (isset($this->editar_secuenciales)) {
            if ($this->editar_secuenciales) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function puedeFinanciar()
    {
        if (isset($this->financiamiento)) {
            if ($this->financiamiento) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function hasActiveWorkflows()
    {
        if (isset($this->race_workflows)) {
            if ($this->race_workflows) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    public function contrasenaSegura()
    {
        if (isset($this->contrasena_segura)) {
            if ($this->contrasena_segura) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function puedeNotificar()
    {
        if (isset($this->notificaciones_off)) {
            if ($this->notificaciones_off) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    public function notificarReceptor()
    {
        if (isset($this->notificar_receptor)) {
            return ($this->notificar_receptor) ? true : false;
        }
        return false;
    }

    public function toggleNotificacionReceptor($switch)
    {
        $this->notificacion_receptor = ($switch) ? true : false;
    }

    public function mostrarResponsable()
    {
        if (isset($this->responsable)) {
            if ($this->responsable) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function mostrarValorLetras()
    {
        if (isset($this->mostrar_valor_letras)) {
            if ($this->mostrar_valor_letras) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function mostrarReimprimirBoton()
    {
        if (isset($this->reimprimir_boton)) {
            if ($this->reimprimir_boton) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function validaCalculos()
    {
        if (isset($this->calculos)) {
            if ($this->calculos) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function toleranciaCalculos()
    {
        if (isset($this->valor_tolerancia)) {
            return $this->valor_tolerancia;
        }

        return 1;
    }

    public function nombrePersonalizado()
    {
        if (isset($this->nombre_personalizado)) {
            if ($this->nombre_personalizado) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function TieneEAN13()
    {
        if (isset($this->validaEAN13)) {
            if ($this->validaEAN13) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function TieneCargaMasiva()
    {
        if (isset($this->cargaMasiva)) {
            if ($this->cargaMasiva) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    public function bannerInferiorNotificacion()
    {
        if (isset($this->banner_inferior_notificacion)) {
            return $this->banner_inferior_notificacion;
        } else {
            return false;
        }
    }

    public function TieneDocsAceptacionSimpleConAuditorias()
    {
        if (isset($this->docs_simples_auditoria)) {
            if ($this->docs_simples_auditoria === false) {
                return false;
            }
        }
        return true;
    }

    public function isEmbedded()
    {
        $response = false;

        if (isset($this->email_embeber)) {
            if ($this->email_embeber) {
                $response = true;
            }
        }

        return $response;
    }

    public function isSkipStatusCer()
    {
        $response = false;

        if (isset($this->omitir_en_procesamiento_cer)) {
            if ($this->omitir_en_procesamiento_cer) {
                $response = true;
            }
        }

        return $response;
    }

    public function checkMaximumValue()
    {
        $response = false;

        if (isset($this->maximum_value)) {
            if ($this->maximum_value > 0) {
                $response = true;
            }
        }

        return $response;
    }

    public function checkSendDispatcherNotification()
    {
        $response = true;

        if (isset($this->send_dispatcher_notification)) {
            if (!$this->send_dispatcher_notification) {
                $response = false;
            }
        }

        return $response;
    }

    public function getIntegrationTypeFeedback(){
        $response = ['Portal', 'Api'];

        if(isset($this->integration_type_feedback)){
            $response = $this->integration_type_feedback;
        }

        return $response;
    }
}
