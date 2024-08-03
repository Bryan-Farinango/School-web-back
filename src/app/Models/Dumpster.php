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



class Dumpster extends Model
{

    protected $collection = 'dumpster';

    protected $fillable = [
        'address',
        'anonymous',
        'residency_status',
        'country',
        'bintypetrash',
        'bincondition',
        'comments',
        'binsize',
        'email'
    ];



}
