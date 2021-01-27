<?php

namespace App\doc_electronicos;

class OrigenFirmasExigidoEnum
{
    public const TODAS = 0;
    public const SOLO_CA = 1;
    public const SOLO_STUPENDO = 2;

    private static function ConstantsAndStrings()
    {
        return array(
            self::TODAS => "Admitir firmas Stupendo y firmas CA (Autoridades Certificadoras)",
            self::SOLO_CA => "Admitir solamente firmas CA (Autoridades Certificadoras)",
            self::SOLO_STUPENDO => "Admitir solamente firmas Stupendo"
        );
    }
}