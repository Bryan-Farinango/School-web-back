<?php

namespace App\Http\Controllers\doc_electronicos;

use App\doc_electronicos\EstadoDocumento;
use App\doc_electronicos\TipoDeAuditoriaDE;
use App\doc_electronicos\TipoDeNotificacionDE;
use App\Http\Controllers\Controller;
use App\Modulo;
use Illuminate\Support\Facades\Config;

class ZPreparaAmbientesController extends Controller
{
    public function __construct()
    {
    }

    public static function AutomaticosIniciales()
    {
        self::RellenoInicialTiposAuditoria();
        self::RellenoInicialEstadosDocumentos();
        self::RellenoInicialTiposNotificacion();
        self::BorrarOpenSSLCnf();
    }

    public static function BorrarOpenSSLCnf()
    {
        $carpeta_openssl_cnf = storage_path("/doc_electronicos/openssl_cnf");
        $archivo_openssl_cnf = "openssl.cnf";
        $camino_openssl_cnf = $carpeta_openssl_cnf . "/" . $archivo_openssl_cnf;
        if (is_dir($carpeta_openssl_cnf)) {
            EliminarDirectorio($carpeta_openssl_cnf);
        }
        $fc = new FirmasController();
        $fc->GenerarOpenSSL_CNF($carpeta_openssl_cnf, $camino_openssl_cnf);
    }

    public static function SobreescribirPFXStupendo()
    {
        $camino_openssl_cnf = storage_path("/doc_electronicos/openssl_cnf") . "/openssl.cnf";

        $configargs = array
        (
            'config' => $camino_openssl_cnf,
            "digest_alg" => "sha512",
            "x509_extensions" => "v3_ext",
            "req_extensions" => "v3_ext",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
            "encrypt_key" => false,
            "encrypt_key_cipher" => OPENSSL_CIPHER_AES_256_CBC
        );

        $dn = array
        (
            "serialNumber" => "1791889789001",
            "member" => "ESDINAMICO CIA. LTDA.",
            "commonName" => "Rodrigo Sandoval Vernimmen",
            "surname" => "1704158367",
            "emailAddress" => "rodrigo.sandoval@stupendo.com",
            "telephoneNumber" => "3947210",
            "destinationIndicator" => "Momento creación: " . date("U"),
            "businessCategory" => "Plataforma Stupendo",
            "description" => "Certificado de Firma Electrónica Stupendo",
            "supportedApplicationContext" => "Firmar documentos dentro de la plataforma Stupendo",
            "countryName" => "EC",
            "stateOrProvinceName" => "Pichincha",
            "localityName" => "Quito",
            "organizationName" => "(ESDINAMICO CIA. LTDA.)",
            "organizationalUnitName" => "Stupendo"
        );

        $private_key = openssl_pkey_new($configargs);
        $csr = openssl_csr_new($dn, $private_key, $configargs, array());
        $sscert = openssl_csr_sign(
            $csr,
            null,
            $private_key,
            Config::get('app.dias_validez_firma_personal_stupendo'),
            $configargs,
            "1791889789001"
        );
        openssl_x509_export($sscert, $certout);
        $args = array('extracerts' => array(0 => null), 'friendly_name' => "Certificados adicionales");
        openssl_pkcs12_export($certout, $pfx, $private_key, "EsdinamCo17", $args);

        $carpeta_pfx = storage_path("doc_electronicos/PFXStupendo");
        if (!is_dir($carpeta_pfx)) {
            mkdir($carpeta_pfx, 0777, true);
        }
        $camino_pfx = $carpeta_pfx . "/1791889789001.pfx";
        if (file_exists($camino_pfx)) {
            EliminarDirectorio($carpeta_pfx);
            mkdir($carpeta_pfx, 0777, true);
        }
        file_put_contents($camino_pfx, $pfx);
    }

