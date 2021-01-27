<?php

namespace App\doc_electronicos;

use App\Http\Controllers\doc_electronicos\ProcesoSimpleController;
use Illuminate\Support\Facades\Log;
use mikehaertl\pdftk\Pdf as PdfTk;
use PDF as PDF;

class ProcesoSimple extends ProcesoBase
{
    protected $collection = 'de_procesos_simples';
    protected $fillable = [
        '_id',
        'id_propio',
        'id_cliente_emisor',
        'id_usuario_emisor',
        'titulo',
        'id_estado_actual_proceso',
        'momento_emitido',
        'mensaje_email',
        'documentos',
        'firmantes',
        'historial',
        'storage',
        'orden',
        'variante_aceptacion',
        'via',
        'nombre_enmas',
        'correo_enmas',
        'cuerpo_email',
        'url_banner',
        'docs_agregar_auditoria',
        'ftp_filename'
    ];

    public function seDebeAgregarAuditoriaADocumentos()
    {
        if (isset($this->docs_agregar_auditoria)) {
            return $this->docs_agregar_auditoria == true;
        }
    }

    public function getCaminoADocumentoOriginal($id_documento)
    {
        $id_cliente_emisor = $this->id_cliente_emisor;
        $id_propio = $this->id_propio;
        $documento = $this->getDocumento($id_documento);
        $extension = $this->getExtensionDocumento($id_documento);
        $storage = $this->storage;

        if ($storage == ProcesoSimple::STORAGE_LOCAL) {
            $camino = storage_path($documento["camino_original"]);
        } else {
            $camino_original = $documento["camino_original"];
            $posicion = strpos($camino_original, "doc_electronicos/procesos_simples/cliente_");
            $camino = substr(
                    $camino_original,
                    0,
                    $posicion
                ) . "doc_electronicos/procesos_simples/cliente_$id_cliente_emisor/$id_propio/documentos/$id_documento.$extension";
        }
        return $camino;
    }

    public function getCaminoADocumentoActual($id_documento)
    {
        if ($this->seDebeAgregarAuditoriaADocumentos()) {
            $date = new \DateTime();
            $timestamp = $date->getTimestamp();

            $camino_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
                "doc_electronicos_simple" . DIRECTORY_SEPARATOR .
                $this->id_cliente_emisor . DIRECTORY_SEPARATOR .
                $this->id . DIRECTORY_SEPARATOR .
                $id_documento . "-" .
                $timestamp . DIRECTORY_SEPARATOR;

            if (!file_exists($camino_dir)) {
                mkdir($camino_dir, 0777, true);
            }

            $documento = $this->getDocumento($id_documento);

            $camino = $camino_dir . $documento['titulo'] . "." . $this->getExtensionDocumento($id_documento);
            $binaryContent = $this->getContenidoDocumento($id_documento);
            file_put_contents($camino, $binaryContent);
            return $camino;
        }
        return $this->getCaminoADocumentoOriginal($id_documento);
    }

    private function getExtensionDocumento($id_documento)
    {
        $documento = $this->getDocumento($id_documento);
        $arreglo = explode(".", $documento["camino_original"]);
        return array_pop($arreglo);
    }

    public function getContenidoDocumento($id_documento)
    {
        $camino = $this->getCaminoADocumentoOriginal($id_documento);
        $extension = $this->getExtensionDocumento($id_documento);

        try {
            if ($extension == "PDF" || $extension == "pdf") {
                if ($this->seDebeAgregarAuditoriaADocumentos()) {
                    $psc = new ProcesoSimpleController();

                    $pdf_aud = PDF::loadHTML($psc->AnexoAuditoriaDocumentoSimple($this->_id, $id_documento));
                    $tmp_file = tempnam(sys_get_temp_dir(), 'procesosimpledoc00');
                    $pdf_aud->save($tmp_file, true);

                    $merger = new PdfTk(
                        [
                            "O" => $camino,
                            "A" => $tmp_file
                        ]
                    );

                    $tmp_file_result = tempnam(sys_get_temp_dir(), 'procsimpledocres00');

                    $result = $merger->cat(1, "end", "O")
                        ->cat(1, "end", "A")
                        ->saveAs($tmp_file_result);

                    if ($result) {
                        $binaryContent = file_get_contents($tmp_file_result);
                        @unlink($tmp_file_result);
                        return $binaryContent;
                    } else {
                        Log::info('No se hizo merge de los PDF: ' . $result);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error(
                "Exception en ProcesoSimple->getContenidoDocumento($id_documento) [merge auditoría pdf]: " .
                $e->getMessage() . " - " . $e->getTraceAsString()
            );
        }

        return file_get_contents($camino);
    }
}