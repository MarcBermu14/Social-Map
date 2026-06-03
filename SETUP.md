# CityLive - Guía Rápida de Setup

## ✨ Nuevas Características Implementadas

✅ **Email Verification** - Los usuarios reciben un correo de confirmación al registrarse
✅ **Sin Duplicados** - Validación completa de usuarios y emails únicos
✅ **Login Seguro** - Requiere confirmación de email antes de iniciar sesión
✅ **Listo para Producción** - Configuración para desplegar en cualquier servidor

---

## 🏠 Configuración Local (XAMPP)

### 1. Actualizar Base de Datos

```bash
# Ejecutar en MySQL
mysql -u root < sql/schema.sql

# O en phpMyAdmin:
# - Importar sql/schema.sql
```

### 2. Crear archivo `.env` en la raíz

Copia `.env.example` a `.env`:

```
DB_HOST=localhost
DB_NAME=citylive
DB_USER=root
DB_PASS=
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM=noreply@citylive.com
SMTP_FROM_NAME=CityLive
APP_URL=http://localhost/citylive
```

### 3. Configurar Email (Opcional para desarrollo)

Sin configuración, los emails se simulan en desarrollo. Para probar de verdad:

- Crear Gmail con [myaccount.google.com](https://myaccount.google.com)
- Ir a Security → App passwords → Crear contraseña para "Mail"
- Copiar contraseña de 16 caracteres en `SMTP_PASS` del `.env`

### 4. Iniciar Apache + MySQL

- Abrir XAMPP Control Panel
- Click en "Start" para Apache y MySQL
- Acceder a [http://localhost/citylive](http://localhost/citylive)

---

## 🚀 Despliegue en Producción

Ver archivo completo: [DEPLOYMENT.md](DEPLOYMENT.md)

**Resumen rápido:**

1. **Hosting recomendado:**
   - Hostinger, Bluehost, SiteGround (compartido)
   - DigitalOcean, Linode, Vultr (VPS)

2. **Pasos básicos:**
   - Subir archivos vía FTP a `public_html/`
   - Crear BD en cPanel
   - Configurar `.env` con credenciales reales
   - Importar `sql/schema.sql`
   - Activar SSL/HTTPS

3. **Email en Producción:**
   - Usar SendGrid, Mailgun, o Gmail SMTP
   - Configurar en `.env`

---

## 📋 Flujo de Registro y Login

### Registro

```
Usuario rellena formulario
        ↓
Validación de:
  - Usuario no repetido
  - Email no repetido
  - Contraseña válida
        ↓
Crear usuario con verified=0
        ↓
Generar token de verificación
        ↓
Enviar email con link de confirmación
        ↓
Usuario hace click en email
        ↓
verify-email.php confirma token
        ↓
Marcar usuario como verified=1
        ↓
Usuario puede iniciar sesión
```

### Login

```
Usuario ingresa email + contraseña
        ↓
Buscar usuario en BD
        ↓
Verificar contraseña
        ↓
¿Email verificado?
  - NO → Mostrar error: "Confirma tu email"
  - SÍ → Crear sesión → Ir a dashboard
```

---

## 🔧 Archivos Modificados/Nuevos

### Nuevos:
- **`config/email.php`** - Funciones para envío de emails
- **`verify-email.php`** - Página de confirmación de email
- **`DEPLOYMENT.md`** - Guía completa de despliegue
- **`.env.example`** - Plantilla de configuración

### Modificados:
- **`sql/schema.sql`** - Agregados campos `verification_token` y `token_created_at`
- **`config/db.php`** - Carga variables de `.env`
- **`register.php`** - Usa nuevo sistema de verificación
- **`index.php`** - Requiere email verificado para login

---

## 🧪 Pruebas en Desarrollo

### Registrar usuario:

1. Ir a [http://localhost/citylive/register.php](http://localhost/citylive/register.php)
2. Llenar formulario
3. Ver en error_log (o archivo de logs) el link de verificación
4. Copiar token desde el log
5. Acceder a: `http://localhost/citylive/verify-email.php?token=XXXX`
6. Ahora puedes hacer login

### Ver logs de email:

```bash
# En Linux/Mac:
tail -f /var/log/apache2/error.log

# En Windows (XAMPP):
C:\xampp\apache\logs\error.log
```

---

## 🔐 Seguridad Implementada

✅ Contraseñas hasheadas con `PASSWORD_DEFAULT`
✅ SQL Injection: Prepared statements (PDO)
✅ CSRF: Agregar CSRF tokens después (si lo requieres)
✅ Token de verificación de 64 caracteres aleatorios
✅ Tokens expiran en 24 horas
✅ Email requerido antes de login

---

## 📞 Troubleshooting

### "Error: connection refused"
```
→ Asegúrate de tener Apache + MySQL corriendo en XAMPP
→ Verificar credenciales en .env
```

### "Email no se envía"
```
→ En desarrollo es normal (se simula)
→ Ver logs en error.log
→ En producción, configurar SMTP_USER/SMTP_PASS correctamente
```

### "Token expirado"
```
→ Tokens expiran en 24 horas
→ Usuario debe registrarse de nuevo
→ Cambiar en config/email.php función validateVerificationToken()
```

### "Login no funciona"
```
→ ¿Confirmó el email? Revisar BD:
   SELECT verified FROM users WHERE email = 'xxx@xxx.com';
→ Debe ser 1 (1 = verificado, 0 = no verificado)
```

---

## 🎯 Próximos Pasos

- [ ] Agregar autenticación de 2 factores (2FA)
- [ ] Implementar "Olvidé mi contraseña"
- [ ] Rate limiting en login
- [ ] CSRF tokens en formularios
- [ ] Validar email real con bounce checking
- [ ] Guardar dirección IP de registro
- [ ] Alertas de login sospechoso

---

## 📚 Recursos Útiles

- [PHP PDO Documentation](https://www.php.net/manual/en/class.pdo.php)
- [PHP Password Hashing](https://www.php.net/manual/en/function.password-hash.php)
- [Gmail App Passwords](https://support.google.com/accounts/answer/185833)
- [OWASP Security Best Practices](https://owasp.org/)

---

## 📄 Licencia

Este proyecto está bajo licencia MIT. Úsalo libremente.

**¡Disfruta CityLive! 🗺️✨**
