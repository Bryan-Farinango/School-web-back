<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\EstadoDocumento;
use App\doc_electronicos\EstadoProcesoEnum;
use App\doc_electronicos\Firma;
use App\doc_electronicos\Preferencia;
use App\doc_electronicos\Proceso;
use App\doc_electronicos\ProcesoSimple;
use App\Formulario_vinculacion;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EmailController;
use App\Poliza\Broker;
use App\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class ApiController extends Controller
{
    use \App\Packages\Traits\UserUtilTrait;

    public function __construct()
    {
    }
    public function EmitirSimple(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        $arr_documentos = array();
        $arr_firmantes = array();

        $ruc = $request->input("ruc");
        Filtrar($ruc, "STRING");
        $titulo_proceso = $request->input("titulo_proceso");
        Filtrar($titulo_proceso, "STRING");
        $orden = $request->input("orden");
        Filtrar($orden, "INTEGER");
        $arreglo_documentos = $request->input("documentos");
        Filtrar($arreglo_documentos, "ARRAY", []);
        $arreglo_firmantes = $request->input("signatarios");
        Filtrar($arreglo_firmantes, "ARRAY", []);
        $via = $request->input("via");
        if (empty($via)) {
            $via = "API";
        }
        Filtrar($via, "STRING", "API");
        $api_key = $request->input("api_key");
        Filtrar($api_key, "STRING", "");
        $variante_aceptacion = $request->input("variante_aceptacion");
        Filtrar($variante_aceptacion, "STRING", "");
        $cuerpo_email = null;
        if (isset($_POST['cuerpo_email'])) {
            $cuerpo_email = $_POST['cuerpo_email'];
            Filtrar($cuerpo_email, "STRING", "");
        }
        $ftp_filename = $request->input("ftp_filename");
        Filtrar($ftp_filename, "STRING", "");

        if (empty($ruc)) {
            $Res = -1;
            $Mensaje = "No se recibió el campo RUC.";
        }
        if ($Res >= 0) {
            if (!RUCValido($ruc)) {
                $Res = -2;
                $Mensaje = "El RUC indicado es inválido.";
            }
        }

        if ($Res >= 0) {
            $cliente_emisor = Cliente::where("identificacion", $ruc)->first();
            if (!$cliente_emisor) {
                $Res = -3;
                $Mensaje = "El cliente con RUC $ruc no es usuario de Stupendo.";
            } else {
                $id_cliente_emisor = $cliente_emisor->_id;
                $nombre_cliente_emisor = $cliente_emisor->nombre_identificacion;
            }
        }
        
        // if ($Res >= 0 && $via == "API") {
        //     $api_key_guardada = Preferencia::get_api_key($id_cliente_emisor);
        //     if (empty($api_key)) {
        //         $Res = -3;
        //         $Mensaje = "No se recibió la API Key.";
        //     } else {
        //         if ($api_key !== $api_key_guardada) {
        //             $Res = -4;
        //             $Mensaje = "API Key incorrecta.";
        //         }
        //     }
        // }
        if ($Res >= 0) {
            $usuarios = $this->ObtenerAdministradoresDocElectronicos($id_cliente_emisor);
            if (!$usuarios || $usuarios->count() == 0) {
                $usuario = Usuarios::where("clientes.cliente_id", $id_cliente_emisor)->first(["_id"]);
                if (!$usuario) {
                    $Res = -4;
                    $Mensaje = "El cliente $nombre_cliente_emisor no tienen ningún usuario asociado.";
                } else {
                    $id_usuario_emisor = $usuario->_id;
                }
            } else {
                $id_usuario_emisor = $usuarios[0]->_id;
            }
        }

        if ($Res >= 0) {
            if (empty($titulo_proceso)) {
                $Res = -5;
                $Mensaje = "No se recibió un título para el proceso.";
            }
        }
        if ($Res >= 0) {
            $numTitulo = strlen($titulo_proceso);
            if ($numTitulo > 128) {
                $Res = -25;
                $Mensaje = "El titulo del proceso no se debe acceder del límite 128 caracteres ";
            }
        }

        if ($Res >= 0) {
            if (!is_numeric($orden)) {
                $Res = -41;
                $Mensaje = "La variante de flujo indicada es incorrecta, debe ser un valor numérico (1 - Paralelo, 2 - Secuencial), sin comillas.";
            }
            if ($orden == 0) {
                $Res = -42;
                $Mensaje = "La variante de flujo indicada es incorrecta. Solo admite los números 1 y 2. (1 - Paralelo, 2 - Secuencial)";
            }
        }
        if ($Res >= 0) {
            if ($orden != 1 && $orden != 2) {
                $Res = -5;
                $Mensaje = "La variante de flujo indicada es incorrecta. Solo admite los números 1 y 2. (1 - Paralelo, 2 - Secuencial)";
            }
        }
        if ($Res >= 0) {
            if (empty($orden)) {
                $orden = Preferencia::get_default($id_cliente_emisor, "orden");
            }
        }

        if ($Res >= 0) {
            if ($variante_aceptacion != 'EMAIL' && $variante_aceptacion != 'SMS') {
                $variante_aceptacion = 'EMAIL';
            }
        }
        if ($Res >= 0) {
            if (empty($arreglo_documentos)) {
                $Res = -6;
                $Mensaje = "Debe indicar al menos 1 documento a su proceso.";
            }
        }
        if ($Res >= 0) {
            if (!is_array($arreglo_documentos)) {
                $Res = -7;
                $Mensaje = "El campo documentos no es un array válido.";
            }
        }
        if ($Res >= 0) {
            $indice = 0;

            foreach ($arreglo_documentos as $documento) {
                if ($Res >= 0) {
                    @$titulo_documento = $documento["titulo_documento"];
                    @$base64 = $documento["base64"];
                    $indice++;
                }
                if ($Res >= 0) {
                    if (empty($titulo_documento)) {
                        $Res = -8;
                        $Mensaje = "No se recibió un título para el documento $indice.";
                    }
                }

                if ($Res >= 0) {
                    $numtitulo_documento = strlen($titulo_documento);
                    if ($numtitulo_documento > 128) {
                        $Res = -8;
                        $Mensaje = "El título para el documento $indice,  no se debe acceder del límite 128 caracteres.";
                    }
                }

                if ($Res >= 0) {
                    if (empty($base64)) {
                        $Res = -11;
                        $Mensaje = "No se recibió el archivo (en base64) relativo al documento $indice";
                    } else {
                        if (!is_dir(sys_get_temp_dir() . "/doc_electronicos")) {
                            mkdir(sys_get_temp_dir() . "/doc_electronicos");
                        }
                        $camino_temporal = sys_get_temp_dir() . "/doc_electronicos/$id_cliente_emisor" . "_" . date(
                                "U"
                            ) . rand(0, 99999) . ".pdf";
                        if (file_exists($camino_temporal)) {
                            sleep(1);
                            $camino_temporal = sys_get_temp_dir() . "/doc_electronicos/$id_cliente_emisor" . "_" . date(
                                    "U"
                                ) . rand(0, 99999) . ".pdf";
                        }
                        if (!file_put_contents($camino_temporal, base64_decode($base64))) {
                            $Res = -12;
                            $Mensaje = "Ocurrió un error recreando el archivo PDF del documento $indice.";
                        }
                    }
                }
                if ($Res >= 0) {
                    $item_documento = UnirData(
                        array($titulo_documento, $camino_temporal)
                    );
                    array_push($arr_documentos, $item_documento);
                }
            }
        }
        if ($Res >= 0) {
            if (empty($arreglo_firmantes)) {
                $Res = -13;
                $Mensaje = "Debe indicar al menos 1 participante a su proceso.";
            }
        }
        if ($Res >= 0) {
            if (!is_array($arreglo_firmantes)) {
                $Res = -14;
                $Mensaje = "El campo participantes no es un array válido.";
            }
        }
        if ($Res >= 0) {
            $indice = 0;
            $aidentificaciones = array();
            $aemails = array();
            foreach ($arreglo_firmantes as $firmante) {
                if ($Res >= 0) {
                    @$identificacion = $firmante["identificacion"];
                    if (empty($identificacion)) {
                        @$identificacion = $firmante["cedula"];
                    }
                    @$nombre = $firmante["nombre"];
                    @$email = $firmante["email"];
                    @$telefono = $firmante["telefono"];
                    @$tipo_identificacion = $firmante["tipo_identificacion"];
                    $indice++;

                    if (empty($identificacion)) {
                        $Res = -15;
                        $Mensaje = "No se recibió la cédula para el participante $indice.";
                    }
                }
                if ($Res >= 0) {
                    if (!isset($tipo_identificacion) || empty($tipo_identificacion)) {
                        $Res = -16;
                        $Mensaje = "No se recibió el tipo de identificación para el participante $indice.";
                    }
                }

                if ($Res >= 0) {
                    if (!justNumbers($tipo_identificacion)) {
                        $Res = -26;
                        $Mensaje = "El tipo de identificación para el participante $indice, no es válido, debe ser un valor numérico.";
                    }
                }
                if ($Res >= 0) {
                    if ($tipo_identificacion != "04" && $tipo_identificacion != "05"  &&  $tipo_identificacion != "06") {
                        $Res = -27;
                        $Mensaje = "El tipo de identificación para el participante $indice, no es válido, debe ser un valor de acuerdo a los establecidos en la ficha técnica.";
                    }
                }

                if ($Res >= 0) {
                    if ($tipo_identificacion == "05") {
                        if (!justNumbers($identificacion)) {
                            $Res = -28;
                            $Mensaje = "La identificación para el participante $indice, no es válido, debe ser un valor numérico.";
                        }
                        if (!is_string($identificacion)) {
                            $Res = -29;
                            $Mensaje = "La identificación para el participante $indice, no es válido, verifique que el dato se encuentra entre comillas.";
                        }
                    } elseif($tipo_identificacion == "04")
                    {
                        if (!justNumbers($identificacion)) {
                            $Res = -30;
                            $Mensaje = "El RUC para el participante $indice, no es válido, debe ser un valor numérico.";
                        }
                        if (!is_string($identificacion)) {
                            $Res = -31;
                            $Mensaje = "El RUC para el participante $indice, no es válido, verifique que el dato se encuentra entre comillas.";
                        }
                    }
                }
                if ($Res >= 0) {
                    if ($tipo_identificacion == "05") {
                        if (!CedulaValida($identificacion)) {
                            $Res = -32;
                            $Mensaje = "La identificación para el participante $indice, no es válido, verifique que tenga 10 dígitos sin caráteres especiales ni letras";
                        }
                    } elseif($tipo_identificacion == "04")
                    {
                        if (!RUCValido($identificacion)) {
                            $Res = -33;
                            $Mensaje = "El RUC para el participante $indice, no es válido, verifique que tenga 13 dígitos sin caráteres especiales ni letras";
                        }
                    }
                }
                if ($Res >= 0) {
                    if (empty($nombre)) {
                        $Res = -18;
                        $Mensaje = "No se recibió el nombre completo del participante $indice.";
                    }
                }
                if ($Res >= 0) {
                    if ($nombre == "null" || $nombre == "NULL") {
                        $Res = -34;
                        $Mensaje = "No se recibió el nombre completo del signatario $indice.";
                    }
                }
                if ($Res >= 0) {
                    $numNombre = strlen($nombre);
                    if ($numNombre > 128) {
                        $Res = -38;
                        $Mensaje = "El nombre del signatario $indice, no se debe acceder del límite 128 caracteres ";
                    }
                }
                if ($Res >= 0) {
                    if (empty($email)) {
                        $Res = -19;
                        $Mensaje = "No se recibió un email para el participante $indice.";
                    }
                }
                if ($Res >= 0) {
                    if (!EMailValido($email)) {
                        $Res = -20;
                        $Mensaje = "No se indicó un email correcto para el participante $indice.";
                    }
                }
                if ($Res >= 0) {
                    if (empty($telefono)) {
                        $Res = -21;
                        $Mensaje = "No se recibió un número de celular para el participante $indice.";
                    }
                }

                if ($Res >= 0) {
                    if (!CelularEcuadorValido($telefono)) {
                        $Res = -22;
                        $Mensaje = "El número de celular para el signatario $indice no es válido, verificar que tenga 10 dígitos o inicie con 09";
                    }
                }
                if ($Res >= 0) {
                    if (in_array($identificacion, $aidentificaciones)) {
                        $Res = -23;
                        $Mensaje = "La cédula $identificacion está duplicada en el listado de participantes.";
                    }
                }
                if ($Res >= 0) {
                    if (in_array($email, $aemails)) {
                        $Res = -24;
                        $Mensaje = "El email $email está duplicado en el listado de participantes.";
                    }
                }

                if($via == "API")
                {
                    $nombre_enmas = $request->input("nombre_enmas");
                    Filtrar($nombre_enmas, "STRING", "");

                    $correo_enmas = $request->input("correo_enmas");
                    Filtrar($correo_enmas, "STRING", "");

                    $numenmas = strlen($nombre_enmas);
                    if ($numenmas > 200) {
                        $Res = -39;
                        $Mensaje = "El nombre de emisor que se visualizará en el correo,  no se debe acceder del límite 200 caracteres ";
                    }
                    $numcorreoenmas = strlen($correo_enmas);
                    if ($numcorreoenmas > 100) {
                        $Res = -40;
                        $Mensaje = "La dirección del email que se usará cuando se intente responder al correo,  no se debe acceder del límite 100 caracteres ";
                    }
                }

                if ($Res >= 0) {
                    $item_firmante = UnirData(array($identificacion, $nombre, $email, $telefono));
                    array_push($arr_firmantes, $item_firmante);
                    array_push($aidentificaciones, $identificacion);
                    array_push($aemails, $email);
                }
            }
        }
        if ($Res >= 0) {
            $request_nuevo = new Request();
            $request_nuevo->merge(
                array(
                    "HiddenIdCliente" => $id_cliente_emisor,
                    "HiddenIdUsuario" => $id_usuario_emisor,
                    "TTituloProceso" => $titulo_proceso,
                    "SOrden" => $orden,
                    "HiddenDocumentos" => $arr_documentos,
                    "HiddenDestinatarios" => $arr_firmantes,
                    "SViaAceptacion" => $variante_aceptacion,
                    "cuerpo_email" => $cuerpo_email,
                    "HiddenVia" => $via,
                    "ftp_filename" => $ftp_filename
                )
            );
            $psc = new ProcesoSimpleController();
            $respuesta = $psc->GuardarProcesoSimple($request_nuevo);
            $respuesta = $respuesta->getData();
            $Res = $respuesta->Res;
            $Mensaje = $respuesta->Mensaje;
            $referencia = $respuesta->id;
        }
        if ($Res >= 0) {
            $resultado = true;
            $mensaje = "El proceso de aceptación simple fue iniciado correctamente.";
        } else {
            $resultado = false;
            $mensaje = $Mensaje;
            $referencia = null;
        }

        $mensaje = htmlentities($mensaje);

        return json_encode(array("resultado" => $resultado, "mensaje" => $mensaje, "referencia" => $referencia));
    }

    
}
