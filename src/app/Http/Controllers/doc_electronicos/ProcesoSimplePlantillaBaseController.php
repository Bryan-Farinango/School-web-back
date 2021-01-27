<?php

namespace App\Http\Controllers\doc_electronicos;

use App;
use App\Http\Controllers\Controller;
use Config;
use Exception;
use Illuminate\Support\Facades\Log;

class ProcesoSimplePlantillaBaseController extends Controller
{
    public function __construct()
    {
    }

    public function GetExtension($proceso_simple, $id_documento)
    {
        $documento = $proceso_simple->getDocumento($id_documento);
        $arreglo = explode(".", $documento["camino_original"]);
        return array_pop($arreglo);
    }

    public function GetIcono($proceso_simple, $id_documento)
    {
        $extension = $this->GetExtension($proceso_simple, $id_documento);
        switch ($extension) {
            case "pdf":
            {
                $icono = 'pdf';
                break;
            }
            case "doc":
            {
                $icono = 'doc';
                break;
            }
            case "docx":
            {
                $icono = 'doc';
                break;
            }
            case "xls":
            {
                $icono = 'xls';
                break;
            }
            case "xlsx":
            {
                $icono = 'xls';
                break;
            }
            case "txt":
            {
                $icono = 'txt';
                break;
            }
            default:
            {
                $icono = 'file';
                break;
            }
        }
        return "$icono.png";
    }

    public function GetContentType($proceso_simple, $id_documento)
    {
        $extension = $this->GetExtension($proceso_simple, $id_documento);
        switch ($extension) {
            case "pdf":
            {
                $content_type = 'application/pdf';
                break;
            }
            case "doc":
            {
                $content_type = 'application/msword';
                break;
            }
            case "docx":
            {
                $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                break;
            }
            case "xls":
            {
                $content_type = 'application/vnd.ms-excel';
                break;
            }
            case "xlsx":
            {
                $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            }
            case "txt":
            {
                $content_type = 'text/plain';
                break;
            }
            default:
            {
                $content_type = '*';
                break;
            }
        }
        return $content_type;
    }

    public function reemplazarTags(
        $cuerpo,
        $titulo_proceso,
        $compania,
        $lista_participantes,
        $botones_accion,
        $enlace,
        $lista_documentos = null
    ) {
        try {
            $cuerpo = str_replace("NOMBRE_COMPANIA", $compania, $cuerpo);
            $cuerpo = str_replace("TITULO_PROCESO", $titulo_proceso, $cuerpo);
            $cuerpo = str_replace("LISTA_PARTICIPANTES", $lista_participantes, $cuerpo);
            $cuerpo = str_replace("BOTONES_ACCION", $botones_accion, $cuerpo);
            if ($lista_documentos) {
                $cuerpo = str_replace("LISTA_DOCUMENTOS", $lista_documentos, $cuerpo);
            }
            return $cuerpo;
        } catch (Exception $e) {
            $Res = -3;
            $Mensaje = $e->getMessage();
            Log::info("El error es " . $Mensaje . " el cuerpo que llega es este " . $cuerpo);
            return $cuerpo;
        }
    }


}