<?php

namespace App\doc_electronicos;

class FirmaPorValidar extends FirmaBase
{
    protected $collection = 'de_firmas_por_validar';
    
    public static function ultimaFirmaDelCliente($idCliente)
    {
        $queryFirma = Self::where("id_cliente", $idCliente);
        return $queryFirma->orderBy("version","desc")->first();
    }
}
