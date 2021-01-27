<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Acuerdo extends Eloquent

{
    protected $collection = 'de_acuerdos';
    protected $fillable = [
        '_id',
        'id_usuario_acepta',
        'id_cliente_acepta',
        'id_usuario_destino',
        'id_cliente_destino',
        'tipo_acuerdo',
        'texto',
        'momento',
        'ip',
        'sistema_operativo',
        'navegador',
        'agente'
    ];

    /*
     *  TIPOS DE ACUERDO
     *
     *  1. Acuerdo usuario_acepta con Stupendo.
     *  2. Acuerdo usuario_acepta con cliente_detino
     *  3. Acuerdo usuario_acepta (juridico) con cliente_destino (juridico)
     *
     * */

    public static function TieneAcuerdo($usuario_o_cliente, $id_acepta, $tipo_acuerdo, $id_cliente_destino = null) // $usuario_o_cliente 1. usuario; 2. cliente;
    {
        if ($usuario_o_cliente == 1) {
            $acuerdos = Acuerdo::where("id_usuario_acepta", $id_acepta);
        } else {
            if ($usuario_o_cliente == 2) {
                $acuerdos = Acuerdo::where("id_cliente_acepta", $id_acepta);
            } else {
                return false;
            }
        }
        if ($tipo_acuerdo == 1) {
            $acuerdos = $acuerdos->where("tipo_acuerdo", 1);
        } else {
            $acuerdos = $acuerdos->where("id_cliente_destino", $id_cliente_destino);
        }
        $acuerdos = $acuerdos->get();
        return (count($acuerdos) > 0);
    }

    public static function UsuarioTieneAcuerdoConStupendo($id_usuario_acepta)
    {
        return Acuerdo::TieneAcuerdo(1, $id_usuario_acepta, 1);
    }

    public static function UsuarioTieneAcuerdoConJuridico($id_usuario_acepta, $id_cliente_destino)
    {
        return Acuerdo::TieneAcuerdo(1, $id_usuario_acepta, 3, $id_cliente_destino);
    }

    public static function ClienteTieneAcuerdoConStupendo($id_cliente_acepta)
    {
        return Acuerdo::TieneAcuerdo(2, $id_cliente_acepta, 1);
    }

    public static function ClienteTieneAcuerdoConClienteEmisor($id_cliente_acepta, $id_cliente_destino)
    {
        return Acuerdo::TieneAcuerdo(2, $id_cliente_acepta, 2, $id_cliente_destino);
    }

    public static function ClienteTieneAcuerdoConJuridico($id_cliente_acepta, $id_cliente_destino)
    {
        return Acuerdo::TieneAcuerdo(2, $id_cliente_acepta, 3, $id_cliente_destino);
    }
}