    public static function RellenoInicialTiposAuditoria()
    {
        $tipos_auditoria = TipoDeAuditoriaDE::all();
        if (!$tipos_auditoria || count($tipos_auditoria) != 9) {
            TipoDeAuditoriaDE::truncate();
            $arr_data_tipos_auditoria = array
            (
                ["id_tipo" => 1, "tipo" => "Ingreso a Doc. Electrónicos."],
                ["id_tipo" => 2, "tipo" => "Acuerdo aceptado."],
                ["id_tipo" => 3, "tipo" => "Firma Simple Stupendo generada."],
                ["id_tipo" => 4, "tipo" => "Firma Acreditada adjuntada."],
                ["id_tipo" => 5, "tipo" => "Firma anulada."],
                ["id_tipo" => 6, "tipo" => "Proceso iniciado."],
                ["id_tipo" => 7, "tipo" => "Documento firmado."],
                ["id_tipo" => 8, "tipo" => "Documento rechazado."],
                ["id_tipo" => 9, "tipo" => "Proceso aprobado."],
                ["id_tipo" => 10, "tipo" => "Proceso desaprobado."],
                ["id_tipo" => 11, "tipo" => "Proceso simple iniciado."],
                ["id_tipo" => 12, "tipo" => "Proceso simple aceptado."],
                ["id_tipo" => 13, "tipo" => "Proceso simple rechazado."]
            );
            foreach ($arr_data_tipos_auditoria as $tipo_auditoria) {
                TipoDeAuditoriaDE::create($tipo_auditoria);
            }
        }
    }

    public static function RellenoInicialEstadosDocumentos()
    {
        $estados_documentos = EstadoDocumento::all();
        if (!$estados_documentos || count($estados_documentos) != 3) {
            EstadoDocumento::truncate();
            $arr_data_estados_documentos = array
            (
                ["id_estado" => 0, "estado" => "Original"],
                ["id_estado" => 1, "estado" => "En curso"],
                ["id_estado" => 2, "estado" => "Completado"],
                ["id_estado" => 3, "estado" => "Rechazado"],

            );
            foreach ($arr_data_estados_documentos as $estado_documento) {
                EstadoDocumento::create($estado_documento);
            }
        }
    }

    public static function RellenoInicialTiposNotificacion()
    {
        $tipos_notificacion = TipoDeNotificacionDE::all();
        if (!$tipos_notificacion || count($tipos_notificacion) != 4) {
            TipoDeNotificacionDE::truncate();
            $arr_data_tipos_notificacion = array
            (
                ["id_estado" => 1, "estado" => "Aviso de empresa lista."],
                ["id_estado" => 2, "estado" => "Proceso de firmas finalizado."],
                ["id_estado" => 3, "estado" => "Proceso de firmas rechazado."],
                ["id_estado" => 4, "estado" => "Proceso simple finalizado."],
                ["id_estado" => 5, "estado" => "Proceso simple rechazado."]
            );
            foreach ($arr_data_tipos_notificacion as $tn) {
                TipoDeNotificacionDE::create($tn);
            }
        }
    }

