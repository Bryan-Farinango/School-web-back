<?php

namespace App\doc_electronicos;

class EstadoProcesoEnum
{
    public const ORIGINAL_REVISION = 0;
    public const EN_CURSO = 1;
    public const COMPLETADO = 2;
    public const RECHAZADO = 3;
    public const ANULADO = 4;

    private static function ConstantsAndStrings()
    {
        return array(
            self::ORIGINAL_REVISION => "Orig./Revisión",
            self::EN_CURSO => "En curso",
            self::COMPLETADO => "Completado",
            self::RECHAZADO => "Rechazado",
            self::ANULADO => "Anulado"
        );
    }

    public static function toString($estadoProceso)
    {
        $arr = EstadoProcesoEnum::ConstantsAndStrings();
        if(array_key_exists($estadoProceso, $arr)) {
            return $arr[$estadoProceso];
        }
        return "";
    }

    public static function getOptionsEstadosProcesos($id_estado = -1, $index_0 = true, $texto_index_0 = 'Todos los estados')
    {
        $selected = ($id_estado == -1) ? ' selected="selected" ' : '';
        $options = $index_0 ? ('<option value="-1" ' . $selected . '>' . $texto_index_0 . '</option>') : '';
        $cs = self::ConstantsAndStrings();
        foreach ($cs as $c => $s) {
            $selected = ($c == $id_estado) ? ' selected="selected" ' : '';
            $options .= '<option ' . $selected . ' value="' . $c . '">' . $s . '</option>';
        }
        return $options;
    }
}