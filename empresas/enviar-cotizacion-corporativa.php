<?php
/**
 * Procesador Corporativo Go Clean v1.0
 * Recibe el formulario B2B, registra en base_cotizaciones_corp.csv y envía el correo responsivo.
 */

date_default_timezone_set('America/Santiago');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. CONFIGURACIÓN DE DESTINOS
    $destinatarios = ["estariascl@gmail.com", "gocleansantiago@gmail.com"];
    $to = implode(", ", $destinatarios);
    $from = "contacto@gocleansantiago.cl";
    $from_name = "Go Clean B2B";

    $archivo_csv = 'base_cotizaciones_corp.csv';
    $f_registro = date("d/m/Y H:i:s");

    // 2. CAPTURA Y SANEAMIENTO DE DATOS B2B
    $rubro        = strip_tags($_POST['rubro_empresa'] ?? 'No especificado');
    $razon_social = strip_tags(trim($_POST['razon_social'] ?? 'Empresa no especificada'));
    $rut_empresa  = strip_tags(trim($_POST['rut_empresa'] ?? 'N/A'));
    $comuna       = strip_tags($_POST['comuna'] ?? 'No especificada');
    $nombre_cont  = strip_tags(trim($_POST['nombre_contacto'] ?? 'Contacto'));
    $cargo_cont   = strip_tags(trim($_POST['cargo_contacto'] ?? 'N/A'));
    $email_corp   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono     = strip_tags(trim($_POST['telefono'] ?? ''));
    $descripcion  = strip_tags(trim($_POST['descripcion_instalaciones'] ?? 'Sin requerimientos detallados.'));

    // 3. BASE DE DATOS CSV INDEPENDIENTE (12 COLUMNAS)
    $headers_csv = ['ID Solicitud', 'Fecha Registro', 'Razón Social', 'RUT', 'Rubro', 'Comuna', 'Nombre Contacto', 'Cargo', 'Email', 'Telefono', 'Descripcion', 'Estado Gestion'];
    
    if (!file_exists($archivo_csv) || filesize($archivo_csv) == 0) {
        $fh_init = fopen($archivo_csv, 'w');
        fputs($fh_init, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($fh_init, $headers_csv, ";");
        fclose($fh_init);
    }
    
    $lineas = file($archivo_csv, FILE_SKIP_EMPTY_LINES);
    $id_solicitud = "CORP-" . date("dm") . "-" . count($lineas);

    // Guardado físico en el CSV
    $fh = fopen($archivo_csv, 'a');
    fputcsv($fh, [
        $id_solicitud,
        $f_registro,
        $razon_social,
        $rut_empresa,
        $rubro,
        $comuna,
        $nombre_cont,
        $cargo_cont,
        $email_corp,
        $telefono,
        $descripcion,
        'Nueva'
    ], ";");
    fclose($fh);

    // 4. LECTURA Y CONSTRUCCIÓN DEL CUERPO DEL CORREO
    $plantilla_file = 'email_corporativo_maqueta.html';
    
    if (file_exists($plantilla_file)) {
        $html_body = file_get_contents($plantilla_file);
    } else {
        // Fallback básico si el archivo no existe por alguna razón
        $html_body = "<h2>Nueva Cotización Corporativa #$id_solicitud</h2><p>Razón Social: $razon_social</p>";
    }

    // Limpieza de teléfono para el link de WhatsApp
    $tel_limpio = preg_replace('/[^0-9]/', '', $telefono);
    $mensaje_wa = urlencode("Hola $nombre_cont, te contacto de Go Clean Santiago por tu solicitud de evaluación técnica corporativa #$id_solicitud para la empresa $razon_social.");

    // Reemplazo de marcadores dinámicos en la plantilla HTML
    $remplazos = [
        '[ID_SOLICITUD]' => $id_solicitud,
        '[RUBRO_EMPRESA]' => $rubro,
        '[RAZON_SOCIAL]' => $razon_social,
        '[RUT_EMPRESA]' => $rut_empresa,
        '[COMUNA]' => $comuna,
        '[NOMBRE_CONTACTO]' => $nombre_cont,
        '[CARGO_CONTACTO]' => $cargo_cont,
        '[TELEFONO]' => $telefono,
        '[EMAIL]' => $email_corp,
        '[DESCRIPCION_INSTALACIONES]' => $descripcion,
        '[TELEFONO_LIMPIO]' => $tel_limpio,
        '[MENSAJE_WA]' => $mensaje_wa
    ];

    $html_body = str_replace(array_keys($remplazos), array_values($remplazos), $html_body);

    // 5. ENVÍO DE EMAIL
    $asunto = "=?UTF-8?B?" . base64_encode("[$id_solicitud] $razon_social - $rubro") . "?=";
    
    $headers = "From: $from_name <$from>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    if (mail($to, $asunto, $html_body, $headers)) {
        // Redireccionar a la página de gracias corporativa
        header("Location: gracias.html?ref=" . $id_solicitud);
        exit();
    }
}
?>