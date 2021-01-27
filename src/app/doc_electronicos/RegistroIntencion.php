<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class RegistroIntencion extends Eloquent

{
    protected $collection = 'intencion_compra';
    protected $fillable = [
        '_id',
        'ruc',
        'razon_social',
        'email_contacto',
        'telefono_contacto',
        'interes_emision',
        'interes_recepcion',
        'interes_pronto_pago',
        'interes_recaudos',
        'interes_poliza',
        'interes_documentos_electronicos'
    ];
}