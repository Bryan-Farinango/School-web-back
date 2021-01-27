<?php

namespace App\Http\Controllers\doc_electronicos;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\doc_electronicos\Proceso;
use Exception;
use File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\doc_electronicos\Firma;
use App\doc_electronicos\FirmaPorValidar;
use Illuminate\Support\Facades\URL;


class ServicioTecnicoFirmaJuridica extends Controller
{

    public function EnviarSolicitudFirmaJUridico($porenviar)
    {
        $identificacion = $porenviar['identificacion'];
        $nombre = $porenviar['nombre'];
        $email = $porenviar['email'];
        $id_estado = $porenviar['id_estado'];
        $nro_notificaciones = 1;
        $hora_valida = true;
        $Mensaje ="Primer recorrido";

        Log::info("Inicio de Reenvos " . $Mensaje);

        switch ($id_estado) {
        case 1:

            if ($identificacion) {
                $firma = FirmaPorValidar::where('identificacion', $identificacion)->orderBy(
                    'created_at',
                    'desc'
                )->first();


            if ($firma) {

                if (!isset($firma->nro_notificaciones)) {
                    $nro_notificaciones = 1;
                } else {
                    $nro_notificaciones = $firma->nro_notificaciones + 1;
                }

                $date = Carbon::now();

                $hora_actual = $date->format('H');
                $hora_min = $date->format('H:i');

                $tiempo_parametrizado = Config::get('app.minutos_reenvio_soporte');

                if (!isset($firma->hora_notificacion)) {
                    $registro_hora_min = $date->format('H:i');
                } else {
                    $registro_hora_min = $firma->hora_notificacion;
                }

                $separar[1]=explode(':',$hora_min);
                $separar[2]=explode(':',$registro_hora_min);

                $total_minutos_trasncurridos[1] = ($separar[1][0]*60)+$separar[1][1];
                $total_minutos_trasncurridos[2] = ($separar[2][0]*60)+$separar[2][1];

                $total_minutos_trasncurridos = $total_minutos_trasncurridos[1]-$total_minutos_trasncurridos[2];

                if($total_minutos_trasncurridos >= $tiempo_parametrizado)
                {
                    $hora_valida = true;
                } else {
                    $hora_valida = false;
                }

                Log::info("Paso querys " . $hora_min);

               if ($hora_valida == true)
               {

                  Log::info("Entro a modificar " . $hora_min);

                  $dataupdate = [
                    'nro_notificaciones' => $nro_notificaciones,
                    'hora_notificacion'  => $hora_min,
                  ];
                  $dataupdatexfirma = $firma->update($dataupdate);

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


                    $_id = $firma->_id;
                    $identificacion = $firma->identificacion;
                    $id_cliente = $firma->id_cliente;
                    $compania = $firma->nombre;
                    $figura_legal = $firma->figura_legal;
                    $camino_rl = $firma->camino_rl;
                    $camino_ruc = $firma->camino_ruc;
                    $camino_poder = $firma->camino_poder;
                    $banner_url = Config::get('app.url') . '/email/img/banner_validacion_doc.jpg';


                    $asunto = "Reenvío de validación de documentos para la creación de firma de persona jurídica, no procesadas";
                    $lista_documentos = "";
                    $titulos_documentos = array();

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
                        $_id . "_" . $identificacion
                    );

                    $botones_accion = '<tr style="text-align: right; border: 1px solid black;">
                                             <td style="width: 20%;"></td>
                                             <td style="text-align: center; padding: 20px; border-radius: 15px 15px 15px 15px; -moz-border-radius: 15px 15px 15px 15px;   -webkit-border-radius: 15px 15px 15px 15px;">
                                            <a target="_blank" href="' . URL::to(
                                                '/doc_electronicos/aceptar_doc_per_juri/' . $token
                                            ) . '  "><img alt="Aceptar Solicitud" style="cursor:pointer; text-align: center;" src="' . URL::to(
                                                '/img/doc_electronicos/aceptar_solicitud.png'
                                            ) . '" />
                                            </a>
                                            </td>
                                        </tr>
                                        <tr  style="text-align: right; border: 1px solid black;">
                                            <td style="width: 20%;"></td>
                                            <td style="text-align: center; padding: 20px;  border-radius: 15px 15px 15px 15px;-moz-border-radius: 15px 15px 15px 15px; -webkit-border-radius: 15px 15px 15px 15px; ">
                                                <a target="_blank" href="' . URL::to(
                                                '/doc_electronicos/rechazar_doc_per_juri/' . $token
                                            ) . '  "><img alt="Rechazar Solictud" style="cursor:pointer; text-align: center;" src="' . URL::to(
                                                '/img/doc_electronicos/rechazar_solicitud.png'
                                            ) . '" />
                                                </a>
                                            </td>
                                        </tr>';

                    $arretiquetas = array(
                        "banner_url",
                        "compania",
                        "lista_documentos",
                        "lista_participantes",
                        "identificacion",
                        "enlace",
                        "botones_accion",
                        'titulos_documentos'
                    );
                    $arrvalores = array(
                        $banner_url,
                        $compania,
                        $lista_documentos,
                        $lista_participantes,
                        $identificacion,
                        $enlace,
                        $botones_accion,
                        $titulos_documentos
                    );

                    $data_mailgun = array();
                    $data_mailgun["X-Mailgun-Tag"] = "monitoreo_proceso_signatarios_de";
                    $data_mailgun["X-Mailgun-Track"] = "yes";
                    $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $_id]);

                    $mail_view = 'emails.doc_electronicos.validacion_documentos_juridico';

                    $usuarioN1 = Config::get('app.email_soporte_firma');
                    $nombreN1 = Config::get('app.nombre_soporte');

                   Log::info("LLego para enviar correo " . $hora_min);

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
                    $Res = $arr_res[0];
                    $Mensaje = $arr_res[1];
                    if ($Res > 0) {
                        $Mensaje = "El correo de validación fue enviado correctamente a " . $nombreN1;
                        Log::info("Se envio el correo " . $Mensaje);
                    }


               } else {Log::info("No estamos en el tiempo de ejecución del servicio " . $hora_min);}
            }

         }
       }


    }

}
