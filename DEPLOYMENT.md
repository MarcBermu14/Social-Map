# Guía de Deployment - CityLive a Producción

## 📋 Requisitos

- Servidor Linux/Windows con PHP 7.4+
- MySQL 5.7+ o MariaDB
- Acceso a panel de control (cPanel, Plesk, etc.) o SSH
- Dominio registrado

## 🚀 Opción 1: Hosting Compartido (Recomendado para comenzar)

### Proveedores sugeridos:
- **Hostinger** - Buena relación precio/rendimiento
- **Bluehost** - Soporte técnico confiable
- **SiteGround** - Premium, muy rápido
- **HostGator** - Económico y confiable

### Pasos:

1. **Contratar hosting**
   - Elegir plan con PHP 8+ y MySQL
   - Apuntar el dominio a los nameservers del hosting

2. **Conectar vía FTP/SFTP**
   ```
   Usar FileZilla u otro cliente FTP
   Host: ftp.tudominio.com
   Usuario: cpanel_user
   Contraseña: tu_contraseña
   Puerto: 21 (FTP) o 22 (SFTP)
   ```

3. **Subir archivos**
   - Eliminar contenido de `public_html/`
   - Subir todo el contenido de `citylive/` a `public_html/`
   - Estructura final:
     ```
     public_html/
     ├── index.php
     ├── register.php
     ├── dashboard.php
     ├── config/
     ├── css/
     ├── js/
     ├── sql/
     └── ...
     ```

4. **Crear Base de Datos**
   - Acceder a cPanel → MySQL Databases
   - Crear BD: `citylive_db` (o similar)
   - Crear usuario: `citylive_user`
   - Asignar todos los permisos
   - Importar `sql/schema.sql`

5. **Configurar variables de entorno**
   - Crear archivo `.env` en la raíz:
   ```
   DB_HOST=localhost
   DB_NAME=citylive_db
   DB_USER=citylive_user
   DB_PASS=tu_contraseña_mysql
   DB_CHAR=utf8mb4
   DB_PORT=3306
   
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=tu-email@gmail.com
   SMTP_PASS=tu-app-password
   SMTP_FROM=noreply@tudominio.com
   SMTP_FROM_NAME=CityLive
   
   APP_URL=https://tudominio.com
   ```

   - Crear `config/.env.php`:
   ```php
   <?php
   // .env.php - Cargar variables de entorno
   $envFile = __DIR__ . '/../.env';
   if (file_exists($envFile)) {
       $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
       foreach ($lines as $line) {
           if (strpos($line, '=') !== false && $line[0] !== '#') {
               list($key, $value) = explode('=', $line, 2);
               putenv(trim($key) . '=' . trim($value));
           }
       }
   }
   ?>
   ```

   - Incluir al inicio de `config/db.php`:
   ```php
   require_once __DIR__ . '/.env.php';
   ```

---

## 🚀 Opción 2: Servidor Dedicado / VPS

### Proveedores:
- **Linode**
- **DigitalOcean**
- **Vultr**
- **AWS Lightsail**

### Instalación con Docker (Recomendado):

1. **Crear `Dockerfile`:**
   ```dockerfile
   FROM php:8.1-apache
   
   RUN docker-php-ext-install mysqli pdo pdo_mysql
   RUN a2enmod rewrite
   
   COPY . /var/www/html/
   RUN chown -R www-data:www-data /var/www/html
   
   EXPOSE 80
   CMD ["apache2-foreground"]
   ```

2. **Crear `docker-compose.yml`:**
   ```yaml
   version: '3.8'
   services:
     web:
       build: .
       ports:
         - "80:80"
       environment:
         - DB_HOST=mysql
         - DB_USER=citylive_user
         - DB_PASS=secure_password
         - DB_NAME=citylive_db
       depends_on:
         - mysql
     
     mysql:
       image: mysql:8.0
       environment:
         - MYSQL_ROOT_PASSWORD=root
         - MYSQL_DATABASE=citylive_db
         - MYSQL_USER=citylive_user
         - MYSQL_PASSWORD=secure_password
       volumes:
         - mysql_data:/var/lib/mysql
         - ./sql/schema.sql:/docker-entrypoint-initdb.d/schema.sql
   
   volumes:
     mysql_data:
   ```

3. **Deploy:**
   ```bash
   docker-compose up -d
   ```

### Instalación Manual:

```bash
# SSH al servidor
ssh root@tu_servidor_ip

# Instalar dependencias
apt update
apt install -y apache2 php8.1 php8.1-mysql mysql-server git

# Clonar/subir código
cd /var/www/html
git clone https://github.com/tu-usuario/citylive.git
cd citylive

# Configurar permisos
chown -R www-data:www-data .
chmod -R 755 .

# Crear BD
mysql -u root -p < sql/schema.sql

# Habilitar mod_rewrite
a2enmod rewrite
systemctl restart apache2
```

---

## 🔐 Configuración de SSL (HTTPS)

### Con hosting compartido:
- cPanel generalmente lo hace automático
- O usar Let's Encrypt desde cPanel

### Con VPS/Docker:
```bash
# Instalar Certbot
apt install certbot python3-certbot-apache

# Generar certificado
certbot --apache -d tudominio.com

# Auto-renovación
systemctl enable certbot.timer
```

---

## 📧 Configurar Email (Recomendado: Gmail SMTP)

### Pasos:

1. **Crear cuenta Gmail** (o usar existente)
   
2. **Activar "Acceso de apps menos seguras":**
   - Ir a myaccount.google.com/security
   - Buscar "Contraseñas de aplicación"
   - Crear contraseña para "Mail"
   - Copiar la contraseña de 16 caracteres

3. **Agregar en `.env`:**
   ```
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=tu-email@gmail.com
   SMTP_PASS=xxxx xxxx xxxx xxxx
   ```

### Alternativa: SendGrid, Mailgun, etc.

---

## ✅ Checklist Final

- [ ] Dominio apuntando al servidor
- [ ] Base de datos creada e importada
- [ ] `.env` configurado correctamente
- [ ] SSL/HTTPS habilitado
- [ ] Email de verificación funcionando
- [ ] Prueba de registro/login completada
- [ ] Backups automáticos configurados
- [ ] Logs monitoreados
- [ ] Firewall/Seguridad configurada

---

## 🐛 Troubleshooting

### "Error: conexión a BD"
```php
// Verificar credenciales en config/db.php
// Verificar que BD está creada: SHOW DATABASES;
```

### "Error: email no enviado"
```php
// Verificar SMTP configurado
// En desarrollo, los emails se loguean en error_log
tail -f /var/log/apache2/error.log
```

### "Rutas no funcionan correctamente"
```
Asegúrate de:
- Habilitar mod_rewrite en Apache
- `.htaccess` está en la raíz
```

---

## 📞 Soporte

Para problemas específicos, contáctanos o crea un issue en GitHub.
