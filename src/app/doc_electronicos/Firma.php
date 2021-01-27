<?php

namespace App\doc_electronicos;

class Firma extends FirmaBase
{
    protected $collection = 'de_firmas';
    
    public static function ultimaFirmaDelCliente($idCliente, $soloVigente = true)
    {
        $queryFirma = Firma::where("id_cliente", $idCliente);
        if ($soloVigente) {
            $queryFirma = $queryFirma->where("id_estado", 1);
        }
        return $queryFirma->orderBy("version","desc")->first();
    }
}