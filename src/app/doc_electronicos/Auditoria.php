<?php

namespace App\doc_electronicos;

use App\Cliente;
use App\Usuarios;
use DateTime;
use Exception;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use MongoDB\BSON\UTCDateTime;


class Auditoria extends Eloquent
{
    protected $collection = 'de_auditoria';
    protected $fillable = [
        '_id',
        'id_usuario',
        'nombre_usuario',
        'id_cliente',
        'tipo_auditoria',
        'referencia_1',
        'referencia_2',
        'referencia_3',
        'momento',
        'ip',
        'sistema_operativo',
        'navegador',
        'agente',
        'referencia_asociado'
    ];

    public function usuario()
    {
        return $this->belongsTo("App\Usuarios", 'id_usuario');
    }

    public function cliente()
    {
        return $this->belongsTo("App\Cliente", 'id_cliente');
    }

    public function tipo_registro()
    {
        return $this->belongsTo("App\doc_electronicos\TipoDeAuditoriaDE", 'tipo_auditoria', 'id_tipo');
    }

    public static function Registrar(
        $tipo_auditoria,
        $id_usuario = null,
        $id_cliente = null,
        $referencia_1 = null,
        $referencia_2 = null,
        $referencia_3 = null,
        $momento = null,
        $referencia_asociado = null
    ) {
        $Res = 0;
        $Mensaje = "";
        if (empty($id_usuario)) {
            $id_usuario = session()->get("id_usuario");
        }
        if (empty($id_cliente)) {
            $id_cliente = session()->get("id_cliente");
        }
        try {
            if ($Res >= 0) {
                if (!in_array($tipo_auditoria, array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13))) {
                    $Res = -1;
                    $Mensaje = "Tipo de auditoría inexistente.";
                }
            }
            if ($Res >= 0) {
                $usuario = Usuarios::find($id_usuario);
                $cliente = Cliente::find($id_cliente);

                if (!$usuario || !$cliente) {
                    $Res = -2;
                    $Mensaje = "Usuario o Cliente inexistente.";
                } else {
                    $nombre_usuario = $usuario["nombre"];
                }
            }
            if ($Res >= 0) {
                $arr_auditoria =
                    [
                        "tipo_auditoria" => (int)$tipo_auditoria,
                        "id_usuario" => $id_usuario,
                        "nombre_usuario" => $nombre_usuario,
                        "id_cliente" => $id_cliente,
                        "referencia_1" => $referencia_1,
                        "referencia_2" => $referencia_2,
                        "referencia_3" => $referencia_3,
                        "momento" => empty($momento) ? (new UTCDateTime(
                            DateTime::createFromFormat('d/m/Y H:i:s', date("d/m/Y H:i:s"))->getTimestamp() * 1000
                        )) : $momento,
                        "ip" => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : "NO_IDENTIFICADA"),
                        "sistema_operativo" => isset($_SERVER['HTTP_USER_AGENT']) ? getOS(
                            $_SERVER['HTTP_USER_AGENT']
                        ) : "",
                        "navegador" => isset($_SERVER['HTTP_USER_AGENT']) ? getBrowser(
                            $_SERVER['HTTP_USER_AGENT']
                        ) : "",
                        "agente" => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "",
                        "referencia_asociado" => $referencia_asociado
                    ];
                $auditoria = Self::create($arr_auditoria);
                if (!$auditoria) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error guardando el registro de auditoría.";
                } else {
                    $Res = 1;
                    $Mensaje = "Registro de auditoría guardado correctamente";
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }
}