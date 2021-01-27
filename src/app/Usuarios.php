<?php

namespace App;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Poliza\UsuarioP;
use Carbon\Carbon;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Session;

class Usuarios extends Eloquent implements AuthenticatableContract, CanResetPasswordContract
{

    use Authenticatable, CanResetPassword, Notifiable;


    protected $collection = 'usuarios';


    protected $fillable = [
        'creado_por',
        'nombre',
        'email',
        'password',
        'esBroker',
        'registro',
        'id_usuario',
        'identificacion',
        'fecha_cambio_password',
        'tipo',
        'activo',
        'clientes',
        'require_up',
        'venta_estandar'
    ];

    protected $appends = ['cliente_id'];

    protected $hidden = ['password', 'remember_token'];

    public function sendPasswordResetNotification($token)
    {
        try {
            $this->notify(new ResetPasswordNotification($token));
        } catch (\Exception $e) {
            redirect()->back()->withErrors(['email' => "No se pudo enviar el correo."]);
        }
    }

    public function getClienteIdAttribute()
    {
        return $this->clientes[0]['cliente_id'];
    }

    public function cliente($fields = ['*'])
    {
        return Cliente::select($fields)->where('_id', $this->cliente_id)->first();
    }

    public function getClienteId()
    {
        $clientes = $this->clientes;

        if (Session::has("id_cliente")) {
            return Session::get("id_cliente");
        } else {
            return current($clientes)['cliente_id'];
        }
    }

    public function getCliente()
    {
        $clientes = $this->clientes;

        if (Session::has("id_cliente")) {
            $idCliente = Session::get("id_cliente");
        } else {
            $idCliente = current($clientes)['cliente_id'];
        }

        return Cliente::find($idCliente);
    }

    public function getIdentificacionCliente()
    {
        $clientes = $this->clientes;

        if (Session::has("id_cliente")) {
            $idCliente = Session::get("id_cliente");
        } else {
            $idCliente = current($clientes)['cliente_id'];
        }

        $cliente = Cliente::where("_id", $idCliente)->first(["identificacion"]);

        return $cliente['identificacion'];
    }

