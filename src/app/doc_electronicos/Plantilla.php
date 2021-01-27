<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Plantilla extends Eloquent
{
    public const STORAGE_LOCAL = 1;
    public const STORAGE_EXTERNO = 2;//RACKSPACE

    protected $collection = 'de_plantillas';
    protected $fillable = [
        '_id',
        'id_cliente',
        'id_usuario',
        'nombre_plantilla',
        'titulo_proceso',
        'cantidad_procesos',
        'storage',
        'documentos',
        'orden',
        'variante_aceptacion',
        'nombre_enmas',
        'correo_enmas',
        'cuerpo_email',
        'url_banner',
        'tipo_proceso',
        'firma_emisor',
        'firma_stupendo',
        'sello_tiempo',
        'referencia_paginas',
        'origen_exigido',
        'created_at',
        'updated_at'
    ];

    public function cliente_emisor()
    {
        return $this->belongsTo("App\Cliente", 'id_cliente');
    }

    public function get_tipo_proceso()
    {
        if (isset($this->tipo_proceso) && !empty($this->tipo_proceso)) {
            return $this->tipo_proceso;
        } else {
            return 'simple';
        }
    }

    public static function get_options_plantillas(
        $id_cliente = null,
        $tipo_proceso = null,
        $id_seleccionado = false
    ) {
        if (empty($id_cliente)) {
            $id_cliente = session()->get("id_cliente");
        }

        $selected = (!$id_seleccionado) ? ' selected="selected" ' : '';
        $options = '<option value="false" ' . $selected . '>Sin Plantilla</option>';

        if ($tipo_proceso == null) {
            $plantillas = Plantilla::where("id_cliente", $id_cliente);
        } else {
            $plantillas = Plantilla::where("id_cliente", $id_cliente)->where("tipo_proceso", $tipo_proceso);
        }

        $plantillas = $plantillas->get(["_id", "nombre_plantilla"]);

        if (count($plantillas) > 0) {
            foreach ($plantillas as $plantilla) {
                $selected = ($plantilla["_id"] == $id_seleccionado) ? ' selected="selected" ' : '';
                $options .= '<option ' . $selected . ' value="' . $plantilla["_id"] . '">' . $plantilla["nombre_plantilla"] . '</option>';
            }
        } else {
            $options = null;
        }
        return $options;
    }

    public function getDocumento($id_documento)
    {
        foreach ($this->documentos as $documento) {
            if ($documento['id_documento'] == $id_documento) {
                return $documento;
            }
        }
        return null;
    }
}