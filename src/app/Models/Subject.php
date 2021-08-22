<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;
use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use DateTime;
use Illuminate\Support\Facades\Config;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use mikehaertl\pdftk\Pdf as PdftkPdf;


class Subject extends Model
{
    use HasFactory;

    protected $collection = 'asignaturas';

    protected $fillable = [
    '_id',
    'nombre_asignatura',
    'descripcion',
    'anio_escolar',
    'grado_id',
    'nombre_grado',
    'materias',
    'usuario_id',
    'nombre_profesor'
    ];

}
