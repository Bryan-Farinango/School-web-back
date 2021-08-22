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


class Student extends Model
{
    use HasFactory;

    protected $collection = 'estudiantes';

    protected $fillable = [
        '_id',
        'nombres',
        'apellidos',
        'identificacion',
        'edad',
        'genero',
        'grado_id',
        'nombre_grado',
        'jornada',
        'usuario_id',
        'estado'
    ];

    /*
     * estados:
     * 0 PENDIENTE
     * 1 APROBADO
     */

}
