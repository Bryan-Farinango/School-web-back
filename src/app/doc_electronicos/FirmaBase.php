<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class FirmaBase extends Eloquent
{
    protected $fillable = [
        '_id',
        'id_cliente',
        'identificacion_cliente',
        'version',
        'origen',
        'id_estado',
        'serial_number',
        'figura_legal',
        'identificacion',
        'nombre',
        'email',
        'telefono',
        'camino_pfx',
        'camino_rl',
        'camino_ruc',
        'camino_poder',
        'password',
        'desde',
        'hasta',
        'profundidad',
        'public_key',
        'id_usuario_crea',
        'momento_activada',
        'motivo_rechazo',
        'ip',
        'sistema_operativo',
        'navegador',
        'agente',
        'nro_notificaciones',
        'hora_notificacion'
    ];

    public function cliente()
    {
        return $this->belongsTo("", 'id_cliente');
    }
}