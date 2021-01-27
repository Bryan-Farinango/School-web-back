<?php

namespace App\doc_electronicos;

use App\Usuarios;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class WorkflowIn extends Eloquent

{
    protected $collection = 'de_workflows_in';
    protected $fillable = ['_id', 'id_cliente', 'id_usuario_edita', 'nombre_workflow', 'emisores', 'permite_lectura', 'revisores', 'activo'];

    public static function get_opciones_revisores($id_usuario = -1, $index_0 = true, $texto_index_0 = 'Todos los usuarios')
    {
        $selected = ($id_usuario == -1) ? ' selected="selected" ' : '';
        $options = $index_0 ? ('<option value="-1" ' . $selected . '>' . $texto_index_0 . '</option>') : '';
        $id_cliente = session()->get("id_cliente");
        $usuarios = Usuarios::where("clientes.cliente_id", $id_cliente)->where("clientes.perfiles.rol_cliente", "DocumentosElectronicos")->orderBy("nombre", "asc")->get();
        foreach ($usuarios as $usuario) {
            $selected = ($usuario["id_usuario"] == $id_usuario) ? ' selected="selected" ' : '';
            $options .= '<option value="' . $usuario["_id"] . '" ' . $selected . '>' . $usuario["nombre"] . '</option>';
        }
        return $options;
    }
}