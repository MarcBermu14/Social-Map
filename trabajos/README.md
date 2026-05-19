# 🗺️ CityLive

> **Red social urbana colaborativa en tiempo real**

CityLive es una plataforma que combina lo mejor de Google Maps, Waze y las redes sociales. Los usuarios publican incidencias, eventos y actividades en un **mapa interactivo compartido**, creando una comunidad conectada geográficamente.

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Leaflet](https://img.shields.io/badge/Leaflet-1.9-199900?logo=leaflet&logoColor=white)](https://leafletjs.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

---

## 🎯 Características principales

### 🗺️ Mapeo en tiempo real
- Mapa interactivo con marcadores de incidencias, eventos y actividades
- Basado en **Leaflet.js** con tiles oscuros de CartoDB
- Selector de ubicación integrado al crear publicaciones
- Panel de detalle con información completa de cada marcador

### 👥 Sistema de comunidad
- **Perfiles de usuario** con historial de publicaciones
- **Sistema de seguidores** para conectar con otros usuarios
- **Valoraciones y reseñas** en cada publicación
- **Reputación** basada en la actividad y calidad de contribuciones

### 💰 Modelo de monetización
- **3 planes de suscripción** flexibles:
  - 🆓 **Gratuito**: acceso básico
  - ⭐ **Pro**: 1.000 tokens/mes
  - 💎 **Platinum**: 10.000 tokens/mes
- **Sistema de tokens** para actividades premium
- **Historial de transacciones** transparente

### 🔐 Seguridad y confiabilidad
- Autenticación con sesiones PHP seguras
- Validación de datos en servidor y cliente
- Contraseñas hasheadas con algoritmos modernos

---

## � Inicio rápido (3 minutos)

**Para usuarios Windows/Mac/Linux con XAMPP:**

```bash
# 1. Clona el repositorio
git clone https://github.com/TU_USUARIO/citylive.git
cd citylive

# 2. Coloca la carpeta en htdocs de XAMPP
# Windows: C:\xampp\htdocs\citylive\
# Mac: /Applications/XAMPP/htdocs/citylive/
# Linux: /opt/lampp/htdocs/citylive/

# 3. Abre http://localhost/citylive/install.php en tu navegador

# 4. ¡Listo! Accede a http://localhost/citylive/
```

> 💡 **El instalador crea automáticamente la BD, tablas y datos de demo**

---

## 🛠️ Requisitos

| Requisito | Versión |
|-----------|---------|
| **PHP** | 8.0 o superior |
| **MySQL** | 5.7 o superior |
| **Servidor Web** | Apache (incluido en XAMPP) |
| **Navegador** | Chrome, Firefox, Edge (actualizado) |

**Opción recomendada:** Descargar [XAMPP](https://www.apachefriends.org/) (incluye todo lo necesario)

---

## 📦 Instalación detallada

### Paso 1: Preparar el entorno

1. **Descarga e instala XAMPP** desde [apachefriends.org](https://www.apachefriends.org/)
2. **Inicia los servicios**:
   - Abre XAMPP Control Panel
   - Pulsa `Start` en Apache
   - Pulsa `Start` en MySQL

### Paso 2: Obtener el código

```bash
git clone https://github.com/TU_USUARIO/citylive.git
```

O descarga el ZIP y descomprímelo.

### Paso 3: Colocar en XAMPP

Mueve o copia la carpeta `citylive/` dentro de `htdocs`:

**Windows:**
```
C:\xampp\htdocs\citylive\
```

**Mac:**
```
/Applications/XAMPP/htdocs/citylive/
```

**Linux:**
```
/opt/lampp/htdocs/citylive/
```

### Paso 4: Configurar base de datos (opcional)

Si usas credenciales diferentes a las de XAMPP por defecto, edita `config/db.php`:

```php
define('DB_HOST', 'localhost');      // servidor BD
define('DB_NAME', 'citylive');       // nombre BD
define('DB_USER', 'root');           // usuario (root en XAMPP)
define('DB_PASS', '');               // contraseña (vacía en XAMPP)
define('DB_PORT', 3306);             // puerto (3306 por defecto)
```

### Paso 5: Ejecutar instalador

Abre en el navegador:

```
http://localhost/citylive/install.php
```

El instalador automáticamente:
- ✅ Crea la base de datos `citylive`
- ✅ Genera todas las tablas
- ✅ Inserta usuarios de demo
- ✅ Carga datos de ejemplo

**⚠️ Importante:** Tras completar la instalación, **elimina `install.php`** por seguridad.

### Paso 6: ¡Accede a CityLive!

```
http://localhost/citylive/
```

---

## 👤 Cuentas de demostración

Tras ejecutar el instalador, dispones de **5 cuentas de demo** para explorar:

| Email | Contraseña | Plan | Descripción |
|-------|-----------|------|-------------|
| maria@citylive.app | demo1234 | 💎 Platinum | Usuario premium con tokens ilimitados |
| carlos@citylive.app | demo1234 | ⭐ Pro | Usuario con suscripción profesional |
| sara@citylive.app | demo1234 | ⭐ Pro | Más datos de ejemplo |
| alex@citylive.app | demo1234 | 🆓 Free | Usuario con plan gratuito |
| demo@citylive.app | demo1234 | 💎 Platinum | Usuario adicional platinum |

> 💡 **Todas comparten la contraseña `demo1234` para fácil acceso durante desarrollo**

---

## 📁 Estructura del proyecto

```
citylive/
├── 📋 Páginas principales
│   ├── index.php               # Página de login
│   ├── register.php            # Registro de nuevos usuarios
│   ├── dashboard.php           # Mapa principal (core)
│   ├── activity.php            # Detalle de actividad/publicación
│   ├── profile.php             # Perfil de usuario
│   ├── create.php              # Crear nueva publicación
│   ├── subscriptions.php       # Planes y suscripciones
│   ├── tokens.php              # Gestión y historial de tokens
│   ├── logout.php              # Cerrar sesión
│   └── install.php             # Instalador (⚠️ eliminar tras usar)
│
├── 📂 config/
│   └── db.php                  # Configuración BD y funciones helper
│
├── 📂 api/
│   └── publications.php        # API GeoJSON para marcadores del mapa
│
├── 📂 includes/
│   ├── header.php              # Sidebar + topbar compartido
│   └── footer.php              # Cierre HTML y scripts comunes
│
├── 🎨 css/
│   └── style.css               # Estilos globales (tema oscuro)
│
├── 📦 js/
│   ├── app.js                  # Lógica global y utilidades
│   └── map.js                  # Inicialización Leaflet + marcadores
│
├── 💾 sql/
│   └── schema.sql              # Esquema de la base de datos
│
└── 📚 trabajos/
    ├── README.md               # Este archivo
    └── Criterios-de-aceptación.md # Especificaciones del proyecto
```

---

## 🗄️ Esquema de la base de datos

| Tabla | Descripción |
|-------|-------------|
| `users` | Datos de usuarios (email, contraseña, plan actual, tokens disponibles, reputación) |
| `publications` | Incidencias, eventos y actividades (ubicación, descripción, autor, fecha) |
| `reviews` | Valoraciones y reseñas de publicaciones (puntuación, comentario, autor) |
| `token_transactions` | Historial de todas las transacciones de tokens (tipo, cantidad, fecha) |
| `subscriptions` | Plan activo de cada usuario (plan_id, fecha_inicio, fecha_renovación) |
| `followers` | Relaciones seguidor/seguido entre usuarios |
| `saves` | Publicaciones guardadas por usuarios (para favoritos) |

> 📊 Para ver el esquema completo, consulta [sql/schema.sql](sql/schema.sql)

---

## 🎨 Stack tecnológico

### Backend
- **PHP 8.2** — Lenguaje servidor y renderizado
- **MySQL 8.0** — Base de datos relacional
- **RESTful API** — Endpoints GeoJSON para el mapa

### Frontend
- **Leaflet.js 1.9** — Biblioteca de mapas interactivos (open source)
- **CartoDB Dark** — Tiles del mapa (sin API key requerida)
- **Font Awesome 6** — Iconografía profesional
- **Google Fonts (Inter)** — Tipografía moderna
- **Vanilla JavaScript** — Sin dependencias frontend complejas

### Características de código
- **Arquitectura modular** — Separación clara entre config, includes y páginas
- **Session management** — Sistema de sesiones PHP nativo
- **Validación en servidor** — Seguridad en backend

| Componente | Tecnología |
|-----------|-----------|
| Servidor | Apache |
| Lenguaje backend | PHP 8.2+ |
| Base de datos | MySQL 8.0+ |
| Mapas | Leaflet.js + CartoDB |
| UI/UX | HTML5, CSS3, Vanilla JS |
| Iconografía | Font Awesome 6 |
| Tipografía | Google Fonts |

---

## � Solución de problemas

### ❌ "Not Found" o página en blanco
**Causa:** La carpeta no está en la ubicación correcta de XAMPP.  
**Solución:** 
- Verifica que `citylive/` esté directamente dentro de `htdocs/`
- No debe estar anidada: ❌ `htdocs/proyectos/citylive/` → ✅ `htdocs/citylive/`

### ❌ Error de conexión a la base de datos
**Causa:** MySQL no está iniciado o credenciales incorrectas.  
**Solución:**
1. Abre XAMPP Control Panel y verifica que MySQL esté en `Running` (verde)
2. Comprueba las credenciales en `config/db.php`
3. Por defecto en XAMPP: usuario=`root`, contraseña=vacía

### ❌ El mapa no carga o muestra zona gris
**Causa:** Necesitas conexión a internet (los tiles son externos).  
**Solución:**
1. Verifica que tengas conexión a internet activa
2. Abre la consola del navegador (F12 → Consola) y busca errores
3. Comprueba que no bloques cartodb.com o leaflet

### ❌ Error al crear publicaciones o cambiar plan
**Causa:** Sesión expirada o usuario no autenticado.  
**Solución:**
1. Cierra sesión en `logout.php`
2. Inicia sesión nuevamente
3. Limpia cookies del navegador

### ❌ "install.php not found" pero no lo ejecuté
**Causa:** Ya ejecutaste el instalador y lo eliminaste (comportamiento correcto).  
**Solución:** 
- Esto es normal y esperado por seguridad
- Para reinstalar, recupera `install.php` del repositorio

### ✅ ¿Necesitas más ayuda?
- Consulta la consola del navegador (F12) para errores JavaScript
- Revisa los logs de Apache/MySQL en XAMPP
- Abre un issue en el repositorio

---

## � Guía de uso

### Para usuarios
1. **Regístrate** en la página de registro
2. **Inicia sesión** con tus credenciales
3. **Explora el mapa** y visualiza publicaciones de otros usuarios
4. **Crea contenido** haciendo clic en "Crear publicación"
5. **Selecciona ubicación** directamente en el mapa
6. **Interactúa** valorando y comentando publicaciones
7. **Mejora tu perfil** siguiendo otros usuarios
8. **Gestiona tokens** si tienes plan Pro o Platinum

### Para desarrolladores
Consulta [Criterios-de-aceptación.md](Criterios-de-aceptación.md) para:
- Especificaciones técnicas detalladas
- Casos de uso completos
- Mockups y diseño
- Criterios de aceptación

---

## 🔧 Desarrollo

### Estructura de archivos principales

**Frontend (Lado cliente)**
- `js/map.js` — Inicializa Leaflet y carga marcadores
- `js/app.js` — Lógica compartida y utilidades
- `css/style.css` — Estilos globales

**Backend (Lado servidor)**
- `config/db.php` — Conexión y funciones de BD
- `api/publications.php` — API GeoJSON para mapas
- `includes/header.php` — Componente compartido de navegación

**Base de datos**
- `sql/schema.sql` — Ejecuta para crear estructura BD

### Flujo de desarrollo típico

1. Modifica tu código PHP/JS
2. Recarga el navegador (Ctrl+F5 para limpiar cache)
3. Abre F12 → Consola para ver errores
4. Usa `install.php` para resetear BD si es necesario

---

## 📄 Licencia

Este proyecto está bajo licencia **MIT** — libre para uso educativo, personal y comercial.