    public static function RellenoModulosDocumentosElectronicos()
    {
        $modulo = Modulo::where("id_modulo", 7)->first();
        $menus =
            [
                [
                    "id_menu" => 1,
                    "texto" => "Firmas",
                    "ruta" => "/doc_electronicos/firmas",
                    "tipo" => "CONFIGURACION"
                ],
                /*
                [
                    "id_menu" => 2,
                    "texto" => "Categorías",
                    "ruta" => "/doc_electronicos/categorias",
                    "tipo" => "CONFIGURACION"
                ],
                */
                [
                    "id_menu" => 3,
                    "texto" => "Perfiles",
                    "ruta" => "/perfiles/7",
                    "tipo" => "CONFIGURACION"
                ],
                [
                    "id_menu" => 4,
                    "texto" => "Usuarios",
                    "ruta" => "/usuarios/7",
                    "tipo" => "CONFIGURACION"
                ],
                [
                    "id_menu" => 5,
                    "texto" => "Cambiar contraseña",
                    "ruta" => "/password/7",
                    "tipo" => "CONFIGURACION"
                ],
                [
                    "id_menu" => 6,
                    "texto" => "Dashboard",
                    "icono" => "fa fa-bar-chart-o",
                    "ruta" => "/doc_electronicos/dashboard",
                    "tipo" => "PRINCIPAL"
                ],
                [
                    "id_menu" => 7,
                    "texto" => "Procesos",
                    "icono" => "fa fa-book",
                    "ruta" => "/doc_electronicos/emisiones",
                    "tipo" => "PRINCIPAL"
                ],
                [
                    "id_menu" => 8,
                    "texto" => "Recibidos",
                    "icono" => "fa fa-folder-open-o",
                    "ruta" => "/doc_electronicos/documentos",
                    "tipo" => "PRINCIPAL"
                ],

                [
                    "id_menu" => 10,
                    "texto" => "Registros de auditoría",
                    "icono" => "fa fa-history",
                    "ruta" => "/doc_electronicos/auditoria",
                    "tipo" => "PRINCIPAL"
                ],
                [
                    "id_menu" => 11,
                    "texto" => "Opciones",
                    "ruta" => "/doc_electronicos/opciones",
                    "tipo" => "CONFIGURACION"
                ],
                [
                    "id_menu" => 12,
                    "texto" => "Workflows",
                    "ruta" => "/doc_electronicos/workflows_in",
                    "tipo" => "CONFIGURACION"
                ],
                [
                    "id_menu" => 13,
                    "texto" => "Revisiones",
                    "icono" => "fa fa-check-square-o",
                    "ruta" => "/doc_electronicos/revisiones",
                    "tipo" => "PRINCIPAL"
                ],
                [
                    "id_menu" => 16,
                    "texto" => "Plantillas",
                    "ruta" => "/doc_electronicos/plantillas",
                    "tipo" => "CONFIGURACION"
                ]

            ];
        $grupos_permisos =
            [
                [
                    "nombre_grupo" => "Emisiones",
                    "permisos" => [
                        ["id_permiso" => 1, "nombre_permiso" => "Emitir procesos"],
                        ["id_permiso" => 7, "nombre_permiso" => "Emitir procesos de aceptación simple"]
                    ]
                ],
                [
                    "nombre_grupo" => "Firmas",
                    "permisos" => [
                        ["id_permiso" => 2, "nombre_permiso" => "Generar firmas"],
                        ["id_permiso" => 3, "nombre_permiso" => "Anular firmas"]
                    ]
                ],
                [
                    "nombre_grupo" => "Invitaciones",
                    "permisos" => [["id_permiso" => 4, "nombre_permiso" => "Emitir invitaciones"]]
                ],
                [
                    "nombre_grupo" => "Workflows",
                    "permisos" => [["id_permiso" => 5, "nombre_permiso" => "Crear / editar Workflows"]]
                ],
                [
                    "nombre_grupo" => "Revisiones",
                    "permisos" => [["id_permiso" => 6, "nombre_permiso" => "Realizar revisiones"]]
                ]
            ];
        $perfiles_fijos =
            [
                [
                    "id_perfil" => 1,
                    "perfil" => "Emisor_DE",
                    "menus_activos" => [1, 2, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16],
                    "permisos_activos" => [1, 2, 3, 4, 5, 6, 7]
                ],
                [
                    "id_perfil" => 2,
                    "perfil" => "Receptor_DE",
                    "menus_activos" => [1, 5, 6, 8, 10, 15],
                    "permisos_activos" => [2, 3]
                ],
                [
                    "id_perfil" => 3,
                    "perfil" => "Emisor_Dependiente",
                    "menus_activos" => [1, 2, 5, 6, 7, 8, 10, 11, 14, 15, 16],
                    "permisos_activos" => [1, 2, 3, 7]
                ],
                [
                    "id_perfil" => 4,
                    "perfil" => "Administrador_DE",
                    "menus_activos" => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16],
                    "permisos_activos" => [1, 2, 3, 4, 5, 6, 7]
                ],
            ];
        $modulo->id_modulo = 7;
        $modulo->modulo = "DocumentosElectronicos";
        $modulo->nombre_modulo = "Documentos Electrónicos";
        $modulo->menus = $menus;
        $modulo->grupos_permisos = $grupos_permisos;
        $modulo->en_venta = true;
        $modulo->interno = false;
        $modulo->icono_barra_lateral = "/img/p-verde.png";
        $modulo->icono_menu_modulos = "/img/isotipo/doc_electronicos.png";
        $modulo->icono_barra_superior = "/img/isotipo/doc_electronicos.png";
        $modulo->perfiles_fijos = $perfiles_fijos;
        $modulo->ruta_ayuda = "/doc_electronicos/ayuda/";
        $modulo->save();
    }
}