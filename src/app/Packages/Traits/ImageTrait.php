<?php

namespace App\Packages\Traits;

use App\Http\Controllers\AuditoriaController;
use App\User;
use Config;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\CountValidator\Exception;
use PDF;
use Session;

/**
 * Trait que contiene los métodos necesarios para subir una imágen como Avatar, banner o logo.
 *
 */
trait ImageTrait
{

    public function subirImagen(Request $request)
    {
        $Res = 0;
        $Mensaje = $returnImage = "";
        $status = 400;
        try {
            $tipo_logo = $request->get("tipo_logo");
            $file = $request->file('file');
            if (!$file->isFile()) {
                return response()->json(array("Res" => -1, "Mensaje" => "No existe el archivo."), $status);
            }

            $tipo = $file->getMimeType();
            if (!in_array($tipo, array("image/png", "image/jpeg"))) {
                return response()->json(array("Res" => -2, "Mensaje" => "El archivo no es una imagen admitida. (PNG o JPG)."), $status);
            }

            $carpeta_temporal = sys_get_temp_dir();
            if (Str::contains($tipo_logo, 'banner')) {
                $path = 'banner-';
            } else {
                $path = $tipo_logo . '-';
            }

            $camino_archivo = tempnam($carpeta_temporal, $path) . "." . $file->getClientOriginalExtension();
            $subido = $file->move($carpeta_temporal, $camino_archivo);

            if (!$subido->isFile()) {
                return response()->json(array("Res" => -3, "Mensaje" => "No se pudo cargar el archivo."), $status);
            }

            $bucket = DB::connection('mongodb')->getMongoDB()->selectGridFSBucket();
            $file_id = $bucket->uploadFromStream($camino_archivo, fopen($camino_archivo, "a+"));
            $cliente = getCliente();
            switch ($tipo_logo) {
                case 'avatar':

                    $cliente->avatar_id = $file_id;
                    $mensajeLog = "Avatar actualizado";
                    break;

                case 'logo':

                    $cliente->logo_id = $file_id;
                    $mensajeLog = "Logo actualizado";
                    break;

                case 'emailBanner':
                case 'banner':
                    $cliente->banner_id = $file_id;
                    $mensajeLog = "Banner actualizado";
                    break;

                case 'bannerSuperior':

                    $cliente->banner_superior = $file_id;
                    $mensajeLog = "Banner superior actualizado";
                    break;

                case 'bannerInferiorUpload':

                    $cliente->banner_inferior = $file_id;
                    $mensajeLog = "Banner inferior actualizado";
                    break;

                default:
                    return response()->json("Error en la imagen", 400);
                    break;
            }
            $cliente->save();

            $imageData = base64_encode(file_get_contents($camino_archivo));
            $returnImage = 'data:image/png;base64,' . $imageData;

            AuditoriaController::log($request->user(), "true", $mensajeLog);
        } catch (Exception $e) {
            return response()->json(array("Res" => -5, "Mensaje" => $e->getMessage()), $status);
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El $tipo_logo fue guardado con éxito.";
            $status = 200;
        } else {
            $status = 400;
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje, "Imagen" => $returnImage), $status);
    }

    public static function crearNuevaImagen($bucket, $image_id, $path)
    {
        $downloadStream = $bucket->openDownloadStream(new \MongoDB\BSON\ObjectID($image_id));
        $stream = stream_get_contents($downloadStream, -1);
        $ifp = fopen($path, "a+");
        fwrite($ifp, $stream);
        fclose($ifp);
    }

    public static function getImage($cliente, $imageType, $establecimiento = null)
    {
        try {
            $return_image = '';
            $image_id = self::getImageId($cliente, $imageType, $establecimiento);

            if ($image_id) {
                $bucket = \DB::connection('mongodb')->getMongoDB()->selectGridFSBucket();
                $file_metadata = $bucket->findOne(["_id" => new \MongoDB\BSON\ObjectID($image_id)]);
                $path = $file_metadata->filename;

                if ($imageType == 'avatar' || $imageType == 'logo') {
                    if (!file_exists($path)) {
                        self::crearNuevaImagen($bucket, $image_id, $path);
                    }
                    $return_image = 'data:image/png;base64,' . base64_encode(file_get_contents($path));
                } else {
                    if (Str::contains($imageType, 'banner')) {
                        $filename = substr($path, 5);
                        $path = public_path() . Config::get('app.dir_banner') . $filename;

                        if (!file_exists($path)) {
                            \Log::info("Se crea una nueva imagen");
                            self::crearNuevaImagen($bucket, $image_id, $path);
                        }
                        $return_image = Config::get('app.url') . Config::get('app.dir_banner') . $filename;
                    }
                }
            } else {
                $return_image = self::getDefaultPath($imageType);
            }
        } catch (\Exception $e) {
            $cliente_id = isset($cliente->_id) ? $cliente->_id : "Indefinido";
            \Log::error(
                "Error al buscar el logo del cliente id=> "
                . $cliente_id . "Excepcion: " . $e->getMessage()
                . " - "
            );
            $return_image = self::getDefaultPath($imageType);
        }
        return $return_image;
    }

    public static function getImageId($cliente, $type, $establecimiento = null)
    {
        $image_id = null;
        switch ($type) {
            case 'avatar':
                //Verifico si el establecimiento tiene logo
                if ($establecimiento && $establecimiento->avatar_id) {
                    $image_id = $establecimiento->avatar_id;
                } // Si no tiene, verifico si el cliente tiene logo
                else {
                    if ($cliente->avatar_id) {
                        $image_id = $cliente->avatar_id;
                    }
                }
                break;

            case 'logo':
                if ($establecimiento && $establecimiento->logo_id) {
                    $image_id = $establecimiento->logo_id;
                } else {
                    if ($cliente->logo_id) {
                        $image_id = $cliente->logo_id;
                    }
                }
                break;

            case 'emailBanner':
            case 'banner':

                if ($establecimiento && $establecimiento->banner_id) {
                    $image_id = $establecimiento->banner_id;
                } else {
                    if ($cliente->banner_id) {
                        $image_id = $cliente->banner_id;
                    }
                }
                break;

            case 'bannerSuperior':
                if ($cliente->banner_superior) {
                    $image_id = $cliente->banner_superior;
                }
                break;

            case 'bannerInferior':
                if ($cliente->banner_inferior) {
                    $image_id = $cliente->banner_inferior;
                }
                break;
            default:
                break;
        }
        return $image_id;
    }

    public static function getDefaultPath($type)
    {
        if (Str::contains($type, 'banner')) {
            return Config::get('app.banner');
        } else {
            if ($type == 'avatar') {
                return '/img/perfil-image.png';
            } else {
                if ($type == 'logo') {
                    return \Config::get('app.logo');
                } else {
                    return null;
                }
            }
        }
    }

    public function actualizarVista(Request $request)
    {
        $tipo_logo = $request->get("Valor_1");
        if ($tipo_logo == 'banner') {
            $tipo_logo == 'emailBanner';
        }
        $cliente = getCliente();

        $camino_actual = self::getImage($cliente, $tipo_logo);
        return '<img src="' . $camino_actual . '" style="margin:20px; max-height: 180px; max-width: 300px" />';
    }

    public function deleteImage(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $user = $request->user();
            $cliente = getCliente();
            $tipo_logo = $request->get("Valor_1");
            if ($tipo_logo == "banner") {
                $tipo_logo = "emailBanner";
            }

            switch ($tipo_logo) {
                case 'avatar':

                    $cliente->avatar_id = null;
                    AuditoriaController::log($user, "true", "Avatar eliminado");
                    break;

                case 'logo':

                    $cliente->logo_id = null;
                    AuditoriaController::log($user, "true", "Logo eliminado");
                    break;

                case 'emailBanner':

                    $cliente->banner_id = null;
                    AuditoriaController::log($user, "true", "Banner eliminado");
                    break;

                case 'bannerSuperior':

                    $cliente->banner_superior = null;
                    AuditoriaController::log($user, "true", "Banner eliminado");
                    break;

                case 'bannerInferior':

                    $cliente->banner_inferior = null;
                    AuditoriaController::log($user, "true", "Banner eliminado");
                    break;

                default:
                    $image = null;
                    break;
            }

            $cliente->save();
        } catch (Exception $e) {
            $Res = -5;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El $tipo_logo fue eliminado con éxito.";
            $status = 200;
        } else {
            $status = 400;
        }
        return response()->json(array("Res" => $Res, "Mensaje" => $Mensaje), $status);
    }
}  