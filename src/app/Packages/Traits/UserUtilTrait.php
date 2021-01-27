<?php

namespace App\Packages\Traits;

use App\Usuarios;

/**
 * Trait que contiene métodos utiliitarios para obtener usuarios.
 *
 */
trait UserUtilTrait
{
    
    public function ObtenerAdministradoresDocElectronicos($id_cliente)
    {
        return Usuarios::where('clientes.cliente_id', $id_cliente)->where('clientes.perfiles.perfiles_rol.perfil', 'Administrador_DE')->get();
    }
}  