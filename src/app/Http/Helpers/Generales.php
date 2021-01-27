<?php

function EliminarContenidoDirectorio($Directorio, $except_folder = "")
{
    foreach (glob($Directorio . "/*") as $archivos_carpeta) {
        if (is_dir($archivos_carpeta) && $archivos_carpeta != $except_folder) {
            EliminarContenidoDirectorio($archivos_carpeta, $except_folder);
        } else {
            @unlink($archivos_carpeta);
        }
        if (is_dir($archivos_carpeta) && $archivos_carpeta != $except_folder) {
            rmdir($archivos_carpeta);
        }
    }
}

function EliminarDirectorio($Directorio)
{
    EliminarContenidoDirectorio($Directorio);
    rmdir($Directorio);
}

function ComoXDecimales($numero, $x)
{
    if (!is_numeric($numero)) {
        $cero = '0.';
        for ($i = 0; $i < $x; $i++) {
            $cero .= '0';
        }
        return $cero;
    } else {
        return number_format(round($numero, $x), $x, ".", "");
    }
}

function ComoDinero($numero)
{
    return ComoXDecimales($numero, 2);
}

function NumeroHastaXDecimales($cadena, $X = 2)
{
    $Resultado = true;
    $cadena = str_replace(',', '.', $cadena);
    if (!is_numeric($cadena)) {
        $Resultado = false;
    } else {
        $PosicionPunto = strpos($cadena, '.');
        $LargoCadena = strlen($cadena);
        if ($PosicionPunto && ($PosicionPunto < ($LargoCadena - $X - 1) || $PosicionPunto == ($LargoCadena - 1))) {
            $Resultado = false;
        }
    }
    return $Resultado;
}

function NumeroHasta2Decimales($cadena)
{
    return NumeroHastaXDecimales($cadena, 2);
}

function FechaCorrecta($fecha, $formato = 'd/m/Y')
{
    $fecha_ok = DateTime::createFromFormat($formato, $fecha);
    return $fecha_ok && $fecha_ok->format($formato) == $fecha;
}

function CompletaConCeros($numero, $cant_esperada = 2)
{
    return str_pad($numero, $cant_esperada, '0', STR_PAD_LEFT);
}

