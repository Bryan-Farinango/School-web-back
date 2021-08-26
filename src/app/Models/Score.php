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


class Score extends Model
{
    use HasFactory;

    protected $collection = 'calificaciones';

    protected $fillable = [
        '_id',
        'estudiante_id',
        'grado_id',
        'materia_id',
        'usuario_id',
        'fecha_registro',
        'descripcion',
        'profesor_id',
        'primer_parcial',
        'segundo_parcial',
        'tercer_parcial',
        'quimestre',
        'estado'
    ];

}
