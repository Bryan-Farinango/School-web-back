<?php

use App\Cliente;
use App\Usuarios;

function isActiveRoute($route, $output = 'active')
{
    if (Route::currentRouteName() == $route) {
        return $output;
    }
}

function getCliente($fields = null)
{
    if (strlen(Session::get("id_cliente")) > 0) {
        if (isset($fields)) {
            return Cliente::where("_id", Session::get("id_cliente"))->first($fields);
        }
    } else {
        $user = Auth::user();
        if (!is_null($user)) {
            return Cliente::find($user->getClienteId());
        } else {
            return "false";
        }
    }


    return Cliente::where("_id", Session::get("id_cliente"))->first();
}

function docsPendientes()
{
    if (Auth::user()->hasPerfilInRol("Race", "Asignador")) {
        $dataMatch = ['receptor_id' => Session::get("id_cliente"), 'workflow' => null];
        return DB::collection('documentos_rec')->where($dataMatch)->count();
    } else {
        if (Auth::user()->hasPerfilInRol("Race", "Aprobador")) {
            $dataMatch = ['receptor_id' => Session::get("id_cliente"), 'usuarios' => Session::get('id_usuario')];
            return DB::collection('documentos_rec')->where($dataMatch)->count();
        } else {
            return "";
        }
    }
}

function getUsuario($fields = null)
{
    if (isset($fields)) {
        return Usuarios::where("_id", Session::get("id_usuario"))->first($fields);
    }

    return Usuarios::where("_id", Session::get("id_usuario"))->first(["email"]);
}

