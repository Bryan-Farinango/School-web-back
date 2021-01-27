<?php

namespace App\doc_electronicos;

use Exception;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class TipoDeNotificacionDE extends Eloquent
{
    protected $collection = 'de_enum_tipos_notificacion';
    protected $fillable = ['_id', 'id_tipo', 'tipo'];

    public static function get_options_tipos_notificacion($id_tipo = -1, $index_0 = true, $texto_index_0 = 'Todos los tipos')
    {
        try {
            $selected = ($id_tipo == -1) ? ' selected="selected" ' : '';
            $options = $index_0 ? ('<option value="-1" ' . $selected . '>' . $texto_index_0 . '</option>') : '';
            $tipos = TipoDeNotificacionDE::all()->sortBy("id_tipo");
            foreach ($tipos as $tipo) {
                $selected = ($tipo["id_tipo"] == $id_tipo) ? ' selected="selected" ' : '';
                $options .= '<option ' . $selected . ' value="' . $tipo["id_tipo"] . '">' . $tipo["tipo"] . '</option>';
            }
        } catch (Exception $e) {
            $options = '';
        }
        return $options;
    }
}