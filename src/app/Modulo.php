<?php

namespace App;

use App\Http\Controllers\NavigationController;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Modulo extends Eloquent
{
    protected $collection = 'modulos';
    protected $fillable = [
        '_id',
        'id_modulo',
        'modulo',
        'nombre_modulo',
        'icono_barra_lateral',
        'icono_barra_superior',
        'icono_menu_modulos',
        'menus',
        'grupos_permisos',
        'perfiles_fijos',
        'en_venta',
        'interno',
        'ruta_ayuda'
    ];

    public static function get_modulo_by_id_modulo($id_modulo)
    {
        return Modulo::where("id_modulo", (int)$id_modulo)->first();
    }

    public static function get_modulo_old_name_by_id_modulo($id_modulo)
    {
        $modulo = Self::get_modulo_by_id_modulo($id_modulo);
        return $modulo ? $modulo->modulo : null;
    }

    public static function get_id_modulo_by_old_name($modulo_old_name)
    {
        $modulo = Modulo::where("modulo", $modulo_old_name)->first(["id_modulo"]);
        return $modulo ? $modulo->id_modulo : null;
    }

    public static function get_nombre_modulo_by_id_modulo($id_modulo)
    {
        $modulo = Self::get_modulo_by_id_modulo($id_modulo);
        return $modulo ? $modulo->nombre_modulo : null;
    }

    public static function get_nombres_permiso_by_id_modulo_id_permiso($id_modulo, $id_permisos)
    {
        $nombre_permiso = "";
        $modulo = Self::get_modulo_by_id_modulo($id_modulo);
        if (!is_array($id_permisos)) {
            $id_permisos = [$id_permisos];
        }
        if (isset($modulo->grupos_permisos)) {
            foreach ($modulo->grupos_permisos as $grupo_permisos) {
                if (isset($grupo_permisos["permisos"])) {
                    foreach ($grupo_permisos["permisos"] as $permiso) {
                        if (in_array($permiso["id_permiso"], $id_permisos)) {
                            $nombre_permiso .= $permiso["nombre_permiso"] . " (" . $grupo_permisos["nombre_grupo"] . ")<br/>";
                        }
                    }
                }
            }
        }
        return $nombre_permiso;
    }

    public static function get_nombre_menu_by_id_modulo_id_menu($id_modulo, $id_menu)
    {
        $nombre_menu = "";
        $modulo = Self::get_modulo_by_id_modulo($id_modulo);
        if (isset($modulo->menus)) {
            foreach ($modulo->menus as $menu) {
                if (isset($menu["id_menu"])) {
                    if ((int)$menu["id_menu"] == (int)$id_menu) {
                        $nombre_menu = $menu["texto"];
                        break;
                    }
                }
            }
        }
        return $nombre_menu;
    }

    public static function get_perfil_by_user_cliente_modulo($user, $cliente, $modulo_old_name)
    {
        if( is_object($user) ) {
            foreach ($user->clientes as $cliente_usuario) {
                if ($cliente_usuario["cliente_id"] == $cliente->_id) {
                    foreach ($cliente_usuario["perfiles"] as $mp) {
                        if ($mp["rol_cliente"] == $modulo_old_name) {
                            if (isset($mp["perfiles_rol"][0]["perfil"])) {
                                return $mp["perfiles_rol"][0]["perfil"];
                            } else {
                                return null;
                            }
                        }
                    }
                }
            }
        } else
        {
            return null;
        }

        return null;
    }

    public static function get_perfil_by_user_cliente_solo_rol($user, $cliente, $modulo_old_name)
    {
        if( is_object($user) ) {
        foreach ($user->clientes as $cliente_usuario) {
            if ($cliente_usuario["cliente_id"] == $cliente->_id) {
                foreach ($cliente_usuario["perfiles"] as $mp) {
                    if ($mp["rol_cliente"] == $modulo_old_name) {
                        return isset($mp["rol_cliente"]) ? $mp["rol_cliente"] : null;
                    }
                }
            }
        }
        } else
        {
            return null;
        }
        return null;
    }

    public static function get_array_id_modulos_by_cliente($cliente)
    {
        $arreglo_id_modulos = array();
        if (!empty($cliente->modulos)) {
            foreach ($cliente->modulos as $mod) {
                array_push($arreglo_id_modulos, (int)$mod["id_modulo"]);
            }
        }
        return $arreglo_id_modulos;
    }

    public static function get_id_perfil_by_name($perfil, $modulo, $cliente)
    {
        if (!empty($modulo->perfiles_fijos)) {
            foreach ($modulo->perfiles_fijos as $perfil_fijo) {
                if (strtoupper(trim($perfil_fijo["perfil"])) == strtoupper(trim($perfil))) {
                    return (int)$perfil_fijo["id_perfil"];
                }
            }
        }
        if (!empty($cliente->modulos)) {
            foreach ($cliente->modulos as $mod) {
                if ((int)$mod["id_modulo"] == $modulo->id_modulo) {
                    if (!empty($mod["perfiles"])) {
                        foreach ($mod["perfiles"] as $modperfil) {
                            if (strtoupper(trim($modperfil["perfil"])) == strtoupper(trim($perfil))) {
                                return (int)$modperfil["id_perfil"];
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    public static function get_array_id_menus_activos($modulo, $cliente, $id_perfil)
    {
        if (isset($modulo->perfiles_fijos)) {
            foreach ($modulo->perfiles_fijos as $perfil) {
                if ((int)$perfil["id_perfil"] == (int)$id_perfil) {
                    return $perfil["menus_activos"];
                }
            }
        }

        foreach ($cliente->modulos as $mod) {
            if ((int)$mod["id_modulo"] == (int)$modulo->id_modulo) {
                foreach ($mod["perfiles"] as $perfil) {
                    if ((int)$perfil["id_perfil"] == (int)$id_perfil) {
                        return $perfil["menus_activos"];
                    }
                }
            }
        }
        return array();
    }

    public static function get_array_id_permisos_activos($modulo, $cliente, $id_perfil)
    {
        if (isset($modulo->perfiles_fijos)) {
            foreach ($modulo->perfiles_fijos as $perfil) {
                if ((int)$perfil["id_perfil"] == (int)$id_perfil) {
                    return $perfil["permisos_activos"];
                }
            }
        }
        foreach ($cliente->modulos as $mod) {
            if ((int)$mod["id_modulo"] == (int)$modulo->id_modulo) {
                if (!empty($mod["perfiles"])) {
                    foreach ($mod["perfiles"] as $perfil) {
                        if ((int)$perfil["id_perfil"] == (int)$id_perfil) {
                            return $perfil["permisos_activos"];
                        }
                    }
                }
            }
        }
        return array();
    }

    public static function tienePermisoActivo($user, $cliente, $idPermiso, $idModulo = 6)
    {
        if( is_object($user) ) {

        $idModulo = intval($idModulo);
        $modulo = Modulo::where("id_modulo", $idModulo)->first();
        $modulo_old_name = $modulo->modulo;
        $perfil = Modulo::get_perfil_by_user_cliente_modulo($user, $cliente, $modulo_old_name);
        $id_perfil = Modulo::get_id_perfil_by_name($perfil, $modulo, $cliente);

        foreach ($cliente->modulos as $mod) {
            if ((int)$mod["id_modulo"] == (int)$modulo->id_modulo) {
                if (!empty($mod["perfiles"])) {
                    foreach ($mod["perfiles"] as $perfil) {
                        if ((int)$perfil["id_perfil"] == (int)$id_perfil) {
                            foreach ($perfil["permisos_activos"] as $permiso) {
                                if ($permiso == $idPermiso) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($modulo->perfiles_fijos)) {
            foreach ($modulo->perfiles_fijos as $perfil) {
                if ((int)$perfil["id_perfil"] == (int)$id_perfil) {
                    foreach ($perfil["permisos_activos"] as $permiso) {
                        if ($permiso == $idPermiso) {
                            return true;
                        }
                    }
                }
            }
        }

        } else
        {
            return null;
        }

        return false;
    }

    public static function tienePermisoEditarListaPep($user, $cliente)
    {
        if( is_object($user) ) {
              return Modulo::tienePermisoActivo($user, $cliente, 13);
        } else {
            return null;
        }
    }

    public static function tienePermisoEditarBrokers($user, $cliente)
    {
        if( is_object($user) ) {
             return Modulo::tienePermisoActivo($user, $cliente, 11);
        } else {
            return null;
        }
    }

    public static function tienePermisoEditarUsuarios($user, $cliente)
    {
        if( is_object($user) ) {
             return Modulo::tienePermisoActivo($user, $cliente, 6);
        } else {
            return null;
        }
    }

    public static function tienePermisoEditarPerfiles($user, $cliente, $idModuloActual)
    {
        if( is_object($user) ) {
            if ($idModuloActual == 6) {
                return Modulo::tienePermisoActivo($user, $cliente, 4, $idModuloActual);
            } else if ($idModuloActual == 7) {

                return self::ComprobarMenu($idModuloActual, 3) == false;
            }
        }
        return false;
    }

    public static function ComprobarMenu($id_modulo, $id_menu)
    {
        $arr_menus_activos = session()->get("menus");
        $id_cliente = session()->get("id_cliente");
        if (!empty($arr_menus_activos) && !empty($id_cliente)) {
            if (!in_array((int)$id_menu, $arr_menus_activos[$id_modulo])) {
                return NavigationController::SinAccesoMenu($id_modulo, $id_menu);
            }
        }
        return false;
    }

    public static function tienePermisoEditarAseguradoras($user, $cliente)
    {
        if( is_object($user) )
        {
             return Modulo::tienePermisoActivo($user, $cliente, 9);
        } else {
            return null;
        }
    }

    public static function ClientHasAccessModule($id_cliente, $id_modulo)
    {
        $cliente = Cliente::find($id_cliente);
        if (!empty($cliente->modulos)) {
            foreach ($cliente->modulos as $mod) {
                if ((int)$mod["id_modulo"] == (int)$id_modulo) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function UserHasAccessModuleInClient($user, $id_cliente, $modulo_old_name)
    {
        if( is_object($user) ) {
            foreach ($user->clientes as $cli) {
                if ($cli["cliente_id"] == $id_cliente) {
                    foreach ($cli["perfiles"] as $mp) {
                        if ($mp["rol_cliente"] == $modulo_old_name) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public static function UserHasAccessModuleInClientConPerfil($user, $id_cliente, $modulo_old_name, $perfil)
    {
        if( is_object($user) ) {
        foreach ($user->clientes as $cli) {
            if ($cli["cliente_id"] == $id_cliente) {
                foreach ($cli["perfiles"] as $mp) {
                    if ($mp["rol_cliente"] == $modulo_old_name) {
                        if (($mp["perfiles_rol"][0]["perfil"]) == $perfil) {
                            return true;
                        }
                    }
                }
            }
        }

        } else
        {
            return false;
        }
        return false;
    }

    public static function HasAccessModule($user, $cliente, $id_modulo)
    {
        if( is_object($user) ) {
        $modulo_old_name = Self::get_modulo_old_name_by_id_modulo($id_modulo);
        if (empty($user) || empty($cliente)) {
            return true;
        }
        return ((Self::ClientHasAccessModule($cliente->_id, $id_modulo) && Self::UserHasAccessModuleInClient($user, $cliente->_id, $modulo_old_name)));

        } else
        {
            return null;
        }

    }

    public static function get_options_modulos($cliente, $id_modulo = null, $texto_index_0 = "Todos los módulos")
    {
        if ($id_modulo == null) {
            $selected = 'selected="selected"';
        } else {
            $selected = '';
        }
        $options = '<option value="-1" ' . $selected . '>' . $texto_index_0 . '</option>';
        if (!empty($cliente->modulos)) {
            foreach ($cliente->modulos as $mod) {
                if ((int)$mod["id_modulo"] == (int)$id_modulo) {
                    $selected = 'selected="selected"';
                } else {
                    $selected = '';
                }
                $options .= '<option value="' . $mod["id_modulo"] . '" ' . $selected . '>' . Modulo::get_nombre_modulo_by_id_modulo($mod["id_modulo"]) . '</option>';
            }
        }
        return $options;
    }

    public static function get_max_id_perfil($modulo, $cliente)
    {
        $max = 0;
        if (!empty($cliente->modulos)) {
            foreach ($cliente->modulos as $mod) {
                if ((int)$mod["id_modulo"] == (int)$modulo->id_modulo) {
                    if (!empty($mod["perfiles"])) {
                        foreach ($mod["perfiles"] as $per) {
                            if ((int)$per["id_perfil"] > $max) {
                                $max = (int)$per["id_perfil"];
                            }
                        }
                    }
                    break;
                }
            }
        }
        if (!empty($modulo->perfiles_fijos)) {
            foreach ($modulo->perfiles_fijos as $per) {
                if ((int)$per["id_perfil"] > $max) {
                    $max = (int)$per["id_perfil"];
                }
            }
        }
        return $max;
    }

    public static function get_perfil_by_id($modulo, $cliente, $id_perfil)
    {
        if (!empty($modulo->perfiles_fijos)) {
            foreach ($modulo->perfiles_fijos as $per) {
                if ((int)$per["id_perfil"] == (int)$id_perfil) {
                    return $per["perfil"];
                }
            }
        }
        if (!empty($cliente->modulos)) {
            foreach ($cliente->modulos as $mod) {
                if ((int)$mod["id_modulo"] == (int)$modulo->id_modulo) {
                    if (!empty($mod["perfiles"])) {
                        foreach ($mod["perfiles"] as $per) {
                            if ((int)$per["id_perfil"] == (int)$id_perfil) {
                                return $per["perfil"];
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    public static function get_options_perfiles($id_modulo, $id_cliente, $id_perfil = null, $texto_index_0 = "Todos los perfiles")
    {
        if ($id_perfil == null) {
            $selected = 'selected="selected"';
        } else {
            $selected = '';
        }
        $options = '<option value="-1" ' . $selected . '>' . $texto_index_0 . '</option>';
        if ($id_modulo != null && $id_modulo != -1) {
            $modulo = Self::get_modulo_by_id_modulo($id_modulo);
            if (!empty($modulo->perfiles_fijos)) {
                foreach ($modulo->perfiles_fijos as $per) {
                    if ((int)$per["id_perfil"] == (int)$id_perfil) {
                        $selected = 'selected="selected"';
                    } else {
                        $selected = '';
                    }
                    $options .= '<option value="' . $per["id_perfil"] . '" ' . $selected . '>' . $per["perfil"] . '</option>';
                }
            }
            $cliente = Cliente::find($id_cliente);
            if (!empty($cliente->modulos)) {
                foreach ($cliente->modulos as $mod) {
                    if ((int)$mod["id_modulo"] == (int)$id_modulo) {
                        if (!empty($mod["perfiles"])) {
                            foreach ($mod["perfiles"] as $per) {
                                if ((int)$per["id_perfil"] == (int)$id_perfil) {
                                    $selected = 'selected="selected"';
                                } else {
                                    $selected = '';
                                }
                                $options .= '<option value="' . $per["id_perfil"] . '" ' . $selected . '>' . $per["perfil"] . '</option>';
                            }
                        }
                        break;
                    }
                }
            }
        }
        return $options;
    }
}