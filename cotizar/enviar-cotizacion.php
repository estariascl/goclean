<?php
/**
 * Procesador Go Clean v6.9/v7.0 - FINAL PRODUCCIÓN
 * Sincronizado con Cotizador Modular (Dormitorios 1-7+).
 * Genera Reporte Integral con Fecha de Solicitud en el encabezado.
 * Utiliza mail() nativo con cabeceras MIME para alta entregabilidad.
 */

// Configurar zona horaria de Chile
date_default_timezone_set('America/Santiago');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. CONFIGURACIÓN DE DESTINATARIOS Y REMITENTE
    // Puedes agregar más correos separados por coma
    $destinatarios = ["estariascl@gmail.com", "gocleansantiago@gmail.com"];
    $to = implode(", ", $destinatarios);
    
    // Remitente: Debe ser una cuenta real de tu dominio para evitar filtros de spam
    $from = "contacto@gocleansantiago.cl"; 
    $from_name = "Go Clean Santiago";

    $archivo_csv = __DIR__ . '/base_cotizaciones.csv';
    $f_registro = date("d/m/Y H:i:s"); // Fecha y hora exacta de entrada
    $brand_blue = "#3385d9";

    // 2. GESTIÓN DE BASE DE DATOS (CSV - Estándar de 13 columnas)
    $headers_csv = ['ID Solicitud', 'Fecha Registro', 'Nombre', 'Email', 'Telefono', 'Comuna', 'M2', 'Estado Propiedad', 'Servicios', 'Fotos', 'Estado Gestion', 'Notas', 'Fecha Propuesta'];
    
    if (!file_exists($archivo_csv) || filesize($archivo_csv) == 0) {
        $fh_init = fopen($archivo_csv, 'w');
        fputs($fh_init, "\xEF\xBB\xBF"); // BOM UTF-8 para compatibilidad con Excel
        fputcsv($fh_init, $headers_csv, ";");
        fclose($fh_init);
    }
    
    // Generar ID único basado en el número de registros actuales
    $lineas = file($archivo_csv, FILE_SKIP_EMPTY_LINES);
    $id_solicitud = date("dm") . "-" . count($lineas);

    // 3. CAPTURA Y SANEAMIENTO DE DATOS
    $nombre    = strip_tags(trim($_POST['nombre_completo'] ?? 'Cliente'));
    $email_c   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono  = strip_tags(trim($_POST['telefono'] ?? ''));
    $comuna    = strip_tags($_POST['comuna'] ?? 'No especificada');
    $fecha_p   = strip_tags($_POST['fecha_servicio'] ?? 'No definida'); // Fecha de servicio propuesta
    
    $m2        = $_POST['metros'] ?? 'N/A';
    $estado_p  = $_POST['estado'] ?? 'N/A';
    $comentarios = strip_tags(trim($_POST['comentarios'] ?? 'Sin notas adicionales.'));

    // 4. LÓGICA DE SERVICIOS ESPECIALIZADOS (DETALLE TÉCNICO)
    $servicios_array = $_POST['servicios_extra'] ?? [];
    $especializados_html = "";

    // A. Detalle de Vidrios
    if (in_array("Limpieza de vidrios", $servicios_array)) {
        $tipo_v = $_POST['tipo_prop_vidrio'] ?? 'N/A';
        $p_v = $_POST['qty_pisos'] ?? '1';
        $v_v = $_POST['qty_ventanas'] ?? '0';
        $v_l = $_POST['qty_ventanales'] ?? '0';
        $especializados_html .= "
            <div style='margin-bottom:15px; border-bottom:1px solid #dbeafe; padding-bottom:10px;'>
                <b style='font-size:11px; color:#1e1b4b; text-transform:uppercase;'>🖼️ Limpieza de Vidrios:</b>
                <p style='margin:5px 0; font-size:13px; color:#334155;'>Tipo: $tipo_v ($p_v pisos)<br>Cant: $v_v Vent. / $v_l Ventanales</p>
            </div>";
    }

    // B. Detalle de Alfombras (vía JSON)
    if (!empty($_POST['detalle_alfombras_json']) && $_POST['detalle_alfombras_json'] !== '[]') {
        $alf = json_decode($_POST['detalle_alfombras_json'], true);
        $especializados_html .= "<div style='margin-bottom:15px; border-bottom:1px solid #dbeafe; padding-bottom:10px;'><b style='font-size:11px; color:#1e1b4b; text-transform:uppercase;'>🧶 Lavado de Alfombras:</b><p style='margin:5px 0; font-size:13px; color:#334155;'>";
        foreach($alf as $idx => $a) { $n = $idx + 1; $especializados_html .= "• Alfombra $n: {$a['material']} ({$a['dim']})<br>"; }
        $especializados_html .= "</p></div>";
    }

    // C. Tapices y Pulido
    if (in_array("Limpieza de tapices", $servicios_array)) {
        $mueble = $_POST['mueble_tapiz'] ?? 'N/A';
        $tela = $_POST['tela_tapiz'] ?? 'N/A';
        $especializados_html .= "<div style='margin-bottom:10px;'><b style='font-size:11px; color:#1e1b4b; text-transform:uppercase;'>🛋️ Tapices:</b> <span style='font-size:13px;'>$mueble ($tela)</span></div>";
    }
    
    if (in_array("Pulido de pisos", $servicios_array)) {
        $piso = $_POST['tipo_piso_pulido'] ?? 'N/A';
        $especializados_html .= "<div style='margin-bottom:10px;'><b style='font-size:11px; color:#1e1b4b; text-transform:uppercase;'>✨ Pulido:</b> <span style='font-size:13px;'>$piso</span></div>";
    }

    // 5. REGISTRO EN CSV
    $servicios_log = (isset($_POST['req_aseo']) ? "Integral, " : "") . implode(", ", $servicios_array);
    $cant_fotos = 0;
    if (isset($_FILES['fotos_referencia'])) {
        foreach ($_FILES['fotos_referencia']['error'] as $err) if ($err == UPLOAD_ERR_OK) $cant_fotos++;
    }
    
    $fh = fopen($archivo_csv, 'a');
    fputcsv($fh, [$id_solicitud, $f_registro, $nombre, $email_c, $telefono, $comuna, $m2, $estado_p, rtrim($servicios_log, ", "), $cant_fotos, 'Nueva', '[]', $fecha_p], ";");
    fclose($fh);

    // 6. CONSTRUCCIÓN DEL CORREO (REPORTE v6.9)
    $tel_clean = preg_replace('/[^0-9]/', '', $telefono);
    $wa_link = "https://wa.me/$tel_clean?text=" . urlencode("Hola $nombre, te contacto de Go Clean por tu solicitud #$id_solicitud recibida el $f_registro.");
    
    $asunto = "=?UTF-8?B?" . base64_encode("[$id_solicitud] Nueva Solicitud Go Clean - $nombre") . "?=";
    $uid = md5(uniqid(time()));
    
    $header = "From: $from_name <$from>\r\nReply-To: $email_c\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$uid\"\r\n\r\n";

    // Cuerpo del mensaje
    $html_body = "
    <html><body style='margin:0; padding:0; background-color:#f4f7fa; font-family:sans-serif;'>
        <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='margin:20px auto; background-color:#ffffff; border-radius:24px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 12px 30px rgba(0,0,0,0.08);'>
            <!-- CABECERA CON FECHA DE SOLICITUD -->
            <tr><td bgcolor='$brand_blue' style='padding:40px; text-align:center;'>
                <h1 style='color:#ffffff; margin:0; font-size:22px; font-weight:900; letter-spacing:1px; text-transform:uppercase;'>SOLICITUD #$id_solicitud</h1>
                <p style='color: #e0f2fe; margin: 5px 0 0; font-size: 13px; font-weight: 400; text-transform: uppercase; letter-spacing: 0.5px;'>
                    Recibida el: <b>$f_registro</b>
                </p>
                <div style='display:inline-block; margin-top:15px; padding:8px 15px; background:rgba(255,255,255,0.2); border-radius:10px;'>
                    <p style='color:#ffffff; margin:0; font-size:14px; font-weight:600;'>🗓️ Fecha Propuesta: <b>$fecha_p</b></p>
                </div>
            </td></tr>
            
            <!-- DATOS CLIENTE -->
            <tr><td style='padding:35px 40px;'>
                <h2 style='color:#1e1b4b; font-size:18px; border-bottom:2px solid #f1f5f9; padding-bottom:10px; text-transform:uppercase;'>Información del Cliente</h2>
                <p style='font-size:15px; color:#1e293b; line-height:1.6;'>
                    <b>Nombre:</b> $nombre<br>
                    <b>Comuna:</b> 📍 $comuna<br>
                    <b>WhatsApp:</b> $telefono
                </p>
                <div style='text-align:center; margin:25px 0;'><a href='$wa_link' style='background:#25d366; color:#fff; padding:18px 30px; border-radius:15px; text-decoration:none; font-weight:800;'>💬 CONTACTAR POR WHATSAPP</a></div>
            </td></tr>";

    // Bloque Integral (Solo si aplica)
    if (isset($_POST['req_aseo'])) {
        $dorm = $_POST['dorm'] ?? 'N/A';
        $banos = $_POST['banos'] ?? 'N/A';
        $obs = $_POST['obs_remodelacion'] ?? '';
        $html_body .= "
            <tr><td style='padding:0 40px 30px 40px;'>
                <div style='background:#f8fafc; border-radius:20px; padding:25px; border-left:6px solid $brand_blue;'>
                    <h3 style='margin:0 0 15px 0; color:$brand_blue; font-size:17px; font-weight:800;'>🏠 Limpieza Profunda Integral</h3>
                    <p style='margin:0; font-size:14px; color:#1e293b;'><b>$m2 m²</b> | $estado_p | $dorm Dorm / $banos Baños</p>
                    " . ($obs ? "<div style='background:#fef2f2; padding:12px; border-radius:12px; border-left:4px solid #ef4444; margin-top:10px;'><b style='font-size:11px; color:#b91c1c;'>NOTA OBRA:</b> <span style='font-size:13px;'>$obs</span></div>" : "") . "
                </div>
            </td></tr>";
    }

    // Bloque Especializados
    if ($especializados_html) {
        $html_body .= "
            <tr><td style='padding:0 40px 30px 40px;'>
                <div style='background:#eff6ff; border-radius:20px; padding:25px; border:1px solid #dbeafe; border-left:6px solid #1e1b4b;'>
                    <h3 style='margin:0 0 20px 0; color:#1e1b4b; font-size:17px; font-weight:800;'>🛠️ Servicios Técnicos</h3>
                    $especializados_html
                </div>
            </td></tr>";
    }

    // Cierre
    $html_body .= "
            <tr><td style='padding:0 40px 40px 40px;'>
                <h4 style='color:#64748b; font-size:11px; text-transform:uppercase;'>Notas del Cliente:</h4>
                <div style='background:#fdfdfd; border:1px solid #f1f5f9; padding:20px; border-radius:18px; font-style:italic; font-size:14px; color:#475569;'>$comentarios</div>
                <p style='margin-top:20px; font-size:12px; color:#94a3b8; text-align:center;'>Generado por Go Clean v6.9 | © 2026</p>
            </td></tr>
        </table></body></html>";

    $message = "--$uid\r\nContent-type:text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n$html_body\r\n\r\n";

    // 7. MANEJO DE ADJUNTOS
    if (isset($_FILES['fotos_referencia'])) {
        foreach ($_FILES['fotos_referencia']['tmp_name'] as $idx => $tmpName) {
            if ($_FILES['fotos_referencia']['error'][$idx] == UPLOAD_ERR_OK) {
                $fName = $_FILES['fotos_referencia']['name'][$idx];
                $fType = $_FILES['fotos_referencia']['type'][$idx];
                $fContent = chunk_split(base64_encode(file_get_contents($tmpName)));
                $message .= "--$uid\r\nContent-Type: $fType; name=\"$fName\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fName\"\r\n\r\n$fContent\r\n\r\n";
            }
        }
    }
    $message .= "--$uid--";

    // 8. ENVÍO FINAL
    if (mail($to, $asunto, $message, $header, "-f$from")) {
        header("Location: gracias.html?ref=" . $id_solicitud);
        exit();
    } else {
        echo "Error en servidor de correo. Verifique logs del hosting.";
    }
}
?>