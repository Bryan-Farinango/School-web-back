<?php

namespace App\Packages\Exceptions;

use Exception;

final class InvalidEventHandler extends Exception
{
    public static function eventHandlingMethodDoesNotExist(object $eventHandler, string $methodName): self
    {
        $eventHandlerClass = get_class($eventHandler);
        $eventClass = get_class($event);

        return new static("Se intentó llamar al método `$methodName` en `$eventHandlerClass` para manejar un evento de clase `$eventClass` pero ese método no existe.");
    }

}