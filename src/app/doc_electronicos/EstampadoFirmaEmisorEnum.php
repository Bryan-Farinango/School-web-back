<?php

namespace App\doc_electronicos;

class EstampadoFirmaEmisorEnum
{
    public const NO_ESTAMPAR = 0;
    public const ESTAMPAR_AL_INICIAR = 1;
    public const ESTAMPAR_AL_FINALIZAR = 2;   

    private static function ConstantsAndStrings()
    {
        return array(
            self::NO_ESTAMPAR => "No estampar firma emisora",
            self::ESTAMPAR_AL_INICIAR => "Al iniciar el proceso (Primera firma en estamparse)",
            self::ESTAMPAR_AL_FINALIZAR => "Al finalizar el proceso (Última firma en estamparse)"
        );
    }
}