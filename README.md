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