function EMailValido($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function URLValida($url)
{
    return filter_var($url, FILTER_VALIDATE_URL);
}

function getOS($user_agent)
{
    if (stripos($user_agent, "Windows NT 10.0") !== false) {
        return "Windows 10";
    } else {
        if (stripos($user_agent, "Windows NT 6.3") !== false) {
            return "Windows 8.1";
        } else {
            if (stripos($user_agent, "Windows NT 6.2") !== false) {
                return "Windows 8";
            } else {
                if (stripos($user_agent, "Windows NT 6.1") !== false) {
                    return "Windows 7";
                } else {
                    if (stripos($user_agent, "Windows NT 6.0") !== false) {
                        return "Windows Vista";
                    } else {
                        if (stripos($user_agent, "Windows XP") !== false) {
                            return "Windows XP";
                        } else {
                            if (stripos($user_agent, "Windows NT 5.1") !== false) {
                                return "Windows XP";
                            } else {
                                if (stripos($user_agent, "Windows NT 5.2") !== false) {
                                    return "Windows Server 2003 / XP x64";
                                } else {
                                    if (stripos($user_agent, "Windows NT 5.0") !== false) {
                                        return "Windows 2000";
                                    } else {
                                        if (stripos($user_agent, "Windows ME") !== false) {
                                            return "Windows ME";
                                        } else {
                                            if (stripos($user_agent, "Win98") !== false) {
                                                return "Windows 98";
                                            } else {
                                                if (stripos($user_agent, "Win95") !== false) {
                                                    return "Windows 95";
                                                } else {
                                                    if (stripos($user_agent, "WinNT4.0") !== false) {
                                                        return "Windows NT 4.0";
                                                    } else {
                                                        if (stripos($user_agent, "Win16") !== false) {
                                                            return "Windows 3.11";
                                                        } else {
                                                            if (stripos($user_agent, "Windows Phone") !== false) {
                                                                return "Windows Phone";
                                                            } else {
                                                                if (stripos($user_agent, "Windows") !== false) {
                                                                    return "Windows";
                                                                } else {
                                                                    if (stripos($user_agent, "Android") !== false) {
                                                                        return "Android";
                                                                    } else {
                                                                        if (stripos($user_agent, "iPhone") !== false) {
                                                                            return "iPhone";
                                                                        } else {
                                                                            if (stripos(
                                                                                    $user_agent,
                                                                                    "iPad"
                                                                                ) !== false) {
                                                                                return "iPad";
                                                                            } else {
                                                                                if (stripos(
                                                                                        $user_agent,
                                                                                        "iPod"
                                                                                    ) !== false) {
                                                                                    return "iPod";
                                                                                } else {
                                                                                    if (stripos(
                                                                                            $user_agent,
                                                                                            "Debian"
                                                                                        ) !== false) {
                                                                                        return "Debian";
                                                                                    } else {
                                                                                        if (stripos(
                                                                                                $user_agent,
                                                                                                "Ubuntu"
                                                                                            ) !== false) {
                                                                                            return "Ubuntu";
                                                                                        } else {
                                                                                            if (stripos(
                                                                                                    $user_agent,
                                                                                                    "Slackware"
                                                                                                ) !== false) {
                                                                                                return "Slackware";
                                                                                            } else {
                                                                                                if (stripos(
                                                                                                        $user_agent,
                                                                                                        "Linux Mint"
                                                                                                    ) !== false) {
                                                                                                    return "Linux Mint";
                                                                                                } else {
                                                                                                    if (stripos(
                                                                                                            $user_agent,
                                                                                                            "Gentoo"
                                                                                                        ) !== false) {
                                                                                                        return "Gentoo";
                                                                                                    } else {
                                                                                                        if (stripos(
                                                                                                                $user_agent,
                                                                                                                "Elementary OS"
                                                                                                            ) !== false) {
                                                                                                            return "ELementary OS";
                                                                                                        } else {
                                                                                                            if (stripos(
                                                                                                                    $user_agent,
                                                                                                                    "Fedora"
                                                                                                                ) !== false) {
                                                                                                                return "Fedora";
                                                                                                            } else {
                                                                                                                if (stripos(
                                                                                                                        $user_agent,
                                                                                                                        "Kubuntu"
                                                                                                                    ) !== false) {
                                                                                                                    return "Kubuntu";
                                                                                                                } else {
                                                                                                                    if (stripos(
                                                                                                                            $user_agent,
                                                                                                                            "Linux"
                                                                                                                        ) !== false) {
                                                                                                                        return "Linux";
                                                                                                                    } else {
                                                                                                                        if (stripos(
                                                                                                                                $user_agent,
                                                                                                                                "FreeBSD"
                                                                                                                            ) !== false) {
                                                                                                                            return "FreeBSD";
                                                                                                                        } else {
                                                                                                                            if (stripos(
                                                                                                                                    $user_agent,
                                                                                                                                    "OpenBSD"
                                                                                                                                ) !== false) {
                                                                                                                                return "OpenBSD";
                                                                                                                            } else {
                                                                                                                                if (stripos(
                                                                                                                                        $user_agent,
                                                                                                                                        "NetBSD"
                                                                                                                                    ) !== false) {
                                                                                                                                    return "NetBSD";
                                                                                                                                } else {
                                                                                                                                    if (stripos(
                                                                                                                                            $user_agent,
                                                                                                                                            "SunOS"
                                                                                                                                        ) !== false) {
                                                                                                                                        return "Solaris";
                                                                                                                                    } else {
                                                                                                                                        if (stripos(
                                                                                                                                                $user_agent,
                                                                                                                                                "BlackBerry"
                                                                                                                                            ) !== false) {
                                                                                                                                            return "BlackBerry";
                                                                                                                                        } else {
                                                                                                                                            if (stripos(
                                                                                                                                                    $user_agent,
                                                                                                                                                    "Mobile"
                                                                                                                                                ) !== false) {
                                                                                                                                                return "Firefox OS";
                                                                                                                                            } else {
                                                                                                                                                if (stripos(
                                                                                                                                                        $user_agent,
                                                                                                                                                        "Mac OS X+"
                                                                                                                                                    ) || stripos(
                                                                                                                                                        $user_agent,
                                                                                                                                                        "CFNetwork+"
                                                                                                                                                    ) !== false) {
                                                                                                                                                    return "Mac OS X";
                                                                                                                                                } else {
                                                                                                                                                    if (stripos(
                                                                                                                                                            $user_agent,
                                                                                                                                                            "Mac_powerpc"
                                                                                                                                                        ) !== false) {
                                                                                                                                                        return "Mac OS 9";
                                                                                                                                                    } else {
                                                                                                                                                        if (stripos(
                                                                                                                                                                $user_agent,
                                                                                                                                                                "Macintosh"
                                                                                                                                                            ) !== false) {
                                                                                                                                                            return "Mac OS Classic";
                                                                                                                                                        } else {
                                                                                                                                                            if (stripos(
                                                                                                                                                                    $user_agent,
                                                                                                                                                                    "OS/2"
                                                                                                                                                                ) !== false) {
                                                                                                                                                                return "OS/2";
                                                                                                                                                            } else {
                                                                                                                                                                if (stripos(
                                                                                                                                                                        $user_agent,
                                                                                                                                                                        "webos"
                                                                                                                                                                    ) !== false) {
                                                                                                                                                                    return "Mobile / Webos";
                                                                                                                                                                } else {
                                                                                                                                                                    if (stripos(
                                                                                                                                                                            $user_agent,
                                                                                                                                                                            "BeOS"
                                                                                                                                                                        ) !== false) {
                                                                                                                                                                        return "BeOS";
                                                                                                                                                                    } else {
                                                                                                                                                                        if (stripos(
                                                                                                                                                                                $user_agent,
                                                                                                                                                                                "Nintendo"
                                                                                                                                                                            ) !== false) {
                                                                                                                                                                            return "Nintendo";
                                                                                                                                                                        } else {
                                                                                                                                                                            return "No identificado";
                                                                                                                                                                        }
                                                                                                                                                                    }
                                                                                                                                                                }
                                                                                                                                                            }
                                                                                                                                                        }
                                                                                                                                                    }
                                                                                                                                                }
                                                                                                                                            }
                                                                                                                                        }
                                                                                                                                    }
                                                                                                                                }
                                                                                                                            }
                                                                                                                        }
                                                                                                                    }
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

function getBrowser($user_agent)
{
    if (stripos($user_agent, "Maxthon") !== false) {
        return "Maxthon";
    } else {
        if (stripos($user_agent, "SeaMonkey") !== false) {
            return "SeaMonkey";
        } else {
            if (stripos($user_agent, "Vivaldi") !== false) {
                return "Vivaldi";
            } else {
                if (stripos($user_agent, "Arora") !== false) {
                    return "Arora";
                } else {
                    if (stripos($user_agent, "Avant Browser") !== false) {
                        return "Avant Browser";
                    } else {
                        if (stripos($user_agent, "Beamrise") !== false) {
                            return "Beamrise";
                        } else {
                            if (stripos($user_agent, "Epiphany") !== false) {
                                return "Epiphany";
                            } else {
                                if (stripos($user_agent, "Chromium") !== false) {
                                    return "Chromium";
                                } else {
                                    if (stripos($user_agent, "Iceweasel") !== false) {
                                        return "Iceweasel";
                                    } else {
                                        if (stripos($user_agent, "Galeon") !== false) {
                                            return "Galeon";
                                        } else {
                                            if (stripos($user_agent, "Edge") !== false) {
                                                return "Microsoft Edge";
                                            } else {
                                                if (stripos($user_agent, "Trident") !== false || stripos(
                                                        $user_agent,
                                                        "MSIE"
                                                    ) !== false) {
                                                    return "Internet Explorer";
                                                } else {
                                                    if (stripos($user_agent, "Opera Mini") !== false) {
                                                        return "Opera Mini";
                                                    } else {
                                                        if (stripos($user_agent, "Opera") || strpos(
                                                                $user_agent,
                                                                "OPR"
                                                            ) !== false) {
                                                            return "Opera";
                                                        } else {
                                                            if (stripos($user_agent, "Firefox") !== false) {
                                                                return "Mozilla Firefox";
                                                            } else {
                                                                if (stripos($user_agent, "Chrome") !== false) {
                                                                    return "Google Chrome";
                                                                } else {
                                                                    if (stripos($user_agent, "Safari") !== false) {
                                                                        return "Safari";
                                                                    } else {
                                                                        if (stripos($user_agent, "iTunes") !== false) {
                                                                            return "iTunes";
                                                                        } else {
                                                                            if (stripos(
                                                                                    $user_agent,
                                                                                    "Konqueror"
                                                                                ) !== false) {
                                                                                return "Konqueror";
                                                                            } else {
                                                                                if (stripos(
                                                                                        $user_agent,
                                                                                        "Dillo"
                                                                                    ) !== false) {
                                                                                    return "Dillo";
                                                                                } else {
                                                                                    if (stripos(
                                                                                            $user_agent,
                                                                                            "Netscape"
                                                                                        ) !== false) {
                                                                                        return "Netscape";
                                                                                    } else {
                                                                                        if (stripos(
                                                                                                $user_agent,
                                                                                                "Midori"
                                                                                            ) !== false) {
                                                                                            return "Midori";
                                                                                        } else {
                                                                                            if (stripos(
                                                                                                    $user_agent,
                                                                                                    "ELinks"
                                                                                                ) !== false) {
                                                                                                return "ELinks";
                                                                                            } else {
                                                                                                if (stripos(
                                                                                                        $user_agent,
                                                                                                        "Links"
                                                                                                    ) !== false) {
                                                                                                    return "Links";
                                                                                                } else {
                                                                                                    if (stripos(
                                                                                                            $user_agent,
                                                                                                            "Lynx"
                                                                                                        ) !== false) {
                                                                                                        return "Lynx";
                                                                                                    } else {
                                                                                                        if (stripos(
                                                                                                                $user_agent,
                                                                                                                "w3m"
                                                                                                            ) !== false) {
                                                                                                            return "w3m";
                                                                                                        } else {
                                                                                                            if (stripos(
                                                                                                                    $user_agent,
                                                                                                                    "mobile"
                                                                                                                ) !== false) {
                                                                                                                return "Handheld Browser";
                                                                                                            } else {
                                                                                                                if (stripos(
                                                                                                                        $user_agent,
                                                                                                                        "MyIE"
                                                                                                                    ) !== false) {
                                                                                                                    return "MyIE";
                                                                                                                } else {
                                                                                                                    return 'No identificado';
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

function CedulaValida($cedula)
{
    $num = strlen("$cedula");
    if ($num == 10) {
        $total = 0;
        $digito = (1 * $cedula[9]);
        for ($i = 0; $i < ($num - 1); $i++) {
            if ($i % 2 != 0) {
                $total += 1 * $cedula[$i];
            } else {
                $total += 2 * $cedula[$i];
                if ((2 * $cedula[$i]) > 9) {
                    $total -= 9;
                }
            }
        }
        $final = (((floor($total / 10)) + 1) * 10) - $total;
        return (($final == 10 && $digito == 0) || ($final == $digito));
    } else {
        return false;
    }
}

function RUCValido($RUC)
{
    $Resultado = true;
    if (strlen($RUC) != 13 || !is_numeric($RUC)) {
        $Resultado = false;
    } else {
        for ($index = 0; $index < 13; $index++) {
            if (is_int(substr($RUC, $index, 1))) {
                $Resultado = false;
            }
        }
        if (1 * substr($RUC, 0, 2) > 24) {
            $Resultado = false;
        }
        if (substr($RUC, 2, 1) == '7' || substr($RUC, 2, 1) == '8') {
            $Resultado = false;
        }
        if (substr($RUC, 12, 1) == '0') {
            $Resultado = false;
        }
        if (1 * substr($RUC, 2, 1) < 6) {
            if (!CedulaValida(substr($RUC, 0, 10))) {
                $Resultado = false;
            }
        } else {
            if (substr($RUC, 2, 1) == '9') {
                $Suma = 0;
                $ArrRef = array(4, 3, 2, 7, 6, 5, 4, 3, 2);
                for ($index = 0; $index < 9; $index++) {
                    $Suma += $ArrRef[$index] * 1 * substr($RUC, $index, 1);
                }
                $DV = 11 - ($Suma % 11);
                if ($DV == 11) {
                    $DV = 0;
                }
                if (substr($RUC, 9, 1) != $DV) {
                    $Resultado = false;
                }
            } else {
                if (substr($RUC, 2, 1) == '6') {
                    $Suma = 0;
                    $ArrRef = array(3, 2, 7, 6, 5, 4, 3, 2);
                    for ($index = 0; $index < 8; $index++) {
                        $Suma += $ArrRef[$index] * 1 * substr($RUC, $index, 1);
                    }
                    $DV = 11 - ($Suma % 11);
                    if ($DV == 11) {
                        $DV = 0;
                    }
                    if (substr($RUC, 8, 1) != $DV) {
                        $Resultado = false;
                    }
                }
            }
        }
    }
    return $Resultado;
}

function EnviarCorreo(
    $view,
    $de,
    $asunto,
    $arr_emails_destinatarios,
    $arr_nombres_destinatarios,
    $arretiquetas,
    $arrvalores,
    $arr_caminos_adjuntos = null,
    $data_mailgun = null,
    $nombreEnmas = null,
    $correoEnmas = null
) {
    try {
        $Res = 0;
        $Mensaje = "";
        if ($Res >= 0) {
            Mail::send(
                $view,
                array_combine($arretiquetas, $arrvalores),
                function ($message) use (
                    $de,
                    $asunto,
                    $arr_emails_destinatarios,
                    $arr_nombres_destinatarios,
                    $arr_caminos_adjuntos,
                    $data_mailgun,
                    $nombreEnmas,
                    $correoEnmas
                ) {
                    if ($nombreEnmas == null || empty(trim($nombreEnmas))) {
                        $nombreEnmas = $de;
                    }
                    if ($correoEnmas == null || empty(trim($correoEnmas))) {
                        $correoEnmas = Config::get('app.mail_from_address');
                    }
                    $message->from($correoEnmas, $nombreEnmas);


                    $message->to($arr_emails_destinatarios, $arr_nombres_destinatarios)->subject($asunto);
                    if (is_array($arr_caminos_adjuntos)) {
                        foreach ($arr_caminos_adjuntos as $camino_adjunto) {
                            $message->attach($camino_adjunto);
                        }
                    }
                    if (!empty($data_mailgun) && $data_mailgun != null && is_array($data_mailgun)) {
                        foreach ($data_mailgun as $label => $value) {
                            $message->getHeaders()->addTextHeader($label, $value);
                        }
                    }
                }
            );
            if (count(Mail::failures()) > 0) {
                $Res = -1;
                $Mensaje = "Ocurrió un error enviando el correo";
                \Log::error('Error al enviar correo: ' . print_r(Mail::failures()));
            } else {
                $Res = 1;
                $Mensaje = "El correo fue enviado correctamente.";
            }
        }
    } catch (Exception $e) {
        $Res = -2;
        $Mensaje = $e->getMessage();
        \Log::error($e);
    }
    return array($Res, $Mensaje);
}

function FormatearMongoISODate($ISODate, $formato = "d/m/Y H:i:s")
{
    if (empty($ISODate) || !method_exists($ISODate, 'toDateTime')) {
        return '';
    }
    return $ISODate->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($formato);
}

function TienePermisos($id_modulo, $id_permisos, $operador_logico = "AND")
{
    if (empty(session()->get("permisos"))) {
        return false;
    } else {
        $matriz_id_permisos = session()->get("permisos");
        if (empty($matriz_id_permisos[$id_modulo])) {
            return false;
        } else {
            $arreglo_id_permisos = $matriz_id_permisos[$id_modulo];
            if (is_array($id_permisos)) {
                foreach ($id_permisos as $id_permiso) {
                    if ($operador_logico == "AND") {
                        if (!in_array((int)$id_permiso, $arreglo_id_permisos)) {
                            return false;
                        }
                    } else {
                        if (in_array((int)$id_permiso, $arreglo_id_permisos)) {
                            return true;
                        }
                    }
                }
                return ($operador_logico == "AND");
            } else {
                return in_array((int)$id_permisos, $arreglo_id_permisos);
            }
        }
    }
}

function NormalizarString($cadena)
{
    $no_permitidas = array(
        "á",
        "é",
        "í",
        "ó",
        "ú",
        "Á",
        "É",
        "Í",
        "Ó",
        "Ú",
        "à",
        "è",
        "ì",
        "ò",
        "ù",
        "ñ",
        "Ñ",
        "ç",
        "Ç",
        "ü"
    );
    $permitidas = array(
        "a",
        "e",
        "i",
        "o",
        "u",
        "A",
        "E",
        "I",
        "O",
        "U",
        "a",
        "e",
        "i",
        "o",
        "u",
        "n",
        "N",
        "c",
        "C",
        "u"
    );
    return str_replace($no_permitidas, $permitidas, $cadena);
}

function OrdenarMatrizPorIndice($Arreglo, $indice, $resto_universo_0 = false, $orden = SORT_DESC)
{
    $ArrT = array();
    foreach ($Arreglo as $key => $Arr) {
        $ArrT[$key] = $Arr[$indice];
    }
    if (count($ArrT)) {
        array_multisort($ArrT, $orden, $Arreglo);
        if ($resto_universo_0) {
            if (isset($Arreglo["'0'"])) {
                $LineaRestoUniverso = $Arreglo["'0'"];
                unset($Arreglo["'0'"]);
                array_push($Arreglo, $LineaRestoUniverso);
            }
        }
    }
    return $Arreglo;
}

function ContrasenaCompleja($password, $longitud_minima = 6)
{
    $numero = true;
    $mayuscula = true;
    $minuscula = true;
    $longitud = strlen($password);

    for ($index = 0; $index < $longitud; $index++) {
        $caracter = $password[$index];
    }

    return ($numero && $mayuscula && $minuscula && $longitud >= $longitud_minima);
}

function CelularEcuadorValido($numero_celular)
{
    if (strlen($numero_celular) != 10) {
        return false;
    } else {
        if (substr($numero_celular, 0, 2) != "09") {
            return false;
        } else {
            if (!ctype_digit($numero_celular)) {
                return false;
            }
        }
    }
    return true;
}

function Filtrar(&$variable, $tipo, $valor_si_incumple = null)
{
    switch (strtoupper($tipo)) {
        case "STRING":
        {
            if (!is_string($variable)) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "EMAIL":
        {
            if (!filter_var($variable, FILTER_VALIDATE_EMAIL)) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "BOOLEAN":
        {
            if (!filter_var($variable, FILTER_VALIDATE_BOOLEAN)) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "INTEGER":
        {
            if (filter_var($variable, FILTER_VALIDATE_INT) === false) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "FLOAT":
        {
            if (!filter_var($variable, FILTER_VALIDATE_FLOAT)) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "ARRAY":
        {
            if (!is_array($variable)) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "OBJECT":
        {
            if (!is_object($variable)) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "JSON":
        {
            if (json_decode($variable) === null) {
                $variable = $valor_si_incumple;
            }
            break;
        }
        case "URL":
        {
            if (filter_var($variable, FILTER_VALIDATE_URL) === false) {
                $variable = $valor_si_incumple;
            }
            break;
        }
    }
}

function EncriptarId($id)
{
    return $id;
}

function DesencriptarId($id_encriptado)
{
    return $id_encriptado;
}

function UnirData($arreglo, $separador = "||**||")
{
    return implode($separador, $arreglo);
}

function DesunirData($cadena, $separador = "||**||")
{
    return explode($separador, $cadena);
}

function EnteroEnLetrasPersonal($numero)
{
    $cadena_final = "";
    $arreglo = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K"];
    $numero = (string)$numero;
    for ($posicion = 0, $largo = strlen($numero); $posicion < $largo; $posicion++) {
        $cadena_final .= $arreglo[$numero[$posicion]];
    }
    return $cadena_final;
}

function justNumbers($numero) {
    $permitidos = "0123456789";
    for ($i=0; $i<strlen($numero); $i++){
        if (strpos($permitidos, substr($numero,$i,1))===false){
            return false;
        }
    }
    return true;
}



?>