<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class WorkflowOut extends Eloquent

{
    protected $collection = 'de_workflows_out';
    protected $fillable = [
        '_id',
        'id_cliente',
        'id_usuario_edita',
        'nombre_workflow',
        'receptores',
        'logica_receptores',
        'logica_enlace',
        'revisores',
        'activo'
    ];
}
