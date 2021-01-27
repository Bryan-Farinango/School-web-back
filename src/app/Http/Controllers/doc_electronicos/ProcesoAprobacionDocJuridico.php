<?php

namespace App\Http\Controllers\doc_electronicos;

use App;
use App\Cliente;
use App\doc_electronicos\Firma;
use App\doc_electronicos\FirmaPorValidar;
use App\doc_electronicos\Proceso;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Poliza\AutoLoginController;
use App\Http\Controllers\Poliza\FirmaController;
use App\Usuarios;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use MongoDB\BSON\UTCDateTime;

class ProcesoAprobacionDocJuridico extends Controller
{

    public function AceptarDocJuri($token)
    {
        $Res = 0;
        $Mensaje = "";
        $id_cliente = "";
        try {
            if ($Res >= 0) {
                $arr_res = $this->ValidarTokenDocJuri($token);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
                $id_cliente = $arr_res["id_cliente"];
                $identificacion = $arr_res["identificacion"];
            }
            if ($Res >= 0) {
                $data = array(
                    "HiddenActor" => "PERSONA JURIDICA",
                    "HiddenIdCliente" => $arr_res["id_cliente"],
                    "HiddenIdentificacion" => $arr_res["identificacion"],
                );

                $new_request = new Request();
                $new_request->merge($data);
                $json_resultado_aceptar = $this->CambioEstadoDocJuri($new_request);
                $respuesta = $json_resultado_aceptar->getData();
                $Mensaje = $respuesta->Mensaje;
                $id_estado = $respuesta->id_estado;
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return $this->MostrarResultadoAprobar(1, $Res, $Mensaje, $identificacion, $id_estado);
    }


    private function ValidarTokenDocJuri($token)
    {
        $Res = 0;
        $Mensaje = "";
        $id_cliente = null;
        $identificacion = null;

        try {
            if ($Res >= 0) {
                $fc = new FirmasController();
                $arr_key_vector = $fc->getArrayKeyVector();
                $cadena = $fc->Desencriptar($arr_key_vector["llave"], $arr_key_vector["vector"], $token);
                $arr = explode("_", $cadena);


                if (count($arr) != 2) {
                    $Res = -1;
                    $Mensaje = "Token incorrecto";
                } else {
                    $id_cliente = $arr[0];
                    $identificacion = $arr[1];
                }
            }
        } catch (Exception $e) {
            $Res = -8;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Token correcto.";
        }
        return array(
            "Res" => $Res,
            "Mensaje" => $Mensaje,
            "id_cliente" => $id_cliente,
            "identificacion" => $identificacion
        );
    }

    public function CambioEstadoDocJuri(Request $request)
    {
        $Res = 0;
        $id_estado = 0;
        $Mensaje = "";

        try {
            $actor = $request->input("HiddenActor");
            Filtrar($actor, "STRING");

            $id_proceso = DesencriptarId($request->input("HiddenIdCliente"));
            Filtrar($id_proceso, "STRING");

            $identificacion = $request->input("HiddenIdentificacion");
            Filtrar($identificacion, "STRING");

            $firma = FirmaPorValidar::where('identificacion', $identificacion)->orderBy('created_at', 'desc')->first();

            if (!$firma) {
                $Res = -1;
                $Mensaje = "Firma inexistente";
            } else {
                $id_estado = $firma->id_estado;
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "Los documentos de la firma fueron revisados, ¿Desea aprobarlos y notificar al cliente.?";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "id_estado" => $id_estado), 200);
    }

    public function MostrarResultadoAprobar($accion, $Res, $Mensaje, $identificacion, $id_estado)
    {
        if ($identificacion) {
            $cliente = Cliente::where('identificacion', $identificacion)->first();
            if ($cliente) {
                if ($id_estado == 1) {
                    $text_aceptacion = "Estas a punto de validar la documentación de una persona jurídica solicitante de una firma electrónica.";
                    $text_aceptacion_dos = "Esto implica que la documentación enviada ha sido correctamente validada.";
                    $icono = 'banner_aprobacion_uno.png';
                } else {
                    if ($id_estado == 2) {
                        $solicitud = "aprobados.";
                    } elseif ($id_estado == 3) {
                        $solicitud = "rechazados.";
                    } else {
                        $solicitud = "";
                    }

                    $text_aceptacion = "La solicitud que estás intentando aprobar o rechazar, ya fue atendida previamente por un operador.";
                    $text_aceptacion_dos = "Los documentos enviados por el cliente fueron " . " " . $solicitud;
                    $icono = 'banner_solicitud_atendida.png';
                }

                $logo_stupendo = "logo_stupendo_large_validar.png";
            }
        }


        $dominio_personalizado = "";

        return view(
            "doc_electronicos.firma_juridica.accion_aceptacion_doc_firma_juri",
            array(
                "icono" => $icono,
                "mensaje" => $Mensaje,
                "identificacion" => $identificacion,
                "text_aceptacion" => $text_aceptacion,
                "text_aceptacion_dos" => $text_aceptacion_dos,
                "logo_stupendo" => $logo_stupendo,
                "dominio_personalizado" => $dominio_personalizado,
                "id_estado" => $id_estado,
            )
        );
    }


    public function RechazarDocJuri($token)
    {
        $Res = 0;
        $Mensaje = "";
        $id_cliente = "";
        $id_estado = "";
        try {
            if ($Res >= 0) {
                $arr_res = $this->ValidarTokenDocJuri($token);
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
                $id_cliente = $arr_res["id_cliente"];
                $identificacion = $arr_res["identificacion"];
            }
            if ($Res >= 0) {
                $data = array(
                    "HiddenActor" => "PERSONA JURIDICA",
                    "HiddenIdCliente" => $arr_res["id_cliente"],
                    "HiddenIdentificacion" => $arr_res["identificacion"],
                );

                $new_request = new Request();
                $new_request->merge($data);
                $json_resultado_aceptar = $this->CambioEstadoRechazoDocJuri($new_request);
                $respuesta = $json_resultado_aceptar->getData();
                $Mensaje = $respuesta->Mensaje;
                $id_estado = $respuesta->id_estado;
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        return $this->MostrarResultadoRechazo(1, $Res, $Mensaje, $identificacion, $id_estado);
    }


    public function CambioEstadoRechazoDocJuri(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $actor = $request->input("HiddenActor");
            Filtrar($actor, "STRING");

            $id_proceso = DesencriptarId($request->input("HiddenIdCliente"));
            Filtrar($id_proceso, "STRING");

            $identificacion = $request->input("HiddenIdentificacion");
            Filtrar($identificacion, "STRING");

            $firma = FirmaPorValidar::where('identificacion', $identificacion)->orderBy('created_at', 'desc')->first();


            if (!$firma) {
                $Res = -1;
                $Mensaje = "Firma inexistente";
            } else {
                $id_estado = $firma->id_estado;
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "Los documentos de la firma fueron rechazados para crear una firma jurídica.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "id_estado" => $id_estado), 200);
    }

    public function MostrarResultadoRechazo($accion, $Res, $Mensaje, $identificacion, $id_estado)
    {
        $text_rechazo = "";
        $text_rechazo_dos = "";
        $icono = "";

        if ($identificacion) {
            $cliente = Cliente::where('identificacion', $identificacion)->first();
            if ($cliente) {
                if ($id_estado == 1) {
                    $text_rechazo = "Se rechazan los documetos adjuntos para crear la firma del cliente, " . " " . $cliente->nombre_identificacion . " con identificación  n°  " . " " . $identificacion;
                    $icono = 'banner_rechazo_uno.png';
                    $text_rechazo_dos = "";
                } else {
                    if ($id_estado == 2) {
                        $solicitud = "aprobados.";
                    } elseif ($id_estado == 3) {
                        $solicitud = "rechazados.";
                    } else {
                        $solicitud = "";
                    }
                    $text_rechazo = "La solicitud que estás intentando aprobar o rechazar, ya fue atendida previamente por un operador.";
                    $text_rechazo_dos = "";
                    $icono = 'banner_solicitud_atendida.png';
                }
            }
        }

        $logo_stupendo = "logo_stupendo_large_validar.png";


        $dominio_personalizado = "";

        return view(
            "doc_electronicos.firma_juridica.accion_rechazar_doc_firma_juri",
            array(
                "icono" => $icono,
                "mensaje" => $Mensaje,
                "identificacion" => $identificacion,
                "text_rechazo" => $text_rechazo,
                "text_rechazo_dos" => $text_rechazo_dos,
                "logo_stupendo" => $logo_stupendo,
                "dominio_personalizado" => $dominio_personalizado,
                "id_estado" => $id_estado
            )
        );
    }


    public function AprobarDocJuri($identificacion)
    {
        $Res = 0;
        $Mensaje = "";
        $text_aceptacion = "";
        $text_aceptacion_dos = "";
        $logo_stupendo = "";
        $icono = "";

        try {
            Log::info("Inicia proceso de aprobación");
            $firma = FirmaPorValidar::where('identificacion', $identificacion)->orderBy('created_at', 'desc')->first();

            $proceso = Proceso::where('firmantes.identificacion', $identificacion)
                ->where('id_estado_actual_proceso', 1)
                ->orderBy('created_at', 'desc')->first();

            if (!$firma) {
                $Res = -1;
                $Mensaje = "Firma inexistente";
            } else {
                $id_estado_inicial = $firma->id_estado;


                if ($id_estado_inicial == 1) {
                    $id_estado = 2;
                    $dataupdate = [
                        "id_estado" => $id_estado
                    ];
                    $firma_update = $firma->update($dataupdate);

                    if ($Res >= 0) {
                        $arr_firma["id_cliente"] = $firma->id_cliente;
                        $arr_firma["identificacion_cliente"] = $firma->identificacion_cliente;
                        $arr_firma["identificacion"] = $firma->identificacion;
                        $arr_firma["version"] = $firma->version;
                        $arr_firma["origen"] = $firma->origen;
                        $arr_firma["id_estado"] = 1;//Vigente
                        $arr_firma["figura_legal"] = $firma->figura_legal;
                        $arr_firma["telefono"] = $firma->telefono;
                        $arr_firma["camino_pfx"] = $firma->camino_pfx;
                        $arr_firma["camino_rl"] = $firma->camino_rl;
                        $arr_firma["camino_ruc"] = $firma->camino_ruc;
                        $arr_firma["camino_poder"] = $firma->camino_poder;
                        $arr_firma["password"] = $firma->password;
                        $arr_firma["id_usuario_crea"] = $firma->id_usuario_crea;
                        $arr_firma["momento_activada"] = $firma->momento_activada;
                        $arr_firma["ip"] = $firma->ip;
                        $arr_firma["sistema_operativo"] = $firma->sistema_operativo;
                        $arr_firma["navegador"] = $firma->navegador;
                        $arr_firma["agente"] = $firma->agente;
                        $arr_firma["public_key"] = $firma->public_key;
                        $arr_firma["profundidad"] = $firma->profundidad;
                        $arr_firma["desde"] = $firma->desde;
                        $arr_firma["hasta"] = $firma->hasta;
                        $arr_firma["email"] = $firma->email;
                        $arr_firma["nombre"] = $firma->nombre;
                        $arr_firma["serial_number"] = $firma->serial_number;

                        $firma = Firma::create($arr_firma);

                        if (!$firma) {
                            $Res = -6;
                            $Mensaje = "Ocurrió un error guardando la firma";
                        } else {
                            $Res = 1;
                        }
                    }


                    $fechaCarbon = Carbon::now();
                    $hora = $fechaCarbon->format('H:i');
                    $fecha = date("d-m-Y", strtotime($fechaCarbon));

                    $text_aceptacion = "Se ha aprobado la solicitud de la firma electrónica de la empresa " . $firma->nombre . ", con fecha  " . $fecha . " a las  " . " " . $hora . ".";
                    $text_aceptacion_dos = "Se han notificado a las partes pertinentes para seguir con el proceso.";
                    $logo_stupendo = "logo_stupendo_large_validar.png";


                    $usuarioN1 = $firma->email;

                    Log::info("Entra al envio de mail");

                    if ($usuarioN1) {
                        $Res = 0;
                        $Mensaje = "";


                        $compania = $firma->nombre;
                        $identificacion = $firma->identificacion;

                        $camino_ruc = $firma["camino_ruc"];
                        $camino_rl = $firma["camino_rl"];
                        $camino_poder = $firma["camino_poder"];

                        $documentos1 = array(
                            'id_documento' => 1,
                            'titulo' => "RUC",
                            'camino_original' => $camino_ruc
                        );
                        $documentos2 = array(
                            'id_documento' => 2,
                            'titulo' => "Nombramiento",
                            'camino_original' => $camino_rl
                        );
                        $documentos3 = array(
                            'id_documento' => 3,
                            'titulo' => "Poder",
                            'camino_original' => $camino_poder
                        );

                        if ($camino_poder != null) {
                            $documentos = array($documentos1, $documentos2, $documentos3);
                        } else {
                            $documentos = array($documentos1, $documentos2);
                        }


                        $asunto = "Aviso de aprobación de documentos para crear firma electrónica";
                        $lista_documentos = "";
                        $titulos_documentos = array();

                        $index = 0;
                        foreach ($documentos as $documento) {
                            $index++;
                            $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
                            $titulos_documentos[] = $documento["titulo"];
                        }

                        $de = Config::get('app.mail_from_name');
                        $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
                        $lista_documentos = "";
                        $index = 0;
                        $arr_adjuntos = array();

                        $nombreEnmas = "";
                        $correoEnmas = "";

                        if ($correoEnmas != "") {
                            $de = $nombreEnmas;
                        }

                        foreach ($documentos as $documento) {
                            $index++;
                            $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
                            array_push($arr_adjuntos, storage_path($documento["camino_original"]));
                        }

                        $lista_participantes = "";

                        $fc = new FirmasController();
                        $arr_key_vector = $fc->getArrayKeyVector();

                        $token = $fc->Encriptar(
                            $arr_key_vector["llave"],
                            $arr_key_vector["vector"],
                            $firma["_id"] . "_" . $firma["identificacion"]
                        );


                        $cliente_receptor = Cliente::where('identificacion', $identificacion)->first();
                        if ($cliente_receptor) {
                            $id_cliente = $cliente_receptor->_id;
                        } else {
                            $id_cliente = "";
                        }


                        $inicio_vigencia_enlace = new UTCDateTime(Carbon::now()->getTimestamp() * 1000);

                        if ($proceso) {
                            $id_proceso = $proceso->_id;
                        } else {
                            $id_proceso = "";
                        }


                        if ($firma->email) {
                            $mail_auto = $firma->email;
                            $usuarios_receptor = Usuarios::where('email', $mail_auto)->first();

                            $id_usuario = $usuarios_receptor->_id;
                            if ($id_usuario) {
                                $fcOcodigo = new FirmaController();
                                $password_autologin = $fcOcodigo->ObtenerCodigoNuevo();
                            }
                        }

                        $alc = new AutoLoginController();
                        $dataUrl = array(
                            'id_cliente' => $id_cliente,
                            'id_proceso' => $id_proceso,
                            'pass' => $password_autologin,
                            'email' => $mail_auto,
                            'inicio_vigencia_enlace' => $inicio_vigencia_enlace
                        );
                        $dataUrl_encypt = $alc->encriptDatosAutologin($dataUrl);
                        $enlace = (Config::get('app.url') . '/docs_electronicos/autologin/' . $dataUrl_encypt);

                        $botones_accion =
                            '<td>
                               <a target="_blank" href="' . $enlace . '  ">
                                <img  align="center" alt="Ingresar y firmar"  src="' . URL::to('/img/doc_electronicos/ingresar_firmar_aprobado.png') . '" />
                               </a>
                            </td>';

                        $banner_url = Config::get('app.url') . '/email/img/header2.png';


                        $motivo = "Aprobación";
                        $motivo_emisor = "aprobada";
                        $emisor_nombre = "";

                        if ($proceso) {
                            $id_cliente_emisor = $proceso->id_cliente_emisor;
                            if ($id_cliente_emisor) {
                                $cliente_emisor = Cliente::where('_id', $id_cliente_emisor)->first();
                                if ($cliente_emisor) {
                                    $emisor_nombre = $cliente_emisor->nombre_identificacion;
                                }
                            }
                        }


                        $arretiquetas = array(
                            "banner_url",
                            "compania",
                            "lista_documentos",
                            "lista_participantes",
                            "identificacion",
                            "enlace",
                            "botones_accion",
                            'titulos_documentos',
                            'fecha',
                            'hora',
                            'motivo',
                            'motivo_emisor',
                            'emisor',
                        );
                        $arrvalores = array(
                            $banner_url,
                            $compania,
                            $lista_documentos,
                            $lista_participantes,
                            $identificacion,
                            $enlace,
                            $botones_accion,
                            $titulos_documentos,
                            $fecha,
                            $hora,
                            $motivo,
                            $motivo_emisor,
                            $emisor_nombre,
                        );


                        $data_mailgun = array();
                        $data_mailgun["X-Mailgun-Tag"] = "monitoreo_proceso_signatarios_de";
                        $data_mailgun["X-Mailgun-Track"] = "yes";
                        $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $firma["_id"]]);

                        $mail_view = 'emails.doc_electronicos.aviso_aprobacion_documentos_juridico';


                        $nombreN1 = $compania;

                        $arr_res = EnviarCorreo(
                            $mail_view,
                            $de,
                            $asunto,
                            $usuarioN1,
                            $nombreN1,
                            $arretiquetas,
                            $arrvalores,
                            null,
                            $data_mailgun,
                            $nombreEnmas,
                            $correoEnmas
                        );

                        if ($proceso) {
                            $id_cliente_emisor = $proceso->id_cliente_emisor;
                            if ($id_cliente_emisor) {
                                if($cliente_emisor) {
                                    $nombreN1 = $cliente_emisor->nombre_identificacion;
                                    $usuarioN1 = $cliente_emisor->email;

                                    $mail_view = 'emails.doc_electronicos.aviso_solicitud_documentos_juridico_emisor';

                                    $arr_res_emi = EnviarCorreo(
                                        $mail_view,
                                        $de,
                                        $asunto,
                                        $usuarioN1,
                                        $nombreN1,
                                        $arretiquetas,
                                        $arrvalores,
                                        null,
                                        $data_mailgun,
                                        $nombreEnmas,
                                        $correoEnmas
                                    );

                                    $ResEmi = $arr_res_emi[0];
                                    $Mensaje = $arr_res_emi[1];
                                    if ($ResEmi > 0) {
                                        $MensajeEmi = "de notificación al emisor  " . $emisor_nombre . " " . "sobre aprobación de" . " " . $compania;
                                        Log::info("se envio el correo " . " " . $MensajeEmi);
                                    }
                                } else {Log::info("El emisor no existe, no se notifico del rechazo"); }
                            }
                        }
                        $icono = 'banner_aprobacion_dos.png';

                        $Res = $arr_res[0];
                        $Mensaje = $arr_res[1];
                        if ($Res > 0) {
                            $Mensaje = "Se aprobo la creación de la firma  " . $compania;
                            Log::info("se envio el correo de aprobación a " . " " . $compania);
                        }
                    }
                    $icono = 'banner_aprobacion_dos.png';
                } else {
                    $text_aceptacion = "La solicitud que estás intentando aprobar o rechazar, ya fue atendida previamente por un operador.";
                    $text_aceptacion_dos = "";
                    $logo_stupendo = "logo_stupendo_large_validar.png";
                    $icono = 'banner_solicitud_atendida.png';
                    $Mensaje = "Ya esta solicitud de firma fue atendida anteriormente";
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "Los documentos fueron aprobados para crear una firma jurídica.";
        }


        $dominio_personalizado = "";

        return view(
            "doc_electronicos.firma_juridica.fin_aprobar_doc_firma_juri",
            array(
                "icono" => $icono,
                "mensaje" => $Mensaje,
                "identificacion" => $identificacion,
                "text_aceptacion" => $text_aceptacion,
                "text_aceptacion_dos" => $text_aceptacion_dos,
                "logo_stupendo" => $logo_stupendo,
                "dominio_personalizado" => $dominio_personalizado,
                "id_estado" => $id_estado_inicial
            )
        );
    }

    public function RechazarDefDocJuri(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        $text_de_rechazo = "";
        $text_de_rechazo_dos = "";
        $logo_stupendo = "";
        $text_de_rechazo = "";
        $motivo = "";
        $icono = "";

        $identificacion = $request->input('txt_identificacion');
        $Rechazo = $request->input('Rechazo');

        try {
            Log::info("Inicia proceso de rechazo");
            $firma = FirmaPorValidar::where('identificacion', $identificacion)->orderBy('created_at', 'desc')->first();

            $proceso = Proceso::where('firmantes.identificacion', $identificacion)->where(
                'id_estado_actual_proceso',
                1
            )->orderBy('created_at', 'desc')->first();


            if (!$firma) {
                $Res = -1;
                $Mensaje = "Firma inexistente";
            } else {
                $id_estado_inicial = $firma->id_estado;

                if ($id_estado_inicial == 1)
                {
                    $id_estado = 3;
                    $dataupdate = [
                        "id_estado" => $id_estado,
                        "motivo_rechazo" => $Rechazo
                    ];
                    $firma_update = $firma->update($dataupdate);

                    $fechaCarbon = Carbon::now();
                    $hora = $fechaCarbon->format('H:i');
                    $fecha = date("d-m-Y", strtotime($fechaCarbon));


                    $text_de_rechazo = "Se ha rechazado la solicitud de la firma electrónica de la empresa " . $firma->nombre . ", con fecha  " . $fecha . " a las  " . " " . $hora . ".";
                    $text_de_rechazo_dos = "Se han notificado a las partes pertinentes sobre el motivo del rechazo.";
                    $logo_stupendo = "logo_stupendo_large_validar.png";

                    $usuarioN1 = $firma->email;

                    Log::info("Entran al envio de mail");

                    if ($usuarioN1) {
                        $Res = 0;
                        $Mensaje = "";


                        $compania = $firma->nombre;
                        $identificacion = $firma->identificacion;

                        $camino_ruc = $firma["camino_ruc"];
                        $camino_rl = $firma["camino_rl"];
                        $camino_poder = $firma["camino_poder"];

                        $documentos1 = array(
                            'id_documento' => 1,
                            'titulo' => "RUC",
                            'camino_original' => $camino_ruc
                        );
                        $documentos2 = array(
                            'id_documento' => 2,
                            'titulo' => "Nombramiento",
                            'camino_original' => $camino_rl
                        );
                        $documentos3 = array(
                            'id_documento' => 3,
                            'titulo' => "Poder",
                            'camino_original' => $camino_poder
                        );

                        if ($camino_poder != null) {
                            $documentos = array($documentos1, $documentos2, $documentos3);
                        } else {
                            $documentos = array($documentos1, $documentos2);
                        }


                        $asunto = "Aviso de rechazo de documentos para crear firma electrónica";
                        $lista_documentos = "";
                        $titulos_documentos = array();
                        $icono = 'banner_rechazo_dos.png';

                        $index = 0;
                        foreach ($documentos as $documento) {
                            $index++;
                            $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
                            $titulos_documentos[] = $documento["titulo"];
                        }


                        $de = Config::get('app.mail_from_name');
                        $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
                        $lista_documentos = "";
                        $index = 0;
                        $arr_adjuntos = array();

                        $nombreEnmas = "";
                        $correoEnmas = "";

                        if ($correoEnmas != "") {
                            $de = $nombreEnmas;
                        }

                        foreach ($documentos as $documento) {
                            $index++;
                            $lista_documentos .= $index . " - " . $documento["titulo"] . "<br/>";
                            array_push($arr_adjuntos, storage_path($documento["camino_original"]));
                        }

                        $lista_participantes = "";

                        $botones_accion = '';

                        $fc = new FirmasController();
                        $arr_key_vector = $fc->getArrayKeyVector();

                        $token = $fc->Encriptar(
                            $arr_key_vector["llave"],
                            $arr_key_vector["vector"],
                            $firma["_id"] . "_" . $firma["identificacion"]
                        );

                        $cliente_receptor = Cliente::where('identificacion', $identificacion)->first();
                        if ($cliente_receptor) {
                            $id_cliente = $cliente_receptor->_id;
                        } else {
                            $id_cliente = "";
                        }


                        $inicio_vigencia_enlace = new UTCDateTime(Carbon::now()->getTimestamp() * 1000);

                        if ($proceso) {
                            $id_proceso = $proceso->_id;
                        } else {
                            $id_proceso = "";
                        }


                        if ($firma->email) {
                            $mail_auto = $firma->email;
                            $usuarios_receptor = Usuarios::where('email', $mail_auto)->first();

                            $id_usuario = $usuarios_receptor->_id;
                            if ($id_usuario) {
                                $fcOcodigo = new FirmaController();
                                $password_autologin = $fcOcodigo->ObtenerCodigoNuevo();
                            }
                        }

                        $alc = new AutoLoginController();
                        $dataUrl = array(
                            'id_cliente' => $id_cliente,
                            'id_proceso' => $id_proceso,
                            'pass' => $password_autologin,
                            'email' => $mail_auto,
                            'inicio_vigencia_enlace' => $inicio_vigencia_enlace
                        );
                        $dataUrl_encypt = $alc->encriptDatosAutologin($dataUrl);
                        $enlace = (Config::get('app.url') . '/docs_electronicos/autologin/' . $dataUrl_encypt);

                        $botones_accion =
                        '<td>
                           <a target="_blank" href="' . $enlace . '  ">
                            <img img  align="center" alt="Solicitar firma electrónica"  src="' . URL::to('/img/doc_electronicos/solicitar_firma_rechazado.png') . '" />
                           </a>
                        </td>';

                        $banner_url = Config::get('app.url') . '/email/img/header2.png';

                        if ($Rechazo == "1") {
                            $motivo = "Documentos inconsistentes";
                        } elseif ($Rechazo == "2") {
                            $motivo = "Uno o varios documentos han expirado";
                        } elseif ($Rechazo == "3") {
                            $motivo = "La persona jurídica no existe";
                        } elseif ($Rechazo == "4") {
                            $motivo = "Otro motivo";
                        } else {
                            $motivo = "";
                        }

                        $motivo_emisor = "rechazada";

                        $emisor_nombre = "";

                        if ($proceso) {
                            $id_cliente_emisor = $proceso->id_cliente_emisor;
                            if ($id_cliente_emisor) {
                                $cliente_emisor = Cliente::where('_id', $id_cliente_emisor)->first();
                                if ($cliente_emisor) {
                                    $emisor_nombre = $cliente_emisor->nombre_identificacion;
                                }
                            }
                        }


                        $arretiquetas = array(
                            "banner_url",
                            "compania",
                            "lista_documentos",
                            "lista_participantes",
                            "identificacion",
                            "enlace",
                            "botones_accion",
                            'titulos_documentos',
                            'fecha',
                            'hora',
                            'motivo',
                            'motivo_emisor',
                            'emisor'
                        );
                        $arrvalores = array(
                            $banner_url,
                            $compania,
                            $lista_documentos,
                            $lista_participantes,
                            $identificacion,
                            $enlace,
                            $botones_accion,
                            $titulos_documentos,
                            $fecha,
                            $hora,
                            $motivo,
                            $motivo_emisor,
                            $emisor_nombre
                        );


                        $data_mailgun = array();
                        $data_mailgun["X-Mailgun-Tag"] = "monitoreo_proceso_signatarios_de";
                        $data_mailgun["X-Mailgun-Track"] = "yes";
                        $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $firma["_id"]]);

                        $mail_view = 'emails.doc_electronicos.aviso_rechazo_documentos_juridico';

                        $nombreN1 = $compania;

                        $arr_res = EnviarCorreo(
                            $mail_view,
                            $de,
                            $asunto,
                            $usuarioN1,
                            $nombreN1,
                            $arretiquetas,
                            $arrvalores,
                            $arr_adjuntos,
                            $data_mailgun,
                            $nombreEnmas,
                            $correoEnmas
                        );

                        if ($proceso)
                        {
                                $id_cliente_emisor = $proceso->id_cliente_emisor;
                                if ($id_cliente_emisor) {
                                    if($cliente_emisor) {
                                        $nombreN1 = $cliente_emisor->nombre_identificacion;
                                        $usuarioN1 = $cliente_emisor->email;

                                        $mail_view = 'emails.doc_electronicos.aviso_solicitud_documentos_juridico_emisor';

                                        $arr_res_emi = EnviarCorreo(
                                            $mail_view,
                                            $de,
                                            $asunto,
                                            $usuarioN1,
                                            $nombreN1,
                                            $arretiquetas,
                                            $arrvalores,
                                            null,
                                            $data_mailgun,
                                            $nombreEnmas,
                                            $correoEnmas
                                        );

                                        $ResEmi = $arr_res_emi[0];
                                        $Mensaje = $arr_res_emi[1];
                                        if ($ResEmi > 0) {
                                            $MensajeEmi = "de notificación al emisor  " . $emisor_nombre . " " . "sobre el rechazo de" . " " . $compania;
                                            Log::info("se envio el correo " . " " . $MensajeEmi);
                                        }
                                    }
                                }
                        }
                    }
                        $icono = 'banner_rechazo_dos.png';
                        $Res = $arr_res[0];
                        $Mensaje = $arr_res[1];
                        if ($Res > 0)
                            {
                                $Mensaje = "de rechazo la creación de la firma  de " . $compania . "por  los siguientes motivos: " . $Rechazo;
                                Log::info("se envio el correo " . $Mensaje);
                            }
                } else {
                    $text_de_rechazo = "La solicitud que estás intentando aprobar o rechazar, ya fue atendida previamente por un operador.";
                    $text_de_rechazo_dos = "";
                    $logo_stupendo = "logo_stupendo_large_validar.png";
                    $icono = 'banner_solicitud_atendida.png';
                    $Mensaje = "Ya esta solicitud de firma fue atendida anteriormente";
                }

            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
            Log::info("Error de catch " . $Mensaje);
        }
        if ($Res >= 0) {
            $Mensaje = "Se ha rechazado los documentos para crear la firma electrónica de la persona jurídica.";
        }


        $dominio_personalizado = "";

        return view(
            "doc_electronicos.firma_juridica.fin_rechazar_doc_firma_juri",
            array(
                "icono" => $icono,
                "mensaje" => $Mensaje,
                "identificacion" => $identificacion,
                "text_de_rechazo" => $text_de_rechazo,
                "text_de_rechazo_dos" => $text_de_rechazo_dos,
                "logo_stupendo" => $logo_stupendo,
                "dominio_personalizado" => $dominio_personalizado,
                "motivo" => $motivo,
                "id_estado" => $id_estado_inicial
            )
        );
    }

}