    public function hasRole($rol)
    {
        $cliente_id = $this->getClienteId();
        $cliente = Cliente::where("_id", $cliente_id)->first(["roles"]);

        if ($cliente) {
            $roles = $cliente->roles;
            foreach ($roles as $miRol) {
                if ($miRol == $rol) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasPerfilInRol($rol, $perfilComp)
    {
        if ($this->hasRole($rol)) {
            $clientes = $this->clientes;

            foreach ($clientes as $cliente) {
                if ($cliente['cliente_id'] == Session::get("id_cliente")) {
                    foreach ($cliente['perfiles'] as $perfil) {
                        if ($perfil['rol_cliente'] == $rol) {
                            foreach ($perfil['perfiles_rol'] as $perfilRol) {
                                if ($perfilRol['perfil'] == $perfilComp) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function getTipo()
    {
        if ($this->hasPerfilInRol("Emisor", "Vendedor")) {
            return 1;
        }
        if ($this->hasPerfilInRol("Emisor", "Contador")) {
            return 2;
        }
        if ($this->hasPerfilInRol("Emisor", "Consulta")) {
            return 4;
        }
        if ($this->hasPerfilInRol("Emisor", "Facturador")) {
            return 5;
        }

        return null;
    }

    public function actualizarPerfilByRole($role, $perfil, $estab_id = null, $segmento_id = null)
    {
        $clientes = $this->clientes;

        if ($perfil == "Vendedor") {
            $perfiles_rol = [
                [
                    'perfil' => $perfil,
                    'establecimiento_id' => $estab_id
                ]
            ];
        } else {
            if ($perfil == "Consulta") {
                $perfiles_rol = [
                    [
                        'perfil' => $perfil,
                        'segmento_id' => $segmento_id
                    ]
                ];
            } else {
                $perfiles_rol = [
                    [
                        'perfil' => $perfil
                    ]
                ];
            }
        }

        for ($i = 0; $i < count($clientes); $i++) {
            if ($clientes[$i]["cliente_id"] == $this->getClienteId()) {
                $existeRole = false;

                for ($j = 0; $j < count($clientes[$i]["perfiles"]); $j++) {
                    if ($clientes[$i]["perfiles"][$j]["rol_cliente"] == $role) {
                        if ($perfil == null || $perfil == "") {
                            array_splice($clientes[$i]["perfiles"], $j, 1);
                        } else {
                            array_splice($clientes[$i]["perfiles"], $j, 1);

                            $clientes[$i]["perfiles"][$j]["rol_cliente"] = $role;
                            $clientes[$i]["perfiles"][$j]["perfiles_rol"] = $perfiles_rol;
                        }

                        $existeRole = true;
                        break;
                    }
                }

                if ($existeRole == false && $perfil != null && $perfil != "") {
                    $clientes[$i]["perfiles"][$j] = [
                        'rol_cliente' => $role,
                        'perfiles_rol' => $perfiles_rol
                    ];
                }

                $clientes[$i]["perfiles"];
            }
        }

        $this->clientes = $clientes;
    }

    public function actualizarPerfilByRoleAndId($role, $perfil, $estab_id = null, $segmento_id = null, $id_cliente)
    {
        $clientes = $this->clientes;

        if ($perfil == "Vendedor") {
            $perfiles_rol = [
                [
                    'perfil' => $perfil,
                    'establecimiento_id' => $estab_id
                ]
            ];
        } else {
            if ($perfil == "Consulta") {
                $perfiles_rol = [
                    [
                        'perfil' => $perfil,
                        'segmento_id' => $segmento_id
                    ]
                ];
            } else {
                $perfiles_rol = [
                    ['perfil' => $perfil]
                ];
            }
        }

        for ($i = 0; $i < count($clientes); $i++) {
            if ($clientes[$i]["cliente_id"] == $id_cliente) {
                $existeRole = false;

                for ($j = 0; $j < count($clientes[$i]["perfiles"]); $j++) {
                    if ($clientes[$i]["perfiles"][$j]["rol_cliente"] == $role) {
                        if ($perfil == null || $perfil == "") {
                            array_splice($clientes[$i]["perfiles"], $j, 1);
                        } else {
                            array_splice($clientes[$i]["perfiles"], $j, 1);
                            $clientes[$i]["perfiles"][$j]["rol_cliente"] = $role;
                            $clientes[$i]["perfiles"][$j]["perfiles_rol"] = $perfiles_rol;
                        }

                        $existeRole = true;

                        break;
                    }
                }

                if ($existeRole == false && $perfil != null && $perfil != "") {
                    $clientes[$i]["perfiles"][$j] = [
                        'rol_cliente' => $role,
                        'perfiles_rol' => $perfiles_rol
                    ];
                }

                $clientes[$i]["perfiles"];
            }
        }

        $this->clientes = $clientes;
    }

    public function getPerfiles($multiempresa = false)
    {
        $profile = '';

        foreach ($this->clientes as $cliente) {
            $nombre = Cliente::find($cliente['cliente_id'])->nombre_identificacion;

            if ($multiempresa) {
                foreach ($cliente['perfiles'] as $perfil) {
                    foreach ($perfil['perfiles_rol'] as $miPerfil) {
                        $profile .= '<li style="margin-left:15px">';
                            $profile .= '<strong>' . $nombre . '</strong> - ';
                            $profile .= $this->translatePerfil($perfil['rol_cliente']) . " - " . $miPerfil['perfil'];
                        $profile .= '</li>';
                    }
                }
            } else {
                if ($cliente['cliente_id'] == Session::get("id_cliente")) {
                    foreach ($cliente['perfiles'] as $perfil) {
                        foreach ($perfil['perfiles_rol'] as $miPerfil) {
                            $profile .= '<li style="margin-left:15px">';
                                $profile .= $this->translatePerfil($perfil['rol_cliente']) . " - " . $miPerfil['perfil'];
                            $profile .= '</li>';
                        }
                    }
                }
            }
        }

        return $profile;
    }

    public function getProfiles()
    {
        $perfiles = "";

        foreach ($this->clientes as $cliente) {
            foreach ($cliente['perfiles'] as $perfil) {
                foreach ($perfil['perfiles_rol'] as $miPerfil) {
                    $perfiles .= $this->translatePerfil(
                            $perfil['rol_cliente']
                        ) . " - " . $miPerfil['perfil'] . ' <br> ';
                }
            }
        }

        return substr($perfiles, 0, -6) ? substr($perfiles, 0, -6) : "";
    }

    public function getPerfilesSoporte()
    {
        $perfiles = "";

        foreach ($this->clientes as $cliente) {
            foreach ($cliente['perfiles'] as $perfil) {
                foreach ($perfil['perfiles_rol'] as $miPerfil) {
                    $perfiles .= $perfil['rol_cliente'] . " - " . $miPerfil['perfil'] . ', ';
                }
            }
        }

        return $perfiles;
    }

    public function getPerfilesByRole($role)
    {
        $perfiles = "";

        foreach ($this->clientes as $cliente) {
            if ($cliente['cliente_id'] == Session::get("id_cliente")) {
                foreach ($cliente['perfiles'] as $perfil) {
                    if ($perfil['rol_cliente'] == $role) {
                        $perfiles = $perfil['perfiles_rol'][0];
                    }
                }
            }
        }

        return $perfiles;
    }

    public function getProfileByUser($role, $client)
    {
        $arrayProfiles = "";

        foreach ($this->clientes as $clienteProfile) {
            if ($clienteProfile['cliente_id'] == $client->_id) {
                foreach ($clienteProfile['perfiles'] as $profile) {
                    if ($profile['rol_cliente'] == $role) {
                        $arrayProfiles = $profile['perfiles_rol'][0];
                    }
                }
            }
        }

        return $arrayProfiles;
    }

    public function puedeVenderEstandar()
    {
        if ($this->hasPerfilInRol('Administrador', 'VentaDirecta')) {
            $permiso = $this->venta_estandar;
            if (isset($permiso) && $permiso == true) {
                return true;
            }
        }

        return false;
    }

    public function getFirstRolByCurrentCustomer()
    {
        $first_perfil = false;

        if (isset($this->clientes[0]["perfiles"][0]["rol_cliente"])) {
            $first_perfil = $this->clientes[0]["perfiles"][0]["rol_cliente"];
        }

        return $first_perfil;
    }

    public function poseePerfil($perfil)
    {
        foreach ($this->clientes[0]["perfiles"] as $perfiles) {
            if ($perfiles["rol_cliente"] == $perfil) {
                return true;
            }
        }

        return false;
    }

    public function getFirstPerfilByCurrentCustomer()
    {
        $first_perfil = false;

        if (isset($this->clientes[0]["perfiles"][0]["perfiles_rol"][0]["perfil"])) {
            $first_perfil = $this->clientes[0]["perfiles"][0]["perfiles_rol"][0]["perfil"];
        }

        return $first_perfil;
    }

    public function isValidPolizaUser()
    {
        return $this->poseePerfil('PolizaElectronica');
    }


    public function esPyme()
    {
        $cliente = $this->getCliente();

        if (isset($cliente) && isset($cliente->pyme) && $cliente->pyme == true) {
            return true;
        }

        return false;
    }

    public function setPolizaSessionVars()
    {
        $id_usuario = $this->_id;

        $usuario_p = UsuarioP::where("id_usuario", $id_usuario)->first(["id_perfil", "id_aseguradora", "id_broker"]);
        $id_perfil = $usuario_p["id_usuario"];
        $id_aseguradora = $usuario_p["id_aseguradora"];
        $id_aseguradora = (!empty($id_aseguradora)) ? $id_aseguradora : false;
        $id_broker = $usuario_p["id_broker"];
        $id_broker = (!empty($id_broker)) ? $id_broker : false;
        $user = Auth::user();
        $rol = $user->getFirstRolByCurrentCustomer();
        $perfil = $user->getFirstPerfilByCurrentCustomer();

        Session::put('id_modulo', 6);
        Session::put('modulo', 'Poliza');
        Session::put('poliza_rol', $rol);
        Session::put('poliza_id_perfil', $id_perfil);
        Session::put('poliza_perfil', $perfil);
        Session::put('poliza_id_aseguradora', $id_aseguradora);
        Session::put('poliza_id_broker', $id_broker);
    }

    public function getEstablecimiento($role, $miPerfil)
    {
        $perfiles = "";
        foreach ($this->clientes as $cliente) {
            if ($cliente['cliente_id'] == Session::get("id_cliente")) {
                foreach ($cliente['perfiles'] as $perfil) {
                    if ($perfil['rol_cliente'] == $role) {
                        foreach ($perfil['perfiles_rol'] as $misPerfilesRol) {
                            if ($misPerfilesRol['perfil'] == $miPerfil) {
                                return $misPerfilesRol['establecimiento_id'];
                            }
                        }
                        $perfiles = $perfil['perfiles_rol'][0];
                    }
                }
            }
        }

        return false;
    }

    public function getPerfilSegmento($role, $miPerfil)
    {
        foreach ($this->clientes as $cliente) {
            if ($cliente['cliente_id'] == Session::get("id_cliente")) {
                foreach ($cliente['perfiles'] as $perfil) {
                    if ($perfil['rol_cliente'] == $role) {
                        foreach ($perfil['perfiles_rol'] as $misPerfilesRol) {
                            if ($misPerfilesRol['perfil'] == $miPerfil) {
                                return $misPerfilesRol['segmento_id'];
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function getEstado()
    {
        if ($this->activo == true) {
            return 'Activo';
        }

        return 'Inactivo';
    }

    public function hasAccessProveedor($opcion)
    {
        $administrador = ['menu_cargar', 'menu_doc_emitidos'];

        $features = [];

        if ($this->hasPerfilInRol("Proveedor", "Administrador")) {
            $features = array_merge($features, $administrador);
        }

        foreach ($features as $feature) {
            if ($feature == $opcion) {
                return true;
            }
        }

        return false;
    }

    public function hasAccessRace($opcion)
    {
        $administrador = [
            'menu_dashboard',
            'menu_opciones',
            'menu_cargar_documentos',
            'menu_bandeja_entrada',
            'menu_reportes',
            'menu_workflows',
            'menu_usuarios',
            'menu_proveedores',
            'menu_reporte_compras',
            'menu_reporte_doc_errados',
            'menu_reporte_retenciones',
            'menu_reporte_personalizado',
            'menu_reporte_exportados',
            'menu_administrar_empresas'
        ];
        $asignador = [
            'menu_dashboard',
            'menu_cargar_documentos',
            'menu_bandeja_entrada',
            'menu_reportes',
            'menu_proveedores',
            'menu_reporte_compras',
            'menu_reporte_doc_errados',
            'menu_reporte_personalizado',
            'menu_reporte_exportados'
        ];
        $aprobador = [
            'menu_cargar_documentos',
            'menu_bandeja_entrada',
            'menu_reporte_personalizado',
            'menu_reporte_exportados'
        ];

        $features = [];

        if ($this->hasPerfilInRol("Race", "Administrador")) {
            $features = array_merge($features, $administrador);
        }
        if ($this->hasPerfilInRol("Race", "Aprobador")) {
            $features = array_merge($features, $aprobador);
        }
        if ($this->hasPerfilInRol("Race", "Asignador")) {
            $features = array_merge($features, $asignador);
        }

        foreach ($features as $feature) {
            if ($feature == $opcion) {
                return true;
            }
        }

        return false;
    }

    public function hasAccessRecaudos($opcion)
    {
        $administrador = ['menu_opciones', 'menu_registro_transacciones', 'menu_registro_botonpagos'];

        $features = [];

        if ($this->hasPerfilInRol("Recaudos", "Administrador")) {
            $features = array_merge($features, $administrador);
        }

        foreach ($features as $feature) {
            if ($feature == $opcion) {
                return true;
            }
        }

        return false;
    }

    public function hasAccessProntoPago($opcion)
    {
        $administrador = ['menu_proveedores', 'bandeja', 'bandeja_aprobada', 'menu_perfil'];
        $financista = ['menu_pagadores', 'bandeja_financiar', 'menu_condiciones', 'menu_perfil', 'bandeja_financiados'];

        $features = [];

        if ($this->hasPerfilInRol("Prontopago", "Administrador")) {
            $features = array_merge($features, $administrador);
        }

        if ($this->hasPerfilInRol("Prontopago", "Financista")) {
            $features = array_merge($features, $financista);
        }

        foreach ($features as $feature) {
            if ($feature == $opcion) {
                return true;
            }
        }

        return false;
    }

    public function hasAccessStupendo($opcion)
    {
        $administrador = ['dashboard', 'planes_vendidos', 'crear_usuario_venta', 'reporte_consumo_plan'];
        $emisor = ['dashboard', 'nuevo_documento', 'docemitidos', 'reports'];
        $receptor = ['docrecibidos'];
        $vendedor = ['nuevo_documento', 'docemitidos'];
        $contador = ['docemitidos', 'docrecibidos'];
        $facturador = ['nuevo_documento', 'docemitidos', 'docrecibidos'];

        $features = [];

        if ($this->hasPerfilInRol("Administrador", "AdministradorControl")) {
            $features = array_merge($features, $administrador);
        }
        if ($this->hasPerfilInRol("Emisor", "Administrador")) {
            $features = array_merge($features, $emisor);
        }
        if ($this->hasPerfilInRol("Receptor", "Adquiriente")) {
            $features = array_merge($features, $receptor);
        }
        if ($this->hasPerfilInRol("Emisor", "Vendedor")) {
            $features = array_merge($features, $vendedor);
        }
        if ($this->hasPerfilInRol("Emisor", "Contador")) {
            $features = array_merge($features, $contador);
        }
        if ($this->hasPerfilInRol("Emisor", "Facturador")) {
            $features = array_merge($features, $facturador);
        }

        foreach ($features as $feature) {
            if ($feature == $opcion) {
                return true;
            }
        }

        return false;
    }


    public function hasAccessPoliza($opcion)
    {
        $asegurador = ['dashboard', 'consultar_polizas', 'firmar_polizas', 'consultar_formularios'];
        $consultor = ['dashboard', 'consultar_formularios'];
        $asegurado = ['dashboard', 'formularios_vinculacion', 'consultar_polizas', 'consultar_formularios'];
        $juridico = ['dashboard', 'formularios_vinculacion'];
        $broker = ['dashboard', 'consultar_formularios'];

        $features = [];

        if ($this->hasPerfilInRol("Asegurador", "Administrador")) {
            $features = array_merge($features, $asegurador);
        }
        if ($this->hasPerfilInRol("Asegurador", "Consultor")) {
            $features = array_merge($features, $consultor);
        }
        if ($this->hasPerfilInRol("Asegurado", "Asegurado")) {
            $features = array_merge($features, $asegurado);
        }
        if ($this->hasPerfilInRol("Asegurado", "Juridico")) {
            $features = array_merge($features, $juridico);
        }
        if ($this->hasPerfilInRol("PolizaElectronica", "Broker")) {
            $features = array_merge($features, $broker);
        }


        foreach ($features as $feature) {
            if ($feature == $opcion) {
                return true;
            }
        }

        return false;
    }

    public function hasEnableRoute($route)
    {
        $estado = Auth::user()->getCliente(['estado'])->estado;

        $rutas['nuevo_documento'] = '5';
        $rutas['docemitidos'] = '5';
        $rutas['reports'] = '5';

        if ($rutas[$route] != $estado) {
            return 'disabled';
        }
    }

    public function getEstablecimientoId($perfilComp)
    {
        foreach ($this->clientes as $cliente) {
            foreach ($cliente['perfiles'] as $perfil) {
                foreach ($perfil['perfiles_rol'] as $perfilRol) {
                    if ($perfilRol['perfil'] == $perfilComp) {
                        return $perfilRol['establecimiento_id'];
                    }
                }
            }
        }

        return null;
    }

    public function translatePerfil($perfil)
    {
        $tipo = "";

        switch ($perfil) {
            case 'Race':
                $tipo = "Recepción";
                break;

            case 'Emisor':
                $tipo = "Emisión";
                break;

            case 'Proveedor':
                $tipo = "Proveedor";
                break;

            case 'Receptor':
                $tipo = "Receptor";
                break;

            case 'Administrador':
                $tipo = "Administrador";
                break;

            case 'Asegurador':
                $tipo = "Asegurador";
                break;

            case 'PolizaElectronica':
                $tipo = "Póliza";
                break;

            case 'DocumentosElectronicos':
                $tipo = "Documentos Electrónicos";
                break;

            case 'Broker':
                $tipo = "Broker";
                break;

            case 'Prontopago':
                $tipo = "Pronto Pago";
                break;

            case 'Recaudos':
                $tipo = "Recaudos";
                break;

            case 'Asegurado':
                $tipo = "Asegurado";
                break;
        }

        return $tipo;
    }


    public function setFechaCambioContrasenaHoy()
    {
        $this->fecha_cambio_password = Carbon::now()->format('d/m/Y');
        $this->save();
    }

    public function setRequireUpdate($require)
    {
        $this->require_up = $require;
        $this->save();
    }

    public function isContrasenaCaducada()
    {
        if ($this->require_up) {
            return true;
        }

        $cliente = $this->getCliente();
        $dias_cad = $cliente->getDiasCaducidadContrasena();

        if ($dias_cad == false) {
            return false;
        }

        if (isset($this->fecha_cambio_password)) {
            $d = explode("/", $this->fecha_cambio_password);
            $fecha_cad = Carbon::create($d[2], $d[1], $d[0], 0, 0, 0); // INICIO DEL DIA
            $dif = $fecha_cad->diffInDays(Carbon::now(), false);

            if ($dif > $dias_cad) {
                return true;
            }
            return false;
        } else {
            $this->setFechaCambioContrasenaHoy();
        }

        return false;
    }

    public function verificarSeguridadContrasenaReseteada($password)
    {
        $cliente = $this->getCliente();
        return $cliente->validarContrasena($password);
    }

    public function tienePerfilValido()
    {
        return true;
        if (
            $this->hasPerfilInRol("Race", "Administrador") ||
            $this->hasPerfilInRol("Emisor", "Administrador") ||
            $this->hasPerfilInRol("Emisor", "Vendedor") ||
            $this->hasPerfilInRol("Emisor", "Contador") ||
            $this->hasPerfilInRol("Emisor", "Consulta") ||
            $this->hasPerfilInRol("Administrador", "AdministradorControl") ||
            $this->hasPerfilInRol("Administrador", "AdministradorInterno") ||
            $this->hasPerfilInRol("Administrador", "Soporte") ||
            $this->hasPerfilInRol("Administrador", "CustomerCare") ||
            $this->hasPerfilInRol("Administrador", "VentaDirecta") ||
            $this->hasPerfilInRol("Administrador", "Director") ||
            $this->hasPerfilInRol("Partner", "Partner") ||
            $this->hasPerfilInRol("Receptor", "Adquiriente") ||
            $this->isValidPolizaUser() ||
            $this->hasPerfilInRol("Proveedor", "Administrador") ||
            $this->hasPerfilInRol("Prontopago", "Financista")
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function esReceptor()
    {
        dd($this->hasPerfilInRol("Receptor", "Adquiriente"));
        if ($this->hasPerfilInRol("Receptor", "Adquiriente")) {
            return true;
        } else {
            return false;
        }
    }

    public function esAdministrativo()
    {
        if (
            $this->hasPerfilInRol("Administrador", "AdministradorControl") ||
            $this->hasPerfilInRol("Administrador", "AdministradorInterno") ||
            $this->hasPerfilInRol("Administrador", "Soporte") ||
            $this->hasPerfilInRol("Administrador", "CustomerCare") ||
            $this->hasPerfilInRol("Administrador", "Director") ||
            $this->hasPerfilInRol("Administrador", "VentaDirecta")
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function getPerfil()
    {
        $user_tipo = "";
        if ($this->hasPerfilInRol("Emisor", "Vendedor")) {
            $user_tipo = 'V';
        } elseif ($this->hasPerfilInRol("Emisor", "Contador")) {
            $user_tipo = 'C';
        } elseif ($this->hasPerfilInRol("Emisor", "Facturador")) {
            $user_tipo = 'F';
        } else {
            $user_tipo = 'P';
        }

        return $user_tipo;
    }

    //poliza
    public function isBroker()
    {
        $cliente = $this->getCliente();

        if ($cliente->hasRole("Broker")) {
            return true;
        }

        return false;
    }

    public function eliminarAccesoEmpresa($cliente_id)
    {
        $clientes = $this->clientes;
        $i = 0;

        for (; $i < count($clientes); $i++) {
            if ($clientes[$i]["cliente_id"] == $cliente_id) {
                array_splice($clientes, $i, 1);
            }
        }

        $this->clientes = $clientes;
        $this->save();
    }

    //fin poliza

    public function agregarPerfilYRolEnCasoDeSerNecesario($id_cliente, $perfil_predeterminado, $nombre_modulo)
    {
        $seActualizo = false;
        try {
            if (!Modulo::UserHasAccessModuleInClient($this, $id_cliente, $nombre_modulo)) {
                $clientes_final = array();
                $clientes = $this->clientes;
                $cliente_existente_en_usuario = false;
                foreach ($clientes as $cliente_usuario) {
                    if ($cliente_usuario["cliente_id"] == $id_cliente) {
                        $cliente_existente_en_usuario = true;
                        if (!empty($cliente_usuario["perfiles"])) {
                            $perfiles = $cliente_usuario["perfiles"];
                        } else {
                            $perfiles = array();
                        }
                        $perfil_nuevo = array(
                            "rol_cliente" => $nombre_modulo,
                            "perfiles_rol" => array(
                                array("perfil" => $perfil_predeterminado)
                            )
                        );
                        array_push($perfiles, $perfil_nuevo);
                        $cliente_usuario["perfiles"] = $perfiles;
                    }
                    array_push($clientes_final, $cliente_usuario);
                }
                if (!$cliente_existente_en_usuario) {
                    $perfil = $perfil_predeterminado;
                    $perfiles_rol = array(array("perfil" => $perfil));
                    $perfiles = array(
                        array(
                            'rol_cliente' => $nombre_modulo,
                            'perfiles_rol' => $perfiles_rol
                        )
                    );
                    $cliente_usuario = array('cliente_id' => $id_cliente, 'perfiles' => $perfiles);
                    array_push($clientes_final, $cliente_usuario);
                }
                $this["clientes"] = $clientes_final;
                $resultado = $this->save();
                if (!$resultado) {
                    Log::error(
                        "En Usuarios->agregarPerfilYRolEnCasoDeSerNecesario($id_cliente, $perfil_predeterminado, $nombre_modulo) - " .
                        "No se pudo preparar el usuario $this->email para $nombre_modulo."
                    );
                } else {
                    $seActualizo = true;
                }
            }
        } catch (\Exception $e) {
            Log::error(
                "Exception en Usuarios->agregarPerfilYRolEnCasoDeSerNecesario($id_cliente, $perfil_predeterminado, $nombre_modulo) - "
                . $e->getMessage() . ': ' . $e->getTraceAsString()
            );
        }
        return $seActualizo;
    }
}