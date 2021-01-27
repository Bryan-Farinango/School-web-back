<?php

namespace App\Packages\Utilities;

use App\Feriado;
use Carbon\Carbon;

class StupendoCalendar extends Carbon
{

    private $holidays = [];

    public function nextBusinessDay(): Carbon
    {
        if (!$this->holidays) {
            $nextBusinessDay = Carbon::now()->addDay();

            $feriados = Feriado::where('annio', '>=', $nextBusinessDay->year)->get();
            $dates = [];

            $feriados->each(
                function ($feriado) use (&$dates) {
                    foreach ($feriado->fecha_no_laborable as $fecha_no_laborable) {
                        $dates[] = $fecha_no_laborable;
                    }
                }
            );

            $this->holidays = $dates;
        }

        $businessDayFound = false;

        while (!$businessDayFound) {
            //verificacion de fecha, que no sea un dia feriado y tampoco fin de semana
            if (in_array($nextBusinessDay->format('d/m/Y'), $this->holidays) || $nextBusinessDay->isWeekend()) {
                $nextBusinessDay->addDay();
            } else {
                // ok, lo encontramos, salimos del while
                $businessDayFound = true;
            }
        }

        return $nextBusinessDay;
    }
}