<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\Acuerdo;
use App\doc_electronicos\Auditoria;
use App\doc_electronicos\Firma;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PerfilesController;
use App\Usuarios;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use MongoDB\BSON\UTCDateTime;

class AcuerdoController extends Controller
{
    public function __construct()
    {
    }

    public function FirmarAcuerdoGeneracionFirmaElectronica($texto)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            if ($Res >= 0) {
                if (empty($texto)) {
                    $Res = -1;
                    $Mensaje = "Texto de acuerdo vacío.";
                }
            }
            if ($Res >= 0) {
                $arr_acuerdo = Self::Traza(4);
                $arr_acuerdo["texto"] = $texto = $this->DesencriptarAcuerdo($texto);
                $acuerdo = Acuerdo::create($arr_acuerdo);
                if (!$acuerdo) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error guardando el acuerdo";
                } else {
                    $Res = 1;
                    $Mensaje = "Acuerdo Generación de Firma Electrónica Aceptado";
                }
            }
            if ($Res >= 0) {
                Auditoria::Registrar(2, null, null, $acuerdo->_id, null, null, $arr_acuerdo["momento"]);
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    private function Traza($tipo_acuerdo)
    {
        $arr_acuerdo = array();
        $arr_acuerdo["id_usuario_acepta"] = session()->get("id_usuario");
        $arr_acuerdo["id_cliente_acepta"] = session()->get("id_cliente");
        $arr_acuerdo["tipo_acuerdo"] = $tipo_acuerdo;
        $arr_acuerdo["id_usuario_destino"] = null;
        $arr_acuerdo["id_cliente_destino"] = null;
        $arr_acuerdo["momento"] = new UTCDateTime(DateTime::createFromFormat('d/m/Y H:i:s', date("d/m/Y H:i:s"))->getTimestamp() * 1000);
        $arr_acuerdo["ip"] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : "NO_IDENTIFICADA");
        $arr_acuerdo["sistema_operativo"] = isset($_SERVER['HTTP_USER_AGENT']) ? getOS($_SERVER['HTTP_USER_AGENT']) : "";
        $arr_acuerdo["navegador"] = isset($_SERVER['HTTP_USER_AGENT']) ? getBrowser($_SERVER['HTTP_USER_AGENT']) : "";
        $arr_acuerdo["agente"] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
        return $arr_acuerdo;
    }

    public function DesencriptarAcuerdo($texto)
    {
        $texto_html_acuerdo_encriptado = $texto;
        Filtrar($texto_html_acuerdo_encriptado, "STRING");
        $fc = new FirmasController();
        $arreglo_llave_vector = $fc->getArrayKeyVector();
        return $fc->Desencriptar($arreglo_llave_vector["llave"], $arreglo_llave_vector["vector"], $texto_html_acuerdo_encriptado);
    }
}