<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Http\Controllers\Auth\LoginController;
use App\Modulo;
use App\Poliza\Aseguradora;
use App\SolicitudVinculacion;
use App\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class PerfilesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function MostrarFiltroPerfiles($id_modulo_actual)
    {
        $id_cliente = session()->get("id_cliente");
        if (!Modulo::UserHasAccessModuleInClient(
            Auth::user(),
            $id_cliente,
            Modulo::get_modulo_old_name_by_id_modulo((int)$id_modulo_actual)
        )) {
            return NavigationController::SinAcceso($id_modulo_actual);
        }
        if ($id_modulo_actual == 7) {
            $cm = Modulo::ComprobarMenu($id_modulo_actual, 3);
            if ($cm) {
                return $cm;
            }
        }
        $options_modulos = Modulo::get_options_modulos(Cliente::find($id_cliente));

        $user = Auth::user();
        $cliente = getCliente();
        $tienePermisoEditarPerfiles = Modulo::tienePermisoEditarPerfiles($user, $cliente, $id_modulo_actual);

        return view(
            'perfiles.filtro_perfiles',
            array(
                "id_modulo_actual" => $id_modulo_actual,
                "options_modulos" => $options_modulos,
                "tienePermisoEditarPerfiles" => $tienePermisoEditarPerfiles
            )
        );
    }

    public function getPerfiles(Request $request)
    {
        $filtro_id_modulo = $request->input("filtro_id_modulo");
        Filtrar($filtro_id_modulo, "INTEGER", -1);
        $filtro_perfil = $request->input("filtro_perfil");
        Filtrar($filtro_perfil, "STRING", "");
        $filtro_perfil = !empty($filtro_perfil) ? $filtro_perfil : "";
        $filtro_tipo = $request->input("filtro_tipo");
        Filtrar($filtro_tipo, "INTEGER", -1);
        $arreglo = array();

        $user = Auth::user();
        $cliente = getCliente();
        $tienePermisoEditarPerfiles = Modulo::tienePermisoEditarPerfiles($user, $cliente, $filtro_id_modulo);

        if ($filtro_tipo != 1) {
            if (!empty($cliente->modulos)) {
                $fijo = false;
                $tipo = "Personalizado";
                foreach ($cliente->modulos as $mod) {
                    if (!empty($mod["perfiles"])) {
                        $id_modulo = $mod["id_modulo"];
                        $modulo = Modulo::get_nombre_modulo_by_id_modulo($id_modulo);
                        foreach ($mod["perfiles"] as $per) {
                            $id_perfil = $per["id_perfil"];
                            $perfil = $per["perfil"];
                            if (($filtro_id_modulo != -1 && 
                                (int)$filtro_id_modulo == (int)$id_modulo) || 
                                ($filtro_id_modulo == -1)) {
                                
                                $coincide = empty($filtro_perfil) || 
                                            strpos(strtoupper($perfil), strtoupper(trim($filtro_perfil))) !== false;
                                if ($coincide) {
                                    array_push(
                                        $arreglo,
                                        array(
                                            "id_modulo" => $id_modulo,
                                            "modulo" => $modulo,
                                            "id_perfil" => $id_perfil,
                                            "perfil" => $perfil,
                                            "fijo" => $fijo,
                                            "tipo" => $tipo,
                                            "activo" => true,
                                            "acciones" => "",
                                            "tienePermisoEditarPerfiles" => $tienePermisoEditarPerfiles
                                        )
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($filtro_tipo != 0) {
            $modulos = Modulo::all();
            $fijo = true;
            $tipo = "Estático";
            foreach ($modulos as $mod) {
                if (Modulo::ClientHasAccessModule($cliente->_id, $mod["id_modulo"])) {
                    $id_modulo = $mod["id_modulo"];
                    $modulo = $mod["nombre_modulo"];
                    if (!empty($mod["perfiles_fijos"])) {
                        foreach ($mod["perfiles_fijos"] as $per) {
                            $id_perfil = $per["id_perfil"];
                            $perfil = $per["perfil"];
                            if (($filtro_id_modulo != -1 && 
                                (int)$filtro_id_modulo == (int)$id_modulo) || 
                                ($filtro_id_modulo == -1)) {
                                
                                $coincide = empty($filtro_perfil) || 
                                            strpos(strtoupper($perfil), strtoupper(trim($filtro_perfil))) !== false;
                                if ($coincide) {
                                    array_push(
                                        $arreglo,
                                        array(
                                            "id_modulo" => $id_modulo,
                                            "modulo" => $modulo,
                                            "id_perfil" => $id_perfil,
                                            "perfil" => $perfil,
                                            "fijo" => $fijo,
                                            "tipo" => $tipo,
                                            "activo" => true,
                                            "acciones" => "",
                                            "tienePermisoEditarPerfiles" => $tienePermisoEditarPerfiles
                                        )
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        return response()->json(array("data" => $arreglo), 200);
    }

    public function MostrarEditarPerfil($id_modulo_actual, $id_modulo = null, $id_perfil = null)
    {
        $arretiquetas = array(
            "id_modulo_actual",
            "titulo",
            "id_modulo",
            "id_perfil",
            "disabled_modulo",
            "options_modulos",
            "nombre_perfil",
            "checks_menu_conf",
            "checks_menu_princ",
            "checks_permisos"
        );
        $id_cliente = session()->get("id_cliente");
        $cliente = Cliente::find($id_cliente);
        if (!Modulo::UserHasAccessModuleInClient(
            Auth::user(),
            $id_cliente,
            Modulo::get_modulo_old_name_by_id_modulo((int)$id_modulo_actual)
        )) {
            return NavigationController::SinAcceso($id_modulo_actual);
        } else {
            if (!empty($id_modulo) && !Modulo::UserHasAccessModuleInClient(
                    Auth::user(),
                    $id_cliente,
                    Modulo::get_modulo_old_name_by_id_modulo(
                        $id_modulo
                    )
                )) {
                return NavigationController::SinAcceso($id_modulo);
            }
        }

        if (empty($id_modulo) && empty($id_perfil)) {
            $titulo = 'Nuevo Perfil';
            $id_modulo = "";
            $id_perfil = "";
            $disabled_modulo = "";
            $nombre_perfil = "";
            $options_modulos = Modulo::get_options_modulos($cliente, $id_modulo_actual, "Seleccione el módulo");
            $checks_menu_conf = $this->GetChecksMenuConfiguracion(null, $id_modulo_actual, $id_perfil);
            $checks_menu_princ = $this->GetChecksMenuPrincipal(null, $id_modulo_actual, $id_perfil);
            $checks_permisos = $this->GetChecksPermisos(null, $id_modulo_actual, $id_perfil);
        } else {
            $titulo = 'Editar Perfil';
            $disabled_modulo = 'disabled="disabled"';
            $nombre_perfil = Modulo::get_perfil_by_id(
                Modulo::get_modulo_by_id_modulo($id_modulo),
                $cliente,
                $id_perfil
            );
            $options_modulos = Modulo::get_options_modulos($cliente, (int)$id_modulo, "Seleccione el módulo");
            $checks_menu_conf = $this->GetChecksMenuConfiguracion(null, $id_modulo, $id_perfil);
            $checks_menu_princ = $this->GetChecksMenuPrincipal(null, $id_modulo, $id_perfil);
            $checks_permisos = $this->GetChecksPermisos(null, $id_modulo, $id_perfil);
        }
        $arrvalores = array(
            $id_modulo_actual,
            $titulo,
            $id_modulo,
            $id_perfil,
            $disabled_modulo,
            $options_modulos,
            $nombre_perfil,
            $checks_menu_conf,
            $checks_menu_princ,
            $checks_permisos
        );
        return view('perfiles.perfil', array_combine($arretiquetas, $arrvalores));
    }

    public function GetChecksMenu($tipo, Request $request = null, $id_modulo = null, $id_perfil = null)
    {
        $checks_menu = '';
        $cliente = Cliente::find(session()->get("id_cliente"));
        if (empty($id_modulo)) {
            $id_modulo = -1;
            if (!empty($request)) {
                $id_modulo = $request->input("Valor_1");
            }
        }
        Filtrar($id_modulo, "INTEGER", -1);
        if (empty($id_perfil)) {
            $id_perfil = -1;
        }

        if ($id_modulo != -1) {
            $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);

            if ($id_perfil != -1) {
                $arreglo_menus_activos = Modulo::get_array_id_menus_activos($modulo, $cliente, (int)$id_perfil);
            } else {
                $arreglo_menus_activos = array();
            }

            if (!empty($modulo->menus)) {
                $checks_menu .= '<div class="row"><div class="col-sm-11 grupo_permiso"><div class="row">';
                foreach ($modulo->menus as $menu) {
                    if ($menu["tipo"] == $tipo && (!isset($menu["solo_fijo"]) || $menu["solo_fijo"] != true)) {
                        $IDCH = 'CH_' . $tipo . '_' . $menu["id_menu"];
                        $checked = in_array((int)$menu["id_menu"], $arreglo_menus_activos) ? 'checked="checked"' : '';
                        $checks_menu .= '<div class="col-sm-4"><label class="form-inline label_check"><input type="checkbox" ' . $checked . ' id="' . $IDCH . '" name="' . $IDCH . '" class="form-control form-inline label_check" value="' . $menu["id_menu"] . '" style="margin: 0px">' . $menu["texto"] . '</label></div>';
                    }
                }
                $checks_menu .= '</div></div></div>';
            }
        } else {
            if ($id_modulo == -1) {
                $checks_menu = '<div align="center">Seleccione el módulo</div>';
            } else {
                $checks_menu = '<div align="center">El módulo no tiene opciones para este menú.</div>';
            }
        }
        return $checks_menu;
    }

    public function GetChecksMenuConfiguracion(Request $request = null, $id_modulo = null, $id_perfil = null)
    {
        return $this->GetChecksMenu("CONFIGURACION", $request, $id_modulo, $id_perfil);
    }

    public function GetChecksMenuPrincipal(Request $request = null, $id_modulo = null, $id_perfil = null)
    {
        return $this->GetChecksMenu("PRINCIPAL", $request, $id_modulo, $id_perfil);
    }

    public function GetChecksPermisos(Request $request = null, $id_modulo = null, $id_perfil = null)
    {
        $checks_permisos = '';
        $cliente = Cliente::find(session()->get("id_cliente"));
        if (empty($id_modulo)) {
            $id_modulo = -1;
            if (!empty($request)) {
                $id_modulo = $request->input("Valor_1");
            }
        }
        Filtrar($id_modulo, "INTEGER", -1);
        if (empty($id_perfil)) {
            $id_perfil = -1;
        }

        if ($id_modulo != -1) {
            $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
            if ($id_perfil != -1) {
                $arreglo_permisos_activos = Modulo::get_array_id_permisos_activos($modulo, $cliente, (int)$id_perfil);
            } else {
                $arreglo_permisos_activos = array();
            }

            if (!empty($modulo->grupos_permisos)) {
                $abierto = false;
                $index = 0;
                foreach ($modulo->grupos_permisos as $grupo_permiso) {
                    if (!$abierto) {
                        $checks_permisos .= '<div class="row">';
                        $abierto = true;
                    }
                    $nombre_grupo = $grupo_permiso["nombre_grupo"];
                    $checks_permisos .= '<div class="col-sm-5 grupo_permiso"><h5><strong><i><u>' . $nombre_grupo . '</u></i></strong></h5>';
                    if (!empty($grupo_permiso["permisos"])) {
                        foreach ($grupo_permiso["permisos"] as $permiso) {
                            $id_permiso = (int)$permiso["id_permiso"];
                            $nombre_permiso = $permiso["nombre_permiso"];
                            $checked = in_array($id_permiso, $arreglo_permisos_activos) ? 'checked="checked"' : '';
                            $checks_permisos .= '<div><label class="form-inline label_check" ' .
                                (isset($permiso["nota"]) ? 'data-toggle="tooltip" data-placement="right" title="' . $permiso["nota"] . '"' : "") . ' >' .
                                '<input type="checkbox" ' . $checked . ' id="CHPermiso_' . $id_permiso . '" name="CHPermiso_' . $id_permiso . '" class="form-control form-inline label_check" value="' . $id_permiso . '">' . $nombre_permiso .
                                '</label></div>';
                        }
                    }
                    $checks_permisos .= '</div>';
                    $index++;
                    if ($index % 2 == 0) {
                        $checks_permisos .= '</div>';
                        $abierto = false;
                    }
                }
                if ($abierto) {
                    $checks_permisos .= '</div>';
                    $abierto = false;
                }
            }
        } else {
            if ($id_modulo == -1) {
                $checks_permisos = '<div align="center">Seleccione el módulo</div>';
            } else {
                $checks_permisos = '<div align="center">El módulo no tiene permisos definidos.</div>';
            }
        }
        return $checks_permisos;
    }

    public function GuardarPerfil(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $id_modulo = (int)$request->input("SModulo");
            Filtrar($id_modulo, "INTEGER");
            $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
            if (!$modulo) {
                $Res = -1;
                $Mensaje = "Módulo inexistente o accesos insuficientes";
            }
        }
        if ($Res >= 0) {
            $nombre_modulo = $modulo->nombre_modulo;
            $id_cliente = session()->get("id_cliente");
            $cliente = Cliente::find($id_cliente);
            $id_perfil = 1 + Modulo::get_max_id_perfil($modulo, $cliente);
            if ((int)$id_perfil < 1001) {
                $id_perfil = 1001;
            }
            $perfil = $request->input("TPerfil");
            Filtrar($perfil, "STRING");
            if (empty($perfil)) {
                $Res = -2;
                $Mensaje = "Perfil en blanco";
            }
        }
        if ($Res >= 0) {
            $menus_activos = array();
            $permisos_activos = array();
            if (!Modulo::ClientHasAccessModule($id_cliente, $id_modulo)) {
                $Res = -3;
                $Mensaje = "Módulo inexistente o accesos insuficientes";
            }
        }
        if ($Res >= 0) {
            @$id_perfil_existente = Modulo::get_id_perfil_by_name($perfil, $modulo, $cliente);
            if (!empty($id_perfil_existente)) {
                $Res = -4;
                $Mensaje = "Ya existe un perfil en el módulo $nombre_modulo con nombre $perfil";
            }
        }
        if ($Res >= 0) {
            $algun_menu = false;
            foreach ($request->input() as $campo => $valor) {
                if (substr($campo, 0, 17) == "CH_CONFIGURACION_" || substr($campo, 0, 13) == "CH_PRINCIPAL_") {
                    Filtrar($valor, "INTEGER");
                    array_push($menus_activos, (int)$valor);
                    $algun_menu = true;
                } else {
                    if (substr($campo, 0, 10) == "CHPermiso_") {
                        Filtrar($valor, "INTEGER");
                        array_push($permisos_activos, (int)$valor);
                    }
                }
            }
            if (!$algun_menu) {
                $Res = -5;
                $Mensaje = "Debe asociarle al menos un menú al perfil.";
            }
        }
        if ($Res >= 0) {
            if (empty($cliente->modulos)) {
                $modulos = array(
                    "id_modulo" => $id_modulo,
                    "perfiles" => array(
                        array(
                            "id_perfil" => $id_perfil,
                            "perfil" => $perfil,
                            "menus_activos" => $menus_activos,
                            "permisos_activos" => $permisos_activos
                        )
                    )
                );
            } else {
                $modulos = array();
                foreach ($cliente->modulos as $mod) {
                    if ((int)$mod["id_modulo"] == (int)$id_modulo) {
                        if (!empty($mod["perfiles"])) {
                            $perfiles = $mod["perfiles"];
                        } else {
                            $perfiles = array();
                        }
                        array_push(
                            $perfiles,
                            array(
                                "id_perfil" => $id_perfil,
                                "perfil" => $perfil,
                                "menus_activos" => $menus_activos,
                                "permisos_activos" => $permisos_activos
                            )
                        );
                        $mod = array("id_modulo" => $id_modulo, "perfiles" => $perfiles);
                    }
                    array_push($modulos, $mod);
                }
            }
        }
        if ($Res >= 0) {
            $cliente->modulos = $modulos;
            $cliente->save();
        }
        if ($Res >= 0) {
            $Res = $id_perfil;
            $Mensaje = "El perfil fue guardado correctamente.";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function ModificarPerfil(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $id_modulo = (int)$request->input("HiddenIdModulo");
            Filtrar($id_modulo, "INTEGER");
            $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
            if (!$modulo) {
                $Res = -1;
                $Mensaje = "Módulo inexistente o accesos insuficientes";
            }
        }
        if ($Res >= 0) {
            $nombre_modulo = $modulo->nombre_modulo;
            $id_cliente = session()->get("id_cliente");
            $cliente = Cliente::find($id_cliente);
            $id_perfil = (int)$request->input("HiddenPerfil");
            Filtrar($id_perfil, "INTEGER", -1);
            $perfil = $request->input("TPerfil");
            Filtrar($perfil, "STRING");
            if (empty($perfil)) {
                $Res = -2;
                $Mensaje = "Perfil en blanco";
            }
        }
        if ($Res >= 0) {
            $menus_activos = array();
            $permisos_activos = array();
            if (!Modulo::ClientHasAccessModule($id_cliente, $id_modulo)) {
                $Res = -3;
                $Mensaje = "Módulo inexistente o accesos insuficientes";
            }
        }
        if ($Res >= 0) {
            @$id_perfil_existente = Modulo::get_id_perfil_by_name($perfil, $modulo, $cliente);
            if (!empty($id_perfil_existente) && (int)$id_perfil_existente != (int)$id_perfil) {
                $Res = -4;
                $Mensaje = "Ya existe un perfil en el módulo $nombre_modulo con nombre $perfil";
            }
        }
        if ($Res >= 0) {
            $algun_menu = false;
            foreach ($request->input() as $campo => $valor) {
                if (substr($campo, 0, 17) == "CH_CONFIGURACION_" || substr($campo, 0, 13) == "CH_PRINCIPAL_") {
                    Filtrar($valor, "INTEGER");
                    array_push($menus_activos, (int)$valor);
                    $algun_menu = true;
                } else {
                    if (substr($campo, 0, 10) == "CHPermiso_") {
                        Filtrar($valor, "INTEGER");
                        array_push($permisos_activos, (int)$valor);
                    }
                }
            }
            if (!$algun_menu) {
                $Res = -5;
                $Mensaje = "Debe asociarle al menos un menú al perfil.";
            }
        }
        if ($Res >= 0) {
            $modulos = array();
            foreach ($cliente->modulos as $mod) {
                if ((int)$mod["id_modulo"] == (int)$id_modulo) {
                    $perfiles = array();
                    foreach ($mod["perfiles"] as $per) {
                        if ((int)$per["id_perfil"] == (int)$id_perfil) {
                            $per = array(
                                "id_perfil" => (int)$id_perfil,
                                "perfil" => $perfil,
                                "menus_activos" => $menus_activos,
                                "permisos_activos" => $permisos_activos
                            );
                        }
                        array_push($perfiles, $per);
                    }
                    $mod = array("id_modulo" => (int)$id_modulo, "perfiles" => $perfiles);
                }
                array_push($modulos, $mod);
            }
        }
        if ($Res >= 0) {
            $cliente->modulos = $modulos;
            $cliente->save();
        }
        if ($Res >= 0) {
            $Res = $id_perfil;
            $Mensaje = "El perfil fue guardado correctamente.";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function MostrarFiltroUsuarios($id_modulo_actual)
    {
        $id_cliente = session()->get("id_cliente");
        if (!Modulo::UserHasAccessModuleInClient(
            Auth::user(),
            $id_cliente,
            Modulo::get_modulo_old_name_by_id_modulo((int)$id_modulo_actual)
        )) {
            return NavigationController::SinAcceso($id_modulo_actual);
        }
        if ($id_modulo_actual == 7) {
            $cm = Modulo::ComprobarMenu($id_modulo_actual, 4);
            if ($cm) {
                return $cm;
            }
        }

        $cliente = Cliente::find($id_cliente);
        $user = Auth::user();
        $tienePermisoEditarUsuarios = Modulo::tienePermisoEditarUsuarios($user, $cliente);
        $options_modulos = Modulo::get_options_modulos($cliente);
        $options_perfiles = Modulo::get_options_perfiles(null, -1, $id_cliente);
        return view(
            'perfiles.filtro_usuarios',
            array(
                "id_modulo_actual" => $id_modulo_actual,
                "nombre_cliente" => $cliente->nombre_identificacion,
                "options_modulos" => $options_modulos,
                "options_perfiles" => $options_perfiles,
                'tienePermisoEditarUsuarios' => $tienePermisoEditarUsuarios
            )
        );
    }

    public function ActualizarPerfilesPorModulo(Request $request)
    {
        $id_modulo = $request->input("Valor_1");
        Filtrar($id_modulo, "INTEGER", -1);
        $id_cliente = session()->get("id_cliente");
        if ($id_modulo != -1 && !Modulo::UserHasAccessModuleInClient(
                Auth::user(),
                $id_cliente,
                Modulo::get_modulo_old_name_by_id_modulo(
                    $id_modulo
                )
            )) {
            return '';
        }
        return Modulo::get_options_perfiles($id_modulo, $id_cliente);
    }

    public function getUsuarios(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $filtro_id_modulo = $request->input("filtro_id_modulo");
        Filtrar($filtro_id_modulo, "INTEGER", -1);
        $filtro_id_perfil = $request->input("filtro_id_perfil");
        Filtrar($filtro_id_perfil, "INTEGER", -1);
        $filtro_nombre_email = $request->input("filtro_nombre_email");
        Filtrar($filtro_nombre_email, "STRING", "");
        $filtro_id_estado = $request->input("filtro_id_estado");
        Filtrar($filtro_id_estado, "INTEGER", -1);
        $arreglo = array();
        $valida_aseguradora = false;
        $multi_broker = false;

        $cliente_valida = getCliente();
        $user_valida = Auth::user();
        $perfil_poliza = Modulo::get_perfil_by_user_cliente_modulo($user_valida, $cliente_valida, "PolizaElectronica");

        $tienePermisoEditarUsuarios = Modulo::tienePermisoEditarUsuarios($user_valida, $cliente_valida);

        $usuarios = Usuarios::where('clientes.cliente_id', $id_cliente);
        if ($filtro_id_modulo != -1) {
            $usuarios = $usuarios->where(
                "clientes.perfiles.rol_cliente",
                Modulo::get_modulo_old_name_by_id_modulo($filtro_id_modulo)
            );
        }

        if ($id_cliente) {
            $cliente = Cliente::find($id_cliente);
        }

        if ($filtro_id_modulo != -1 && $filtro_id_perfil != -1) {
            $modulo = Modulo::get_modulo_by_id_modulo($filtro_id_modulo);
            $perfil = Modulo::get_perfil_by_id($modulo, $cliente, $filtro_id_perfil);
            $usuarios = $usuarios->where("clientes.perfiles.perfiles_rol.perfil", $perfil);
        }
        if (!isset($cliente->parametros->valida_aseguradora)) {
            $valida_aseguradora = false;
        } else {
            $valida_aseguradora = $cliente->parametros->valida_aseguradora;
        }

        $multi_broker = $cliente->esMultiBroker();

        if (!empty($filtro_nombre_email)) {
            $usuarios = $usuarios->where(
                function ($query) use ($filtro_nombre_email) {
                    $query->where("nombre", "like", "%$filtro_nombre_email%")->orWhere(
                        "email",
                        "like",
                        "%$filtro_nombre_email%"
                    );
                }
            );
        }
        if ((int)$filtro_id_estado != -1) {
            $usuarios = $usuarios->where("activo", (int)$filtro_id_estado ? true : false);
        }
        $usuarios = $usuarios->get();

        foreach ($usuarios as $usuario) {
            foreach ($usuario["clientes"] as $usuario_cliente) {
                if ($usuario_cliente["cliente_id"] == $id_cliente) {
                    $modulos_perfiles = '';
                    foreach ($usuario_cliente["perfiles"] as $perfil) {
                        $nombre_modulo = Modulo::get_nombre_modulo_by_id_modulo(
                            Modulo::get_id_modulo_by_old_name($perfil["rol_cliente"])
                        );
                        $nombre_perfil = $perfil["perfiles_rol"][0]["perfil"];
                        $modulos_perfiles .= "<li style='margin-left:15px'>$nombre_modulo / $nombre_perfil</li>";
                    }
                }
            }
            $arreglo[] = array(
                "_id" => EncriptarId($usuario["_id"]),
                "nombre" => $usuario["nombre"],
                "correo" => $usuario["email"],
                "modulos_perfiles" => $modulos_perfiles,
                "activo" => $usuario["activo"],
                "acciones" => "",
                "id_usuario_sin_emcrip" => $usuario["_id"],
                "valida_aseguradora" => $valida_aseguradora,
                "perfil_poliza" => $perfil_poliza,
                "multi_broker" => $multi_broker,
                "tienePermisoEditarUsuarios" => $tienePermisoEditarUsuarios
            );
        }
        return response()->json(array("data" => $arreglo), 200);
    }

    public function MostrarEditarUsuario(Request $request)
    {
        $arretiquetas = array(
            "id_cliente",
            "titulo",
            "_id",
            "email",
            "disabled_email",
            "nombre",
            "modulos_activos_perfiles",
            "disabled_password"
        );
        $id_cliente = session()->get("id_cliente");
        $cliente = Cliente::find($id_cliente);
        $modulos_activos_perfiles = '';
        if (empty($request->input("Valor_1"))) {
            $titulo = 'Nuevo Usuario';
            $_id = "";
            $email = "";
            $disabled_email = "";
            $nombre = "";
            $disabled_password = "";
            if (!empty($cliente->modulos)) {
                foreach ($cliente->modulos as $modulo) {
                    $id_modulo = $modulo["id_modulo"];
                    $nombre_modulo = Modulo::get_nombre_modulo_by_id_modulo($id_modulo);
                    $options_perfiles = Modulo::get_options_perfiles(
                        $id_modulo,
                        $id_cliente,
                        null,
                        "Sin perfil asociado"
                    );
                    $modulos_activos_perfiles .= '<div class="form-group row"><div class="col-sm-4"><label class="control-label">Módulo ' . $nombre_modulo . ':</label></div>
                                                  <div class="col-sm-8"><select id="SModuloPerfil_' . EncriptarId(
                            $id_modulo
                        ) . '" name="SModuloPerfil_' . EncriptarId(
                            $id_modulo
                        ) . '" class="form-control">' . $options_perfiles . '</select></div></div>';
                }
            }
        } else {
            $titulo = 'Editar Usuario';
            $id_usuario = DesencriptarId($request->input("Valor_1"));
            Filtrar($id_usuario, "STRING", "");
            $_id = $id_usuario;
            $usuario = Usuarios::find($id_usuario);
            $email = $usuario->email;
            $disabled_email = 'disabled="disabled"';
            $nombre = $usuario->nombre;
            $disabled_password = 'disabled="disabled"';
            if (!empty($cliente->modulos)) {
                foreach ($cliente->modulos as $mod) {
                    $id_modulo = $mod["id_modulo"];
                    $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
                    $nombre_modulo = $modulo->nombre_modulo;
                    $perfil = Modulo::get_perfil_by_user_cliente_modulo($usuario, $cliente, $modulo->modulo);
                    $id_perfil = Modulo::get_id_perfil_by_name($perfil, $modulo, $cliente);
                    $options_perfiles = Modulo::get_options_perfiles(
                        $id_modulo,
                        $id_cliente,
                        $id_perfil,
                        "Sin perfil asociado"
                    );
                    $modulos_activos_perfiles .= '<div class="form-group row"><div class="col-sm-4"><label class="control-label">Módulo ' . $nombre_modulo . ':</label></div>
                                                  <div class="col-sm-8"><select id="SModuloPerfil_' . EncriptarId(
                            $id_modulo
                        ) . '" name="SModuloPerfil_' . EncriptarId(
                            $id_modulo
                        ) . '" class="form-control">' . $options_perfiles . '</select></div></div>';
                }
            }
        }
        $arrvalores = array(
            $id_cliente,
            $titulo,
            EncriptarId($_id),
            $email,
            $disabled_email,
            $nombre,
            $modulos_activos_perfiles,
            $disabled_password
        );
        return view('perfiles.usuario', array_combine($arretiquetas, $arrvalores));
    }

    public function GuardarUsuario(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $email = addslashes(trim(strtolower($request->input("TEMail"))));
            Filtrar($email, "EMAIL");
            $usuarios = Usuarios::where('email', $email)->first();
        }

        if ($usuarios) {
            $cliente_id = session()->get("id_cliente");
            $perfiles = array();
            foreach ($request->input() as $campo => $value) {
                if (substr($campo, 0, 14) == "SModuloPerfil_" && $value != -1 && $value != null) {
                    $id_modulo = (int)DesencriptarId(substr($campo, 14));
                    Filtrar($id_modulo, "INTEGER", -1);
                    $id_perfil = $value;
                    Filtrar($id_perfil, "INTEGER", -1);
                    $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
                    $cliente = Cliente::find($cliente_id);
                    $rol_cliente = $modulo->modulo;
                    $perfil = Modulo::get_perfil_by_id($modulo, $cliente, $id_perfil);
                }
            }

            if ($perfil == 'Broker' || $perfil == 'Receptor_DE' || $perfil == 'Emisor_DE') {
                $clientes_final = array();

                $clientes = $usuarios->clientes;
                $cliente_existente_en_usuario = false;
                foreach ($clientes as $cliente_usuario) {
                    if ($cliente_usuario["cliente_id"] == $cliente_id) {
                        $cliente_existente_en_usuario = true;
                        if (!empty($cliente_usuario["perfiles"])) {
                            $perfiles = $cliente_usuario["perfiles"];
                        } else {
                            $perfiles = array();
                        }
                        $perfil_nuevo = array(
                            "rol_cliente" => "PolizaElectronica",
                            "perfiles_rol" => array(array("perfil" => $perfil_predeterminado))
                        );
                        array_push($perfiles, $perfil_nuevo);
                        $cliente_usuario["perfiles"] = $perfiles;
                    }
                    array_push($clientes_final, $cliente_usuario);
                }

                if (!$cliente_existente_en_usuario) {
                    $rol = "PolizaElectronica";
                    $perfil = "Broker";
                    $rol_cliente2 = "DocumentosElectronicos";
                    $perfil2 = 'Receptor_DE';
                    $perfiles_rol = array(array("perfil" => $perfil));
                    $perfiles_rol2 = array(array("perfil" => $perfil2));

                    $perfiles = array('rol_cliente' => $rol, 'perfiles_rol' => $perfiles_rol);
                    $perfiles2 = array('rol_cliente' => $rol_cliente2, 'perfiles_rol' => $perfiles_rol2);
                    $cliente_usuario = array('cliente_id' => $cliente_id, 'perfiles' => array($perfiles, $perfiles2));
                    array_push($clientes_final, $cliente_usuario);
                }

                $usuarios["clientes"] = $clientes_final;
                $usuarios->creado_por = $cliente_id;
                $resultado = $usuarios->save();

                if ($resultado) {
                    $Res = 1;
                    $Mensaje = "El mail indicado con: " . $email . " ya existía como usuario, se le agrego el perfil de Bróker para formularios de vinculación";
                }
            } else {
                $Res = -1;
                $Mensaje = "El correo indicado ya se encuentra registrado";
            }
        } else {
            if ($Res >= 0) {
                $nombre = addslashes(trim($request->input("TNombre")));
                Filtrar($nombre, "STRING");
                if (empty($nombre)) {
                    $Res - 2;
                    $Mensaje = "Nombre de usuario vacío.";
                }
            }
            if ($Res >= 0) {
                $cliente_id = session()->get("id_cliente");
                $perfiles = array();
                foreach ($request->input() as $campo => $value) {
                    if (substr($campo, 0, 14) == "SModuloPerfil_" && $value != -1 && $value != null) {
                        $id_modulo = (int)DesencriptarId(substr($campo, 14));
                        Filtrar($id_modulo, "INTEGER", -1);
                        $id_perfil = $value;
                        Filtrar($id_perfil, "INTEGER", -1);
                        $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
                        $cliente = Cliente::find($cliente_id);
                        $rol_cliente = $modulo->modulo;
                        $perfil = Modulo::get_perfil_by_id($modulo, $cliente, $id_perfil);
                        $perfiles_rol = array(array("perfil" => $perfil));
                        array_push($perfiles, array("rol_cliente" => $rol_cliente, "perfiles_rol" => $perfiles_rol));
                    }
                }
                if (empty($perfiles)) {
                    $Res = -3;
                    $Mensaje = "No se definieron perfiles";
                }
            }
            if ($Res >= 0) {
                $clientes = array(array("cliente_id" => $cliente_id, "perfiles" => $perfiles));
                $password = $request->input("TPassword");
                Filtrar($password, "STRING", "");
                if (!ContrasenaCompleja($password)) {
                    $Res = -4;
                    $Mensaje = "La contraseña no cumple con los requisitos mínimos de complejidad.";
                }
            }
            if ($Res >= 0) {
                if (!Modulo::ClientHasAccessModule($cliente_id, $id_modulo)) {
                    $Res = -5;
                    $Mensaje = "Sin accesos";
                }
            }
            if ($Res >= 0) {
                $usuario_data = array(
                    'nombre' => $nombre,
                    'email' => $email,
                    'password' => bcrypt($password),
                    'clientes' => $clientes,
                    'activo' => true,
                    'creado_por' => $cliente_id
                );
                $usuario = Usuarios::create($usuario_data);
                if (!$usuario) {
                    $Res = -2;
                    $Mensaje = "Ocurrio un error guardando el usuario";
                } else {
                    $Res = 1;
                }
            }
        }
        if ($Res >= 0) {
            if ($Res == 1) {
                $Mensaje = "El mail indicado con: " . $email . " ya existía como usuario, se le agrego el perfil de Bróker para formularios de vinculación";
            } else {
                $Mensaje = "El usuario fue registrado correctamente";
            }
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function ModificarUsuario(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $cliente_id = session()->get("id_cliente");
            $id_usuario = DesencriptarId($request->input("HiddenIdUsuario"));
            Filtrar($id_usuario, "STRING");
            $usuario = Usuarios::where("_id", $id_usuario)->where("clientes.cliente_id", $cliente_id)->first();
            if (!$usuario) {
                $Res = -1;
                $Mensaje = "Usuario no existe";
            }
        }
        if ($Res >= 0) {
            $nombre = addslashes(trim($request->input("TNombre")));
            Filtrar($nombre, "STRING");
            $usuario["nombre"] = $nombre;
            $clientes = array();
            if (empty($nombre)) {
                $Res = -2;
                $Mensaje = "Nombre vacío";
            }
        }
        if ($Res >= 0) {
            foreach ($usuario["clientes"] as $cli) {
                if ($cli["cliente_id"] == $cliente_id) {
                    $perfiles = array();
                    foreach ($cli["perfiles"] as $per) {
                        foreach ($request->input() as $campo => $value) {
                            if (substr($campo, 0, 14) == "SModuloPerfil_" && $value != -1 && $value != null) {
                                $id_modulo = (int)DesencriptarId(substr($campo, 14));
                                Filtrar($id_modulo, "INTEGER", -1);
                                $id_perfil = $value;
                                Filtrar($id_perfil, "INTEGER", -1);
                                $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
                                if ($modulo->modulo == $per["rol_cliente"]) {
                                    $cliente = Cliente::find($cliente_id);
                                    $rol_cliente = $modulo->modulo;
                                    $perfil = Modulo::get_perfil_by_id($modulo, $cliente, $id_perfil);
                                    $perfiles_rol = array(array("perfil" => $perfil));
                                    $per = array("rol_cliente" => $rol_cliente, "perfiles_rol" => $perfiles_rol);
                                }
                            }
                        }
                        array_push($perfiles, $per);
                    }
                    $cli = array("cliente_id" => $cliente_id, "perfiles" => $perfiles);
                }
                array_push($clientes, $cli);
            }
        }
        if ($Res >= 0) {
            if (!Modulo::ClientHasAccessModule($cliente_id, $id_modulo)) {
                $Res = -5;
                $Mensaje = "Sin accesos";
            }
        }
        if ($Res >= 0) {
            $usuario["clientes"] = $clientes;
            $usuario->save();
            if (!$usuario) {
                $Res = -2;
                $Mensaje = "Ocurrio un error actualizando el usuario";
            } else {
                $Res = 1;
            }
        }
        if ($Res >= 0) {
            $Mensaje = "El usuario fue actualizado correctamente";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function AccionUsuario(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            $cliente_id = session()->get("id_cliente");
            $id_usuario = DesencriptarId($request->input("Valor_1"));
            Filtrar($id_usuario, "STRING");
            $usuario = Usuarios::where("_id", $id_usuario)->where("clientes.cliente_id", $cliente_id)->first();
            if (!$usuario) {
                $Res = -1;
                $Mensaje = "Usuario no existe";
            }
        }
        if ($Res >= 0) {
            $usuario["activo"] = ($request->input("Valor_2") == 0) ? false : true;
            $usuario->save();
            if (!$usuario) {
                $Res = -2;
                $Mensaje = "Ocurrio un error en la operación";
            } else {
                $Res = 1;
            }
        }
        if ($Res > 0) {
            $v = ($request->input("Valor_2") == 0) ? "deshabilitado" : "habilitado";
            $Mensaje = "El usuario fue $v correctamente";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function MostrarCambiarPassword(Request $request)
    {
        if (empty($request->input("Valor_1"))) {
            $id_usuario = session()->get("id_usuario");
        } else {
            $id_usuario = DesencriptarId($request->input("Valor_1"));
        }
        if (empty($request->input("Valor_2"))) {
            $ocultar_cancelar = false;
        } else {
            $ocultar_cancelar = true;
        }
        Filtrar($id_usuario, "STRING", "");
        $arretiquetas = array("id_usuario", "ocultar_cancelar");
        $arrvalores = array(EncriptarId($id_usuario), $ocultar_cancelar);
        return view('perfiles.password', array_combine($arretiquetas, $arrvalores));
    }

    public function CambiarPassword(Request $request)
    {
        try {
            $Res = 0;
            $Mensaje = "";
            $propio = false;
            if ($Res >= 0) {
                $_id = $request->input("HiddenIdUsuario");
                if (empty($_id)) {
                    $_id = session()->get("id_usuario");
                    $propio = true;
                } else {
                    $_id = DesencriptarId($_id);
                }
                Filtrar($_id, "STRING", "");
                $cliente_id = session()->get("id_cliente");
                $usuario = Usuarios::where("_id", $_id)->where("clientes.cliente_id", $cliente_id)->first();
                if (!$usuario) {
                    $Res = -1;
                    $Mensaje = "Usuario no existe";
                }
            }
            if ($Res >= 0) {
                $password_actual = $request->input("PActualPasswordPropio");
                Filtrar($password_actual, "STRING", "");
                $password = $request->input("TPassword");
                if (empty($password)) {
                    $password = $request->input("PNuevoPasswordPropio");
                }
                Filtrar($password, "STRING", "");
            }
            if ($Res >= 0 && $propio) {
                if (!Auth::attempt(['email' => $usuario->email, 'password' => $password_actual])) {
                    $Res = -1;
                    $Mensaje = "La contraseña actual es incorrecta.";
                }
            }
            if ($Res >= 0) {
                if (!ContrasenaCompleja($password)) {
                    $Res = -2;
                    $Mensaje = "La contraseña no cumple con los requisitos mínimos de complejidad. (Mínimo 10 caracteres, uso de mayúsculas, minúsculas y números.)";
                }
            }
            if ($Res >= 0) {
                $usuario->password = bcrypt($password);
                $usuario->fecha_cambio_password = date("d/m/Y");
                $usuario->save();
                if (!$usuario) {
                    $Res = -3;
                    $Mensaje = "Ocurrió un error cambiando la contraseña";
                } else {
                    $Res = 1;
                }
            }
            if ($Res >= 0) {
                Session::put('mostrar_cambio_password', 0);
                $Mensaje = "La contraseña fue cambiada con éxito.";
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public static function EsUsuarioNuevo($id_usuario)
    {
        $usuario = Usuarios::find($id_usuario);
        return empty($usuario->fecha_cambio_password);
    }

    public static function TienePermisos($id_modulo, $id_permisos, $operador_logico = "AND")
    {
        return response()->json(array("resultado" => TienePermisos($id_modulo, $id_permisos, $operador_logico)), 200);
    }

    public function MostrarContenedorCambiarPassword($id_modulo_actual)
    {
        $id_cliente = session()->get("id_cliente");
        if (!Modulo::UserHasAccessModuleInClient(
            Auth::user(),
            $id_cliente,
            Modulo::get_modulo_old_name_by_id_modulo((int)$id_modulo_actual)
        )) {
            return NavigationController::SinAcceso($id_modulo_actual);
        }
        if ($id_modulo_actual == 7) {
            $cm = Modulo::ComprobarMenu($id_modulo_actual, 5);
            if ($cm) {
                return $cm;
            }
        }
        $arretiquetas = array("id_modulo_actual");
        $arrvalores = array($id_modulo_actual);
        return view('perfiles.cambio_password_propio', array_combine($arretiquetas, $arrvalores));
    }

    public function MostrarContenedorPerfil($id_modulo_actual)
    {
        $id_cliente = session()->get("id_cliente");
        if (!Modulo::UserHasAccessModuleInClient(
            Auth::user(),
            $id_cliente,
            Modulo::get_modulo_old_name_by_id_modulo((int)$id_modulo_actual)
        )) {
            return NavigationController::SinAcceso($id_modulo_actual);
        }
        if ($id_modulo_actual == 7) {
            $cm = Modulo::ComprobarMenu($id_modulo_actual, 5);
            if ($cm) {
                return $cm;
            }
        }
        $arretiquetas = array("id_modulo_actual");
        $arrvalores = array($id_modulo_actual);
        return view('perfiles.perfil_propio', array_combine($arretiquetas, $arrvalores));
    }

    public function ForzarLogout(Request $request)
    {
        $cliente = null;
        @$cliente = Auth::user();
        if (!empty($cliente)) {
            $lc = new LoginController();
            return $lc->logout($request);
        } else {
            return redirect('/');
        }
    }

    public static function CambiarPerfil($usuario, $id_cliente, $modulo_old_name, $nuevo_perfil)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $clientes_final = array();
            foreach ($usuario->clientes as $cli) {
                if ($cli["cliente_id"] == $id_cliente) {
                    $perfiles_final = array();
                    foreach ($cli["perfiles"] as $per) {
                        if ($per["rol_cliente"] == $modulo_old_name) {
                            $per["perfiles_rol"] = array(array("perfil" => $nuevo_perfil));
                        }
                        array_push($perfiles_final, $per);
                    }
                    $cli["perfiles"] = $perfiles_final;
                }
                array_push($clientes_final, $cli);
            }
            $usuario["clientes"] = $clientes_final;
            $resultado = $usuario->save();
            if (!$resultado) {
                $Res = -1;
                $Mensaje = "Ocurrió un error cambiando el perfil.";
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Res = 1;
            $Mensaje = "El perfil fue modificado correctamente.";
        }
        return array("Res" => $Res, "Mensaje" => $Mensaje);
    }

    public static function TieneProcesosPendientesConClienteQueNoQuiereQueCambiePassword($userId)
    {
        $solicitudes = SolicitudVinculacion::where('id_usuario', $userId)->get();
        if (!empty($solicitudes) && count($solicitudes) > 0) {
            foreach ($solicitudes as $solicitud) {
                $aseguradora = Aseguradora::find($solicitud->asegurador_id);
                if (!empty($aseguradora)) {
                    $cliente = Cliente::where('identificacion', '=', $aseguradora->ruc)->first();
                    if (!empty($cliente)) {
                        if (!$cliente->permitePedirPasswordUsuarioFinalNuevo()) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

}