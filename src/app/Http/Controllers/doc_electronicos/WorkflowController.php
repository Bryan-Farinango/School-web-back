<?php

namespace App\Http\Controllers\doc_electronicos;

use App\Cliente;
use App\doc_electronicos\WorkflowIn;
use App\doc_electronicos\WorkflowOut;
use App\Http\Controllers\Controller;
use App\Usuarios;
use Exception;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct()
    {
    }

    public function MostrarFiltroWorkflowsIn()
    {
        $arretiquetas = array("opciones_revisores");
        $opciones_revisores = WorkflowIn::get_opciones_revisores();
        $arrvalores = array($opciones_revisores);
        return view("doc_electronicos.workflows.filtro_workflows_in", array_combine($arretiquetas, $arrvalores));
    }

    public function GetWorkflowsIn(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $filtro_id_emisor = $request->input("filtro_id_emisor");
        Filtrar($filtro_id_emisor, "STRING");
        $filtro_id_revisor = $request->input("filtro_id_revisor");
        Filtrar($filtro_id_revisor, "STRING");
        $filtro_estado = $request->input("filtro_estado");
        Filtrar($filtro_estado, "INTEGER");

        $workflows_in = WorkflowIn::where("id_cliente", $id_cliente);
        if (!empty($filtro_id_emisor)) {
            $workflows_in = $workflows_in->where("emisores", $filtro_id_emisor);
        }
        if ($filtro_id_revisor != -1) {
            $workflows_in = $workflows_in->where("revisores", $filtro_id_revisor);
        }
        if ($filtro_estado != -1) {
            $activo = $filtro_estado == 1 ? true : false;
            $workflows_in = $workflows_in->where("activo", $activo);
        }
        $workflows_in = $workflows_in->orderBy('nombre_workflow', 'asc')->get();

        $result = array();
        foreach ($workflows_in as $workflow_in) {
            if (count($workflow_in["emisores"]) == 0) {
                $condiciones = "Para cualquier compañía emisora.";
            } else {
                if (count($workflow_in["emisores"]) == 1) {
                    $condiciones = "Para el emisor '" . Cliente::find(
                            $workflow_in["emisores"][0]
                        )["nombre_identificacion"] . "'.";
                } else {
                    $condiciones = "Para cualquiera de estos <u><b><span id='SpanEmisores_" . $workflow_in["_id"] . "' style='cursor:pointer;' class='azul_stupendo'>emisores</span></b></u>.";
                }
            }
            if (count($workflow_in["revisores"]) == 1) {
                $revisores = Usuarios::find($workflow_in["revisores"][0])["nombre"];
            } else {
                $revisores = "Múltiples <u><b><span id='SpanRevisores_" . $workflow_in["_id"] . "' style='cursor:pointer' class='azul_stupendo'>revisores</span></b></u>";
            }
            $result[] =
                [
                    "_id" => $workflow_in["_id"],
                    "nombre" => $workflow_in["nombre_workflow"],
                    "condiciones" => $condiciones,
                    "revisores" => $revisores,
                    "acciones" => "",
                    "activo" => $workflow_in["activo"] ? 1 : 0,
                    "permiso_editar_workflows" => TienePermisos(7, 5) ? 1 : 0
                ];
        }
        return response()->json(array("data" => $result), 200);
    }

    function MostrarListaIn(Request $request)
    {
        $arretiquetas = array("titulo_lista", "lista");
        $id_workflow = $request->input("Valor_1");
        Filtrar($id_workflow, "STRING");
        $tipo_lista = $request->input("Valor_2");
        Filtrar($tipo_lista, "STRING");
        $workflow_in = WorkflowIn::find($id_workflow);
        $lista = "";
        switch ($tipo_lista) {
            case "EMISORES_IN":
            {
                $titulo_lista = "Emisores incluidos";
                foreach ($workflow_in->emisores as $idClienteEmisor) {
                    $lista .= '<li style="padding:5px 0px">' . Cliente::find(
                            $idClienteEmisor
                        )["nombre_identificacion"] . '</li>';
                }
                break;
            }
            case "REVISORES_IN":
            {
                $titulo_lista = "Revisores definidos";
                foreach ($workflow_in->revisores as $id_usuario_revisor) {
                    $lista .= '<li style="padding:5px 0px">' . Usuarios::find($id_usuario_revisor)["nombre"] . '</li>';
                }
                break;
            }
        }
        $arrvalores = array($titulo_lista, $lista);
        return view("doc_electronicos.workflows.listas", array_combine($arretiquetas, $arrvalores));
    }

    public function MostrarEditarWorkflowIn(Request $request)
    {
        $id_workflow = $request->input("Valor_1");
        $arretiquetas = array(
            "titulo",
            "nombre_workflow",
            "opciones_origen_emisor",
            "ruc_nombre_emisor",
            "id_emisor",
            "opciones_revisores",
            "cantidad_emisores",
            "arreglo_ids",
            "opciones_permite_lectura",
            "cantidad_revisores",
            "arreglo_ids_revisores",
            "display_div_emisores",
            "id_workflow_in",
            "lista_hidden_emisores",
            "lista_hidden_revisores"
        );
        $ruc_nombre_emisor = "";
        $id_emisor = "";
        $opciones_revisores = WorkflowIn::get_opciones_revisores(-1, true, 'Seleccione el revisor');
        $lista_hidden_emisores = '';
        $lista_hidden_revisores = '';
        if (empty($id_workflow)) {
            $titulo = "Nuevo workflow de entrada";
            $nombre_workflow = "";
            $opciones_origen_emisor = '<option selected="selected" value="0">Todos los emisores</option><option value="1">Emisores específicos</option>';
            $cantidad_emisores = 0;
            $arreglo_ids = "";
            $cantidad_revisores = 0;
            $arreglo_ids_revisores = "";
            $display_div_emisores = 'style="display:none"';
            $id_workflow_in = "''";
            $opciones_permite_lectura = '<option value="1" selected="selected">Todos los usuarios pueden leer los documentos mientras se revisan.</option><option value="0">Los usuarios no pueden leer los documentos hasta que estén revisados.</option>';
        } else {
            $workflow_in = WorkflowIn::find($id_workflow);
            $titulo = "Modificar workflow de entrada";
            $nombre_workflow = $workflow_in->nombre_workflow;
            if (count($workflow_in->emisores) == 0) {
                $s0 = 'selected="selected"';
                $s1 = '';
            } else {
                $s0 = '';
                $s1 = 'selected="selected"';
            }
            $opciones_origen_emisor = '<option ' . $s0 . ' value="0">Todos los emisores</option><option ' . $s1 . ' value="1">Emisores específicos</option>';
            $cantidad_emisores = count($workflow_in->emisores);
            $arreglo_ids = array();
            foreach ($workflow_in->emisores as $idClienteEmisor) {
                $lista_hidden_emisores .= '<input type="hidden" id="HiddenListaEmisor_' . $idClienteEmisor . '" name="HiddenListaEmisor[]" value="' . $idClienteEmisor . '">';
                $idClienteEmisor = "'$idClienteEmisor'";
                array_push($arreglo_ids, $idClienteEmisor);
            }
            $arreglo_ids = implode(",", $arreglo_ids);
            $cantidad_revisores = count($workflow_in->revisores);
            $arreglo_ids_revisores = array();
            foreach ($workflow_in->revisores as $id_usuario_revisor) {
                $lista_hidden_revisores .= '<input type="hidden" id="HiddenListaRevisor_' . $id_usuario_revisor . '" name="HiddenListaRevisor[]" value="' . $id_usuario_revisor . '">';
                $id_usuario_revisor = "'$id_usuario_revisor'";
                array_push($arreglo_ids_revisores, $id_usuario_revisor);
            }
            $arreglo_ids_revisores = implode(",", $arreglo_ids_revisores);
            $display_div_emisores = ($cantidad_emisores == 0) ? 'style="display:none"' : '';
            $id_workflow_in = "'$id_workflow'";
            if ($workflow_in->permite_lectura) {
                $s1 = 'selected="selected"';
                $s0 = '';
            } else {
                $s1 = '';
                $s0 = 'selected="selected"';
            }
            $opciones_permite_lectura = '<option value="1" ' . $s1 . '>Todos los usuarios pueden leer los documentos mientras se revisan.</option><option ' . $s0 . ' value="0">Los usuarios no pueden leer los documentos hasta que estén revisados.</option>';
        }
        $arrvalores = array(
            $titulo,
            $nombre_workflow,
            $opciones_origen_emisor,
            $ruc_nombre_emisor,
            $id_emisor,
            $opciones_revisores,
            $cantidad_emisores,
            $arreglo_ids,
            $opciones_permite_lectura,
            $cantidad_revisores,
            $arreglo_ids_revisores,
            $display_div_emisores,
            $id_workflow_in,
            $lista_hidden_emisores,
            $lista_hidden_revisores
        );
        return view("doc_electronicos.workflows.workflow_in", array_combine($arretiquetas, $arrvalores));
    }

    public function GuardarWorkflowIn(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $id_workflow = $request->input("Valor_1");
            Filtrar($id_workflow, "STRING");
            if (empty($id_workflow)) {
                $id_workflow = null;
            }
            $id_cliente = session()->get("id_cliente");
            $id_usuario_edita = session()->get("id_usuario");
            $nombre_workflow = $request->input("TNombreWorkflowIn");
            Filtrar($nombre_workflow, "STRING");
            $variante_emisor = $request->input("SVarianteEmisor");
            Filtrar($variante_emisor, "INTEGER", 0);
            if ($variante_emisor == 0) {
                $emisores = [];
            } else {
                $emisores = $request->input("HiddenListaEmisor");
                Filtrar($emisores, "ARRAY", []);
            }
            $revisores = $request->input("HiddenListaRevisor");
            Filtrar($revisores, "ARRAY", []);
            $permite_lectura = $request->input("SPermiteLectura");
            Filtrar($permite_lectura, "INTEGER", 1);
            if ($Res >= 0) {
                if (empty($nombre_workflow) || !in_array($permite_lectura, array(0, 1))) {
                    $Res = -1;
                    $Mensaje = "Datos incompletos.";
                }
            }
            if ($Res >= 0) {
                $workflows_in = WorkflowIn::where("id_cliente", $id_cliente)->where(
                    "nombre_workflow",
                    $nombre_workflow
                )->where("_id", "<>", $id_workflow)->first();
                if ($workflows_in) {
                    $Res = -2;
                    $Mensaje = "Ya tiene definido un workflow con idéntico nombre";
                }
            }
            if ($Res >= 0) {
                if ($variante_emisor == 0) {
                    $workflows_in = WorkflowIn::where("id_cliente", $id_cliente)->where(
                        "emisores.0",
                        "exists",
                        false
                    )->where("_id", "<>", $id_workflow)->first();
                    if ($workflows_in) {
                        $Res = -3;
                        $Mensaje = "Ya tiene definido un workflow para todos los emisores.";
                    }
                } else {
                    foreach ($emisores as $idClienteEmisor) {
                        $workflows_in = WorkflowIn::where("id_cliente", $id_cliente)->where(
                            "emisores",
                            $idClienteEmisor
                        )->where("_id", "<>", $id_workflow)->first();
                        if ($workflows_in) {
                            $Res = -5;
                            $Mensaje = "El emisor " . Usuarios::find(
                                    $idClienteEmisor
                                )["nombre_identificacion"] . " ya está definido en otro workflow.<br/>";
                        }
                    }
                }
            }
            if ($Res >= 0 && empty($id_workflow)) {
                $data_workflow_in =
                    [
                        "id_cliente" => $id_cliente,
                        "id_usuario_edita" => $id_usuario_edita,
                        "nombre_workflow" => $nombre_workflow,
                        "emisores" => $emisores,
                        "permite_lectura" => ($permite_lectura == 1),
                        "revisores" => $revisores,
                        "activo" => true
                    ];
                $wi = WorkflowIn::create($data_workflow_in);
                if (!$wi) {
                    $Res = -5;
                    $Mensaje = "Ocurrió un error guardando el workflow de entrada.<br/>";
                }
            }
            if ($Res >= 0 && !empty($id_workflow)) {
                $workflow = WorkflowIn::find($id_workflow);
                $workflow->id_usuario_edita = $id_usuario_edita;
                $workflow->nombre_workflow = $nombre_workflow;
                $workflow->emisores = $emisores;
                $workflow->permite_lectura = (int)$permite_lectura;
                $workflow->revisores = $revisores;
                $workflow->save();
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El workflow de entrada fue guardado correctamente.<br/>";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function AccionWorkflowIn(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $id_workflow = $request->input("Valor_1");
            Filtrar($id_workflow, "STRING");
            $accion = $request->input("Valor_2");
            Filtrar($accion, "INTEGER", 1);
            if ($Res >= 0) {
                $workflow_in = WorkflowIn::find($id_workflow);
                $workflow_in->activo = ($accion == 1) ? true : false;
                $resultado = $workflow_in->save();
                $gerundio = ($accion == 1) ? "activando" : "desactivando";
                $participio = ($accion == 1) ? "activado" : "desactivado";
                if (!$resultado) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error $gerundio el workflow de entrada.<br/>";
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El workflow de entrada fue $participio correctamente.<br/>";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function GetListaEmisoresEditar(Request $request)
    {
        $id_workflow_in = $request->input("id_workflow_in");
        $workflow_in = WorkflowIn::find($id_workflow_in);
        if (!$workflow_in) {
            $result = [];
        } else {
            $result = [];
            foreach ($workflow_in->emisores as $idClienteEmisor) {
                $cliente_emisor = Cliente::find($idClienteEmisor);
                $ruc = $cliente_emisor["identificacion"];
                $razon_social = $cliente_emisor["nombre_identificacion"];
                $quitar = '<a title="Quitar Emisor"><i id="IQuitarEmisor_' . $idClienteEmisor . '" class="fa fa-trash text-navy fa-2x" style="padding:0px 5px"></i></a>';
                $data = [$ruc, $razon_social, $quitar, $idClienteEmisor];
                $result[] = $data;
            }
        }
        return response()->json(array("data" => $result), 200);
    }

    public function GetListaRevisoresEditar(Request $request)
    {
        $id_workflow_in = $request->input("id_workflow_in");
        $workflow_in = WorkflowIn::find($id_workflow_in);
        $result = $this->getListaRevisoresJson($workflow_in);
        return response()->json(array("data" => $result), 200);
    }

    public function MostrarFiltroWorkflowsOut()
    {
        $arretiquetas = array(
            "opciones_revisores"
        );
        $opciones_revisores = WorkflowIn::get_opciones_revisores();
        $arrvalores = array($opciones_revisores);
        return view("doc_electronicos.workflows.filtro_workflows_out", array_combine($arretiquetas, $arrvalores));
    }

    public function GetWorkflowsOut(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $filtro_id_receptor = $request->input("filtro_id_receptor");
        Filtrar($filtro_id_receptor, "STRING");
        $filtro_id_revisor = $request->input("filtro_id_revisor");
        Filtrar($filtro_id_revisor, "STRING");
        $filtro_estado = $request->input("filtro_estado");
        Filtrar($filtro_estado, "INTEGER");

        $workflows_out = WorkflowOut::where("id_cliente", $id_cliente);

        
        if (!empty($filtro_id_receptor)) {
            $workflows_out = $workflows_out->where("receptores", $filtro_id_receptor);
        }
        if ($filtro_id_revisor != -1) {
            $workflows_out = $workflows_out->where("revisores", $filtro_id_revisor);
        }
        if ($filtro_estado != -1) {
            $activo = $filtro_estado == 1 ? true : false;
            $workflows_out = $workflows_out->where("activo", $activo);
        }
        $workflows_out = $workflows_out->orderBy('nombre_workflow', 'asc')->get();

        $result = array();
        foreach ($workflows_out as $workflow_out) {
            if (count($workflow_out["receptores"]) == 0) {
                $condiciones_receptor = "Para cualquier receptor";
            } else {
                if (count($workflow_out["receptores"]) == 1) {
                    $condiciones_receptor = "Si el receptor es '" . Cliente::find(
                            $workflow_out["receptores"][0]
                        )["nombre_identificacion"] . "'";
                } else {
                    if ($workflow_out["logica_receptores"] == "AND") {
                        $condiciones_receptor = "Si todos estos <u><b><span id='SpanReceptores_" . $workflow_out["_id"] . "' style='cursor:pointer;' class='azul_stupendo'>receptores</span></b></u> están presentes en el proceso";
                    } else {
                        if ($workflow_out["logica_receptores"] == "OR") {
                            $condiciones_receptor = "Si alguno de estos <u><b><span id='SpanReceptores_" . $workflow_out["_id"] . "' style='cursor:pointer;' class='azul_stupendo'>receptores</span></b></u> está presente en el proceso";
                        }
                    }
                }
            }
            if ($workflow_out["logica_enlace"] == "AND") {
                $enlace = "<b> Y </b>";
            } else {
                if ($workflow_out["logica_enlace"] == "OR") {
                    $enlace = "<b> O </b>";
                }
            }
            
            $condiciones = "$condiciones_receptor";
            if (count($workflow_out["revisores"]) == 1) {
                $revisores = Usuarios::find($workflow_out["revisores"][0])["nombre"];
            } else {
                $revisores = "Múltiples <u><b><span id='SpanRevisores_" . $workflow_out["_id"] . "' style='cursor:pointer' class='azul_stupendo'>revisores</span></b></u>";
            }
            $result[] =
                [
                    "_id" => $workflow_out["_id"],
                    "nombre" => $workflow_out["nombre_workflow"],
                    "condiciones" => $condiciones,
                    "revisores" => $revisores,
                    "acciones" => "",
                    "activo" => $workflow_out["activo"] ? 1 : 0,
                    "permiso_editar_workflows" => TienePermisos(7, 5) ? 1 : 0
                ];
        }
        return response()->json(array("data" => $result), 200);
    }

    public function MostrarEditarWorkflowOut(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $id_workflow = $request->input("Valor_1");
        $arretiquetas = array(
            "titulo",
            "nombre_workflow",
            "opciones_operador_receptores",
            "opciones_origen_receptor",
            "display_div_receptores",
            "opciones_operador_enlace",
            "opciones_revisores",
            "id_workflow_out",
            "cantidad_receptores",
            "arreglo_ids_receptores",
            "cantidad_revisores",
            "arreglo_ids_revisores",
            "lista_hidden_receptores",
            "lista_hidden_revisores"
        );

        $lista_hidden_receptores = '';
        $lista_hidden_revisores = '';
        $opciones_revisores = WorkflowIn::get_opciones_revisores(-1, true, 'Seleccione el revisor');
        
        if (empty($id_workflow)) {
            $titulo = "Nuevo workflow de salida";
            $nombre_workflow = "";
            $opciones_operador_receptores = '<option selected="selected" value="AND"><b>(Y)</b> Están presentes en el proceso todos los receptores de la lista</option><option value="OR"><b>(O)</b> Está presente en el proceso al menos un receptor de la lista</option>';
            $id_workflow_out = "''";
            $cantidad_receptores = 0;
            $arreglo_ids_receptores = "";
            $cantidad_revisores = 0;
            $arreglo_ids_revisores = "";
            $opciones_origen_receptor = '<option selected="selected" value="0">Para todos los receptores</option><option value="1">Para receptores específicos</option>';
            $display_div_receptores = 'style="display:none"';
            $opciones_operador_enlace = '<option value="AND"><b>(Y)</b> Se cumpla la condición de receptores Y la condición de categorías</option><option selected="selected" value="OR"><b>(O)</b> Se cumpla la condición de receptores O la condición de categorías</option>';
        } else {
            $workflow_out = WorkflowOut::find($id_workflow);
            $titulo = "Modificar workflow de salida";
            $nombre_workflow = $workflow_out->nombre_workflow;
            $id_workflow_out = "'$id_workflow'";

            $cantidad_receptores = count($workflow_out->receptores);
            $arreglo_ids_receptores = array();
            foreach ($workflow_out->receptores as $id_cliente_receptor) {
                $lista_hidden_receptores .= '<input type="hidden" id="HiddenListaReceptor_' . $id_cliente_receptor . '" name="HiddenListaReceptor[]" value="' . $id_cliente_receptor . '">';
                $id_cliente_receptor = "'$id_cliente_receptor'";
                array_push($arreglo_ids_receptores, $id_cliente_receptor);
            }
            $arreglo_ids_receptores = implode(",", $arreglo_ids_receptores);

            $cantidad_revisores = count($workflow_out->revisores);
            $arreglo_ids_revisores = array();
            foreach ($workflow_out->revisores as $id_usuario_revisor) {
                $lista_hidden_revisores .= '<input type="hidden" id="HiddenListaRevisor_' . $id_usuario_revisor . '" name="HiddenListaRevisor[]" value="' . $id_usuario_revisor . '">';
                $id_usuario_revisor = "'$id_usuario_revisor'";
                array_push($arreglo_ids_revisores, $id_usuario_revisor);
            }
            $arreglo_ids_revisores = implode(",", $arreglo_ids_revisores);

            if (count($workflow_out->receptores) == 0) {
                $s0 = 'selected="selected"';
                $s1 = '';
            } else {
                $s0 = '';
                $s1 = 'selected="selected"';
            }
            $opciones_origen_receptor = '<option ' . $s0 . ' value="0">Para todos los receptores</option><option ' . $s1 . ' value="1">Para receptores específicos</option>';
            $display_div_receptores = ($cantidad_receptores == 0) ? 'style="display:none"' : '';

            if (count($workflow_out->categorias) == 0) {
                $s0 = 'selected="selected"';
                $s1 = '';
            } else {
                $s0 = '';
                $s1 = 'selected="selected"';
            }

            if ($workflow_out->logica_receptores == "AND") {
                $sand = 'selected="selected"';
                $sor = '';
            } else {
                if ($workflow_out->logica_receptores == "OR") {
                    $sand = '';
                    $sor = 'selected="selected"';
                }
            }
            $opciones_operador_receptores = '<option ' . $sand . ' value="AND">(Y) Están presentes en el proceso todos los receptores de la lista</option><option ' . $sor . ' value="OR">(O) Está presente en el proceso al menos un receptor de la lista</option>';    
            $sand = '';
            $sor = 'selected="selected"';
            
            $opciones_operador_enlace = '<option ' . $sand . ' value="AND">(Y) Se cumpla la condición de receptores Y la condición de categorías</option><option ' . $sor . ' value="OR">(O) Se cumpla la condición de receptores O la condición de categorías</option>';
        }
        $arrvalores = array(
            $titulo,
            $nombre_workflow,
            $opciones_operador_receptores,
            $opciones_origen_receptor,
            $display_div_receptores,
            $opciones_operador_enlace,
            $opciones_revisores,
            $id_workflow_out,
            $cantidad_receptores,
            $arreglo_ids_receptores,
            $cantidad_revisores,
            $arreglo_ids_revisores,
            $lista_hidden_receptores,
            $lista_hidden_revisores
        );
        return view("doc_electronicos.workflows.workflow_out", array_combine($arretiquetas, $arrvalores));
    }

    public function GuardarWorkflowOut(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $id_workflow = $request->input("Valor_1");
            Filtrar($id_workflow, "STRING");
            if (empty($id_workflow)) {
                $id_workflow = null;
            }
            $id_cliente = session()->get("id_cliente");
            $id_usuario_edita = session()->get("id_usuario");
            $nombre_workflow = $request->input("TNombreWorkflowOut");
            Filtrar($nombre_workflow, "STRING");
            $variante_receptor = $request->input("SVarianteReceptor");
            Filtrar($variante_receptor, "INTEGER", 0);
            if ($variante_receptor == 0) {
                $receptores = [];
                $logica_receptores = "AND";
            } else {
                $receptores = $request->input("HiddenListaReceptor");
                Filtrar($receptores, "ARRAY", []);
                $logica_receptores = $request->input("SOperadorReceptores");
                Filtrar($logica_receptores, "STRING", "AND");
            }
        
            $logica_enlace = $request->input("SOperadorEnlace");
            Filtrar($logica_enlace, "STRING", "AND");
            $revisores = $request->input("HiddenListaRevisor");
            Filtrar($revisores, "ARRAY", []);
            if ($Res >= 0) {
                if (empty($nombre_workflow) || !in_array($variante_receptor, array(0, 1)) || !in_array(
                        $logica_receptores,
                        array("AND", "OR")
                    ) || !in_array(
                        $logica_enlace,
                        array("AND", "OR")
                    )) {
                    $Res = -1;
                    $Mensaje = "Datos incompletos.";
                }
            }
            if ($Res >= 0) {
                $workflows_out = WorkflowOut::where("id_cliente", $id_cliente)->where(
                    "nombre_workflow",
                    $nombre_workflow
                )->where("_id", "<>", $id_workflow)->first();
                if ($workflows_out) {
                    $Res = -2;
                    $Mensaje = "Ya tiene definido un workflow con idéntico nombre";
                }
            }
            if ($Res >= 0) {
                $workflow_out = WorkflowOut::where("id_cliente", $id_cliente)->where("receptores", $receptores)->where(
                    "logica_receptores",
                    $logica_receptores
                )
                    ->where(
                        "logica_enlace",
                        $logica_enlace
                    )->where("_id", "<>", $id_workflow)->first();
                if ($workflow_out) {
                    $Res = -3;
                    $Mensaje = "Ya tiene definido un workflow con idénticas condiciones ({$workflow_out->nombre_workflow}).";
                }
            }
            if ($Res >= 0 && empty($id_workflow)) {
                $data_workflow_out =
                    [
                        "id_cliente" => $id_cliente,
                        "id_usuario_edita" => $id_usuario_edita,
                        "nombre_workflow" => $nombre_workflow,
                        "receptores" => $receptores,
                        "logica_receptores" => $logica_receptores,
                        "logica_enlace" => $logica_enlace,
                        "revisores" => $revisores,
                        "activo" => true
                    ];
                $wi = WorkflowOut::create($data_workflow_out);
                if (!$wi) {
                    $Res = -5;
                    $Mensaje = "Ocurrió un error guardando el workflow de salida.<br/>";
                }
            }
            if ($Res >= 0 && !empty($id_workflow)) {
                $workflow = WorkflowOut::find($id_workflow);
                $workflow->id_usuario_edita = $id_usuario_edita;
                $workflow->nombre_workflow = $nombre_workflow;
                $workflow->receptores = $receptores;
                $workflow->logica_receptores = $logica_receptores;
                $workflow->logica_enlace = $logica_enlace;
                $workflow->revisores = $revisores;
                $workflow->save();
            }
        } catch (Exception $e) {
            $Res = -1;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El workflow de salida fue guardado correctamente.<br/>";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    public function AccionWorkflowOut(Request $request)
    {
        $Res = 0;
        $Mensaje = "";
        try {
            $id_workflow = $request->input("Valor_1");
            Filtrar($id_workflow, "STRING");
            $accion = $request->input("Valor_2");
            Filtrar($accion, "INTEGER", 1);
            if ($Res >= 0) {
                $workflow_out = WorkflowOut::find($id_workflow);
                $workflow_out->activo = ($accion == 1) ? true : false;
                $resultado = $workflow_out->save();
                $gerundio = ($accion == 1) ? "activando" : "desactivando";
                $participio = ($accion == 1) ? "activado" : "desactivado";
                if (!$resultado) {
                    $Res = -1;
                    $Mensaje = "Ocurrió un error $gerundio el workflow de salida.<br/>";
                }
            }
        } catch (Exception $e) {
            $Res = -2;
            $Mensaje = $e->getMessage();
        }
        if ($Res >= 0) {
            $Mensaje = "El workflow de salida fue $participio correctamente.<br/>";
        }
        $result = array("Res" => $Res, "Mensaje" => $Mensaje);
        return response()->json($result, 200);
    }

    function MostrarListaOut(Request $request)
    {
        $id_cliente = session()->get("id_cliente");
        $arretiquetas = array("titulo_lista", "lista");
        $id_workflow = $request->input("Valor_1");
        Filtrar($id_workflow, "STRING");
        $tipo_lista = $request->input("Valor_2");
        Filtrar($tipo_lista, "STRING");
        $workflow_out = WorkflowOut::find($id_workflow);
        $lista = "";
        switch ($tipo_lista) {
            case "RECEPTORES_OUT":
            {
                $titulo_lista = "Receptores definidos";
                foreach ($workflow_out->receptores as $id_cliente_receptor) {
                    $lista .= '<li style="padding:5px 0px">' . Cliente::find(
                            $id_cliente_receptor
                        )["nombre_identificacion"] . '</li>';
                }
                break;
            }
            case "REVISORES_OUT":
            {
                $titulo_lista = "Revisores incluidos";
                foreach ($workflow_out->revisores as $id_usuario_revisor) {
                    $lista .= '<li style="padding:5px 0px">' . Usuarios::find($id_usuario_revisor)["nombre"] . '</li>';
                }
                break;
            }
        }
        $arrvalores = array($titulo_lista, $lista);
        return view("doc_electronicos.workflows.listas", array_combine($arretiquetas, $arrvalores));
    }

    public function GetListaReceptoresEditar(Request $request)
    {
        $id_workflow_out = $request->input("id_workflow_out");
        $workflow_out = WorkflowOut::find($id_workflow_out);
        if (!$workflow_out) {
            $result = [];
        } else {
            $result = [];
            foreach ($workflow_out->receptores as $id_cliente_receptor) {
                $cliente_receptor = Cliente::find($id_cliente_receptor);
                $ruc = $cliente_receptor["identificacion"];
                $razon_social = $cliente_receptor["nombre_identificacion"];
                $quitar = '<a title="Quitar Receptor"><i id="IQuitarReceptor_' . $id_cliente_receptor . '" class="fa fa-trash text-navy fa-2x" style="padding:0px 5px"></i></a>';
                $data = [$ruc, $razon_social, $quitar, $id_cliente_receptor];
                $result[] = $data;
            }
        }
        return response()->json(array("data" => $result), 200);
    }

    public function GetListaRevisoresOutEditar(Request $request)
    {
        $id_workflow_out = $request->input("id_workflow_out");
        $workflow_out = WorkflowOut::find($id_workflow_out);
        $result = $this->getListaRevisoresJson($workflow_out);
        return response()->json(array("data" => $result), 200);
    }

    public function GetWorkflowIn($id_cliente_receptor, $idClienteEmisor)
    {
        $workflow_in = WorkflowIn::where("id_cliente", $id_cliente_receptor)->where("activo", true)->where(
            "emisores",
            $idClienteEmisor
        )->first();
        if ($workflow_in) {
            return $workflow_in;
        } else {
            $workflow_in = WorkflowIn::where("id_cliente", $id_cliente_receptor)->where("activo", true)->where(
                "emisores.0",
                "exists",
                false
            )->first();
            if ($workflow_in) {
                return $workflow_in;
            }
        }
        return null;
    }

    public function GetWorkflowOut($idClienteEmisor, $arrParticipantes)
    {
        $workflows_out = WorkflowOut::where("id_cliente", $idClienteEmisor)->where("activo", true)->get();
        $prioridad_ganadora = 100;
        $ganador = null;
        for ($index = 0, $cant_workflows = count($workflows_out); $index < $cant_workflows; $index++) {
            $workflow_out = $workflows_out[$index];
            $arrReceptoresWorkflow = $workflow_out["receptores"];
            $r = $workflow_out["logica_receptores"];

            if ($r == "AND" && $this->All($arrReceptoresWorkflow, $arrParticipantes)) {
                $prioridad = 1;
            } else {
                if ($r == "OR" && $this->Any($arrReceptoresWorkflow, $arrParticipantes)) {
                    $prioridad = 2;
                } else {
                    if ($r == "OR" && $this->Any( $arrReceptoresWorkflow, $arrParticipantes)) {
                        $prioridad = 3;
                    } else {
                        $prioridad = 4;
                    }
                }
            }
        
            if ($prioridad < $prioridad_ganadora) {
                $prioridad_ganadora = $prioridad;
                $ganador = $workflow_out;
            }
        }
        return $ganador;
    }

    private function UnificarArrayCat($arr_workflow, $arr_proceso)
    {
        foreach ($arr_workflow as $valor_w) {
            if (strpos($valor_w, "PRISEC-1") !== false) {
                $avw = explode("PRISEC", $valor_w);
                foreach ($arr_proceso as &$valor_p) {
                    $avp = explode("PRISEC", $valor_p);
                    if ($avp[0] == $avw[0]) {
                        $valor_p = $avw[0] . "PRISEC-1";
                    }
                }
            }
        }
        return array_unique($arr_proceso);
    }

    private function All($arr_workflow, $arr_proceso)
    {
        return count($arr_workflow) == count(array_intersect($arr_workflow, $arr_proceso)) && count($arr_workflow) > 0;
    }

    private function Any($arr_workflow, $arr_proceso)
    {
        return count(array_intersect($arr_workflow, $arr_proceso)) > 0;
    }

    /**
     * @param $workflow
     * @return array
     */
    public function getListaRevisoresJson($workflow)
    {
        if (!$workflow) {
            $result = [];
        } else {
            $result = [];
            foreach ($workflow->revisores as $id_usuario_revisor) {
                $usuario_revisor = Usuarios::find($id_usuario_revisor);
                $revisor = $usuario_revisor["nombre"];
                $quitar = '<a title="Quitar Revisor"><i id="IQuitarRevisor_' . $id_usuario_revisor . '" class="fa fa-trash text-navy fa-2x" style="padding:0px 5px"></i></a>';
                $data = [$revisor, $quitar, $id_usuario_revisor];
                $result[] = $data;
            }
        }
        return $result;
    }

}