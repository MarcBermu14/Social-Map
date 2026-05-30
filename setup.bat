@echo off
REM ═════════════════════════════════════════════════════════
REM setup.bat - Script de Instalación Rápida para Windows
REM Ejecutar: setup.bat
REM ═════════════════════════════════════════════════════════

echo.
echo 🗺️  CityLive - Setup Rápido (Windows)
echo ═════════════════════════════════════════════════════════
echo.

REM 1. Crear .env desde .env.example
if not exist .env (
  echo 📝 Creando archivo .env...
  copy .env.example .env
  echo ✅ Archivo .env creado
  echo.
  echo ⚠️  ABRE .env Y CONFIGURA:
  echo  - DB_PASS: Tu contraseña de MySQL
  echo  - SMTP_USER: Tu email Gmail
  echo  - SMTP_PASS: Contraseña de aplicación de Gmail
  echo.
) else (
  echo ✅ .env ya existe
)

REM 2. Verificar que exista el schema
if not exist sql\schema.sql (
  echo ❌ Error: No se encuentra sql\schema.sql
  pause
  exit /b 1
)

echo 📊 Base de Datos
echo ═════════════════════════════════════════════════════════
echo.
echo PASOS MANUALES:
echo 1. Abre phpMyAdmin: http://localhost/phpmyadmin
echo 2. Selecciona la BD "citylive"
echo 3. Haz click en "SQL"
echo 4. Abre el archivo sql\schema.sql
echo 5. Copia y pega todo el contenido
echo 6. Haz click en "Ejecutar"
echo.

REM 3. Crear directorios si no existen
if not exist uploads mkdir uploads
if not exist logs mkdir logs

echo ✅ Directorios creados
echo.
echo ═════════════════════════════════════════════════════════
echo 🚀 ¡Listo para iniciar!
echo ═════════════════════════════════════════════════════════
echo.
echo PRÓXIMOS PASOS:
echo 1. ▶️  Abre XAMPP Control Panel
echo 2. ▶️  Haz click en "Start" para Apache y MySQL
echo 3. ▶️  Abre: http://localhost/citylive/health-check.php
echo 4. ▶️  Ir a: http://localhost/citylive/register.php
echo 5. ▶️  ¡Crea una cuenta!
echo.
echo DOCUMENTACIÓN:
echo  📖 SETUP.md - Guía completa
echo  📖 DEPLOYMENT.md - Cómo publicar en internet
echo  📖 CHANGES.md - Detalle de cambios
echo.
pause
