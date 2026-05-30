#!/bin/bash
# ═════════════════════════════════════════════════════════
# setup.sh - Script de Instalación Rápida para Linux/Mac
# Ejecutar: bash setup.sh
# ═════════════════════════════════════════════════════════

echo "🗺️  CityLive - Setup Rápido"
echo "═════════════════════════════════════════════════════════"
echo ""

# 1. Crear .env desde .env.example
if [ ! -f .env ]; then
  echo "📝 Creando archivo .env..."
  cp .env.example .env
  echo "✅ Archivo .env creado"
  echo ""
  echo "⚠️  Edita .env con tus credenciales:"
  echo "  - DB_PASS: Contraseña de MySQL"
  echo "  - SMTP_USER: Tu email Gmail"
  echo "  - SMTP_PASS: Contraseña de aplicación de Gmail"
  echo ""
else
  echo "✅ .env ya existe"
fi

# 2. Verificar que exista el schema
if [ ! -f sql/schema.sql ]; then
  echo "❌ Error: No se encuentra sql/schema.sql"
  exit 1
fi

echo "📊 Importando Base de Datos..."
echo "Ejecuta manualmente en MySQL:"
echo ""
echo "  mysql -u root citylive < sql/schema.sql"
echo ""
echo "O si necesitas crear la BD primero:"
echo "  mysql -u root < sql/schema.sql"
echo ""

# 3. Crear directorios si no existen
mkdir -p uploads logs

echo "✅ Directorios creados"
echo ""
echo "═════════════════════════════════════════════════════════"
echo "🚀 ¡Listo para iniciar!"
echo ""
echo "Próximos pasos:"
echo "1. Iniciar Apache + MySQL en XAMPP"
echo "2. Abrir http://localhost/citylive/health-check.php"
echo "3. Ir a http://localhost/citylive/register.php"
echo "4. ¡Crear una cuenta!"
echo ""
echo "Documentación:"
echo "  - SETUP.md: Guía completa"
echo "  - DEPLOYMENT.md: Cómo publicar en internet"
echo "  - CHANGES.md: Detalle de cambios"
echo ""
