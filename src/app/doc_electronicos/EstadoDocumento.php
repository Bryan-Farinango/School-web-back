<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class EstadoDocumento extends Eloquent

{
    protected $collection = 'de_enum_estados_documentos';
    protected $fillable = ['_id', 'id_estado', 'estado'];

    public static function get_options_estados_documentos($id_estado = -1, $index_0 = true, $texto_index_0 = 'Todos los estados')
    {
        $selected = ($id_estado == -1) ? ' selected="selected" ' : '';
        $options = $index_0 ? ('<option value="-1" ' . $selected . '>' . $texto_index_0 . '</option>') : '';
        $estados = EstadoDocumento::all(["id_estado", "estado"]);
        if (count($estados) > 0) {
            foreach ($estados as $estado) {
                $selected = ($estado["id_estado"] == $id_estado) ? ' selected="selected" ' : '';
                $options .= '<option ' . $selected . ' value="' . $estado["id_estado"] . '">' . $estado["estado"] . '</option>';
            }
        }
        return $options;
    }
}
