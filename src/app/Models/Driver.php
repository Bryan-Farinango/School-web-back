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


class Driver extends Model
{
    use HasFactory;

    protected $collection = 'transporte';

    protected $fillable = [
        'nombres',
        'apellidos',
        'email',
        'capacidad',
        'rol',
        'telefono',
        'experiencia_laboral',
        'password',
        'temporal_password',
        'firebase_uid'
    ];



}
