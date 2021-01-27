<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\PeriodoPrueba;
use App\doc_electronicos\RegistroIntencion;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PerfilesController;
use App\Usuarios;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use MongoDB\BSON\UTCDateTime;

class RegistroIntencionController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarRegistroIntencion()
    {
        $arretiquetas = array();
        $arrvalores = array();
        return view(
            "doc_electronicos.registro_intencion.registro_intencion",
            array_combine($arretiquetas, $arrvalores)
        );
    }

    public function GuardarIntereses(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $ruc = $request->input("TRUC");
            Filtrar($ruc, "STRING");
            $razon_social = $request->input("TRazonSocial");
            Filtrar($razon_social, "STRING");
            $email = $request->input("TEMail");
            Filtrar($email, "EMAIL");
            $telefono = $request->input("TTelefono");
            Filtrar($telefono, "STRING");
            $interes_emision = $request->input("CH_Emision") ? 1 : 0;
            $interes_recepcion = $request->input("CH_Recepcion") ? 1 : 0;
            $interes_pronto_pago = $request->input("CH_Pronto_Pago") ? 1 : 0;
            $interes_recaudos = $request->input("CH_Recaudos") ? 1 : 0;
            $interes_poliza = $request->input("CH_Poliza") ? 1 : 0;
            $interes_documentos_electronicos = $request->input("CH_Documentos_Electronicos") ? 1 : 0;
            if (!RUCValido($ruc)) {
                $Res = -1;
                $Mensaje = "RUC inválido";
            } else {
                if (empty($razon_social)) {
                    $Res = -2;
                    $Mensaje = "Razón social inválida";
                } else {
                    if (!EMailValido($email)) {
                        $Res = -3;
                        $Mensaje = "Email inválido";
                    } else {
                        if (empty($telefono)) {
                            $Res = -4;
                            $Mensaje = "Teléfono inválido";
                        }
                    }
                }
            }
        }
        if ($Res >= 0) {
            $registro_intencion_data =
                [
                    'ruc' => $ruc,
                    'razon_social' => $razon_social,
                    'email_contacto' => $email,
                    'telefono_contacto' => $telefono,
                    'interes_emision' => $interes_emision,
                    'interes_recepcion' => $interes_recepcion,
                    'interes_pronto_pago' => $interes_pronto_pago,
                    'interes_recaudos' => $interes_recaudos,
                    'interes_poliza' => $interes_poliza,
                    'interes_documentos_electronicos' => $interes_documentos_electronicos
                ];
            $registro_intencion = RegistroIntencion::create($registro_intencion_data);
            if (!$registro_intencion) {
                $Res = -5;
                $Mensaje = "Ocurrió un error guardando el registro.";
            }
        }
        if ($Res >= 0) {
            $arretiquetas = array("razon_social", "ruc", "lista_intereses", "email", "telefono");
            $lista_intereses = '';
            if ($interes_emision == 1) {
                $lista_intereses .= '<li>Emisión</li>';
            }
            if ($interes_recepcion == 1) {
                $lista_intereses .= '<li>Recepción</li>';
            }
            if ($interes_pronto_pago == 1) {
                $lista_intereses .= '<li>Pronto Pago</li>';
            }
            if ($interes_recaudos == 1) {
                $lista_intereses .= '<li>Recaudos</li>';
            }
            if ($interes_poliza == 1) {
                $lista_intereses .= '<li>Póliza</li>';
            }
            if ($interes_documentos_electronicos == 1) {
                $lista_intereses .= '<li>Documentos Electrónicos</li>';
            }
            $arrvalores = array($razon_social, $ruc, $lista_intereses, $email, $telefono);
            $de = "Stupendo.";
            $asunto = "Cliente interesado. $razon_social";
            $arreglo_emails =
                [
                    "dariem.martinez@stupendo.com",
                    "dariemml@gmail.com"
                ];
            $arreglo_nombres =
                [
                    "Dariem Stupendo",
                    "Dariem GMail"
                ];
            $arr_res = EnviarCorreo(
                'emails.doc_electronicos.registro_intencion_compra',
                $de,
                $asunto,
                $arreglo_emails,
                $arreglo_nombres,
                $arretiquetas,
                $arrvalores
            );
            $Res = $arr_res[0];
            $Mensaje = $arr_res[1];
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Los intereses fueron registrados y notificados correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function MostrarAutoRegistroDE()
    {
        $arretiquetas = array();
        $arrvalores = array();
        return view("doc_electronicos.registro_intencion.auto_registro_de", array_combine($arretiquetas, $arrvalores));
    }

    public function GuardarInteresDocumentosElectronicos(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $ruc = $request->input("TRUC");
            Filtrar($ruc, "STRING");
            $razon_social = $request->input("TRazonSocial");
            Filtrar($razon_social, "STRING");
            $email = $request->input("TEMail");
            Filtrar($email, "EMAIL");
            $telefono = $request->input("TTelefono");
            Filtrar($telefono, "STRING");
            $cantidad_documentos_electronicos = $request->input("SEstimadoDocumentos");
            Filtrar($cantidad_documentos_electronicos, "INTEGER", 0);
            $interes_emision = 0;
            $interes_recepcion = 0;
            $interes_pronto_pago = 0;
            $interes_recaudos = 0;
            $interes_poliza = 0;
            $interes_documentos_electronicos = 1;
            if (!RUCValido($ruc)) {
                $Res = -1;
                $Mensaje = "RUC inválido";
            } else {
                if (empty($razon_social)) {
                    $Res = -2;
                    $Mensaje = "Razón social inválida";
                } else {
                    if (!EMailValido($email)) {
                        $Res = -3;
                        $Mensaje = "Email inválido";
                    } else {
                        if (empty($telefono)) {
                            $Res = -4;
                            $Mensaje = "Teléfono inválido";
                        }
                    }
                }
            }
        }
        if ($Res >= 0) {
            $registro_intencion_data =
                [
                    'ruc' => $ruc,
                    'razon_social' => $razon_social,
                    'email_contacto' => $email,
                    'telefono_contacto' => $telefono,
                    'interes_emision' => $interes_emision,
                    'interes_recepcion' => $interes_recepcion,
                    'interes_pronto_pago' => $interes_pronto_pago,
                    'interes_recaudos' => $interes_recaudos,
                    'interes_poliza' => $interes_poliza,
                    'interes_documentos_electronicos' => $interes_documentos_electronicos
                ];
            $registro_intencion = RegistroIntencion::create($registro_intencion_data);
            if (!$registro_intencion) {
                $Res = -5;
                $Mensaje = "Ocurrió un error guardando el registro.";
            }
        }
        if ($Res >= 0) {
            $arretiquetas = array("razon_social", "ruc", "email", "telefono", "rango_documentos");
            switch ($cantidad_documentos_electronicos) {
                case 0:
                {
                    $rango_documentos = "0";
                    break;
                }
                case 1:
                {
                    $rango_documentos = "entre 1 y 100";
                    break;
                }
                case 2:
                {
                    $rango_documentos = "entre 101 y 500";
                    break;
                }
                case 3:
                {
                    $rango_documentos = "entre 501 y 5000";
                    break;
                }
                case 4:
                {
                    $rango_documentos = "más de 5000";
                    break;
                }
            }
            $arrvalores = array($razon_social, $ruc, $email, $telefono, $rango_documentos);
            $de = "Stupendo.";
            $asunto = "Cliente interesado en Documentos Electrónicos. $razon_social";
            $arreglo_emails =
                [
                    "dariem.martinez@stupendo.com",
                    "dariemml@gmail.com"
                ];
            $arreglo_nombres =
                [
                    "Dariem Stupendo",
                    "Dariem GMail"
                ];
            $arr_res = EnviarCorreo(
                'emails.doc_electronicos.registro_intencion_de',
                $de,
                $asunto,
                $arreglo_emails,
                $arreglo_nombres,
                $arretiquetas,
                $arrvalores
            );
            $Res = $arr_res[0];
            $Mensaje = $arr_res[1];
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El interés fue registrado y notificado correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function PrepararClientePruebaDE(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        $nuevo_usuario = false;
        $nuevo_cliente_de = false;
        try {
            if ($Res >= 0) {
                $ruc = $request->input("TRUC");
                Filtrar($ruc, "STRING");
                $razon_social = $request->input("TRazonSocial");
                Filtrar($razon_social, "STRING");
                $email = $request->input("TEMail");
                Filtrar($email, "EMAIL");
                $telefono = $request->input("TTelefono");
                Filtrar($telefono, "STRING");
                if (!RUCValido($ruc)) {
                    $Res = -1;
                    $Mensaje = "RUC inválido";
                } else {
                    if (empty($razon_social)) {
                        $Res = -2;
                        $Mensaje = "Razón social inválida";
                    } else {
                        if (!EMailValido($email)) {
                            $Res = -3;
                            $Mensaje = "Email inválido";
                        } else {
                            if (empty($telefono)) {
                                $Res = -4;
                                $Mensaje = "Teléfono inválido";
                            }
                        }
                    }
                }
            }
            if ($Res >= 0) {
                $arr_res = ProcesoController::prepararClienteUsuarioDE(
                    $ruc,
                    $razon_social,
                    $email,
                    $telefono,
                    "Emisor_DE"
                );
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
                $id_cliente = $arr_res["id_cliente"];
                $id_usuario = $arr_res["id_usuario"];
                $nuevo_cliente_de = $arr_res["nuevo_cliente_de"];
                $nuevo_usuario = $arr_res["nuevo_usuario"];
            }
            if ($Res >= 0) {
                $arr_res = $this->Establece_En_Pruebas_Y_Notifica(
                    $id_cliente,
                    $id_usuario,
                    $email,
                    $nuevo_usuario,
                    $nuevo_cliente_de
                );
                $Res = $arr_res["Res"];
                $Mensaje = $arr_res["Mensaje"];
            }
        } catch (Exception $e) {
            $Res = -6;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "Usuario y cliente preparados correctamente.";
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
    }

    public function Sacar_De_Pruebas($id_cliente)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $cliente_invitado_estaba_en_pruebas = PeriodoPrueba::where("id_cliente", $id_cliente)->first();
            if ($cliente_invitado_estaba_en_pruebas) {
                $resultado = $cliente_invitado_estaba_en_pruebas->delete();
                if (!$resultado) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error sacando de pruebas al cliente.";
                } else {
                    $Res = 1;
                    $Mensaje = "El cliente fue sacado de pruebas correctamente.";
                }
            } else {
                $Res = 2;
                $Mensaje = "El cliente no estaba en pruebas.";
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        return array("Res" => $Res, "Mensaje" => $Mensaje);
    }

    public function Establece_En_Pruebas_Y_Notifica(
        $id_cliente,
        $id_usuario,
        $email,
        $nuevo_usuario = true,
        $nuevo_cliente_de = true
    ) {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0 && $nuevo_cliente_de) {
                $data_periodo_pruebas = [
                    'id_cliente' => $id_cliente,
                    'id_usuario' => $id_usuario,
                    'momento_inicia_pruebas' => new UTCDateTime(
                        DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000
                    )
                ];
                $periodo_pruebas = PeriodoPrueba::create($data_periodo_pruebas);
                if (!$periodo_pruebas) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error estableciendo el período de pruebas.";
                }
            }
            if ($Res >= 0) {
                $arretiquetas = array(
                    "razon_social_nombre",
                    "enlace",
                    "credenciales",
                    "dias_prueba",
                    "fecha_vencimiento"
                );
                $cliente = Cliente::find($id_cliente);
                $usuario = Usuarios::find($id_usuario);
                if ($nuevo_usuario) {
                    $password_autogenerado = substr(md5($id_usuario), -8);
                    $usuario->password = bcrypt($password_autogenerado);
                    $usuario->save();
                    $credenciales = "Tus credenciales de acceso, por primera vez, a Stupendo son:<br/>Usuario: <b>$email</b><br/>Contraseña: <b>$password_autogenerado</b><br/>Se te requerirá automáticamente a cambiar tus credenciales en el primer ingreso.";
                } else {
                    $credenciales = "";
                }
                $razon_social_nombre = $usuario->nombre . " (" . $cliente->nombre_identificacion . ")";
                $enlace = '<a href="' . URL::to('/force_logout') . '">Stupendo -> Documentos electrónicos.</a>';
                $fecha_vencimiento = Carbon::now()->addDays(PeriodoPrueba::DIAS_DE_PRUEBA)->format("d/m/Y");
                $de = Config::get('app.mail_from_name');
                $asunto = "STUPENDO. Bienvenido a Documentos electrónicos.";
                $arrvalores = array(
                    $razon_social_nombre,
                    $enlace,
                    $credenciales,
                    PeriodoPrueba::DIAS_DE_PRUEBA,
                    $fecha_vencimiento
                );
                try {
                    EnviarCorreo(
                        'emails.doc_electronicos.notifica_autoregistro',
                        $de,
                        $asunto,
                        array($email),
                        array($razon_social_nombre),
                        $arretiquetas,
                        $arrvalores
                    );
                } catch (Exception $e) {
                    $Res = -3;
                    $Mensaje = $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $Res = -4;
            $Mensaje = $e->getMessage();
        }
        return array("Res" => $Res, "Mensaje" => $Mensaje);
    }

    public function convierte_a_receptores_usuarios_de_clientes_prueba_vencida()
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                $momento_referencia = Carbon::now()->subDays(PeriodoPrueba::DIAS_DE_PRUEBA);
                $clientes_con_pruebas_vencidas = PeriodoPrueba::where(
                    "momento_inicia_pruebas",
                    "<=",
                    $momento_referencia
                )->get();
                foreach ($clientes_con_pruebas_vencidas as $cli) {
                    $nombre_cliente = Cliente::find($cli["id_cliente"])["nombre_identificacion"];
                    $usuarios_de_cliente = Usuarios::where("clientes.cliente_id", $cli["id_cliente"])->where(
                        "clientes.perfiles.rol_cliente",
                        "DocumentosElectronicos"
                    )->get();
                    foreach ($usuarios_de_cliente as $usu) {
                        $arr_res = PerfilesController::CambiarPerfil(
                            $usu,
                            $cli["id_cliente"],
                            "DocumentosElectronicos",
                            "Receptor_DE"
                        );
                        $Res = $arr_res["Res"];
                        $Mensaje = $arr_res["Mensaje"];
                        if ($Res > 0) {
                            Log::info(
                                "DE: Usuario {$usu['nombre']}, representando al cliente $nombre_cliente fue degradado a Receptor_DE, por haber terminado el período de pruebas de $nombre_cliente"
                            );
                        } else {
                            Log::error(
                                "DE: Ocurrió un error degradando a Receptor_DE al usuario {$usu['nombre']}, representando al cliente $nombre_cliente"
                            );
                        }
                    }
                    if ($Res >= 0) {
                        @$this->Sacar_De_Pruebas($cli["id_cliente"]);
                    }
                }
            }
        } catch (Exception $e) {
            @Log::error("convierte_a_receptores_usuarios_de_clientes_prueba_vencida: " . $e->getMessage());
        }
    }

}