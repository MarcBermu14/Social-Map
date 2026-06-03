<<<<<<< HEAD
# CityLive

App PHP/MySQL para incidencias, eventos y actividades en mapa.

## Despliegue rapido con Docker

1. Copia variables de entorno:
   - `cp .env.example .env`
2. Levanta servicios:
   - `docker compose up -d --build`
3. Ejecuta instalacion inicial (una sola vez):
   - abre `http://localhost:8080/install.php`
4. Desactiva instalador:
   - en `.env`, cambia `INSTALLER_ENABLED=false`
   - reinicia: `docker compose up -d`
5. Abre la app:
   - `http://localhost:8080`

## Abrir acceso a otras personas

Opciones:

1. Red local:
   - comparte `http://TU_IP_LOCAL:8080`
   - abre el puerto 8080 en firewall.
2. Internet (recomendado):
   - publica con reverse proxy/tunel (Cloudflare Tunnel, Nginx Proxy Manager, etc.).
   - activa HTTPS y pon `SESSION_SECURE_COOKIE=true`.

## Cambios tecnicos principales

- Configuracion por entorno (`.env`) para DB, sesiones y base path.
- Rutas dinamicas (`appUrl`) para funcionar en `/` o subcarpetas.
- Proteccion CSRF en formularios y APIs de escritura.
- Regeneracion de sesion al login/registro.
- `install.php` bloqueado por defecto mediante `INSTALLER_ENABLED=false`.
=======
🗺️ **CityLive** - Aplicación Web de Eventos Urbanos en Tiempo Real

---

## ⚡ INICIO RÁPIDO (3 Pasos)

### 1️⃣ Descargar e Instalar

```bash
# Linux/Mac: Ejecutar setup automático
bash setup.sh

# Windows: Ejecutar setup automático  
setup.bat
```

### 2️⃣ Configurar Variables de Entorno

Crear archivo `.env` en la raíz (o editarlo si ya fue creado):

```env
DB_HOST=localhost
DB_NAME=citylive
DB_USER=root
DB_PASS=
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
APP_URL=http://localhost/citylive
```

### 3️⃣ Importar Base de Datos

**Opción A: phpMyAdmin**
- Abrir http://localhost/phpmyadmin
- BD → SQL
- Copiar contenido de `sql/schema.sql`
- Ejecutar

**Opción B: Terminal**
```bash
mysql -u root < sql/schema.sql
```

---

## 🚀 ¡LISTO! Ahora:

1. Iniciar Apache + MySQL en XAMPP
2. Abrir http://localhost/citylive
3. ¡Crear cuenta y disfrutar!

---

## 📚 Documentación Completa

| Documento | Descripción |
|-----------|-------------|
| **[SETUP.md](SETUP.md)** | Guía completa de instalación local |
| **[DEPLOYMENT.md](DEPLOYMENT.md)** | Cómo publicar en internet con dominio propio |
| **[CHANGES.md](CHANGES.md)** | Detalle de todas las características implementadas |

---

## ✨ Características Principales

✅ **Email Verification** - Confirmar email al registrarse
✅ **Sin Duplicados** - Validación de usuarios y emails únicos
✅ **Login Seguro** - Contraseñas protegidas y encriptadas
✅ **Listo para Producción** - Fácil de desplegar en servidor real
✅ **Panel de Salud** - http://localhost/citylive/health-check.php

---

## 🔗 Enlaces Principales

- 🏠 [Home](http://localhost/citylive)
- 📝 [Registrarse](http://localhost/citylive/register.php)
- 🔑 [Login](http://localhost/citylive/index.php)
- ❤️ [Eventos](http://localhost/citylive/events.php)
- 💬 [Foro](http://localhost/citylive/forum.php)
- 🏥 [Salud del Sistema](http://localhost/citylive/health-check.php)

---

## 🛠️ Stack Tecnológico

- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, JavaScript
- **Email:** SMTP (Gmail, SendGrid, etc.)

---

## 🔐 Seguridad

✅ Contraseñas hasheadas (PASSWORD_BCRYPT)
✅ SQL Injection prevention
✅ Email verificación requerida
✅ Tokens expiran en 24 horas
✅ HTTPS ready

---

## 📞 Soporte

¿Problemas? Revisa:
1. `SETUP.md` → Troubleshooting
2. `health-check.php` → Estado del sistema
3. Logs en `C:\xampp\apache\logs\error.log`

---

## 🎯 Próximas Mejoras

- Olvidé mi contraseña
- 2 Factor Authentication
- Social Login
- Notificaciones en tiempo real

---

## 📄 Licencia

MIT - Libre para usar, modificar y distribuir

---

**Hecho con ❤️ para tu ciudad**

🗺️ CityLive v1.0
>>>>>>> main
