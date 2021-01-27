<?php

namespace App\doc_electronicos;

class OrdenProcesoEnum
{
    public const PARALELO = 1;
    public const SECUENCIAL = 2;   

    private static function ConstantsAndStrings()
    {
        return array(
            self::PARALELO => "Paralelo (Los participantes son invitados simultáneamente)",
            self::SECUENCIAL => "Secuencial (Los participantes son invitados según el orden definido)"
        );
    }
}