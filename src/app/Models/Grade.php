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


class Grade extends Model
{
    use HasFactory;

    protected $collection = 'grados';

    protected $fillable = [
        'nombre_grado',
        'jornada',
        'descripcion',
        'rango_edad'
    ];



}
