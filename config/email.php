<?php
/**
 * ═════════════════════════════════════════════════════════════
 *  Email Configuration & Functions
 *  - SMTP setup for sending verification emails
 *  - Use environment variables for production
 * ═════════════════════════════════════════════════════════════
 */

// ─── SMTP Configuration ────────────────────────────────────────
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: 'your-email@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'your-app-password');
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'noreply@citylive.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'CityLive');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/citylive');

/**
 * Enviar correo de verificación de email
 *
 * @param string $email - Email del usuario
 * @param string $fullName - Nombre completo del usuario
 * @param string $verificationToken - Token único de verificación
 * @return bool - true si fue exitoso, false si falló
 */
function sendVerificationEmail($email, $fullName, $verificationToken) {
    // URL para verificar el correo (cambiar cuando sea producción)
    $verifyUrl = APP_URL . '/verify-email.php?token=' . urlencode($verificationToken);
    
    $subject = 'Confirma tu correo en CityLive';
    
    $htmlBody = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 8px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; margin: 20px 0; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🗺️ CityLive</h1>
            </div>
            <div class='content'>
                <h2>¡Hola " . htmlspecialchars($fullName) . "!</h2>
                <p>Gracias por registrarte en <strong>CityLive</strong>. Para completar tu registro, por favor confirma tu correo electrónico.</p>
                
                <center>
                    <a href='" . htmlspecialchars($verifyUrl) . "' class='button'>Confirmar correo</a>
                </center>
                
                <p>O copia este enlace en tu navegador:</p>
                <p style='word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 4px;'>
                    " . htmlspecialchars($verifyUrl) . "
                </p>
                
                <p style='color: #999; font-size: 12px;'>
                    Este enlace expirará en 24 horas. Si no creaste una cuenta, por favor ignora este mensaje.
                </p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " CityLive. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plainBody = "
Hola " . $fullName . ",

Gracias por registrarte en CityLive. Para completar tu registro, por favor confirma tu correo.

Enlace de confirmación:
" . $verifyUrl . "

Este enlace expirará en 24 horas.

Si no creaste una cuenta, ignora este mensaje.

CityLive
    ";
    
    return sendEmail($email, $subject, $plainBody, $htmlBody);
}

/**
 * Enviar correo genérico con SMTP (usando PHPMailer)
 *
 * @param string $to - Email destinatario
 * @param string $subject - Asunto
 * @param string $plainBody - Cuerpo en texto plano
 * @param string $htmlBody - Cuerpo en HTML (opcional)
 * @return bool
 */
function sendEmail($to, $subject, $plainBody, $htmlBody = null) {
    // Si estamos en ambiente local (sin SMTP configurado), solo logueamos
    if (SMTP_USER === 'your-email@gmail.com') {
        error_log("EMAIL SIMULADO para: $to\nAsunto: $subject\n$plainBody");
        return true; // Simular éxito en desarrollo
    }
    
    try {
        // Usar socket directo para SMTP (compatible con Infinityfree)
        $smtpHost = SMTP_HOST;
        $smtpPort = SMTP_PORT;
        $smtpUser = SMTP_USER;
        $smtpPass = SMTP_PASS;
        $fromEmail = SMTP_FROM;
        $fromName = SMTP_FROM_NAME;
        
        // Conectar al servidor SMTP
        $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 10);
        if (!$socket) {
            error_log("Error SMTP: No se puede conectar a $smtpHost:$smtpPort - $errstr");
            return false;
        }
        
        function smtpRead($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') break;
            }
            return $response;
        }
        
        function smtpWrite($socket, $cmd) {
            fputs($socket, $cmd . "\r\n");
        }
        
        // Leer respuesta de bienvenida
        smtpRead($socket);
        
        // EHLO
        smtpWrite($socket, "EHLO " . $_SERVER['SERVER_NAME']);
        smtpRead($socket);
        
        // STARTTLS
        smtpWrite($socket, "STARTTLS");
        smtpRead($socket);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // AUTH LOGIN
        smtpWrite($socket, "EHLO " . $_SERVER['SERVER_NAME']);
        smtpRead($socket);
        
        smtpWrite($socket, "AUTH LOGIN");
        smtpRead($socket);
        
        smtpWrite($socket, base64_encode($smtpUser));
        smtpRead($socket);
        
        smtpWrite($socket, base64_encode($smtpPass));
        smtpRead($socket);
        
        // MAIL FROM
        smtpWrite($socket, "MAIL FROM: <" . $fromEmail . ">");
        smtpRead($socket);
        
        // RCPT TO
        smtpWrite($socket, "RCPT TO: <" . $to . ">");
        smtpRead($socket);
        
        // DATA
        smtpWrite($socket, "DATA");
        smtpRead($socket);
        
        // Construir headers
        $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n";
        $headers .= "To: " . $to . "\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "X-Mailer: CityLive\r\n";
        
        if ($htmlBody) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body = $htmlBody;
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body = $plainBody;
        }
        
        // Enviar mensaje
        smtpWrite($socket, $headers . "\r\n" . $body . "\r\n.");
        smtpRead($socket);
        
        // QUIT
        smtpWrite($socket, "QUIT");
        smtpRead($socket);
        
        fclose($socket);
        return true;
        
    } catch (Exception $e) {
        error_log("Exception sending email: " . $e->getMessage());
        return false;
    }
}

/**
 * Generar un token de verificación único
 *
 * @return string - Token aleatorio de 64 caracteres hexadecimales
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Validar si el token de verificación es válido y no ha expirado
 *
 * @param string $token - Token a verificar
 * @param int $expirationHours - Horas de validez (default 24)
 * @return array|false - Array con user_id, email si es válido; false si expiró o no existe
 */
function validateVerificationToken($token, $expirationHours = 24) {
    $db = getDB();
    
    $stmt = $db->prepare('
        SELECT id, email, verification_token, token_created_at
        FROM users
        WHERE verification_token = ? AND verified = 0
    ');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    $tokenAge = (time() - strtotime($user['token_created_at'])) / 3600; // en horas
    
    if ($tokenAge > $expirationHours) {
        return false; // Token expirado
    }
    
    return [
        'user_id' => $user['id'],
        'email' => $user['email']
    ];
}

?>
