<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class AdjuntoProceso extends Eloquent
{

    protected $collection = 'de_adjuntos_proceso';

    protected $fillable = [
        'nombre_doc',
        'url'
    ];
}
