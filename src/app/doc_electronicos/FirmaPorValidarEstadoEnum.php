<?php

namespace App\doc_electronicos;

class FirmaPorValidarEstadoEnum
{
    public const POR_VALIDAR = 1;
    public const ACEPTADA = 2;
    public const RECHAZADA = 3;

    private static function ConstantsAndStrings()
    {
        return array(
            self::POR_VALIDAR => "Por validar",
            self::ACEPTADA => "Aceptada",
            self::RECHAZADA => "Rechazada"
        );
    }

    public static function toString($estado)
    {
        $arr = Self::ConstantsAndStrings();
        if(array_key_exists($estado, $arr)) {
            return $arr[$estado];
        }
        return "";
    }
}