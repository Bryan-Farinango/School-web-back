<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\Preferencia;
use App\Http\Controllers\Config\ClienteController;
use App\Http\Controllers\Controller;
use App\Packages\Traits\ImageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class PreferenciasController extends Controller
{
    use ImageTrait;

    public function __construct()
    {
    }

    public function MostrarPreferencias()
    {
        $id_cliente = session()->get("id_cliente");
        $cliente = Cliente::find($id_cliente);

        $camino_logo_actual = ClienteController::getUrlLogo($cliente);
        $camino_banner_actual = $this->getURLEmailBanner($cliente);

        $de_email = Preferencia::get_default_email_data($id_cliente, "de_email");
        $de_enmascaramiento = Preferencia::get_default_email_data($id_cliente, "de_enmascaramiento");
        $asunto_email_citacion = Preferencia::get_default_email_data($id_cliente, "asunto_email_citacion");
        $asunto_email_asimple = Preferencia::get_default_email_data($id_cliente, "asunto_email_asimple");
        $asunto_email_finalizado = Preferencia::get_default_email_data($id_cliente, "asunto_email_finalizado");
        $asunto_email_rechazado = Preferencia::get_default_email_data($id_cliente, "asunto_email_rechazado");
        $asunto_email_invitacion = Preferencia::get_default_email_data($id_cliente, "asunto_email_invitacion");
        $asunto_email_revision = Preferencia::get_default_email_data($id_cliente, "asunto_email_revision");
        $enviar_correo_finalizacion_emisor = Preferencia::get_default_email_data(
            $id_cliente,
            "enviar_correo_finalizacion_emisor"
        );;

        $api_key = Preferencia::get_api_key($id_cliente);
        $api_key_mostrar = (empty($api_key)) ? "No ha establecido nunca su API Key" : $api_key;

        $url_respuesta = Preferencia::get_url_respuesta($id_cliente);
        $options_orden = Preferencia::get_options_default($id_cliente, "orden");
        $options_firma_emisor = Preferencia::get_options_default($id_cliente, "firma_emisor");
        $options_firma_stupendo = Preferencia::get_options_default($id_cliente, "firma_stupendo");
        $options_sello_tiempo = Preferencia::get_options_default($id_cliente, "sello_tiempo");
        $options_referencia_paginas = Preferencia::get_options_default($id_cliente, "referencia_paginas");
        $options_origen_exigido = Preferencia::get_options_default($id_cliente, "origen_exigido");
        $direcciones_email_finalizacion = Preferencia::get_direcciones_emails_finalizacion($id_cliente);

        return view(
            "doc_electronicos.preferencias.preferencias",
            array(
                "camino_logo_actual" => $camino_logo_actual,
                "camino_banner_actual" => $camino_banner_actual,
                "de_email" => $de_email,
                "de_enmascaramiento" => $de_enmascaramiento,
                "asunto_email_citacion" => $asunto_email_citacion,
                "asunto_email_asimple" => $asunto_email_asimple,
                "asunto_email_finalizado" => $asunto_email_finalizado,
                "asunto_email_rechazado" => $asunto_email_rechazado,
                "asunto_email_invitacion" => $asunto_email_invitacion,
                "asunto_email_revision" => $asunto_email_revision,
                "api_key" => $api_key,
                "api_key_mostrar" => $api_key_mostrar,
                "options_orden" => $options_orden,
                "options_firma_emisor" => $options_firma_emisor,
                "options_firma_stupendo" => $options_firma_stupendo,
                "options_sello_tiempo" => $options_sello_tiempo,
                "options_referencia_paginas" => $options_referencia_paginas,
                "options_origen_exigido" => $options_origen_exigido,
                "url_respuesta" => $url_respuesta,
                'enviar_correo_finalizacion_emisor' => $enviar_correo_finalizacion_emisor,
                'direcciones_email_finalizacion' => $direcciones_email_finalizacion
            )
        );
    }

    public function SubirLogoBanner(Request $request)
    {
        return $this->subirImagen($request);
    }

    public function EliminarLogoBannerActual(Request $request)
    {
        return $this->deleteImage($request);
    }

    public function ActualizarVistaLogoBannerActual(Request $request)
    {
        return $this->actualizarVista($request);
    }

    public function getURLEmailBanner($cliente, $establecimiento = null)
    {
        return self::getImage($cliente, 'banner', $establecimiento);
    }

    public function GuardarPreferenciasEmail(Request $request)
    {
        $Res = 0;
        $Mensaje = "";

        try {
            if ($Res >= 0) {
                $id_cliente = session()->get("id_cliente");
                $id_usuario_establece = session()->get("id_usuario");
                $default_de_email = $request->input("TDeEmail");
                Filtrar($default_de_email, "STRING", Config::get('app.mail_from_name'));
                $default_de_enmascaramiento = $request->input("TDeEnmascaramiento");
                Filtrar($default_de_enmascaramiento, "STRING", Config::get('app.mail_from_address'));
                $default_asunto_email_citacion = $request->input("TAsuntoEmailCitacion");
                Filtrar($default_asunto_email_citacion, "STRING", Preferencia::DEF_ASUNTO_EMAIL_CITACION);
                $default_asunto_email_asimple = $request->input("TAsuntoEmailASimple");
                Filtrar($default_asunto_email_asimple, "STRING", Preferencia::DEF_ASUNTO_EMAIL_ASIMPLE);
                $default_asunto_email_finalizado = $request->input("TAsuntoEmailFinalizado");
                Filtrar($default_asunto_email_finalizado, "STRING", Preferencia::DEF_ASUNTO_EMAIL_FINALIZADO);
                $default_asunto_email_rechazado = $request->input("TAsuntoEmailRechazado");
                Filtrar($default_asunto_email_rechazado, "STRING", Preferencia::DEF_ASUNTO_EMAIL_RECHAZADO);
                $default_asunto_email_invitacion = $request->input("TAsuntoEmailInvitacion");
                Filtrar($default_asunto_email_invitacion, "STRING", Preferencia::DEF_ASUNTO_EMAIL_INVITACION);
                $default_enviar_correo_finalizacion_emisor = $request->input("BEnviarCorreoFinalizacionEmisor");
                $default_enviar_correo_finalizacion_emisor = filter_var(
                    $default_enviar_correo_finalizacion_emisor,
                    FILTER_VALIDATE_BOOLEAN
                );

                $direcciones_emails_finalizacion = $request->input("direcciones_emails_finalizacion");
                Filtrar($direcciones_emails_finalizacion, "STRING", '');
            }
            if ($Res >= 0) {
                $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
                if ($preferencias) {
                    $preferencias->id_usuario_establece = $id_usuario_establece;
                    $preferencias->default_de_email = $default_de_email;
                    $preferencias->default_de_enmascaramiento = $default_de_enmascaramiento;
                    $preferencias->default_asunto_email_citacion = $default_asunto_email_citacion;
                    $preferencias->default_asunto_email_asimple = $default_asunto_email_asimple;
                    $preferencias->default_asunto_email_finalizado = $default_asunto_email_finalizado;
                    $preferencias->default_asunto_email_rechazado = $default_asunto_email_rechazado;
                    $preferencias->default_asunto_email_invitacion = $default_asunto_email_invitacion;
                    $preferencias->default_enviar_correo_finalizacion_emisor = $default_enviar_correo_finalizacion_emisor;
                    $preferencias->direcciones_emails_finalizacion = $direcciones_emails_finalizacion;
                    $preferencias->save();
                } else {
                    $data_preferencias =
                        [
                            "id_cliente" => $id_cliente,
                            "id_usuario_establece" => $id_usuario_establece,
                            "default_de_email" => $default_de_email,
                            "default_de_enmascaramiento" => $default_de_enmascaramiento,
                            "default_asunto_email_citacion" => $default_asunto_email_citacion,
                            "default_asunto_email_asimple" => $default_asunto_email_asimple,
                            "default_asunto_email_finalizado" => $default_asunto_email_finalizado,
                            "default_asunto_email_rechazado" => $default_asunto_email_rechazado,
                            "default_asunto_email_invitacion" => $default_asunto_email_invitacion,
                            "default_enviar_correo_finalizacion_emisor" => $default_enviar_correo_finalizacion_emisor,
                            "direcciones_emails_finalizacion" => $direcciones_emails_finalizacion
                        ];
                    $preferencias = Preferencia::create($data_preferencias);
                    if (!$preferencias) {
                        $Res = -7;
                        $Mensaje = "Ocurrió un error guardando las preferencias.<br/>";
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -8;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Las preferencias de correos fueron guardadas correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function GuardarPreferenciasEmision(Request $request)
    {
        $Res = 0;
        $Mensaje = "";

        try {
            if ($Res >= 0) {
                $id_cliente = session()->get("id_cliente");
                $id_usuario_establece = session()->get("id_usuario");
                $default_orden = (int)$request->input("SOrden");
                Filtrar($default_orden, "INTEGER", Preferencia::DEF_ORDEN);
                $default_firma_emisor = (int)$request->input("SFirmaEmisor");
                Filtrar($default_firma_emisor, "INTEGER", Preferencia::DEF_FIRMA_EMISOR);

                $default_sello_tiempo = 2;

                $default_referencia_paginas = (int)$request->input("SReferenciaPaginas");
                Filtrar($default_referencia_paginas, "INTEGER", Preferencia::DEF_REFERENCIA_PAGINAS);
                $default_origen_exigido = (int)$request->input("SOrigenExigido");
                Filtrar($default_origen_exigido, "INTEGER", Preferencia::DEF_ORIGEN_EXIGIDO);
            }
            if ($Res >= 0) {
                if (!in_array($default_orden, array(1, 2))) {
                    $Res = -1;
                    $Mensaje = "Datos incorrectos";
                } else {
                    if (!in_array($default_firma_emisor, array(0, 1, 2))) {
                        $Res = -2;
                        $Mensaje = "Datos incorectos";
                    } else {
                                if (!in_array($default_referencia_paginas, array(0, 1))) {
                                    $Res = -5;
                                    $Mensaje = "Datos incorectos";
                                } else {
                                    if (!in_array($default_origen_exigido, array(0, 1, 2))) {
                                        $Res = -6;
                                        $Mensaje = "Datos incorectos";
                                    }
                                }
                    }
                }
            }
            if ($Res >= 0) {
                $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
                if ($preferencias) {
                    $preferencias->id_usuario_establece = $id_usuario_establece;
                    $preferencias->default_orden = $default_orden;
                    $preferencias->default_firma_emisor = $default_firma_emisor;
                    $preferencias->default_sello_tiempo = 2;
                    $preferencias->default_referencia_paginas = $default_referencia_paginas;
                    $preferencias->default_origen_exigido = $default_origen_exigido;
                    $preferencias->save();
                } else {
                    $data_preferencias =
                        [
                            "id_cliente" => $id_cliente,
                            "id_usuario_establece" => $id_usuario_establece,
                            "default_orden" => $default_orden,
                            "default_firma_emisor" => $default_firma_emisor,
                            "default_sello_tiempo" => $default_sello_tiempo,
                            "default_referencia_paginas" => $default_referencia_paginas,
                            "default_origen_exigido" => $default_origen_exigido
                        ];
                    $preferencias = Preferencia::create($data_preferencias);
                    if (!$preferencias) {
                        $Res = -7;
                        $Mensaje = "Ocurrió un error guardando las preferencias.<br/>";
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -8;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Las preferencias de emisión fueron guardadas correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function GuardarPreferenciasAPI(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $id_cliente = session()->get("id_cliente");
            $id_usuario_establece = session()->get("id_usuario");
            $url_respuesta = $request->input("Valor_1");
            Filtrar($url_respuesta, "URL", Preferencia::DEF_URL_RESPUESTA);

            if ($Res >= 0) {
                if (strpos($url_respuesta, 'http') !== 0 && strpos($url_respuesta, 'HTTP') !== 0) {
                    $Res = -1;
                    $Mensaje = "Datos incorrectos";
                }
            }
            if ($Res >= 0) {
                $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
                if ($preferencias) {
                    $preferencias->url_respuesta = $url_respuesta;
                    $preferencias->save();
                } else {
                    $data_preferencias =
                        [
                            "id_cliente" => $id_cliente,
                            "id_usuario_establece" => $id_usuario_establece,
                            "url_repsuesta" => $url_respuesta
                        ];
                    $preferencias = Preferencia::create($data_preferencias);
                    if (!$preferencias) {
                        $Res = -7;
                        $Mensaje = "Ocurrió un error guardando las preferencias.<br/>";
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -8;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Las preferencias de API fueron guardadas correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function GenerarAPIKey()
    {
        $Res = 0;
        $Mensaje = "";
        $api_key = Preferencia::DEF_API_KEY;
        try {
            if ($Res >= 0) {
                $id_cliente = session()->get("id_cliente");
                $id_usuario_establece = session()->get("id_usuario");
                $fc = new FirmasController();
                $api_key = $fc->ObtenerCodigoNuevo(32);
            }
            if ($Res >= 0) {
                $preferencias = Preferencia::where("id_cliente", $id_cliente)->first();
                if ($preferencias) {
                    $preferencias->api_key = $api_key;
                    $preferencias->save();
                } else {
                    $data_preferencias =
                        [
                            "id_cliente" => $id_cliente,
                            "id_usuario_establece" => $id_usuario_establece,
                            "api_key" => $api_key,
                            "default_orden" => Preferencia::DEF_ORDEN,
                            "default_firma_emisor" => Preferencia::DEF_FIRMA_EMISOR,
                            "default_firma_stupendo" => Preferencia::DEF_FIRMA_STUPENDO,
                            "default_sello_tiempo" => Preferencia::DEF_SELLO_TIEMPO,
                            "default_referencia_paginas" => Preferencia::DEF_REFERENCIA_PAGINAS,
                            "default_origen_exigido" => Preferencia::DEF_ORIGEN_EXIGIDO
                        ];
                    $preferencias = Preferencia::create($data_preferencias);
                    if (!$preferencias) {
                        $Res = -1;
                        $Mensaje = "Ocurrió un error guardando la API Key.<br/>";
                    }
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "La API Key fue establecida correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "api_key" => $api_key), 200);
    }
}