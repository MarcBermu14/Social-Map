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

Importante, para aceptar usuarios:
https://citylive.infinityfree.io/verify-email.php?token=e2c56eeb142c1aa65f6ab2c7d2304f38fff8425af5890d1b89ed339f3d23f703

en el .env hay que cambiar:
1. Obtener contraseña de app en Gmail:
Ve a: https://myaccount.google.com/apppasswords
Elige "Mail" y "Windows Computer"
Google te genera una contraseña de 16 caracteres
Copia esa contraseña
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=tu-email@gmail.com//cambiar
SMTP_PASS=xxxx xxxx xxxx xxxx
SMTP_FROM=tu-email@gmail.com
SMTP_FROM_NAME=CityLive