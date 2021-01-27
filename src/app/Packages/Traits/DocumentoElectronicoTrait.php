<?php

namespace App\Packages\Traits;

use App\User;
use Auth;
use DB;
use Excel;
use PLOP;


/**
 * Trait que contiene los métodos necesarios para subir una imágen como Avatar, banner o logo.
 *
 */
trait DocumentoElectronicoTrait
{

    //recibe como argumento el array de firmantes y devuelve un array con el nombre de todos los firmantes
    public function getNombresFirmantes($firmantes)
    {
        $result = array();
        foreach ($firmantes as $firmante) {
            $detallesCompleto = $this->detallesIndividualesFirmantes($firmante);
            $result[] = $detallesCompleto['nombre'];
        }
        return $result;
    }

    //recibe como argumento un firmante y devuelve los datos del firmante como array
    public function detallesIndividualesFirmantes($firmante)
    {
        return array(
            'identificacion' => $firmante['identificacion'],
            'nombre' => $firmante['nombre'],
            'email' => $firmante['email'],
            'telefono' => $firmante['telefono'],
            'id_usuario' => EncriptarId($firmante["id_usuario"]),
        );
    }

    public function getTituloDocumento($proceso_o_plantilla, $id_documento)
    {
        foreach ($proceso_o_plantilla->documentos as $documento) {
            if ((int)$documento["id_documento"] == (int)$id_documento) {
                return $documento["titulo"];
                break;
            }
        }
        return (string)$id_documento;
    }

}  