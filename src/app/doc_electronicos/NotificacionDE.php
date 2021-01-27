<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class NotificacionDE extends Eloquent
{
    protected $collection = 'de_notificaciones';
    protected $fillable = ['_id', 'id_cliente', 'id_usuario', 'titulo', 'texto', 'id_tipo', 'ruta', 'fecha_envio', 'leida', 'fecha_leida'];

    public function tipo_notificacion()
    {
        return $this->belongsTo("App\doc_electronicos\TipoDeNotificacionDE", 'id_tipo', 'id_tipo');
    }
}