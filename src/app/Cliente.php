<?php
namespace App;

use App\Http\Controllers\BotonDePagoController;
use App\Http\Controllers\EmailController;
use Illuminate\Auth\Authenticatable;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Session;
use App\Documento;
use App\Race\Opcion;
use Carbon\Carbon;
use App\Library\passcheck\Passwd;
use Config;
use MongoDB\BSON\UTCDateTime;

class Cliente extends Eloquent implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, Notifiable;

    const CONSUMIDOR_FINAL = "9999999999999";


    protected $collection = 'clientes';


    protected $fillable = [
        'nombre_identificacion',
        'nombre_comercial',
        'email',
        'email_not',
        'identificacion',
        'tipo_identificacion',
        'dir_matriz',
        'telefono',
        'password',
        'estado',
        'logo_id',
        'banner_id',
        'roles',
        'api_key',
        'secuencial_pre_facturas',
        'yanbal',
        'toni',
        'herbalife',
        'conclinica',
        'clic_factura',
        'clic_factura_api',
        'novatech',
        'novatech_api',
        'novatech_compania',
        'novatech_tipo_orden',
        'app_tokens',
        'app_tokens_push',
        'boton_pagos',
        'rating',
        'solicitud_firma',
        'plan_id',
        'multi_hilos',
        'num_doc_hilos',
        'num_hilos',
        'notificacion_errados',
        'email_notificacion_errados',
        'pagos_datafast',
        'last_reporteTxt',
        'numero_comprobantes',
        'empresa_email',
        'activo',
        'tipo_codificacion',
        'reporte_documentos',
        'first_download',
        'placa',
        'xml',
        'nipro',
        'todo_retroalimentar',
        'opcion_api',
        'campos_personalizados',
        'modulos',
        'valida_monto',
        'banner_superior',
        'banner_inferior',
        'exportador',
        "regimen_microempresa",
        'pyme',
        'url_facturero',
        'solicitud_migracion',
        'motivos_rechazo',
        'reprocesado',
        'valida_ciudad',
        'clientes_dependientes_de',
        'retroalimentar_errados',
        'fix_descuento',
        'valor_dias_caduca',
        'valor_dias_notifica',
        'EAN13_EmpresaCliente',
        'agente_de_retencion'
    ];


    protected $hidden = ['password', 'remember_token'];


    public function certificados()
    {
        return $this->embedsMany('App\Certificado');
    }

    public function segmentos()
    {
        return $this->embedsMany('App\Segmento');
    }

    public function perfiles()
    {
        return $this->embedsMany('App\Perfil');
    }

    public function empresas_administradas()
    {
        return $this->embedsMany('App\empresaAdministrada');
    }

    public function productos()
    {
        return $this->hasMany('App\Producto', 'emisor_id');
    }

    public function establecimientos()
    {
        return $this->embedsMany('App\Establecimiento');
    }

    public function facturas_periodicas()
    {
        return $this->hasMany('App\FacturaRecurrente', 'emisor_id');
    }

    public function planes()
    {
        return $this->hasMany('App\ClientePlan', 'emisor_id');
    }

    public function clientePlan()
    {
        return $this->hasMany('App\ClientePlan', 'cliente_id');
    }

    public function inventarios()
    {
        return $this->hasMany('App\Inventario', 'emisor_id');
    }

    public function parametros()
    {
        return $this->embedsOne('App\Parametro');
    }

    public function emailsDocumentType()
    {
        return $this->embedsOne('App\EmailDocumentType');
    }

    public function pagos_datafast()
    {
        return $this->embedsOne('App\PagoDatafast');
    }

    public function empresa_email()
    {
        return $this->embedsMany('App\EmpresaEmail');
    }

    public function EAN13_EmpresaCliente()
    {
        return $this->embedsMany('App\EAN13EmpresaCliente');
    }

    public function campos_personalizados_race()
    {
        return $this->embedsMany('App\CampoPersonalizadoRace');
    }

    public function motivos_rechazo()
    {
        return $this->embedsMany('App\MotivoRechazo');
    }

    public function catalogo_adicional()
    {
        return $this->embedsMany('App\CatalogoAdicional');
    }


    public function setEstado($e)
    {
        $this->estado = $e;
        $this->save();
    }

    public function getIdentificacion()
    {
        $DocIdentificacion = $this->identificacion;
        return $DocIdentificacion;
    }

    public function hasRole($name)
    {
        foreach ($this->roles as $role) {
            if ($role == $name) {
                return true;
            }
        }

        return false;
    }

    public function hasClientEAN13()
    {
        if ($this->EAN13_EmpresaCliente()->count() > 0) {
            return true;
        }

        return false;
    }

    public function assignRole($role)
    {
        if (!$this->hasRole($role)) {
            $roles = $this->roles;
            $roles[] = $role;
            $this->roles = $roles;
            $this->save();
        }
    }

    public function removeRole($role)
    {
        $roles = $this->roles;
        foreach ($roles as $i => $r) {
            if ($r == $role) {
                unset($roles[$i]);
                $this->roles = $roles;
                $this->save();
                break;
            }
        }
    }

    public function hasAppToken($name)
    {
        if ($this->app_tokens) {
            foreach ($this->app_tokens as $token) {
                if ($token == $name) {
                    return true;
                }
            }
        }

        return false;
    }

    public function assignAppToken($token)
    {
        if (!$this->hasAppToken($token)) {
            $app_tokens = $this->app_tokens;
            $app_tokens[] = $token;
            $this->app_tokens = $app_tokens;
            $this->save();
        }
    }

    public function removeAppToken($token)
    {
        $app_tokens = $this->app_tokens;
        foreach ($app_tokens as $i => $r) {
            if ($r == $token) {
                unset($app_tokens[$i]);
                $this->app_tokens = $app_tokens;
                $this->save();
                break;
            }
        }
    }

    public function hasAppTokenPush($name)
    {
        if ($this->app_tokens_push) {
            foreach ($this->app_tokens_push as $token) {
                if ($token == $name) {
                    return true;
                }
            }
        }

        return false;
    }

    public function assignAppTokenPush($token)
    {
        if (!$this->hasAppTokenPush($token)) {
            $app_tokens_push = $this->app_tokens_push;
            $app_tokens_push[] = $token;
            $this->app_tokens_push = $app_tokens_push;
            $this->save();
        }
    }

    public function removeAppTokenPush($token)
    {
        $app_tokens_push = $this->app_tokens_push;
        foreach ($app_tokens_push as $i => $r) {
            if ($r == $token) {
                unset($app_tokens_push[$i]);
                $this->app_tokens_push = $app_tokens_push;
                $this->save();
                break;
            }
        }
    }

    public function hasTokensPushNotification()
    {
        if (isset($this->app_tokens_push)) {
            if (count($this->app_tokens_push) > 0) {
                return true;
            }
        }

        return false;
    }

    public function isConsumidorFinal()
    {
        return self::CONSUMIDOR_FINAL == $this->identificacion;
    }

    public function getTokensPushNotification()
    {
        return $this->app_tokens_push;
    }

    public function getFechaRegistro($format = 'd/m/Y')
    {
        return date($format, strtotime($this->created_at));
    }

    public function hasFacturaPeriodica()
    {
        if (count($this->facturas_periodicas) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getEstadoAsistente()
    {
        $estado = "";

        switch ($this->estado) {
            case 0:
                $estado = "Firma Electrónica";
                break;
            case 1:
                $estado = "Información Personal";
                break;
            case 2:
                $estado = "Pruebas SRI";
                break;
            case 3:
                $estado = "Producción SRI";
                break;
            case 4:
                $estado = "Planes";
                break;
            case 5:
                $estado = "Facturando";
                break;
        }

        return $estado;
    }

    public function getPorcentajeEstadoAsistente()
    {
        $porcentaje = "";

        switch ($this->estado) {
            case 0:
                $porcentaje = 0;
                break;
            case 1:
                $porcentaje = 20;
                break;
            case 2:
                $porcentaje = 40;
                break;
            case 3:
                $porcentaje = 60;
                break;
            case 4:
                $porcentaje = 80;
                break;
            case 5:
                $porcentaje = 100;
                break;
        }

        return $porcentaje;
    }

    public function actualizarParametrosPlan($cliente_plan)
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($this->parametros->expiracion_plan)) {

                $plan_expiracion = $this->parametros->expiracion_plan;
                $plan_expiracion = Carbon::instance($plan_expiracion->toDateTime())->subDays(30);
                $today = Carbon::now();

                if ($plan_expiracion > $today) {

                    $parametros->increment('cantidad_documentos', $cliente_plan->cantidad_doc);
                } else {
                    $parametros->cantidad_documentos = (int)$cliente_plan->cantidad_doc;
                }
            } else {
                $parametros->cantidad_documentos = (int)$cliente_plan->cantidad_doc;
            }

            $parametros->expiracion_plan = $cliente_plan->expiracion_plan;

            if (isset($parametros->recepcion_automatica_old) && isset($parametros->recepcion_automatica)) {
                if ($parametros->recepcion_automatica_old != $parametros->recepcion_automatica) {
                    $parametros->recepcion_automatica = $parametros->recepcion_automatica_old;
                    $parametros->unset('recepcion_automatica_old');
                    $parametros->save();
                    $this->save();
                }
            }

            $parametros->save();
        } else {
            $parametros = new Parametro(
                array(

                    'cantidad_documentos' => (int)$cliente_plan->cantidad_doc,
                    'expiracion_plan' => $cliente_plan->expiracion_plan

                )
            );

            $this->parametros()->save($parametros);
        }

        return true;
    }

    public function actualizarParametrosEstadisticas($cliente_plan)
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($this->parametros->expiracion_plan_estadisticas)) {

                $plan_expiracion = $this->parametros->expiracion_plan;
                $plan_expiracion = Carbon::instance($plan_expiracion->toDateTime())->subDays(30);
                $today = Carbon::now();

                if ($plan_expiracion > $today) {

                    $parametros->increment('cantidad_documentos', $cliente_plan->cantidad_doc);
                } else {
                    $parametros->cantidad_documentos = (int)$cliente_plan->cantidad_doc;
                }
            } else {
                $parametros->cantidad_documentos = (int)$cliente_plan->cantidad_doc;
            }

            $parametros->expiracion_plan = $cliente_plan->expiracion_plan;

            if (isset($parametros->recepcion_automatica_old) && isset($parametros->recepcion_automatica)) {
                if ($parametros->recepcion_automatica_old != $parametros->recepcion_automatica) {
                    $parametros->recepcion_automatica = $parametros->recepcion_automatica_old;
                    $parametros->unset('recepcion_automatica_old');
                    $parametros->save();
                    $this->save();
                }
            }

            $parametros->save();
        } else {
            $parametros = new Parametro(
                array(

                    'cantidad_documentos' => (int)$cliente_plan->cantidad_doc,
                    'expiracion_plan' => $cliente_plan->expiracion_plan

                )
            );

            $this->parametros()->save($parametros);
        }

        return true;
    }

    public function planCaducado()
    {
        if ($this->parametros == null) {
            return true;
        }

        if (!isset($this->parametros->expiracion_plan) || $this->parametros->expiracion_plan == null) {
            return true;
        }


        $plan_expiracion = Carbon::instance($this->parametros->expiracion_plan->toDateTime());
        $today = Carbon::now();

        if ($today >= $plan_expiracion) {
            return true;
        }

        return false;
    }

    public function facturacion($from, $to)
    {
        $dataMatch = ['emisor_id' => $this->id, 'created_at' => ['$gte' => $from, '$lte' => $to]];
        return \DB::collection('documentos')->where($dataMatch)->count();
    }

    public function esEditable()
    {
        $edit = true;

        if (in_array('Emisor', $this->roles)) {
            $edit = false;
        }

        return $edit;
    }

    public function getUltimoCertificado()
    {
        return $this->certificados()->sortByDesc('created_at')->first();
    }

    public function getEmailNot()
    {
        $emails = str_replace(";", ",", str_replace("|", ",", str_replace(" ", "", $this->email_not)));

        $array_emails = explode(",", $emails);

        $email_receptor_list = array();

        foreach ($array_emails as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                array_push($email_receptor_list, $address);
            }
        }

        return $email_receptor_list;
    }

    public function tieneCredencialesSri()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if ($parametros->contrasena_sri) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function aumentarIntentoSri()
    {
        $parametros = $this->parametros;
        if (isset($parametros)) {
            if (isset($parametros->intentos_sri)) {
                $intentos = $parametros->intentos_sri;
                $parametros->intentos_sri = $intentos + 1;
                $parametros->save();
                if ($parametros->intentos_sri >= Config::get('app.intentos_sri')) {
                    $this->desactivarOpcionesSri();
                }
            } else {
                $parametros->intentos_sri = 1;
                $parametros->save();
            }
        }
    }

    public function desactivarOpcionesSri()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            $parametros->anulacion_automatica = false;
            $parametros->recepcion_automatica = false;
            $parametros->error_login_sri = true;
            $parametros->save();

            $to = (object)[];
            $to->email = $this->email;
            $to->name = $this->nombre_identificacion;

            $parameters = array(
                'name' => $this->nombre_identificacion
            );
            EmailController::sendEmail($to, 'credenciales_sri_incorrectas', $parameters, "Credenciales del SRI incorrectas");
        }
    }

    public function tieneCredencialesErroneasSri()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if ($parametros->error_login_sri) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function permitePedirPasswordUsuarioFinalNuevo()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->pedir_password_usuario_nuevo_vinculacion)) {
                if ($parametros->pedir_password_usuario_nuevo_vinculacion) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        \Log::info('PARAMETROS NOT SET');
        return true;
    }


    public function haRealizadoPagoRecien()
    {
        $from = Carbon::now()->subMinutes(Config::get('app.tiempo_nuevo_pago'));
        $to = Carbon::now();

        $dataMatch = null;

        $dataMatch = [
            'created_at' => [
                '$gte' => new UTCDateTime(strtotime($from) * 1000),
                '$lte' => new UTCDateTime(strtotime($to) * 1000)
            ],
            'comprador_id' => $this->_id
        ];
        $pagos = BotonDePago::where($dataMatch)->get();
        foreach ($pagos as $p) {
            $codigo = $p->result_code;
            if (isset($codigo) && BotonDePagoController::manejarCodigosDatafast($codigo)) {
                $creado = $p->created_at;
                return $creado->diffInMinutes($to);
            }
        }

        return false;
    }

    public function hasPermisos($perm)
    {

        $free = ['opcion_reportes', 'opcion_descarga_sri'];
        $plan4016 = [
            'opcion_establecimientos',
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_usuarios'
        ];
        $pyme = [
            'opcion_establecimientos',
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_usuarios',
            'opcion_descarga_sri'
        ];
        $estandar = [
            'opcion_establecimientos',
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_personalizado',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_usuarios',
            'opcion_descarga_sri'
        ];
        $enterprise = [
            'opcion_usuarios',
            'opcion_establecimientos',
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_email',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_descarga_sri',
            'opcion_mod_segmentos'
        ];


        $Basico = ['opcion_reportes', 'opcion_mod_cobranzas', 'opcion_cobranzas', 'opcion_api', 'factura_periodica', 'opcion_prefactura', 'opcion_descarga_sri'];
        $Profesional = [
            'opcion_reportes',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_email',
            'opcion_leyenda',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_prefactura',
            'opcion_descarga_sri'
        ];
        $Emprendedor = [
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_email',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_descarga_sri'
        ];
        $pyme_ant = [
            'opcion_usuarios',
            'opcion_establecimientos',
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_email',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_descarga_sri'
        ];
        $Personalizado = [
            'opcion_usuarios',
            'opcion_establecimientos',
            'opcion_reportes',
            'opcion_prefactura',
            'imprimir_recibo',
            'factura_periodica',
            'opcion_api',
            'opcion_personalizado',
            'opcion_email',
            'opcion_leyenda',
            'opcion_mod_inventario',
            'opcion_mod_cobranzas',
            'opcion_not_facturas',
            'opcion_cobranzas',
            'opcion_descarga_sri'
        ];

        $plan_actual = Session::get('plan');
        $features = [];
        switch ($plan_actual) {
            //Planes nuevos
            case 'Free':
                $features = $free;
                break;
            case 'Plan 40-16':
                $features = $plan4016;
                break;
            case 'PYME':
                $features = $pyme;
                break;
            case 'Estandar':
                $features = $estandar;
                break;
            case 'Enterprise':
                $features = $enterprise;
                break;
            //Planes antiguos
            case 'Basico':
                $features = $Basico;
                break;
            case 'Profesional':
                $features = $Profesional;
                break;
            case 'Emprendedor':
                $features = $Emprendedor;
                break;
            case 'PYME Ant.':
                $features = $pyme_ant;
                break;
            case 'Personalizado':
                $features = $Personalizado;
                break;
        }
        foreach ($features as $feature) {
            if ($feature == $perm) {
                return true;
            }
        }

        if ($perm == 'opcion_api' && $plan_actual == 'Estandar') {
            $cliente = getCliente(['opcion_api']);
            if (isset($cliente)) {
                $opcion = $cliente->opcion_api;
                if (isset($opcion) && $opcion) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getBodyEmail()
    {
        if (isset($this->parametros->email_cuerpo) && $this->parametros->email_cuerpo != '') {
            return $this->parametros->email_cuerpo;
        } else {
            return file_get_contents(public_path("bodyEmail.html"));
        }
    }

    public function mostrarEncuesta()
    {

        if (Session::get('id_usuario') == null) {
            $rating = $this->rating;

            if ($rating == null || $rating == false) {
                $now = Carbon::now();
                $diferencia_dias = $this->asDateTime($this->created_at)->diffInDays($now, false);
                if ($diferencia_dias >= 15) {
                    $count = Documento::where('emisor_id', '=', $this->_id)->count();
                    if ($count >= 15) {
                        Session::put('mostrar_rating', true);
                    }
                }
            }
        } else
        {
            $rating = Session::get('rating');
            if ($rating == null || $rating == false) {
                $now = Carbon::now();
                $diferencia_dias = $this->asDateTime($this->created_at)->diffInDays($now, false);
                if ($diferencia_dias >= 15) {
                    $count = Documento::where('emisor_id', '=', $this->_id)->count();
                    if ($count >= 15) {
                        Session::put('mostrar_rating', true);
                    }
                }
            }
        }
    }

    public function getPerfil()
    {
        $user_tipo = Session::get('user_tipo');
        if ($user_tipo) {
            if ($user_tipo == 1) {
                $user_tipo = 'V';
            }
            if ($user_tipo == 2) {
                $user_tipo = 'C';
            }
        } else {
            $user_tipo = 'P';
        }
        return $user_tipo;
    }

    public function getNumeroComprobantes()
    {
        switch ($this->numero_comprobantes) {
            case "01":
                $numero_comprobantes = "1 - 50";
                break;
            case "02":
                $numero_comprobantes = "51 - 1500";
                break;
            case "03":
                $numero_comprobantes = "1501 - 10000";
                break;
            case "04":
                $numero_comprobantes = "10001 - o más";
                break;
            default:
                $numero_comprobantes = "N/A";
        }

        return $numero_comprobantes;
    }

    public function getUsuariosByRoleByPerfil($role, $perfil)
    {
        $result = array();
        foreach (Usuarios::where("creado_por", $this->id)->where('activo', true)->get() as $usuario) {
            if ($usuario->hasPerfilInRol($role, $perfil)) {
                $result[] = (object)array(
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'email' => $usuario->email,
                );
            }
        }


        return $result;
    }

    public function getUsuarios()
    {
        $result = array();
        foreach (Usuarios::where("creado_por", $this->id)->get() as $usuario) {
            $result[] = (object)array(
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'perfiles' => $usuario->getPerfilesSoporte()
            );
        }
        return $result;
    }

    public function getUsuariosConAcceso()
    {
        return Usuarios::where("clientes.cliente_id", $this->id)->get();
    }

    public function generarPasswd($validar = false)
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if ($validar) {
                $passwordPolicy = [];
                if ($parametros->incluir_mayusculas == true) {
                    $passwordPolicy["upperCharsCount"] = 1;
                } else {
                    $passwordPolicy["upperCharsCount"] = 0;
                }
                if ($parametros->incluir_especiales == true) {
                    $passwordPolicy["specialCharsCount"] = 1;
                } else {
                    $passwordPolicy["specialCharsCount"] = 0;
                }
                if ($parametros->incluir_numeros == true) {
                    $passwordPolicy["numbersCount"] = 1;
                } else {
                    $passwordPolicy["numbersCount"] = 0;
                }
                if ($parametros->tamano_clave_valor != 0) {
                    $passwordPolicy["minimumPasswordLength"] = $parametros->tamano_clave_valor;
                }

                $password = new Passwd($passwordPolicy);
                return $password;
            }
        }

        return new Passwd();
    }

    public function generarContrasena()
    {
        $generador = $this->generarPasswd();

        return $generador->generate();
    }

    public function validarContrasena($contrasena)
    {
        $validador = $this->generarPasswd(true);
        $resultado = $validador->check($contrasena);

        if ($resultado) {
            return true;
        } else {
            $mensaje = "La contraseña debe tener:<ul>";
            if (!$validador->areNumbersOK($contrasena)) {
                $mensaje .= "<li>Al menos un número</li>";
            }
            if (!$validador->areUpperCharsOK($contrasena)) {
                $mensaje .= "<li>Al menos una letra mayúscula</li>";
            }
            if (!$validador->areSpecialCharsOK($contrasena)) {
                $mensaje .= "<li>Al menos un caracter especial</li>";
            }
            if (!$validador->isPasswordLengthOK($contrasena)) {
                $mensaje .= "<li>Longitud mínima de " . $validador->minimumPasswordLength . "</li>";
            }
            $mensaje .= "</ul>";

            return $mensaje;
        }
    }

    public function getDiasCaducidadContrasena()
    {
        $parametros = $this->parametros;

        if (isset($parametros->tiempo_caducidad) && $parametros->tiempo_caducidad != 0) {
            return $parametros->tiempo_caducidad;
        }

        return false;
    }

    public function getFechaCaducidadPlan()
    {
        if (isset($this->parametros)) {
            $parametros = $this->parametros;
        } else {
            return '';
        }

        if (isset($parametros->expiracion_plan)) {
            return $parametros->expiracion_plan->toDateTime()->format('d/m/Y');
        }
    }

    public function getNombrePlanActivo()
    {
        $plan = ClientePlan::where("cliente_id", "=", $this->_id)->where('estado', '=', 1)
            ->orderBy('created_at', 'DESC')
            ->first(['plan_id']);

        if ($plan != null) {
            $nombre_plan = Plan::where("_id", "=", $plan->plan_id)->first(['descripcion']);
            return $nombre_plan['descripcion'];
        }

        return '';
    }

    public function getFechaCaducidadFirma($format = 'd/m/Y')
    {
        $fecha_cad_firma = $this->getUltimoCertificado() != null ? $this->getUltimoCertificado()->validTo_time_t : null;

        if ($fecha_cad_firma) {
            return $fecha_cad_firma->toDateTime()->format($format);
        } else {
            return '';
        }
    }

    public function tieneIntegracionExcel()
    {
        if (isset($this->parametros->integracion_excel) && $this->parametros->integracion_excel) {
            return true;
        }

        return false;
    }

    public function firstDownload()
    {
        if (isset($this->first_download)) {
            if ($this->first_download) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }


    public function usuarios_race()
    {
        return $this->embedsMany('App\Race\UsuarioRec');
    }

    public function proveedores()
    {
        return $this->embedsMany('App\Race\Proveedor');
    }

    public function workflows()
    {
        return $this->embedsMany('App\Race\Workflow');
    }

    public function opciones()
    {
        return $this->embedsOne('App\Race\Opcion');
    }

    public function esClienteRace()
    {
        if ($this->hasRole("Race")) {
            return true;
        }

        return false;
    }

    public function puedeRecibirDocumentos()
    {
        if ($this->hasRole("Emisor") || $this->hasRole("Race")) {
            if (isset($this->parametros) && isset($this->parametros->expiracion_plan)) {
                $expiracion_plan = Carbon::instance($this->parametros->expiracion_plan->toDateTime());
                $today = Carbon::now();
                if ($this->parametros->cantidad_documentos > 0 && $today < $expiracion_plan) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getEmailNotRecepcion()
    {
        $parametros = $this->parametros;
        if (isset($parametros)) {
            return $parametros->getSimpleEmailNotRecepcion();
        }
        return false;
    }

    public function tagEmails()
    {
        $parametros = $this->parametros;
        if (isset($parametros)) {
            return $parametros->tagEmails();
        }
        return false;
    }

    public function getUsuarioRaceById($id)
    {
        $usuario = $this->usuarios_race()->where('id', $id)->first();

        if (isset($usuario)) {
            $result = (object)array(
                'id' => $usuario->id,
                'nombre' => $usuario->id_usuario,
                'email' => $usuario->email,
            );
            return $result;
        }
        return null;
    }

    public function getEmailByReceptor($id)
    {
        $proveedor = $this->proveedores()->where('receptor_id', $id)->first();

        if (isset($proveedor)) {
            return $proveedor->email;
        }

        return null;
    }

    public function getWorkflow($wf_id)
    {
        $wf = $this->workflows()->where("id", $wf_id)->first();
        if (isset($wf)) {
            return $wf->titulo;
        }
        return "Sin Workflow";
    }

    public function getWorkflows()
    {
        $result = array();
        foreach ($this->workflows()->where("estado", 1) as $workflow) {
            $result[] = (object)array(
                'id' => $workflow->id,
                'titulo' => $workflow->titulo,
            );
        }
        return $result;
    }

    public function getWorkflowByTipoDoc($tipo_documento)
    {
        $opciones = $this->opciones;
        if (isset($opciones)) {
            if (array_key_exists($tipo_documento, $opciones->workflows)) {
                return $opciones->workflows[$tipo_documento];
            }
        }

        return "";
    }

    public function setWorkflowByTipoDoc($tipo_documento, $workflow)
    {
        $opciones = $this->opciones;

        if (!isset($opciones)) {
            $workflows[$tipo_documento] = $workflow;
            $opciones = new Opcion(
                array(
                    'workflows' => $workflows
                )
            );
        } else {
            $workflows = $this->opciones->workflows;
            $workflows[$tipo_documento] = $workflow;
            $opciones->workflows = $workflows;
        }

        $this->opciones()->save($opciones);
    }

    public function hasRecepcionAutomatica()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->recepcion_automatica_race)) {
                if ($parametros->recepcion_automatica_race) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function crear($email, $nombre_identificacion, $identificacion, $tipo_identificacion, $dir_matriz, $telefono, $estado, $roles, $modulos)
    {
        try {
            $datos = [
                'nombre_identificacion' => $nombre_identificacion,
                'tipo_identificacion' => $tipo_identificacion,
                'identificacion' => $identificacion,
                'estado' => $estado,
                'roles' => $roles,
                'modulos' => $modulos
            ];

            if ($email) {
                $datos += ['email' => $email];
            }

            if ($dir_matriz) {
                $datos += ['dir_matriz' => $dir_matriz];
            }

            if ($telefono) {
                $datos += ['telefono' => $telefono];
            }


            $cliente = Cliente::create($datos);

            return $cliente;
        } catch (\Exception $e) {
            \Log::error($e);
            return false;
        }
    }

    public function haTenidoPlanFree()
    {
        $cantidad = ClientePlan::where('cliente_id', '=', $this->id)->where("tipo", "prueba")->count();
        if ($cantidad == 0) {
            return false;
        }

        return true;
    }

    public function haTenidoPlanEnterprise()
    {
        $enterprise = Plan::where('descripcion', 'Enterprise')->first(['_id']);
        $cantidad = ClientePlan::where('cliente_id', '=', $this->id)->where("plan_id", $enterprise['_id'])->count();

        if ($cantidad == 0) {
            return false;
        }

        return true;
    }

    public function crearVariablesSesion($user)
    {
        #region delete session variables
        Session::forget('usuario');
        Session::forget('user_tipo');
        Session::forget('id_usuario');
        Session::forget('cliente_id');
        Session::forget('establecimiento_id');
        Session::forget('establecimiento_alias');
        Session::forget('robot_fecha_procesamiento');
        Session::forget('empresas_administradas');
        Session::forget('comprar_plan');
        Session::forget('plan');
        Session::forget('firma_caducidad');
        Session::forget('firma_mensaje');
        Session::forget('firma_fecha');
        Session::forget('comprar_plan_tipo');
        Session::forget('mostrar_rating');
        #endregion

        Session::put('id_usuario', $user->_id);
        Session::put('usuario_nombre', $user->nombre);
        Session::put('id_cliente', $this->id);
        Session::put('collapse_menu', true);

        $establecimiento_id = $user->getEstablecimiento("Emisor", "Vendedor");
        $establecimiento = $this->establecimientos()->find($establecimiento_id);

        $tipo = $user->getTipo();

        if ($tipo) {
            Session::put('usuario', $user->email);
            Session::put('user_tipo', $tipo);
            Session::put('id_usuario', $user->_id);
        }

        Session::put('cliente_id', $this->id);
        Session::put('establecimiento_id', $establecimiento ? $establecimiento->_id : null);
        Session::put('establecimiento_alias', $establecimiento ? $establecimiento->alias : null);

        //Race

        if (isset($this->parametros->robot_fecha_proc) && $this->parametros->robot_fecha_proc) {
            Session::put('robot_fecha_procesamiento', Carbon::createFromTimestamp(strtotime($this->parametros->robot_fecha_proc))->format('d/m/Y H:i:s'));
        }

        $clients = $user->clientes;

        if (count($clients) > 1) {
            $empresas = array();

            foreach ($clients as $emp) {
                $tmp = Cliente::find($emp['cliente_id']);

                if ($emp['cliente_id'] != $this->id) {
                    $empresas[] = array(
                        "empresa_id" => $emp['cliente_id'],
                        "empresa_nombre" => $tmp->nombre_identificacion
                    );
                }
            }

            Session::put('empresas_administradas', $empresas);
        }

        $cliente_ultimo_plan = ClientePlan::where('cliente_id', '=', $this->id)->where('estado', '=', 1)->orderBy('created_at', 'DESC')->first();

        if ($cliente_ultimo_plan != null) {
            $plan = Plan::find($cliente_ultimo_plan->plan_id);
            Session::put('plan', $plan->descripcion);
        }

        if ($this->hasRole('Emisor')) {

            $cert = $this->getUltimoCertificado();

            if ($cert) {
                $diferencia_dias = $cert->getDiasCaducidadFirma();
                $fecha_cadudidad = $cert->getFechaCaducidad();

                if ($diferencia_dias >= 0) {
                    Session::put('firma_caducidad', true);
                    Session::put('firma_mensaje', 'danger');
                    Session::put('firma_fecha', "$fecha_cadudidad");
                } else {
                    if ($diferencia_dias >= -30) {
                        Session::put('firma_caducidad', true);
                        Session::put('firma_mensaje', 'warning');
                        Session::put('firma_fecha', "$fecha_cadudidad");
                    } else {
                        Session::put('firma_caducidad', false);
                    }
                }
            }

            if ($cliente_ultimo_plan == null) {
                Session::put('comprar_plan', true);
            } else {
                if ($this->estado < 5) {
                    Session::put('comprar_plan', true);
                } else {
                    $plan = Plan::find($cliente_ultimo_plan->plan_id);

                    $documentos_disponibles = $this->parametros->cantidad_documentos;
                    $documentos_minimos = ClientePlan::where('cliente_id', '=', $this->id)->where('estado', '=', 1)->sum('cantidad_doc') * 0.10;

                    $plan_expiracion = $this->parametros->expiracion_plan;
                    if ($plan_expiracion) {
                        $plan_expiracion = Carbon::instance($plan_expiracion->toDateTime())->subDays(30);
                    } else {
                        $plan_expiracion = Carbon::instance($this->parametros->expiracion_plan->toDateTime());
                        $plan_expiracion = $plan_expiracion->subDays(30);
                    }

                    $today = Carbon::now();

                    if ($plan_expiracion < $today) {
                        Session::put('comprar_plan', true);
                        Session::put('comprar_plan_tipo', 'fecha');
                    } else {
                        if ($documentos_disponibles <= $documentos_minimos) {
                            Session::put('comprar_plan', true);
                            Session::put('comprar_plan_tipo', 'documentos');
                        } else {
                            Session::put('comprar_plan', false);
                        }
                    }

                    if ($plan->free == true) {
                        Session::put('comprar_plan', true);
                        Session::put('comprar_plan_tipo', 'prueba');
                    }
                }
            }
        }

        $matriz_id_permisos = array();
        $matriz_id_menus = array();
        $arreglo_id_modulos = Modulo::get_array_id_modulos_by_cliente($this);
        foreach ($arreglo_id_modulos as $id_modulo) {
            $modulo = Modulo::get_modulo_by_id_modulo($id_modulo);
            $perfil = Modulo::get_perfil_by_user_cliente_modulo($user, $this, $modulo->modulo);
            if (!empty($perfil)) {
                $id_perfil = Modulo::get_id_perfil_by_name($perfil, $modulo, $this);
                $matriz_id_permisos[$id_modulo] = Modulo::get_array_id_permisos_activos($modulo, $this, $id_perfil);
                $matriz_id_menus[$id_modulo] = Modulo::get_array_id_menus_activos($modulo, $this, $id_perfil);
            }
        }
        Session::put('permisos', $matriz_id_permisos);
        Session::put('menus', $matriz_id_menus);
    }

    public function tieneAprobacionPendiente()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->empresa_adm_pendiente_aprob)) {
                if ($parametros->empresa_adm_pendiente_aprob) {
                    return true;
                }
            }
        }

        return false;
    }

    public function tieneEmpresaAdministra()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->empresa_adm)) {
                if ($parametros->empresa_adm) {
                    return true;
                }
            }
        }

        return false;
    }

    public function admininstraEmpresa()
    {
        $tmp = $this->empresas_administradas()->where("estado", 1)->count();

        if ($tmp > 0) {
            return true;
        }

        return false;
    }

    public function tienePersonalizacionHtml()
    {
        if (isset($this->personalizacion['factura_html']['estado']) && $this->personalizacion['factura_html']['estado'] == true) {
            return true;
        }
        if (isset($this->personalizacion['nota_credito_html']['estado']) && $this->personalizacion['nota_credito_html']['estado'] == true) {
            return true;
        }
        if (isset($this->personalizacion['nota_debito_html']['estado']) && $this->personalizacion['nota_debito_html']['estado'] == true) {
            return true;
        }
        if (isset($this->personalizacion['guia_remision_html']['estado']) && $this->personalizacion['guia_remision_html']['estado'] == true) {
            return true;
        }
        if (isset($this->personalizacion['comprobante_retencion_html']['estado']) && $this->personalizacion['comprobante_retencion_html']['estado'] == true) {
            return true;
        }
        return false;
    }

    public function bannerSuperior()
    {
        return null;
    }

    public function bannerInferior()
    {
        return null;
    }


    public function scopeReceptoresValidos($query)
    {
        $query->where('roles', 'Receptor')->where('estado', 5);
    }


    public function scopeEmisoresValidos($query)
    {
        $query->where('roles', 'Emisor')->where('estado', 5);
    }


    public function scopeWithParameters($query, $parameters = [])
    {
        foreach ($parameters as $key => $value) {
            if (!is_array($value)) {
                $query->where('parametros.' . $key, '=', $value);
            } else {

                switch ($value['0']) {
                    case 'OR':
                        $query->orWhere('parametros.' . $key, $value[1], $value[2]);
                        break;

                    default:
                        $query->where('parametros.' . $key, $value[1], $value[2]);
                        break;
                }
            }
        }
    }


    public function getParametros($filters = [])
    {
        $parametros = [];

        foreach ($this->attributes['parametros'] as $key => $value) {
            if ($filters) {
                if (in_array($key, $filters)) {
                    $parametros[$key] = $value;
                }
            } else {
                $parametros[$key] = $value;
            }
        }

        return $parametros;
    }

    public function getParametrosFTP()
    {
        return $this->getParametros(
            [
                'scheduling_ftp',
                'scheduling_ftp_tls',
                'scheduling_ftp_server',
                'scheduling_ftp_port',
                'scheduling_ftp_user',
                'scheduling_ftp_password',
                'scheduling_ftp_root',
                'scheduling_ftp_ssl',
                'scheduling_ftp_timeout',
                'scheduling_ftp_ssl_verify_peer',
                'scheduling_ftp_ssl_verify_host',
                'scheduling_ftp_passive',
                'scheduling_ftp_ignore_passive_address'
            ]
        );
    }

    public function checkFtpParams()
    {
        $parametrosFTP = $this->getParametrosFTP();

        return (array_key_exists('scheduling_ftp_server', $parametrosFTP)

            && array_key_exists('scheduling_ftp_user', $parametrosFTP)

            && array_key_exists('scheduling_ftp_port', $parametrosFTP)

            && array_key_exists('scheduling_ftp_password', $parametrosFTP)) ? true : false;
    }

    public function isPublishableToFTP()
    {
        if (isset($this->parametros)) {
            return $this->parametros->hasFeedBackFTP();
        }

        return false;
    }

    public function isPublisReportToFTP()
    {
        if (isset($this->parametros)) {
            return $this->parametros->hasScheduledBackFTP();
        }

        return false;
    }

    public function isPublishableToEmail()
    {
        if (isset($this->parametros)) {
            return $this->parametros->hasFeedBackEmail();
        }

        return false;
    }

    public function isFeedBackPersonalizada()
    {
        if (isset($this->parametros)) {
            return $this->parametros->hasFeedBackPersonalizada();
        }
        return false;
    }

    public function getNodeRaizScheludeFTP()
    {
        if (isset($this->parametros)) {
            return $this->parametros->getNodeScheludeFTP();
        }

        return null;
    }

    public function isAllowedScheduledReports()
    {
        if (isset($this->parametros)) {
            return $this->parametros->isAllowedScheduledReports();
        }

        return false;
    }

    public function customerHasReportsAvaible()
    {
        return $this->amountScheduledReportsAvaible() > 0;
    }

    public function amountScheduledReportsAvaible()
    {
        if (isset($this->parametros)) {
            return $this->parametros->amountScheduledReportsAvailable() > 0;
        }

        return 0;
    }

    public function decreaseScheduledReportsAvailable()
    {
        $this->parametros->decreaseAmountScheduledReportsAvailable();
    }

    public function getSegmentos()
    {
        $result = array();

        foreach ($this->segmentos()->where("estado", true) as $segmento) {
            $result[] = (object)array(
                'id' => $segmento->id,
                'nombre' => $segmento->nombre,
            );
        }

        return $result;
    }

    public function getNombreSegmento($cod_segmento)
    {
        $seg = $this->segmentos()->where("codigo", $cod_segmento)->first();

        if (isset($seg)) {
            return $seg->nombre;
        }

        return "";
    }

    public function esPyme()
    {
        if (isset($this->pyme) && $this->pyme == true) {
            return true;
        }

        return false;
    }

    public function tieneSolicitudMigracion()
    {
        if (isset($this->solicitud_migracion) && $this->solicitud_migracion == true) {
            return true;
        }

        return false;
    }

    public function obtenerUrlPyme()
    {
        return $this->url_facturero;
    }

    public function getTipoDocByPerfilSegmento($perfil_id, $segmento_id)
    {
        $perfil = $this->perfiles()->find($perfil_id);

        foreach ($perfil->detalle as $detalle) {
            if ($detalle['segmento_id'] == "$segmento_id") {
                return $detalle['tipo_documento'];
            }
        }

        return array();
    }

    public function getCamposAdicionalesActivosRace()
    {
        $campos_adicionales = $this->campos_personalizados_race;
        $campos = [];

        if ($campos_adicionales) {
            foreach ($campos_adicionales as $c) {
                if ($c->estado == true) {
                    array_push(
                        $campos,
                        [
                            '_id' => $c->_id,
                            'nombre' => $c->nombre,
                            'tipo' => $c->tipo,
                            'estado' => $c->estado,
                            'valor' => $c->nombre,
                        ]
                    );
                }
            }
        }

        return $campos;
    }

    public function getMotivosRechazo()
    {
        $motivos_rechazo = $this->motivos_rechazo;
        $motivos = [];

        if ($motivos_rechazo) {
            foreach ($motivos_rechazo as $c) {
                if ($c->estado == true) {
                    array_push(
                        $motivos,
                        [
                            '_id' => $c->_id,
                            'nombre' => $c->nombre,
                            'estado' => $c->estado
                        ]
                    );
                }
            }
        }

        return $motivos;
    }

    public function getNombreMotivoRechazoById($id)
    {
        $motivos_rechazo = $this->motivos_rechazo;

        if ($motivos_rechazo) {
            foreach ($motivos_rechazo as $c) {
                if ($c->_id == $id) {
                    return $c->nombre;
                }
            }
        }

        return null;
    }

    public function obtenerDatosNombreArchivo($nombre_archivo)
    {
        if ($this->hasObtenerDatosNombreArchivo() && $nombre_archivo != null) {
            $parametros = $this->parametros;

            $est_i = $parametros->est_i;
            $pto_i = $parametros->pto_i;
            $sec_i = $parametros->sec_i;

            if ($est_i !== null && $pto_i !== null && $sec_i !== null) {
                $establecimiento = substr($nombre_archivo, $est_i, 3);
                $punto_emision = substr($nombre_archivo, $pto_i, 3);
                $secuencial = substr($nombre_archivo, $sec_i, 9);
                $num_doc = $establecimiento . '-' . $punto_emision . '-' . $secuencial;

                if (preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{9}/', $num_doc)) {
                    return $num_doc;
                }
            }
        }

        return false;
    }

    public function hasObtenerDatosNombreArchivo()
    {
        $parametros = $this->parametros;

        if (isset($parametros) && $parametros != null) {
            if ($parametros->obtener_datos_archivo) {
                return true;
            }
        }
        return false;
    }

    public function tieneVistaEmailPersonalizada($vista = null)
    {
        $parametros = $this->parametros;

        return isset($parametros->mail_personalizado) && $parametros->mail_personalizado == true && ($vista == null || \View::exists($vista));
    }

    public function tieneCustodia()
    {
        $parametros = $this->parametros;

        if (isset($parametros) && $parametros != null) {
            if ($parametros->tiene_custodia) {
                return true;
            }
        }

        return false;
    }

    public function EnvioMailCustodia()
    {
        $parametros = $this->parametros;

        if (isset($parametros) && $parametros != null) {
            if ($parametros->envio_mail_custodia) {
                return true;
            }
        }

        return false;
    }

    public function hasAceptacionSimplePorSms()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->aceptacion_simple_sms_activa) && $parametros->aceptacion_simple_sms_activa) {
                return true;
            }
        }

        return false;
    }

    public function getNombreParaSms()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->aceptacion_simple_sms_nombre)) {
                return $parametros->aceptacion_simple_sms_nombre;
            }
        }

        return $this->nombre_identificacion;
    }

    public function esMultiBroker()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros->multi_broker)) {
                return $parametros->multi_broker;
            }
        }

        return false;
    }

    public function getCamposAdjuntosFVaDE()
    {
        $parametros = $this->parametros;

        if (isset($parametros)) {
            if (isset($parametros["adjuntos_fv_a_de"])) {
                return $parametros["adjuntos_fv_a_de"];
            }
        }
        return array();
    }

    public function isNipro()
    {
        $response = false;

        if (isset($this->nipro)) {
            if ($this->nipro) {
                $response = true;
            }
        }

        return $response;
    }

    public function isNovatech()
    {
        $response = false;

        if (isset($this->novatech)) {
            if ($this->novatech) {
                $response = true;
            }
        }

        return $response;
    }

    public function isClick()
    {
        $response = false;

        if (isset($this->clic_factura)) {
            if ($this->clic_factura) {
                $response = true;
            }
        }

        return $response;
    }

    public function isXml()
    {
        $response = false;

        if (isset($this->xml)) {
            if ($this->xml) {
                $response = true;
            }
        }

        return $response;
    }

    public function GetDominio()
    {
        $dominio = Config::get('app.url');
        $customizacion = Customizacion::where('cliente_id', '=', $this->_id)->first();

        if (isset($customizacion) && !empty($customizacion) && isset($customizacion->dominio)) {
            $protocol = substr($dominio, 0, 5) === "https" ? "https://" : "http://";
            $dominio = $protocol . $customizacion->dominio;
        }

        return $dominio;
    }
}