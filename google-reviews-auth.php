<?php
/**
 * GoClean — Google Business Profile API OAuth Helper
 * 
 * Este archivo maneja el intercambio seguro del código de autorización por el Access Token
 * del lado del servidor (PHP) para evitar exponer el Client Secret en el navegador (CORS).
 */

// Credenciales provistas
$clientId = getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET';

// Determinar la URI de redireccionamiento de forma dinámica (debe coincidir exactamente con Google Cloud Console)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$redirectUri = $protocol . "://" . $host . $uri;

// Si viene con el parámetro "code" desde Google
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Intercambiar código por Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para evitar problemas de certificados SSL en localhost
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['access_token'])) {
        $accessToken = $data['access_token'];
        $expiresIn = isset($data['expires_in']) ? $data['expires_in'] : 3600;
        $refreshToken = isset($data['refresh_token']) ? $data['refresh_token'] : '';
        
        // Renderizar script frontend para almacenar el token en localStorage y redireccionar al dashboard PoC
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Autenticando con Google — GoClean</title>
            <style>
                body {
                    margin: 0; padding: 0; min-height: 100vh;
                    display: flex; flex-direction: column; align-items: center; justify-content: center;
                    background: #0f172a; color: #f8fafc;
                    font-family: system-ui, -apple-system, sans-serif;
                }
                .loader {
                    border: 4px solid rgba(255,255,255,0.1);
                    border-left-color: #3385d9;
                    width: 40px; height: 40px;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-bottom: 20px;
                }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                p { font-size: 16px; font-weight: 500; letter-spacing: -0.01em; color: #94a3b8; }
            </style>
            <script>
                // Almacenar las credenciales en el navegador
                localStorage.setItem('g_reviews_access_token', '<?php echo addslashes($accessToken); ?>');
                localStorage.setItem('g_reviews_token_expiry', (Date.now() + (<?php echo intval($expiresIn); ?> * 1000)).toString());
                <?php if (!empty($refreshToken)): ?>
                    localStorage.setItem('g_reviews_refresh_token', '<?php echo addslashes($refreshToken); ?>');
                <?php endif; ?>
                
                // Redireccionar a la interfaz del dashboard PoC
                setTimeout(() => {
                    window.location.href = 'google-reviews-poc.html';
                }, 1000);
            </script>
        </head>
        <body>
            <div class="loader"></div>
            <p>Conexión exitosa. Redireccionando al panel de GoClean...</p>
        </body>
        </html>
        <?php
        exit;
    } else {
        // En caso de error, mostrar detalles legibles para depurar
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error de Autenticación — GoClean</title>
            <style>
                body {
                    font-family: system-ui, sans-serif; padding: 40px;
                    background: #0f172a; color: #f8fafc; line-height: 1.6;
                }
                .error-card {
                    background: #1e293b; padding: 32px; border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
                    max-width: 650px; margin: 40px auto; border: 1px solid #ef4444;
                }
                h1 { color: #f87171; margin-top: 0; font-size: 24px; font-weight: 800; }
                p { color: #94a3b8; }
                pre {
                    background: #0b0f19; padding: 16px; border-radius: 8px;
                    overflow-x: auto; font-family: monospace; font-size: 13px; color: #38bdf8;
                }
                .uri-highlight { color: #10b981; font-weight: bold; }
                .btn {
                    display: inline-block; background: #3385d9; color: white;
                    padding: 12px 28px; border-radius: 999px; text-decoration: none;
                    font-weight: 700; margin-top: 20px; transition: background 0.2s;
                }
                .btn:hover { background: #1e1b4b; }
            </style>
        </head>
        <body>
            <div class="error-card">
                <h1>Error en Intercambio OAuth 2.0</h1>
                <p>Google rechazó la solicitud de token con el código HTTP <strong><?php echo $httpCode; ?></strong>:</p>
                <pre><?php echo htmlspecialchars($response); ?></pre>
                
                <p><strong>Causa común:</strong> La URL de redireccionamiento de tu Google Cloud Console no coincide.</p>
                <p>Asegúrate de registrar exactamente esta URL en los <em>"Authorized redirect URIs"</em> del cliente de Google:</p>
                <pre class="uri-highlight"><?php echo htmlspecialchars($redirectUri); ?></pre>
                
                <a href="google-reviews-poc.html" class="btn">Volver a la PoC</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Si se accede directamente sin código de Google, iniciar el flujo redirigiendo al Consent Screen
$scope = 'https://www.googleapis.com/auth/business.manage';
$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope,
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

header("Location: " . $authUrl);
exit;
