# 🗺️ CityLive

**Red social urbana en tiempo real** — mezcla de Google Maps, Waze y red social.  
Los usuarios publican incidencias, eventos y actividades en un mapa interactivo colaborativo.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)
![Leaflet](https://img.shields.io/badge/Leaflet-1.9-199900?logo=leaflet)

---

## ✨ Funcionalidades

- 🗺️ **Mapa en vivo** con marcadores de incidencias, eventos y actividades (Leaflet + CartoDB Dark)
- 👤 **Perfiles de usuario** con reputación, valoraciones y publicaciones
- ⚡ **Sistema de tokens** — las actividades lucrativas consumen tokens
- 💎 **3 planes de suscripción**: Gratuita, Pro (1.000 tokens/mes), Platinum (10.000 tokens/mes)
- 📍 **Selector de ubicación** en mapa al crear publicaciones
- ⭐ **Sistema de valoraciones** por publicación
- 👥 **Sistema de seguidores**
- 🔐 **Autenticación completa** con sesiones PHP

---

## 🛠️ Requisitos

- [XAMPP](https://www.apachefriends.org/) (incluye Apache + MySQL + PHP)
- PHP 8.0 o superior
- MySQL 5.7 o superior
- Navegador moderno (Chrome, Firefox, Edge)

---

## 🚀 Instalación

### 1. Clona el repositorio

```bash
git clone https://github.com/TU_USUARIO/citylive.git
```

### 2. Copia la carpeta a XAMPP

Mueve o copia la carpeta `citylive` dentro de:

```
C:\xampp\htdocs\citylive\       ← Windows
/Applications/XAMPP/htdocs/citylive/   ← Mac
/opt/lampp/htdocs/citylive/     ← Linux
```

### 3. Arranca XAMPP

Abre el **XAMPP Control Panel** y pulsa **Start** en:
- ✅ Apache
- ✅ MySQL

### 4. Configura la base de datos (si es necesario)

Abre `config/db.php` y ajusta tus credenciales si son distintas de las por defecto:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'citylive');
define('DB_USER', 'root');   // tu usuario MySQL
define('DB_PASS', '');       // tu contraseña MySQL (vacía por defecto en XAMPP)
```

### 5. Ejecuta el instalador

Abre en el navegador:

```
http://localhost/citylive/install.php
```

Este script crea automáticamente:
- La base de datos y todas las tablas
- Usuarios de demo con datos de ejemplo
- Publicaciones, valoraciones y transacciones de prueba

> ⚠️ **Elimina `install.php` después de ejecutarlo** (o no lo subas a producción).

### 6. ¡Listo!

Abre la aplicación en:

```
http://localhost/citylive/
```

---

## 👤 Cuentas de demo

| Email | Contraseña | Plan |
|-------|-----------|------|
| maria@citylive.app | demo1234 | 💎 Platinum |
| carlos@citylive.app | demo1234 | ⭐ Pro |
| alex@citylive.app | demo1234 | 🆓 Free |
| sara@citylive.app | demo1234 | ⭐ Pro |
| demo@citylive.app | demo1234 | 💎 Platinum |

---

## 📁 Estructura del proyecto

```
citylive/
├── config/
│   └── db.php              # Conexión BD y helpers
├── css/
│   └── style.css           # Estilos globales (dark theme)
├── js/
│   ├── app.js              # JS global
│   └── map.js              # Leaflet + markers + panel de detalle
├── includes/
│   ├── header.php          # Sidebar + topbar compartido
│   └── footer.php          # Cierre HTML
├── api/
│   └── publications.php    # API GeoJSON para el mapa
├── sql/
│   └── schema.sql          # Esquema de la base de datos
├── index.php               # Login
├── register.php            # Registro
├── dashboard.php           # Mapa principal
├── activity.php            # Detalle de actividad
├── profile.php             # Perfil de usuario
├── create.php              # Crear publicación
├── subscriptions.php       # Planes y suscripciones
├── tokens.php              # Gestión de tokens
├── logout.php              # Cerrar sesión
└── install.php             # Instalador (eliminar tras usar)
```

---

## 🗄️ Base de datos

| Tabla | Descripción |
|-------|-------------|
| `users` | Usuarios, plan, tokens, reputación |
| `publications` | Incidencias, eventos y actividades |
| `reviews` | Valoraciones por publicación |
| `token_transactions` | Historial de tokens |
| `subscriptions` | Plan activo por usuario |
| `followers` | Relaciones seguidor/seguido |
| `saves` | Publicaciones guardadas |

---

## 🎨 Tecnologías

| Tecnología | Uso |
|-----------|-----|
| PHP 8 | Backend y renderizado |
| MySQL | Base de datos |
| Leaflet.js | Mapas interactivos |
| CartoDB Dark | Tiles del mapa (sin API key) |
| Font Awesome 6 | Iconografía |
| Google Fonts (Inter) | Tipografía |

---

## 📋 Solución de problemas

**"Not Found" al abrir install.php**  
→ Verifica que `citylive/` esté directamente dentro de `htdocs/`, no anidada.

**Error de conexión a la base de datos**  
→ Comprueba que MySQL esté iniciado en XAMPP y las credenciales en `config/db.php`.

**El mapa no carga**  
→ Necesitas conexión a internet (los tiles del mapa son externos). Comprueba la consola del navegador.

---

## 📄 Licencia

MIT — libre para uso educativo y personal.
