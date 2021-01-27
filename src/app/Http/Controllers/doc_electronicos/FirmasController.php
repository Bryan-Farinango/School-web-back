<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\ConsultaDatofast;
use App\CredencialesConsulta;
use App\doc_electronicos\Auditoria;
use App\doc_electronicos\EstadoFirma;
use App\doc_electronicos\Firma;
use App\doc_electronicos\FirmaPorValidar;
use App\doc_electronicos\FirmaPorValidarEstadoEnum;
use App\doc_electronicos\Preferencia;
use App\doc_electronicos\ProcesoSimple;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SMSController;
use App\Poliza\Aseguradora;
use App\Poliza\AseguradoraBroker;
use App\Poliza\Broker;
use App\SolicitudVinculacion;
use App\Usuarios;
use Aw\Nusoap\NusoapClient;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use MongoDB\BSON\UTCDateTime;

class FirmasController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarFiltroFirmas()
    {
        $arretiquetas = array("opciones_estado");
        $opciones_estado = EstadoFirma::get_options_estados_firmas();
        $arrvalores = array($opciones_estado);

        return view("doc_electronicos.firmas.filtro_firmas", array_combine($arretiquetas, $arrvalores));
    }

    public function MostrarListaFirmas(Request $request)
    {
        $id_cliente = session()->get("id_cliente");

        $filtro_desde = $request->input("filtro_desde");
        Filtrar($filtro_desde, "STRING", "");

        $filtro_hasta = $request->input("filtro_hasta");
        Filtrar($filtro_hasta, "STRING", "");

        $filtro_estado = $request->input("filtro_estado");
        Filtrar($filtro_estado, "INTEGER", -1);

        $firmas = Firma::where("id_cliente", $id_cliente);

        if (!empty($filtro_desde)) {
            $firmas = $firmas->where(
                "momento_activada",
                ">=",
                Carbon::createFromFormat("d/m/Y H:i:s", $filtro_desde . " 00:00:00")
            );
        }

        if (!empty($filtro_hasta)) {
            $firmas = $firmas->where(
                "momento_activada",
                "<=",
                Carbon::createFromFormat("d/m/Y H:i:s", $filtro_hasta . " 23:59:59")
            );
        }

        if ($filtro_estado != -1) {
            $firmas = $firmas->where("id_estado", (int)$filtro_estado);
        }

        $firmas = $firmas->get();

        $result = array();

        foreach ($firmas as $firma) {
            $result[] = [
                "_id" => EncriptarId($firma["_id"]),
                "identificacion" => $firma["identificacion"],
                "representante" => $firma["nombre"],
                "vigencia_mostrar" => FormatearMongoISODate($firma["desde"], "d/m/Y") . ' - ' . FormatearMongoISODate(
                        $firma["hasta"],
                        "d/m/Y"
                    ),
                "vigencia_orden" => FormatearMongoISODate($firma["hasta"], "U"),
                "registrada_mostrar" => FormatearMongoISODate($firma["momento_activada"], "d/m/Y"),
                "registrada_orden" => FormatearMongoISODate($firma["momento_activada"], "U"),
                "origen" => ((int)$firma["origen"] == 1) ? "CA" : "Stupendo",
                "pfx" => '<a target="_blank" href="/doc_electronicos/descargar_pfx/' . EncriptarId(
                        $firma["_id"]
                    ) . '"><img src="/img/iconos/pfx.png" height="26px" width="26px" /></a>',
                "rl" => $firma["figura_legal"] == "J" ? '<a target="_blank" href="/doc_electronicos/descargar_rl/' . EncriptarId(
                        $firma["_id"]
                    ) . '"><img src="/img/iconos/pdf.png" height="26px" width="26px" /></a>' : '',
                "ruc" => $firma["figura_legal"] == "J" ? '<a target="_blank" href="/doc_electronicos/descargar_ruc/' . EncriptarId(
                        $firma["_id"]
                    ) . '"><img src="/img/iconos/pdf.png" height="26px" width="26px" /></a>' : '',
                "poder" => (isset($firma["camino_poder"]) && !empty($firma["camino_poder"])) ? '<a target="_blank" href="/doc_electronicos/descargar_poder/' . EncriptarId(
                        $firma["_id"]
                    ) . '"><img src="/img/iconos/pdf.png" height="26px" width="26px" /></a>' : '',
                "acciones" => "",
                "id_estado" => $firma["id_estado"],
                "figura_legal" => $firma["figura_legal"],
                "permiso_anular_firmas" => TienePermisos(7, 3) ? 1 : 0
            ];
        }

        return response()->json(array("data" => $result), 200);
    }

    public function MostrarGenerarFirma(Request $request)
    {
        $id_usuario = session()->get("id_usuario");
        $id_cliente = session()->get("id_cliente");

        $id_input_file_rl = $request->input("HiddenTemporalFirmaIdInputFileRL");
        Filtrar($id_input_file_rl, "STRING");

        $id_input_file_ruc = $request->input("HiddenTemporalFirmaIdInputFileRUC");
        Filtrar($id_input_file_ruc, "STRING");

        $id_input_file_poder = $request->input("HiddenTemporalFirmaIdInputFilePoder");
        Filtrar($id_input_file_poder, "STRING");

        $persona = $request->input("HiddenPersona");
        Filtrar($persona, "STRING");

        $usuario = Usuarios::find($id_usuario);
        $cliente = Cliente::find($id_cliente);

        if (!empty($persona)) {
            $figura_legal = $persona;
        } else {
            $figura_legal = "N";
        }

        $representante = $cliente["nombre_identificacion"];

        $tipo_identificacion_cliente = $cliente->tipo_identificacion;

        if ($tipo_identificacion_cliente == "04" || $tipo_identificacion_cliente == "05") {
            if ($figura_legal == "J") {
                $cedula = $cliente["identificacion"];;
            } else {
                if (CedulaValida($cliente["identificacion"])) {
                    $cedula = $cliente["identificacion"];
                } else {
                    $cedula = $cliente["identificacion"];;
                }
            }
        } else {
            $cedula = $cliente["identificacion"];
        }

        if ($tipo_identificacion_cliente == "05") {
            $descripcionTipoIde = "Cédula de Identidad";
        } else {
            if ($tipo_identificacion_cliente == "04") {
                $descripcionTipoIde = "RUC";
            } else {
                if ($tipo_identificacion_cliente == "06") {
                    $descripcionTipoIde = "Pasaporte";
                } else {
                    if ($tipo_identificacion_cliente == "01") {
                        $descripcionTipoIde = "Pasaporte";
                    } else {
                        $descripcionTipoIde = "Documento del Exterior";
                    }
                }
            }
        }

        $correo = $cliente["email"];

        if (empty($correo)) {
            $correo = $usuario["email"];
        }

        $firma = Firma::where("id_cliente", $id_cliente)->orderBy("version", "desc")->first();

        if ($firma) {
            $telefono = $firma["telefono"];
        } else {
            $telefono = $cliente["telefono"];
        }

        if (!$firma) {
            $mensaje = "No tienes firma electrónica registrada. Este proceso creará una nueva para ti.";
        } else {
            if ((int)date("U") > (int)FormatearMongoISODate($firma["hasta"], "U")) {
                $mensaje = "Tu firma registrada expiró. Este proceso creará una nueva para ti.";
            } else {
                if ($firma["id_estado"] == 1) {
                    $mensaje = "Tu firma registrada está activa. Este proceso la anulará y creará una nueva para ti.";
                } else {
                    if ($firma["id_estado"] == 2) {
                        $mensaje = "Tu firma registrada está anulada. Este proceso creará una nueva para ti.";
                    } else {
                        if ($firma["id_estado"] == 3) {
                            $mensaje = "Tu firma registrada expiró. Este proceso creará una nueva para ti.";
                        } else {
                            $mensaje = "";
                        }
                    }
                }
            }
        }

        if ($figura_legal == "J") {
            $mensaje .= '<br/><b>Nota:</b> <i>Toda la información a ingresar deberá ser relativa al Representante Legal.</i>';
        }

        $texto_html_acuerdo_firma = "El Cliente acuerda y acepta la creación de su identificación de activación segura a la cual le otorga el valor de una firma electrónica, manifestando que conoce claramente que los medios tecnológicos señalados no guardan relación visual con su firma caligráfica, sin perjuicio de esto, le reconoce completa validez y eficacia de acuerdo a lo dispuesto por la Ley de Comercio Electrónico, Firmas Electrónicas y Mensajes de Datos por lo que declara y acepta irrevocablemente como suyos todos los actos derivados del uso de medios electrónicos, informáticos y telemáticos en su relación con ESDINAMICO CIA. LTDA.";
        $arreglo_llave_vector = $this->getArrayKeyVector();
        $texto_html_acuerdo_firma_encriptado = $this->Encriptar(
            $arreglo_llave_vector["llave"],
            $arreglo_llave_vector["vector"],
            $texto_html_acuerdo_firma
        );

        return view(
            "doc_electronicos.firmas.firma",
            array(
                "id_input_file_rl" => $id_input_file_rl,
                "id_input_file_ruc" => $id_input_file_ruc,
                "id_input_file_poder" => $id_input_file_poder,
                "figura_legal" => $figura_legal,
                "representante" => $representante,
                "cedula" => $cedula,
                "correo" => $correo,
                "telefono" => $telefono,
                "mensaje" => $mensaje,
                "texto_html_acuerdo_firma" => $texto_html_acuerdo_firma,
                "texto_html_acuerdo_firma_encriptado" => $texto_html_acuerdo_firma_encriptado,
                "tipo_identificacion_cliente" => $tipo_identificacion_cliente,
                "descripcionTipoIde" => $descripcionTipoIde
            )
        );
    }

    public function SubirPFXTemporal(Request $request)
    {
        return $this->SubirTemporal($request, "pfx");
    }

    public function SubirRLTemporal(Request $request)
    {
        return $this->SubirTemporal($request, "rl");
    }

    public function SubirRUCTemporal(Request $request)
    {
        return $this->SubirTemporal($request, "ruc");
    }

    public function SubirPoderTemporal(Request $request)
    {
        return $this->SubirTemporal($request, "poder");
    }

    private function getSubCarpetaPFXTemporal()
    {
        return sys_get_temp_dir() . "/doc_electronicos/pfx";
    }

    private function getNombreArchivoPFXTemporal($nombre)
    {
        return "Temp_PFX_" . $nombre . ".pfx";
    }

    private function getSubCarpetaRLTemporal()
    {
        return sys_get_temp_dir() . "/doc_electronicos/rl";
    }

    private function getNombreArchivoRLTemporal($nombre)
    {
        return "Temp_RL_" . $nombre . ".pdf";
    }

    private function getSubCarpetaRUCTemporal()
    {
        return sys_get_temp_dir() . "/doc_electronicos/ruc";
    }

    private function getNombreArchivoRUCTemporal($nombre)
    {
        return "Temp_RUC_" . $nombre . ".pdf";
    }

    private function getSubCarpetaPoderTemporal()
    {
        return sys_get_temp_dir() . "/doc_electronicos/poder";
    }

    private function getNombreArchivoPoderTemporal($nombre)
    {
        return "Temp_Poder_" . $nombre . ".pdf";
    }

    public function SubirTemporal(Request $request, $archivo)
    {
        try {
            Log::info("Iniciando SubirTemporal($archivo)");

            switch ($archivo) {
                case "pfx":
                {
                    $mime_type = "application/x-pkcs12";
                    $carpeta_destino_temporal = $this->getSubCarpetaPFXTemporal();
                    $archivo_destino = $this->getNombreArchivoPFXTemporal(date("U"));
                    break;
                }
                case "rl":
                {
                    $mime_type = "application/pdf";
                    $carpeta_destino_temporal = $this->getSubCarpetaRLTemporal();
                    $archivo_destino = $this->getNombreArchivoRLTemporal(date("U"));
                    break;
                }
                case "ruc":
                {
                    $mime_type = "application/pdf";
                    $carpeta_destino_temporal = $this->getSubCarpetaRUCTemporal();
                    $archivo_destino = $this->getNombreArchivoRUCTemporal(date("U"));
                    break;
                }
                case "poder":
                {
                    $mime_type = "application/pdf";
                    $carpeta_destino_temporal = $this->getSubCarpetaPoderTemporal();
                    $archivo_destino = $this->getNombreArchivoPoderTemporal(date("U"));
                    break;
                }
            }

            $file = $request->file('file');

            if (!$file->isFile()) {
                Log::error("SubirTemporal(): No existe el archivo");
                return response()->json('No existe archivo', 400);
            } else {
                if ($file->getClientMimeType() !== $mime_type) {
                    Log::error("SubirTemporal(): El archivo no tiene el formato correcto");
                    return response()->json('El archivo no tiene el formato correcto', 400);
                } else {
                    if (!file_exists($carpeta_destino_temporal)) {
                        mkdir($carpeta_destino_temporal, 0777, true);
                    }
                    $upload_success = $file->move($carpeta_destino_temporal, $archivo_destino);
                }
            }

            if (!$upload_success->isFile()) {
                Log::error("SubirTemporal(): Ocurrió un error cargando el archivo");
                return response()->json('Ocurrió un error cargando el archivo.', 400);
            } else {
                $camino_temporal = $carpeta_destino_temporal . "/" . $archivo_destino;
                return response()->json(
                    array(
                        "camino_temporal_$archivo" => $camino_temporal,
                        "id_cliente" => session()->get("id_cliente"),
                        "id_usuario" => session()->get("id_usuario")
                    ),
                    200
                );
            }
        } catch (Exception $e) {
            Log::error("SubirTemporal(): " . $e->getMessage() . " - " . $e->getTraceAsString());
            return response()->json($e->getMessage(), 400);
        }
    }

    public function guardarFirma(Request $request)
    {
        Log::info("Iniciando guardarFirma()");

        $Res = 0;
        $Mensaje = "";

        try {
            if ($Res >= 0) {
                $_id = null;
                $id_usuario = session()->get("id_usuario");
                $id_cliente = session()->get("id_cliente");

                $origen = (int)$request->input("SOrigenFirma");
                Filtrar($origen, "INTEGER");

                $figura_legal = $request->input("HiddenFiguraLegalFirma");
                Filtrar($figura_legal, "STRING");

                $camino_temporal["rl"] = $request->input("HiddenCaminoTemporalRL");
                Filtrar($camino_temporal["rl"], "STRING", "");

                $camino_temporal["ruc"] = $request->input("HiddenCaminoTemporalRUC");
                Filtrar($camino_temporal["ruc"], "STRING", "");

                $camino_temporal["poder"] = $request->input("HiddenCaminoTemporalPoder");
                Filtrar($camino_temporal["poder"], "STRING", "");

                $archivo_destino = array();
                $camino_destino = array();
                $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : "NO_IDENTIFICADA");
                $sistema_operativo = isset($_SERVER['HTTP_USER_AGENT']) ? getOS($_SERVER['HTTP_USER_AGENT']) : "";
                $navegador = isset($_SERVER['HTTP_USER_AGENT']) ? getBrowser($_SERVER['HTTP_USER_AGENT']) : "";
                $agente = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
            }


            if (!in_array($origen, array(1, 2)) || !in_array($figura_legal, array("N", "J"))) {
                $Res = -1;
                $Mensaje = "Datos incompletos";
            }

            if ($Res >= 0) {
                $file_rl = $request->file('file_rl');
                $file_ruc = $request->file('file_ruc');
                if (!empty($file_rl) && !empty($file_ruc)) {
                    if (!$file_rl->isFile()) {
                        $Res = -101;
                        $Mensaje = "No se recibió el archivo (Copia del nombramiento del Representante Legal).";
                    } else {
                        if (!$file_ruc->isFile()) {
                            $Res = -102;
                            $Mensaje = "No se recibió el archivo (Copia del RUC).";
                        } else {
                            if ($file_rl->getClientMimeType() !== "application/pdf") {
                                $Res = -103;
                                $Mensaje = "No se recibió un pdf correcto (Copia del nombramiento del Representante Legal).";
                            } else {
                                if ($file_ruc->getClientMimeType() !== "application/pdf") {
                                    $Res = -104;
                                    $Mensaje = "No se recibió un pdf correcto (Copia del RUC).";
                                } else {
                                    $camino_temporal["rl"] = $file_rl->getPathname();
                                    $camino_temporal["ruc"] = $file_ruc->getPathname();
                                }
                            }
                        }
                    }
                }
            }

            if ($Res >= 0 && !empty($camino_temporal["poder"])) {
                $file_poder = $request->file('file_poder');
                if (!empty($file_poder)) {
                    if (!$file_poder->isFile()) {
                        $Res = -105;
                        $Mensaje = "No se recibió el archivo (Poder General / Especial).";
                    } else {
                        if ($file_poder->getClientMimeType() !== "application/pdf") {
                            $Res = -106;
                            $Mensaje = "No se recibió un pdf correcto (Poder General / Especial).";
                        } else {
                            $camino_temporal["poder"] = $file_poder->getPathname();
                        }
                    }
                }
            }

            if ($Res >= 0) {
                $arr_key_vector = $this->getArrayKeyVector();
                $llave = $arr_key_vector["llave"];
                $vector = $arr_key_vector["vector"];
            }

            if ($Res >= 0 && $origen == 1) {
                $password = $request->input("PPasswordCA");
                Filtrar($password, "STRING", "");

                if (!$password) {
                    $password = "";
                }

                $camino_temporal["pfx"] = $request->input("Valor_1");
                Filtrar($camino_temporal["pfx"], "STRING", "");

                $email = $request->input("TEMailPJ");
                Filtrar($email, "EMAIL", "");

                $telefono = $request->input("TTelefonoPJ");
                Filtrar($telefono, "STRING", "");

                if (empty($password) || !EMailValido($email) || !CelularEcuadorValido($telefono)) {
                    $Res = -22;
                    $Mensaje = "Datos incompletos.";
                }

                if ($Res >= 0) {
                    $info = $this->getInfoCertificado($camino_temporal["pfx"], $password);
                    if ($info["Res"] < 0) {
                        $Res = -1;
                        $Mensaje = $info["Mensaje"];
                    }
                }

                if ($Res >= 0) {
                    $ahora = date("U");

                    if ($ahora < $info["info"]["desde"] || $ahora > $info["info"]["hasta"]) {
                        $Res = -2;
                        $Mensaje = "El certificado adjuntado no está vigente.";
                    }

                    $info["info"]["desde"] = new UTCDateTime(
                        DateTime::createFromFormat('U', $info["info"]["desde"])->getTimestamp() * 1000
                    );
                    $info["info"]["hasta"] = new UTCDateTime(
                        DateTime::createFromFormat('U', $info["info"]["hasta"])->getTimestamp() * 1000
                    );
                }

                if ($Res >= 0) {
                    $arr_firma = $info["info"];
                    $arr_firma["email"] = $email;
                    $identificacion = $arr_firma["identificacion"];
                }

                if ($Res >= 0) {
                    $momento_activada = new UTCDateTime(
                        DateTime::createFromFormat('d/m/Y H:i:s', date("d/m/Y H:i:s"))->getTimestamp() * 1000
                    );
                }
            } else {
                if ($Res >= 0 && $origen == 2) {
                    $codigo_verificacion = $request->input("TCodigo");
                    Filtrar($codigo_verificacion, "STRING", "");

                    $nombre = $request->input("TNombre");
                    Filtrar($nombre, "STRING", "");

                    $nombre = substr(
                        $nombre,
                        0,
                        50
                    );

                    $identificacion = $request->input("TCedula");
                    Filtrar($identificacion, "STRING", "");

                    $tipo_identificacion = $request->input("TipoIdentificacion");
                    Filtrar($tipo_identificacion, "STRING", "");

                    $email = $request->input("TEMail");
                    Filtrar($email, "EMAIL", "");

                    $telefono = $request->input("TTelefono");
                    Filtrar($telefono, "STRING", "");

                    $fecha_emision_cedula = $request->input("TFechaEmisionCedula");

                    if (isset($fecha_emision_cedula) && $fecha_emision_cedula != '' && $fecha_emision_cedula != null) {
                        list($day, $month, $year) = explode('/', $fecha_emision_cedula);
                        $fecha_emision_cedula = $year . '/' . $month . '/' . $day;
                    }

                    Filtrar($fecha_emision_cedula, "STRING", "");

                    $desde = new UTCDateTime(DateTime::createFromFormat('U', date("U"))->getTimestamp() * 1000);
                    $vigencia = Config::get('app.dias_validez_firma_personal_stupendo') * 24 * 60 * 60;
                    $hasta = new UTCDateTime(
                        (DateTime::createFromFormat('U', date("U"))->getTimestamp() + $vigencia) * 1000
                    );

                    $password = $request->input("PPassword");
                    Filtrar($password, "STRING", "");

                    $serial_number = (int)(substr(date("U"), -7) . random_int(10, 99));

                    $texto_acuerdo = $request->input("HiddenTextoHtmlAcuerdoFirma");
                    Filtrar($texto_acuerdo, "STRING", "");

                    if (empty($nombre) || !EMailValido($email) || !CelularEcuadorValido(
                            $telefono
                        ) || empty($texto_acuerdo) || ($tipo_identificacion == "Cédula de Identidad" && !FechaCorrecta(
                                $fecha_emision_cedula
                            ))) {
                        $Res = -2;
                        $Mensaje = "Datos incompletos.";
                    }

                    if ($Res >= 0) {
                        if (!ContrasenaCompleja($password)) {
                            $Res = -3;
                            $Mensaje = "La contraseña definida no cumple con los requisitos mínimos de complejidad, debe contener al menos 10 caracteres, con al menos una minúscula, una mayúscula y un número.";
                        }
                    }

                    if ($Res >= 0) {
                        if ($codigo_verificacion !== session()->get("de_codigo_verificacion_firma")) {
                            $Res = -1;
                            $Mensaje = "El código de verificación es incorrecto.";
                        }
                    }

                    if ($Res >= 0) {
                        $vigencia_segundos = 5 * 60;

                        if ((int)date("U") - (int)session()->get("de_inicio_verificacion_firma") > $vigencia_segundos) {
                            $Res = -2;
                            $Mensaje = "Expiró la validez del código de verificación.";
                        }
                    }

                    if ($Res >= 0) {
                        $ac = new AcuerdoController();
                        $ArrRes = $ac->FirmarAcuerdoGeneracionFirmaElectronica($texto_acuerdo)->getData();
                        $Res = $ArrRes->Res;
                        $Mensaje = $ArrRes->Mensaje;
                    }

                    if ($Res >= 0) {
                        $momento_activada = new UTCDateTime(
                            DateTime::createFromFormat('d/m/Y H:i:s', date("d/m/Y H:i:s"))->getTimestamp() * 1000
                        );
                    }

                    if ($Res >= 0) {
                        $arr_data = $this->CrearCertificadoStupendo(
                            $id_cliente,
                            $serial_number,
                            $identificacion,
                            $nombre,
                            $email,
                            $telefono,
                            $password,
                            $momento_activada
                        );
                        $camino_destino["pfx"] = $arr_data["camino"];
                        $archivo_destino["pfx"] = $arr_data["archivo"];
                        $public_key = $arr_data["public_key"];
                    }

                    if ($Res >= 0) {
                        $profundidad = "RSA-SHA512";
                        $arr_firma = array(
                            "serial_number" => $serial_number,
                            "nombre" => $nombre,
                            "identificacion" => $identificacion,
                            "email" => $email,
                            "desde" => $desde,
                            "hasta" => $hasta,
                            "profundidad" => $profundidad,
                            "public_key" => $public_key
                        );
                    }
                } else {
                    $Res = -100;
                    $Mensaje = "No llegó el origen de la firma";
                }
            }

            if ($Res >= 0) {
                $password_encriptado = $this->Encriptar($llave, $vector, $password);

                if (empty($password_encriptado)) {
                    $Res = -4;
                    $Mensaje = "No se pudo encriptar la contraseña.";
                }
            }

            if ($Res >= 0) {
                Firma::where("id_cliente", $id_cliente)->where("id_estado", 1)->update(['id_estado' => 2]);
            }

            if ($Res >= 0 && $figura_legal == "J") {
                if ($origen == 1) {
                    $carpeta_destino["pfx"] = storage_path() . "/doc_electronicos/pfx/$id_cliente/";
                }

                $carpeta_destino["rl"] = storage_path() . "/doc_electronicos/documentacion/RL/$id_cliente/";
                $carpeta_destino["ruc"] = storage_path() . "/doc_electronicos/documentacion/RUC/$id_cliente/";
                $carpeta_destino["poder"] = storage_path() . "/doc_electronicos/documentacion/PODER/$id_cliente/";

                foreach ($carpeta_destino as $key => $cd) {
                    if ($Res >= 0) {
                        if (!is_dir($cd)) {
                            mkdir($cd, 0777, true);
                        }

                        if ($key == "pfx") {
                            $extension = "pfx";
                        } else {
                            $extension = "pdf";
                        }

                        $archivo_destino[$key] = "$id_cliente.$extension";
                        $camino_destino[$key] = $carpeta_destino[$key] . "/" . $archivo_destino[$key];
                        $momento = date('U');
                        $registro_anterior = Firma::where("id_cliente", $id_cliente)->where(
                            "version",
                            $this->getMaxVersionFirma(
                                $id_cliente
                            )
                        )->first();

                        if ($registro_anterior) {
                            if (isset($registro_anterior["camino_$key"]) && !empty($registro_anterior["camino_$key"])) {
                                if (file_exists($camino_destino[$key])) {
                                    rename(
                                        $camino_destino[$key],
                                        substr($camino_destino[$key], 0, -4) . "(" . $momento . ").$extension"
                                    );
                                }
                                $registro_anterior["camino_$key"] = substr(
                                        $registro_anterior["camino_$key"],
                                        0,
                                        -4
                                    ) . "(" . $momento . ").$extension";
                                $registro_anterior->save();
                            }
                        }

                        if (!empty($camino_temporal[$key])) {
                            $upload_success = rename($camino_temporal[$key], $camino_destino[$key]);
                        } else {
                            $upload_success = true;
                        }

                        if (!$upload_success) {
                            $Res = -5;
                            $Mensaje = "Ocurrió un error moviendo el archivo $key.";
                        }
                    }
                }
            }

            $date = Carbon::now();
            $hora_min = $date->format('H:i');

            if ($Res >= 0) {
                $arr_firma["id_cliente"] = $id_cliente;
                $arr_firma["identificacion_cliente"] = Cliente::find($id_cliente)["identificacion"];
                $arr_firma["identificacion"] = $identificacion;
                $arr_firma["version"] = $this->getMaxVersionFirma($id_cliente) + 1;
                $arr_firma["origen"] = $origen;
                $arr_firma["figura_legal"] = $figura_legal;
                $arr_firma["telefono"] = $telefono;
                $arr_firma["camino_pfx"] = "doc_electronicos/pfx/$id_cliente/{$archivo_destino["pfx"]}";
                $arr_firma["camino_rl"] = ($figura_legal == "J") ? "doc_electronicos/documentacion/RL/$id_cliente/{$archivo_destino["rl"]}" : null;
                $arr_firma["camino_ruc"] = ($figura_legal == "J") ? "doc_electronicos/documentacion/RUC/$id_cliente/{$archivo_destino["ruc"]}" : null;
                $arr_firma["camino_poder"] = ($figura_legal == "J" && !empty($camino_temporal["poder"])) ? "doc_electronicos/documentacion/PODER/$id_cliente/{$archivo_destino["poder"]}" : null;
                $arr_firma["password"] = $password_encriptado;
                $arr_firma["id_usuario_crea"] = $id_usuario;
                $arr_firma["momento_activada"] = $momento_activada;
                $arr_firma["ip"] = $ip;
                $arr_firma["sistema_operativo"] = $sistema_operativo;
                $arr_firma["navegador"] = $navegador;
                $arr_firma["agente"] = $agente;
                $arr_firma["hora_notificacion"] = $hora_min;

                if ($figura_legal == "J") {
                    $arr_firma["id_estado"] = FirmaPorValidarEstadoEnum::POR_VALIDAR;
                    $firma = FirmaPorValidar::create($arr_firma);


                    if($firma) {
                        $camino_ruc = $firma["camino_ruc"];
                        $camino_rl = $firma["camino_rl"];
                        $camino_poder = $firma["camino_poder"];

                        $documentos1 = array('id_documento' => 1,
                                             'titulo' => "RUC",
                                             'camino_original' => $camino_ruc);
                        $documentos2 = array('id_documento' => 2,
                                             'titulo' => "Nombramiento",
                                             'camino_original' => $camino_rl);
                        $documentos3 = array('id_documento' => 3,
                                             'titulo' => "Poder",
                                             'camino_original' => $camino_poder);
                        if($camino_poder != null)
                        {$documentos = array($documentos1, $documentos2, $documentos3); } else { $documentos = array($documentos1, $documentos2);}


                    $data_proceso_firma = array(
                         "_id" => $firma->_id,
                         "identificacion" => $firma->identificacion,
                         "id_cliente" => $firma->id_cliente,
                         "nombre" => $firma->nombre,
                         "figura_legal" => $firma->figura_legal,
                         "camino_rl" => $firma->camino_rl,
                         "camino_ruc" => $firma->camino_ruc,
                         "camino_poder" => $firma->camino_poder,
                         "documentos" => $documentos,
                         "id_estado" => $firma->id_estado
                     );

                    $url_banner = Config::get('app.url') . '/email/img/banner_validacion_doc.jpg';

                        $result = $this->EnviarEnlaceValidacionDocFirmasJuri(
                         $data_proceso_firma,
                         $url_banner
                     );

                    }
                } else {
                    $arr_firma["id_estado"] = 1;
                    $firma = Firma::create($arr_firma);
                }

                if (!$firma) {
                    $Res = -6;
                    $Mensaje = "Ocurrió un error guardando la firma";
                } else {
                    $Res = 1;
                }
            }

            if ($Res >= 0) {
                if ($origen == 1) {
                    Auditoria::Registrar(4, $id_usuario, $id_cliente, $firma->_id, null, null, $momento_activada);
                } else {
                    if ($origen == 2) {
                        Auditoria::Registrar(3, $id_usuario, $id_cliente, $firma->_id, null, null, $momento_activada);
                    }
                }
            }

            if ($Res >= 0) {
                $Mensaje = "La firma electrónica fue registrada con éxito";
                Session::put("de_intentos_envio_sms", 0);
            } else {
                if (isset($firma)) {
                    $firma->delete();
                }

                foreach ($camino_destino as $cd) {
                    @unlink($cd);
                }
            }
        } catch (Exception $e) {
            $Res = -7;
            $Mensaje = $e->getMessage();
            Log::error("Res: $Res - Mensaje: $Mensaje " . $e->getTraceAsString());
        }

        $result = array(
            "Res" => $Res,
            "Mensaje" => $Mensaje,
            "Persona" => $figura_legal
        );

        return response()->json($result, 200);
    }

    public function getInfoCertificado($camino_certificado, $password = "")
    {
        $Res = 0;
        $Mensaje = "";
        $info = false;
        $array_info = array();

        try {
            if ($Res >= 0) {
                $certificado = file_get_contents($camino_certificado);

                if (empty($certificado)) {
                    $Res = -1;
                    $Mensaje = "No se pudo leer el certificado";
                }
            }

            if ($Res >= 0) {
                openssl_pkcs12_read($certificado, $info, $password);

                if (!$info) {
                    $Res = -2;
                    $Mensaje = "Certificado incorrecto o contraseña inválida.";
                }
            }

            if ($Res >= 0) {
                if (!isset($info["cert"])) {
                    $Res = -3;
                    $Mensaje = "El certificado no tiene información asociada.";
                }
            }

            if ($Res >= 0) {
                $data = openssl_x509_parse($info['cert']);

                if (!$data) {
                    $Res = -4;
                    $Mensaje = "No se pudo leer la información del certificado.";
                }
            }

            if ($Res >= 0) {
                $resource = openssl_pkey_get_public($info["cert"]);
                $key = openssl_pkey_get_details($resource);
                $public_key = trim(
                    str_replace(
                        ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\r\n", "\n"],
                        ['', '', "\n", ''],
                        $key["key"]
                    )
                );
            }

            if ($Res >= 0) {
                try {
                    $serial_number = isset($data["subject"]["serialNumber"]) ? $data["subject"]["serialNumber"] : "N/I";
                    $nombre = isset($data["subject"]["CN"]) ? $data["subject"]["CN"] : "N/I";
                    $identificacion = "N/I";
                    $index = 0;
                    $identificacion_encontrada = false;
                    $cant_extensions = count($data["extensions"]);
                    $arr_extensiones = array_values($data["extensions"]);

                    while (!$identificacion_encontrada && $index < $cant_extensions) {
                        $arr_valor = explode("\\", $arr_extensiones[$index]);
                        $posible_ruc = substr(array_pop($arr_valor), -13);
                        $posible_cedula = substr(array_pop($arr_valor), -10);

                        if (RUCValido($posible_ruc)) {
                            $identificacion_encontrada = true;
                            $identificacion = $posible_ruc;
                        } else {
                            if (CedulaValida($posible_cedula)) {
                                $identificacion_encontrada = true;
                                $identificacion = $posible_cedula;
                            } else {
                                $index++;
                            }
                        }
                    }
                    $email = isset($data["extensions"]["subjectAltName"]) ? $data["extensions"]["subjectAltName"] : "";
                    if (!empty($email)) {
                        $arr_email = explode(":", $email);
                        $email = array_pop($arr_email);
                    } else {
                        $email = "N/I";
                    }
                    $desde = isset($data["validFrom_time_t"]) ? $data["validFrom_time_t"] : "";
                    $hasta = isset($data["validTo_time_t"]) ? $data["validTo_time_t"] : "";
                    $profundidad = isset($data["signatureTypeSN"]) ? $data["signatureTypeSN"] : "";
                    $array_info = array(
                        "serial_number" => $serial_number,
                        "nombre" => $nombre,
                        "identificacion" => $identificacion,
                        "email" => $email,
                        "desde" => $desde,
                        "hasta" => $hasta,
                        "profundidad" => $profundidad,
                        "public_key" => $public_key
                    );
                } catch (Exception $e) {
                    $Res = -5;
                    $Mensaje = $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $Res = -6;
            $Mensaje = $e->getMessage();
        }
        return array("Res" => $Res, "Mensaje" => $Mensaje, "info" => $array_info);
    }

    public function getArrayKeyVector()
    {
        return array("llave" => "cf6ce210ecc0bc3a4e4f38db296ca61c", "vector" => "001f412ba81c8ec1");
    }

    public function Encriptar($llave, $vector, $cadena_a_encriptar, $metodo = "aes-256-cbc")
    {
        return base64_encode(openssl_encrypt($cadena_a_encriptar, $metodo, $llave, 0, $vector));
    }

    public function Desencriptar($llave, $vector, $cadena_a_desencriptar, $metodo = "aes-256-cbc")
    {
        return openssl_decrypt(base64_decode($cadena_a_desencriptar), $metodo, $llave, 0, $vector);
    }

    private function getMaxVersionFirma($id_cliente)
    {
        $max_version = 0;
        if (!empty($id_cliente)) {
            $firma = Firma::where("id_cliente", $id_cliente)->orderBy("version", "desc")->first(["version"]);
            if ($firma) {
                $max_version = $firma["version"];
            }
        }
        return (int)$max_version;
    }

    public function GenerarOpenSSL_CNF($carpeta_openssl_cnf, $camino_openssl_cnf)
    {
        if (!is_dir($carpeta_openssl_cnf)) {
            mkdir($carpeta_openssl_cnf, 0777, true);
        }
        if (!file_exists($camino_openssl_cnf)) {
            $openssl_cnf = fopen($camino_openssl_cnf, "a");
            fwrite($openssl_cnf, "[req]" . PHP_EOL);
            fwrite($openssl_cnf, "distinguished_name = req_distinguished_name" . PHP_EOL);
            fwrite($openssl_cnf, "[v3_ext]" . PHP_EOL);
            fwrite($openssl_cnf, "basicConstraints = CA:false" . PHP_EOL);
            fwrite($openssl_cnf, "keyUsage = digitalSignature, nonRepudiation, dataEncipherment" . PHP_EOL);
            fwrite($openssl_cnf, "subjectKeyIdentifier = hash" . PHP_EOL);
            fwrite($openssl_cnf, "subjectAltName = DNS:stupendo.ec" . PHP_EOL);
            fwrite($openssl_cnf, "[req_distinguished_name]");
            fclose($openssl_cnf);
        }
    }

    public function CrearCertificadoStupendo(
        $id_cliente,
        $serialNumber,
        $identificacion,
        $nombre,
        $email,
        $telefono,
        $password,
        $momento_activada
    ) {
        $variante = 2;
        $carpeta_openssl_cnf = storage_path("/doc_electronicos/openssl_cnf");
        $archivo_openssl_cnf = "openssl.cnf";
        $camino_openssl_cnf = $carpeta_openssl_cnf . "/" . $archivo_openssl_cnf;
        $this->GenerarOpenSSL_CNF($carpeta_openssl_cnf, $camino_openssl_cnf);

        $configargs = array
        (
            'config' => $camino_openssl_cnf,
            "digest_alg" => "sha512",
            "x509_extensions" => "v3_ext",
            "req_extensions" => "v3_ext",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
            "encrypt_key" => false,
            "encrypt_key_cipher" => OPENSSL_CIPHER_AES_256_CBC
        );

        $dn = array
        (
            "serialNumber" => $serialNumber,
            "member" => EncriptarId($id_cliente),
            "commonName" => $nombre,
            "surname" => $identificacion,
            "emailAddress" => $email,
            "telephoneNumber" => $telefono,
            "destinationIndicator" => "Momento creación: " . $momento_activada,
            "businessCategory" => "Plataforma Stupendo",
            "description" => "Certificado de Firma Electrónica Stupendo",
            "supportedApplicationContext" => "Firmar documentos dentro de la plataforma Stupendo",
            "countryName" => "EC",
            "stateOrProvinceName" => "Pichincha",
            "localityName" => "Quito",
            "organizationName" => "(ESDINAMICO CIA. LTDA.)",
            "organizationalUnitName" => "Stupendo"
        );

        $extraattribs = array();
        $private_key = openssl_pkey_new($configargs);
        $dias_validez = Config::get('app.dias_validez_firma_personal_stupendo');
        $csr = openssl_csr_new($dn, $private_key, $configargs, $extraattribs);

        if ($variante == 1) {
            $cacert = null;
            $priv_key = $private_key;
        } else {
            if ($variante == 2) {
                $certificado_stupendo = file_get_contents(storage_path(Config::get("app.path_firma_stupendo")));
                $password_stupendo = $this->getPasswordPlanoFirma("STUPENDO");
                openssl_pkcs12_read($certificado_stupendo, $info_stupendo, $password_stupendo);
                $cacert = $info_stupendo["cert"];
                $priv_key = $info_stupendo["pkey"];
            }
        }

        $sscert = openssl_csr_sign($csr, $cacert, $priv_key, $dias_validez, $configargs, $serialNumber);
        $resource = openssl_pkey_get_public($sscert);
        $key = openssl_pkey_get_details($resource);
        $public_key = trim(
            str_replace(
                ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\r\n", "\n"],
                ['', '', "\n", ''],
                $key["key"]
            )
        );
        openssl_x509_export($sscert, $certout);
        $args = array('extracerts' => array(0 => $cacert), 'friendly_name' => "Certificados adicionales");
        openssl_pkcs12_export($certout, $pfx, $private_key, $password, $args);

        $carpeta_pfx = storage_path("doc_electronicos/pfx/$id_cliente");
        if (!is_dir($carpeta_pfx)) {
            mkdir($carpeta_pfx, 0777, true);
        }
        $archivo_pfx = $id_cliente . ".pfx";
        $camino_pfx = $carpeta_pfx . "/" . $archivo_pfx;
        if (file_exists($camino_pfx)) {
            $momento = date('U');
            $camino_renombrado = substr($camino_pfx, 0, -4) . "($momento)" . substr($camino_pfx, -4);
            rename($camino_pfx, $camino_renombrado);
            $firma_anterior = Firma::where("id_cliente", $id_cliente)->where(
                "version",
                $this->getMaxVersionFirma($id_cliente)
            )->first();
            if ($firma_anterior) {
                $firma_anterior["camino_pfx"] = substr($firma_anterior["camino_pfx"], 0, -4) . "($momento)" . substr(
                        $firma_anterior["camino_pfx"],
                        -4
                    );
                $firma_anterior->save();
            }
        }
        file_put_contents($camino_pfx, $pfx);
        return array(
            "camino" => "doc_electronicos/pfx/$id_cliente/$archivo_pfx",
            "archivo" => $archivo_pfx,
            "public_key" => $public_key
        );
    }

    private function DebeEnviarSMS()
    {
        return Config::get('app.sms_anyway_active') && (session()->get("de_intentos_envio_sms")) <= 2;
    }

    public function EnviarCodigoFirma(Request $request)
    {
        $telefono = $request->input("telefono");
        Filtrar($telefono, "STRING", "");
        $correo = $request->input("email");
        Filtrar($correo, "EMAIL", "");
        $nombre = $request->input("nombre");
        Filtrar($nombre, "STRING", "");
        $ruc_emisor = $request->input("ruc_emisor");
        Filtrar($ruc_emisor, "STRING", null);
        return $this->SendCodigoFirma($nombre, true, $correo, $this->DebeEnviarSMS(), $telefono, $ruc_emisor);
    }

    private function SendCodigoFirma(
        $nombre = "",
        $via_email = true,
        $email = "",
        $via_sms = true,
        $Telefono = "",
        $ruc_emisor = null
    ) {
        $Res_Mail = 0;
        $Res_SMS = 0;
        $Mensaje_Mail = "";
        $Mensaje_SMS = "";
        Session::put("de_intentos_envio_sms", 1 + session()->get("de_intentos_envio_sms"));
        $codigo = $this->ObtenerCodigoNuevo();
        Session::put('de_codigo_verificacion_firma', $codigo);
        Session::put('de_inicio_verificacion_firma', (int)date("U"));

        $asunto = "STUPENDO. Código de verificación (Firma Electrónica)";

        if ($via_email) {
            $Res_Mail = 1;
            $correos = array($email);
            try {
                $mail_view = "emails.doc_electronicos.codigo_verificacion_firma";
                if (!empty($ruc_emisor)) {
                    $cliente = Cliente::where('identificacion', $ruc_emisor)->get()->first();
                    if ($cliente != null) {
                        $nombreEnmas = Preferencia::get_default_email_data($cliente->id, "de_email");
                        $correoEnmas = Preferencia::get_default_email_data($cliente->id, "de_enmascaramiento");

                        if (!isset($cliente->parametros->asunto_codigo_verificacion)) {
                            $asunto = "STUPENDO. Código de verificación (Firma Electrónica)";
                        } else {
                            $bienvenida = $cliente->parametros->asunto_codigo_verificacion;
                            $asunto = $bienvenida . "-" . "Código de verificación (Firma Electrónica)";
                        }

                        $mail_view = "emails.doc_electronicos.$ruc_emisor.codigo_verificacion_firma";
                        if (!$cliente->tieneVistaEmailPersonalizada($mail_view)) {
                            $mail_view = "emails.doc_electronicos.codigo_verificacion_firma";
                        }
                    }
                }
                $nombreEnmas = (isset($nombreEnmas) && !empty($nombreEnmas)) ? $nombreEnmas : Config::get(
                    'app.mail_from_name'
                );
                $correoEnmas = (isset($correoEnmas) && !empty($correoEnmas)) ? $correoEnmas : Config::get(
                    'app.mail_from_address'
                );

                Mail::send(
                    $mail_view,
                    array('nombre' => $nombre, 'codigo' => $codigo),
                    function ($message) use ($correos, $nombre, $correoEnmas, $nombreEnmas, $asunto) {
                        $message->from($correoEnmas, $nombreEnmas);
                        $message->to($correos, $nombre)->subject($asunto);
                    }
                );
            } catch (Exception $e) {
                $Res_Mail = -1;
                $Mensaje_Mail = "No se pudo enviar el correo a: $email<br/>";
            }
            if ($Res_Mail > 0) {
                $Mensaje_Mail = "Se envió el código al correo: $email<br/>";
            }
        }
        if ($via_sms) {
            $Res_SMS = 1;
            try {
                $sms = new SMSController();

                $sms->Enviar_SMS_Codigo_Verificacion($codigo, $Telefono, $nombre, $ruc_emisor);
            } catch (Exception $e) {
                $Res_SMS = -2;
                $Mensaje = "No se pudo enviar el SMS a: $Telefono";
            }
            if ($Res_SMS > 0) {
                $Mensaje_SMS = "Se envió el código al teléfono: $Telefono<br/>";
            }
        }
        return response()->json(
            array(
                "Res_Mail" => $Res_Mail,
                "Res_SMS" => $Res_SMS,
                "Mensaje_Mail" => $Mensaje_Mail,
                "Mensaje_SMS" => $Mensaje_SMS
            ),
            200
        );
    }

    public function ObtenerCodigoNuevo($cantidad_caracteres = 6)
    {
        $codigo = "";
        $caracteres = "23456789ABCDEFGHJKMNPQRSTUVWXYZ";
        for ($index = 0; $index < $cantidad_caracteres; $index++) {
            $codigo .= substr($caracteres, rand(0, strlen($caracteres) - 1), 1);
        }
        return "$codigo";
    }

    public function DescargarPFX($id_firma)
    {
        return response()->download(
            storage_path(Firma::find(DesencriptarId($id_firma))["camino_pfx"]),
            "Certificado de Firma Electrónica.pfx"
        );
    }

    public function DescargarRL($id_firma)
    {
        return response()->download(
            storage_path(Firma::find(DesencriptarId($id_firma))["camino_rl"]),
            "Nombramiento Representante Legal.pdf"
        );
    }

    public function DescargarRUC($id_firma)
    {
        return response()->download(
            storage_path(Firma::find(DesencriptarId($id_firma))["camino_ruc"]),
            "Copia del RUC.pdf"
        );
    }

    public function DescargarPoder($id_firma)
    {
        return response()->download(
            storage_path(Firma::find(DesencriptarId($id_firma))["camino_poder"]),
            "Poder General o Especial otorgado.pdf"
        );
    }

    public function MostrarFirma(Request $request)
    {
        $id_firma = DesencriptarId($request->input("Valor_1"));
        Filtrar($id_firma, "STRING");
        $firma = Firma::find($id_firma);
        $arretiquetas = array(
            "origen",
            "serial_number",
            "nombre",
            "representando",
            "cedula",
            "email",
            "telefono",
            "vigencia",
            "public_key",
            "profundidad",
            "estado",
            "nombre_usuario_crea",
            "momento_activada",
            "ip",
            "sistema_operativo",
            "navegador",
            "agente"
        );
        $origen = $firma["origen"] == 1 ? "Entidad Certificadora" : "Stupendo";
        $serial_number = $firma["serial_number"];
        $nombre = $firma["nombre"];
        $representando = Cliente::find($firma["id_cliente"])["nombre_identificacion"];
        if ($firma["figura_legal"] == "J") {
            $representando .= " (Persona Jurídica)";
        } else {
            $representando .= " (Persona Natural)";
        }
        $cedula = $firma["identificacion"];
        $email = $firma["email"];
        $telefono = $firma["telefono"];
        $vigencia = FormatearMongoISODate($firma["desde"], "d/m/Y") . " - " . FormatearMongoISODate(
                $firma["hasta"],
                "d/m/Y"
            );
        $public_key = $firma["public_key"];
        $profundidad = $firma["profundidad"];
        $color = ($firma["id_estado"] == 2) ? '#8a1f11' : '#777620';
        if ($firma["id_estado"] == 1) {
            $color = "#20A54C";
        }
        $estado = '<b style="color:' . $color . '">' . EstadoFirma::where("id_estado", $firma["id_estado"])->first(
                ["estado"]
            )["estado"] . '</b>';
        $momento_activada = FormatearMongoISODate($firma["momento_activada"]);
        $ip = $firma["ip"];
        $sistema_operativo = $firma["sistema_operativo"];
        $navegador = isset($firma["navegador"]) ? $firma["navegador"] : "";
        $agente = isset($firma["agente"]) ? $firma["agente"] : "";
        if (empty($firma["id_usuario_crea"])) {
            $nombre_usuario_crea = "N/I";
        } else {
            $uc = Usuarios::find($firma["id_usuario_crea"]);
            $nombre_usuario_crea = $uc->nombre . " (" . $uc->email . ")";
        }
        $arrvalores = array(
            $origen,
            $serial_number,
            $nombre,
            $representando,
            $cedula,
            $email,
            $telefono,
            $vigencia,
            $public_key,
            $profundidad,
            $estado,
            $nombre_usuario_crea,
            $momento_activada,
            $ip,
            $sistema_operativo,
            $navegador,
            $agente
        );
        return view("doc_electronicos.firmas.detalles_firma", array_combine($arretiquetas, $arrvalores));
    }

    public function AnularFirma(Request $request)
    {
        try {
            $Res = 0;
            $Mensaje = "";
            $id_firma = DesencriptarId($request->input("Valor_1"));
            Filtrar($id_firma, "STRING");
            if ($Res >= 0) {
                if (!TienePermisos(7, 3)) {
                    $Res = -1;
                    $Mensaje = "Su perfil de usuario no tiene permisos para anular firmas.";
                }
            }
            if ($Res >= 0) {
                $firma = Firma::find($id_firma);
                if (!$firma) {
                    $Res = -1;
                    $Mensaje = "Firma inexistente";
                }
            }
            if ($Res >= 0) {
                if (session()->get("id_cliente") != $firma->id_cliente) {
                    $Res = -2;
                    $Mensaje = "Firma ajena";
                }
            }
            if ($Res >= 0) {
                $firma->id_estado = 2;
                $firma->save();
                if (!$firma) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error anulando la firma";
                } else {
                    $Res = 1;
                }
            }
            if ($Res >= 0) {
                Auditoria::Registrar(5, null, null, $firma->_id);
            }
            if ($Res >= 0) {
                $Mensaje = "La firma fue anulada con éxito";
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function actualizar_estado_firmas()
    {
        try {
            $momento_referencia = Carbon::now()->addHours(2);
            @Firma::where("id_estado", 1)->where("hasta", "<", $momento_referencia)->update(["id_estado" => 3]);
        } catch (Exception $e) {
            @Log::error("actualizar_estado_firmas: " . $e->getMessage());
        }
    }

    public function GetOrigenFirma($id_cliente = null)
    {
        $origen = 0;
        if (empty($id_cliente)) {
            $id_cliente = session()->get("id_cliente");
        }
        $firma = Firma::where("id_cliente", $id_cliente)->orderBy("version", "desc")->first(["origen"]);
        if ($firma) {
            $origen = $firma["origen"];
            if (empty($origen)) {
                $origen = 0;
            }
        }
        return $origen;
    }

    public function GetEstadoFirma($id_cliente = null)
    {
        $id_estado = 0;
        $estado = "";
        $mensaje = "No tiene firma registrada";
        if (empty($id_cliente)) {
            $id_cliente = session()->get("id_cliente");
        }
        $firma = Firma::where("id_cliente", $id_cliente)->orderBy("version", "desc")->first(["id_estado"]);
        if ($firma) {
            $id_estado = $firma["id_estado"];
            $estado = EstadoFirma::where("id_estado", $firma["id_estado"])->first(["estado"])["estado"];
            switch ($id_estado) {
                case 1:
                {
                    $mensaje = "Su firma registrada está vigente.";
                    break;
                }
                case 2:
                {
                    $mensaje = "Su firma registrada está cancelada.";
                    break;
                }
                case 3:
                {
                    $mensaje = "Su firma registrada ha caducado.";
                    break;
                }
            }
        }
        return json_encode(
            array(
                "id_estado" => $id_estado,
                "estado" => $estado,
                "mensaje" => $mensaje
            )
        );
    }

    public function getPasswordPlanoFirma($actor, $id = null)
    {
        $password_plano = null;
        if (strtoupper($actor) == "STUPENDO") {
            $password_encriptado = Config::get("app.password_firma_stupendo");
        } else {
            $password_encriptado = Firma::where("id_cliente", $id)->where("id_estado", 1)->first(
                ["password"]
            )["password"];
        }
        if ($password_encriptado) {
            $arr_key_vector = $this->getArrayKeyVector();
            $password_plano = $this->Desencriptar(
                $arr_key_vector["llave"],
                $arr_key_vector["vector"],
                $password_encriptado
            );
        }
        return $password_plano;
    }

    public function MostrarConfirmarPasswordFirma(Request $request)
    {
        $ruc_emisor = $request->input("Valor_1");
        $id_cliente = session()->get("id_cliente");
        $email = "";
        $telefono = "";
        $nombre = "";
        $origen = 0;
        $firma = Firma::where("id_cliente", $id_cliente)->where("id_estado", 1)->first();
        if ($firma) {
            $email = $firma["email"];
            $telefono = $firma["telefono"];
            $nombre = $firma["nombre"];
            $identificacion = $firma["identificacion"];
            $origen = $firma["origen"];
        }
        $this->SendCodigoFirma($nombre, true, $email, $this->DebeEnviarSMS(), $telefono, $ruc_emisor);
        return view(
            "doc_electronicos.firmas.confirmar_password",
            array(
                "nombre" => $nombre,
                "telefono" => $telefono,
                "email" => $email,
                "identificacion" => $identificacion,
                "origen" => $origen,
                "mostrar_password" => (isset($firma["cancelado_manualmente"]) && $firma["cancelado_manualmente"] == true && $firma["id_estado"] == 1)
            )
        );
    }

    public function VerificarPasswordFirma(Request $request)
    {
        $id_cliente = session()->get("id_cliente");

        $password = $request->input("PPasswordFirmaPersonalUso");
        Filtrar($password, "STRING");

        $codigo_verificacion = $request->input("TCodigoFirmaPersonalUso");
        Filtrar($codigo_verificacion, "STRING");

        $arr_key_vector = $this->getArrayKeyVector();
        $llave = $arr_key_vector["llave"];
        $vector = $arr_key_vector["vector"];
        $password_encriptado = $this->Encriptar($llave, $vector, $password);
        $firmas = Firma::where("id_cliente", $id_cliente)->where("id_estado", 1)->where(
            "password",
            $password_encriptado
        )->get();
        $codigo_valido = true;
        $vigencia_segundos = 5 * 60;

        if (($codigo_verificacion !== session()->get("de_codigo_verificacion_firma")) || ((int)date("U") - (int)session(
                )->get("de_inicio_verificacion_firma") > $vigencia_segundos)) {
            $codigo_valido = false;
        }

        if (count($firmas) > 0 && $codigo_valido) {
            return 1;
        } else {
            return 0;
        }
    }

    public function ElegirPersona(Request $request)
    {
        return view("doc_electronicos.firmas.elegir_persona");
    }

    public function GetFirmaClienteJS(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $firma = Firma::where("id_cliente", $id_cliente)->where("id_estado", 1)->orderBy("version", "desc")->first();
        if (!$firma) {
            return 0;
        } else {
            if ((int)date("U") > (int)($firma["hasta"])->toDateTime()->format("U")) {
                return -1;
            } else {
                return 1;
            }
        }
    }

    public function SiEmailTelefonoDistintosEliminarFirma(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $email = $request->input("Valor_1");
        $telefono = $request->input("Valor_2");

        Log::info("Email recibido: $email - Telefono recibido: $telefono");

        $crear_nueva_firma = 1;

        $firma = Firma::where("id_cliente", $id_cliente)->where("id_estado", 1)->orderBy("version", "desc")->first();
        if ($firma) {
            if ($email != $firma["email"] || $telefono != $firma["telefono"]) {
                Log::info("Email firma actual: " . $firma["email"] . " - Telefono firma: " . $firma["telefono"]);
                $firma->id_estado = 2;
                $firma->save();
                return $crear_nueva_firma;
            } else {
                if ((int)date("U") > (int)($firma["hasta"])->toDateTime()->format("U")) {
                    return $crear_nueva_firma;
                }
            }
        } else {
            return $crear_nueva_firma;
        }
        return 0;
    }


    public function ComprobarFechaExpedicionCedula(Request $request)
    {
        if (!Config::get("app.rc_active")) {
            return response()->json(array("Res" => 1, "Mensaje" => "OK", "NombreRC" => ""), 200);
        }
        $Res = 0;
        $Mensaje = "";
        $nombre_rc = null;
        $wsdl = Config::get("app.rc_wsdl");
        $CodigoInstitucion = Config::get("app.rc_codigo_institucion");
        $CodigoAgencia = Config::get("app.rc_codigo_agencia");
        $Usuario = Config::get("app.rc_usuario");
        $Contrasenia = Config::get("app.rc_contrasenia");

        $NUI = $request->input("Valor_1");
        Filtrar($NUI, "STRING");

        $fechaIngresada = $request->input("Valor_2");

        if (isset($fechaIngresada) && $fechaIngresada != '' && $fechaIngresada != null) {
            list($day, $month, $year) = explode('/', $fechaIngresada);
            $fechaIngresada = $year . '/' . $month . '/' . $day;
        }

        Filtrar($fechaIngresada, "STRING");

        $tipo_identificacion = $request->input("Valor_3");
        $ruc_emisor = $request->input("Valor_4");

        Log::info("DE: Invocando ComprobarFechaExpedicionCedula($NUI, $fechaIngresada, $tipo_identificacion, $ruc_emisor)");

        $origen = "Registro Civil";
        $apiToken = "";
        $credencialesId = "";

        $resultado = null;

        if (!empty($ruc_emisor)) {
            $credenciales = CredencialesConsulta::where('identificacion', '=', $ruc_emisor)->first();
            if ($credenciales) {
                $apiToken = $credenciales->apiToken;
                $credencialesId = $credenciales->id_cliente;
            } else {
                $apiToken = "";
                $credencialesId = "";
            }
        }

        if (empty($tipo_identificacion)) {
            $tipo_identificacion = "05";
        }

        try {
            if ($Res >= 0) {
                if (empty($NUI)) {
                    $Res = -1;
                    $Mensaje = "No se recibió una cédula para validar.<br/>";
                }
            }
            if ($Res >= 0) {
                if (empty($fechaIngresada)) {
                    $Res = -2;
                    $Mensaje = "No se recibió una fecha de expedición para validar.<br/>";
                }
            }

            if ($tipo_identificacion == "05") {
                if ($Res >= 0) {
                    $cliente = new NusoapClient($wsdl, "wsdl");
                    $parametros = array(
                        'CodigoInstitucion' => $CodigoInstitucion,
                        'CodigoAgencia' => $CodigoAgencia,
                        'Usuario' => $Usuario,
                        'Contrasenia' => $Contrasenia,
                        'NUI' => $NUI
                    );
                    
                    if($cliente) {
                        $resultado = $cliente->call('BusquedaPorNui', $parametros);
                        Log::info("Registro de conexión con Registro Civil: $NUI");
                    }

                    if (!$resultado) {
                        $Res = 100;
                        $this->guardarConsultaRigistroCivilSMS($apiToken, 0, $NUI, $credencialesId, $origen);
                        $Mensaje = "No se pudo validar su información con el Registro Civil.<br/>";
                    } else {
                        if (!isset($resultado["return"])) {
                            $Res = 200;
                            $this->guardarConsultaRigistroCivilSMS($apiToken, 0, $NUI, $credencialesId, $origen);
                            $Mensaje = "No se recibió respuesta desde el Registro Civil.<br/>";
                        } else {
                            if (!isset($resultado["return"]["CodigoError"])) {
                                $Res = 1;
                                $this->guardarConsultaRigistroCivilSMS($apiToken, 1, $NUI, $credencialesId, $origen);
                                $Mensaje = "No se recibió una respuesta correcta desde el Registro Civil.<br/>";
                            } else {
                                if ((int)$resultado["return"]["CodigoError"] != 0) {
                                    $Res = 1;
                                    $this->guardarConsultaRigistroCivilSMS(
                                        $apiToken,
                                        1,
                                        $NUI,
                                        $credencialesId,
                                        $origen
                                    );
                                    $Mensaje = (isset($resultado["return"]["Error"])) ? ($resultado["return"]["Error"] . "<br/>") : ("El Registro Civil devolvió un error con estado {$resultado['return']['CodigoError']}. <br/>");
                                } else {
                                    if (!isset($resultado["return"]["FechaCedulacion"])) {
                                        $Res = 1;
                                        $this->guardarConsultaRigistroCivilSMS(
                                            $apiToken,
                                            1,
                                            $NUI,
                                            $credencialesId,
                                            $origen
                                        );
                                        $Mensaje = "El Registro Civil no devolvió la fecha de expedición de la cédula. No se puede validar.<br/>";
                                    } else {
                                        if (!FechaCorrecta($resultado["return"]["FechaCedulacion"], "d/m/Y")) {
                                            $Res = 700;
                                            $this->guardarConsultaRigistroCivilSMS(
                                                $apiToken,
                                                0,
                                                $NUI,
                                                $credencialesId,
                                                $origen
                                            );
                                            $Mensaje = "La fecha devuelta por el Registro Civil tiene un formato no identificado.<br/>";
                                        } else {
                                            if (trim($fechaIngresada) != trim($resultado["return"]["FechaCedulacion"])) {
                                                $Res = -7;
                                                $this->guardarConsultaRigistroCivilSMS(
                                                    $apiToken,
                                                    0,
                                                    $NUI,
                                                    $credencialesId,
                                                    $origen
                                                );
                                                $Mensaje = "Fecha de expedición ingresada es inválida, verificar la fecha de expedición de su última Cédula actualizada.<br/>";
                                            } else {
                                                $Res = 1;
                                                $Mensaje = "La fecha de expedición fue validada y es correcta.<br/>";
                                                $nombre_rc = "";

                                                $this->guardarConsultaRigistroCivilSMS(
                                                    $apiToken,
                                                    1,
                                                    $NUI,
                                                    $credencialesId,
                                                    $origen
                                                );

                                                if (isset($resultado["return"]["Nombre"])) {
                                                    if (strlen($resultado["return"]["Nombre"]) >= 5) {
                                                        $nombre_rc = $resultado["return"]["Nombre"];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $Res = 1;
                $Mensaje = "Se procede a crear la firma con el pasaporte indicado.<br/>";
                $nombre_rc = "Identificación del Exterior";
            }
            Log::info("DE Final sin exception: ComprobarFechaExpedicionCedula($fechaIngresada): $Res - $Mensaje - $nombre_rc");
        } catch (Exception $e) {
            $Res = -8;
            $Mensaje = $e->getMessage();
            $Mensaje = "Por favor comunicarse con el representante del emisor de su documentos electrónicos, ya que existe problema para validar la fecha de expedición de su documento de identidad" . $Mensaje;
            Log::error("DE ComprobarFechaExpedicionCedula(): " . print_r($resultado, true) . $e->getMessage() . " " . $e->getTraceAsString());
            //Agregado para dejar crear la firma si ocurre una excepción:
            $Res = 1;
            $Mensaje = "Se procede a crear la firma...<br/>";
            $nombre_rc = "";
        }

        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "NombreRC" => $nombre_rc), 200);
    }

    public function NotificacionNoValidacionRC(Request $request)
    {
        $nombre = $request->input('nombre');
        $mensaje = $request->input('mensaje');
        $email = $request->input('email');
        $identificacion = $request->input('identificacion');
        $ruc_aseguradora = "";
        $cliente = "ASEGURADO";
        $logo_email = "header2.jpg";

        $solic_vinculacion = SolicitudVinculacion::where('email', $email)->where('estado', 0)->first();

        if ($solic_vinculacion) {
            $ruc_aseguradora = $solic_vinculacion->aseguradora;
            $cliente = "ASEGURADO(A)";
        }

        $Brokers = Broker::Where('identificacion', $identificacion)->where('activo', true)->first();
        if ($Brokers) {
            $id_brokers = $Brokers->_id;
            $brokers_aseguradora = AseguradoraBroker::where('id_broker', $id_brokers)->first();

            if ($brokers_aseguradora) {
                $id_aseguradora = $brokers_aseguradora->id_aseguradora;
                $aseguradoras = Aseguradora::where('_id', $id_aseguradora)->first();
                if ($aseguradoras) {
                    $ruc_aseguradora = $aseguradoras->ruc;
                    $cliente = "BRÓKER";
                }
            }
        }

        $clientes_parametros = Cliente::where("identificacion", $ruc_aseguradora)->first();

        if ($clientes_parametros) {
            if (!isset($clientes_parametros->parametros->logo_email)) {
                $logo_email = "header2.jpg";
            } else {
                $logo_email = $clientes_parametros->parametros->logo_email;
            }
            if (!isset($clientes_parametros->parametros->notificacion_error_registrocivil)) {
                $notificacion_error_registrocivil = false;
            } else {
                $notificacion_error_registrocivil = $clientes_parametros->parametros->notificacion_error_registrocivil;
            }
        }


        if ($notificacion_error_registrocivil == true) {
            $arretiquetas = array("nombre", "url", "mensaje", "logo_email", "identificacion", "cliente");

            $url = Config::get('app.url');
            $correos = Config::get('app.correoanotificar');

            $arrvalores = array($nombre, $url, $mensaje, $logo_email, $identificacion, $cliente);

            Mail::send(
                'emails.poliza.notificacion_error_registro_civil',
                array_combine($arretiquetas, $arrvalores),
                function ($message) use ($correos, $nombre) {
                    $message->from(Config::get('app.mail_from_address'), Config::get('app.mail_from_name'));
                    $message->to($correos, $nombre)->subject('Bienvenido a SWEADEN');
                }
            );

            Mail::send(
                'emails.poliza.notificacion_error_registro_civil',
                array_combine($arretiquetas, $arrvalores),
                function ($message) use ($email, $nombre) {
                    $message->from(Config::get('app.mail_from_address'), Config::get('app.mail_from_name'));
                    $message->to($email, $nombre)->subject('Bienvenido a SWEADEN');
                }
            );
        }
    }

    public function notificar_vencimiento_firmas()
    {
        $dias_antelacion = 8;
        $ahora = Carbon::createFromFormat("d/m/Y H:i:s", date("d/m/Y H:i:s"));
        $desde_referencia = Carbon::createFromFormat("d/m/Y H:i:s", date("d/m/Y H:i:s"));
        $hasta_referencia = Carbon::createFromFormat("d/m/Y H:i:s", date("d/m/Y H:i:s"))->addDays($dias_antelacion);
        $firmas = Firma::where("id_estado", (int)1)->where("hasta", ">", $desde_referencia)->where(
            "hasta",
            "<=",
            $hasta_referencia
        )->get();
        $nc = new NotificacionDEController();
        $ruta = "/doc_electronicos/firmas";
        foreach ($firmas as $firma) {
            $id_cliente = $firma->id_cliente;
            $id_usuario = $firma->id_usuario_crea;
            $fecha_caducidad = FormatearMongoISODate($firma->hasta, "d/m/Y");
            $dias_restantes = abs($ahora->diffInDays(Carbon::createFromFormat("d/m/Y", $fecha_caducidad)));
            try {
                $texto = "Su firma registrada en Stupendo caduca en $dias_restantes días ($fecha_caducidad). Al caducar no podrá firmar ningún documento. No obstante, cuando guste puede generar o adjuntar una nueva firma en nuestra plataforma.";
                @$nc->CrearNotificacion($id_cliente, $id_usuario, "Firma próxima a caducar", $texto, 6, $ruta);
            } catch (Exception $e) {
                Log::error("DE: notificar_vencimiento_firmas: " . $e->getMessage());
            }
        }
    }

    public function EliminarFirmaElectronica(Request $request)
    {
        $cedula_cliente = $request->input('numero_identificacion');
        $mail = $request->input('mail');

        Firma::where('identificacion', $cedula_cliente)->where('email', $mail)
            ->where('id_estado', 1)
            ->update(['id_estado' => 2]);

        $data = [

            'data' => "Se cancelaron las firmas activas"
        ];

        return response()->json($data);
    }

    public function guardarConsultaRigistroCivilSMS($apiToken, $estado, $parametros, $credencialesId, $origen)
    {
        ConsultaDatofast::create(
            [
                'apiToken' => $apiToken,
                'id_cliente' => $credencialesId,
                'estado' => $estado,
                'identificacion' => $parametros,
                'servicio' => "Servicio de Firma Electronica",
                'origen' => $origen
            ]
        );
    }

    private function EnviarEnlaceValidacionDocFirmasJuri(
        $procesofirma,
        $banner_url
    ) {
        try {
            $Res = 0;
            $Mensaje = "";

            $compania = $procesofirma["nombre"];
            $identificacion = $procesofirma["identificacion"];


            $asunto = "Validación de documentos para la creación de firma de persona jurídica";
            $lista_documentos = "";
            $titulos_documentos = array();

            $index = 0;
            foreach ($procesofirma["documentos"] as $documento) {
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

            foreach ($procesofirma["documentos"]  as $documento) {
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
                        $procesofirma["_id"]  . "_" . $procesofirma["identificacion"]);

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
                    $data_mailgun["X-Mailgun-Variables"] = json_encode(["id_proceso" => $procesofirma["_id"]]);

                    $mail_view = 'emails.doc_electronicos.validacion_documentos_juridico';


                    $usuarioN1 = Config::get('app.email_soporte_firma');
                    $nombreN1 = Config::get('app.nombre_soporte');

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
                        Log::info("se envio el correo " . $Mensaje);
                    }
            if ($Res == 0) {
                $Mensaje = "Los documentos será validados por el equipo de Soporte de Stupendo.";
            }
            return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 200);
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
            Log::info("El error es " . $Mensaje);
            return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), 500);
        }
    }


}