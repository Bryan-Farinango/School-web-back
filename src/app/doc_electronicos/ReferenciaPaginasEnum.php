<?php

namespace App\doc_electronicos;

class ReferenciaPaginasEnum
{
    public const NO_AGREGAR = 0;
    public const AGREGAR_EN_TODAS = 1;   

    private static function ConstantsAndStrings()
    {
        return array(
            self::NO_AGREGAR => "No agregar referencia Stupendo en páginas originales",
            self::AGREGAR_EN_TODAS => "Agregar referencia Stupendo en todas las páginas"
        );
    }
}