<?php

namespace App\Packages\Traits;

/**
 * Trait que contiene metodos para armar los detalles de un documento
 *
 * @package stupendo-eloquent
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
trait StupendoEloquentTrait
{
    /**
     * Conexión a cualquier instancia
     * @return QueryBuilder
     */
    public function scopeFromConnection($query, $connection)
    {
        return self::on($connection);
    }
}