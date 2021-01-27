<?php

namespace App\doc_electronicos;

class SelloTiempoEnum
{
    public const NO_AGREGAR = 0;
    public const AGREGAR_DESDE_TSA = 2;   

    private static function ConstantsAndStrings()
    {
        return array(
            self::NO_AGREGAR => "No agregar sello de tiempo",
            self::AGREGAR_DESDE_TSA => "Agregar sello de tiempo desde un TSA"
        );
    }
}