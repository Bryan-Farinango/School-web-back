<?php

namespace App\doc_electronicos;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class PeriodoPrueba extends Eloquent
{
    const DIAS_DE_PRUEBA = 90;
    protected $collection = 'de_periodo_pruebas';
    protected $fillable = ['_id', 'id_cliente', 'id_usuario', 'momento_inicia_pruebas'];
}