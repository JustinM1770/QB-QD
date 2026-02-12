# 🚀 Guía Completa de Migración a VPS
## QuickBite - De Localhost (XAMPP macOS) a Producción

---

## 📋 Tabla de Contenidos
1. [Preparación Local](#1-preparación-local)
2. [Exportar Base de Datos](#2-exportar-base-de-datos)
3. [Preparar Archivos](#3-preparar-archivos)
4. [Configurar VPS](#4-configurar-vps)
5. [Transferir Archivos](#5-transferir-archivos)
6. [Configurar Servidor Web](#6-configurar-servidor-web)
7. [Configurar SSL](#7-configurar-ssl)
8. [Seguridad y Optimización](#8-seguridad-y-optimización)
9. [Post-Migración](#9-post-migración)

---

## 1. Preparación Local

### 1.1 Verificar que todo funcione en localhost
```bash
# Abre tu proyecto en el navegador
# http://localhost/QuickBite/proyecto_quickbite
```

✅ Verifica:
- Login de usuarios funciona
- Registro de negocios funciona
- Carrito agrega productos correctamente
- Wallet API responde
- AI de menús procesa imágenes

### 1.2 Actualizar configuración para producción

**Edita `.env`:**
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/QuickBite/proyecto_quickbite
nano .env
```

Cambia las URLs de desarrollo a producción:
```env
# Base URLs - CAMBIAR A TU DOMINIO
BASE_URL=https://tudominio.com
API_URL=https://tudominio.com/api

# Database - SERÁN DIFERENTES EN EL VPS
DB_HOST=localhost
DB_NAME=app_delivery
DB_USER=tu_usuario_vps
DB_PASS=tu_password_seguro_vps

# APIs - MANTENER IGUAL
GEMINI_API_KEY=tu_api_key_actual
```

---

## 2. Exportar Base de Datos

### 2.1 Usar el script automático

He creado un script que exportará tu BD automáticamente:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/QuickBite/proyecto_quickbite
php export_database.php
```

Esto creará: `database_export_YYYYMMDD_HHMMSS.sql`

### 2.2 Exportar manualmente (alternativa)

```bash
# Desde Terminal en macOS
/Applications/XAMPP/xamppfiles/bin/mysql -u root -p app_delivery > database_backup.sql

# O usando mysqldump
/Applications/XAMPP/xamppfiles/bin/mysqldump -u root -p app_delivery --single-transaction --quick > database_backup.sql
```

### 2.3 Verificar el archivo exportado

```bash
# Ver las primeras líneas
head -n 50 database_backup.sql

# Ver el tamaño
ls -lh database_backup.sql
```

---

## 3. Preparar Archivos

### 3.1 Crear archivo .tar.gz con todo el proyecto

```bash
# Ir al directorio padre
cd /Applications/XAMPP/xamppfiles/htdocs/QuickBite

# Comprimir todo (excluir archivos innecesarios)
tar -czf quickbite_produccion.tar.gz \
    --exclude='proyecto_quickbite/.git' \
    --exclude='proyecto_quickbite/node_modules' \
    --exclude='proyecto_quickbite/logs/*.log' \
    --exclude='proyecto_quickbite/.DS_Store' \
    proyecto_quickbite/

# Ver el tamaño del archivo
ls -lh quickbite_produccion.tar.gz
```

### 3.2 Lista de archivos críticos a verificar

✅ Asegúrate que estos estén incluidos:
- `/config/database.php`
- `/.env` (LO EDITARÁS EN EL VPS)
- `/admin/gemini_menu_parser.php`
- `/api/wallet_api.php`
- `/assets/` (todos los archivos estáticos)
- Todos los `.php` principales

---

## 4. Configurar VPS

### 4.1 Conectarse al VPS

```bash
# Desde tu Mac
ssh root@tu_ip_del_vps
# O si tienes usuario diferente:
ssh usuario@tu_ip_del_vps
```

### 4.2 Actualizar sistema

```bash
# Ubuntu/Debian
sudo apt update
sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

### 4.3 Instalar LAMP Stack

**Para Ubuntu 20.04/22.04:**

```bash
# 1. Apache
sudo apt install apache2 -y
sudo systemctl enable apache2
sudo systemctl start apache2

# 2. MySQL
sudo apt install mysql-server -y
sudo systemctl enable mysql
sudo systemctl start mysql

# Configurar MySQL
sudo mysql_secure_installation
# Responde: YES a todo, elige contraseña segura

# 3. PHP 8.1+ con extensiones necesarias
sudo apt install php8.1 php8.1-fpm php8.1-mysql php8.1-gd php8.1-curl php8.1-mbstring php8.1-xml php8.1-zip php8.1-json -y

# 4. Habilitar módulos de Apache
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo systemctl restart apache2
```

### 4.4 Verificar instalación

```bash
# Verificar versiones
apache2 -v          # Debería ser 2.4+
mysql --version     # Debería ser 8.0+
php -v              # Debería ser 8.1+

# Verificar extensiones PHP críticas
php -m | grep -E 'gd|curl|pdo_mysql|json'
# Deberías ver: gd, curl, pdo_mysql, json
```

---

## 5. Transferir Archivos

### 5.1 Transferir con SCP (recomendado)

**Desde tu Mac:**

```bash
# Transferir el archivo comprimido
scp /Applications/XAMPP/xamppfiles/htdocs/QuickBite/quickbite_produccion.tar.gz usuario@tu_ip_vps:/tmp/

# Transferir el SQL
scp /Applications/XAMPP/xamppfiles/htdocs/QuickBite/proyecto_quickbite/database_backup.sql usuario@tu_ip_vps:/tmp/
```

### 5.2 Descomprimir en el VPS

**En el VPS:**

```bash
# Crear directorio del proyecto
sudo mkdir -p /var/www/quickbite
sudo chown -R $USER:$USER /var/www/quickbite

# Descomprimir
cd /var/www/quickbite
tar -xzf /tmp/quickbite_produccion.tar.gz --strip-components=1

# Verificar archivos
ls -la
```

### 5.3 Configurar permisos

```bash
# Permisos para archivos
sudo find /var/www/quickbite -type f -exec chmod 644 {} \;
sudo find /var/www/quickbite -type d -exec chmod 755 {} \;

# Permisos especiales para uploads y logs
sudo chown -R www-data:www-data /var/www/quickbite/assets/img/restaurants/
sudo chown -R www-data:www-data /var/www/quickbite/logs/
sudo chmod -R 775 /var/www/quickbite/assets/img/restaurants/
sudo chmod -R 775 /var/www/quickbite/logs/
```

---

## 6. Configurar Servidor Web

### 6.1 Importar Base de Datos

```bash
# Conectarse a MySQL
sudo mysql -u root -p

# Crear base de datos y usuario
CREATE DATABASE app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'quickbite_user'@'localhost' IDENTIFIED BY 'password_muy_seguro_123';
GRANT ALL PRIVILEGES ON app_delivery.* TO 'quickbite_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Importar el SQL
sudo mysql -u root -p app_delivery < /tmp/database_backup.sql

# Verificar
sudo mysql -u root -p -e "USE app_delivery; SHOW TABLES;"
```

### 6.2 Configurar Apache VirtualHost

```bash
sudo nano /etc/apache2/sites-available/quickbite.conf
```

**Pega esta configuración:**

```apache
<VirtualHost *:80>
    ServerName tudominio.com
    ServerAlias www.tudominio.com
    ServerAdmin webmaster@tudominio.com

    DocumentRoot /var/www/quickbite

    <Directory /var/www/quickbite>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Proteger archivos sensibles
    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>

    <Directory /var/www/quickbite/logs>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/quickbite_error.log
    CustomLog ${APACHE_LOG_DIR}/quickbite_access.log combined
</VirtualHost>
```

**Activar sitio:**

```bash
# Deshabilitar sitio default
sudo a2dissite 000-default.conf

# Habilitar tu sitio
sudo a2ensite quickbite.conf

# Verificar configuración
sudo apache2ctl configtest

# Reiniciar Apache
sudo systemctl restart apache2
```

### 6.3 Actualizar archivo .env en el VPS

```bash
cd /var/www/quickbite
nano .env
```

**Actualiza con las credenciales del VPS:**

```env
DB_HOST=localhost
DB_NAME=app_delivery
DB_USER=quickbite_user
DB_PASS=password_muy_seguro_123

BASE_URL=https://tudominio.com
API_URL=https://tudominio.com/api

# APIs (mantener igual)
GEMINI_API_KEY=tu_api_key_actual
```

---

## 7. Configurar SSL

### 7.1 Instalar Certbot

```bash
# Ubuntu
sudo apt install certbot python3-certbot-apache -y
```

### 7.2 Obtener certificado SSL GRATIS

```bash
# Esto configurará SSL automáticamente
sudo certbot --apache -d tudominio.com -d www.tudominio.com

# Responde:
# Email: tu_email@example.com
# Terms: A (Agree)
# Redirect: 2 (Redirect HTTP to HTTPS)
```

### 7.3 Configurar renovación automática

```bash
# Probar renovación
sudo certbot renew --dry-run

# Certbot ya configuró un cron job automático
# Verifica:
sudo systemctl status certbot.timer
```

### 7.4 Verificar SSL

Abre en tu navegador:
- `https://tudominio.com` ✅ Debería mostrar candado verde
- Usa: https://www.ssllabs.com/ssltest/ para verificar calificación

---

## 8. Seguridad y Optimización

### 8.1 Configurar Firewall

```bash
# UFW (Ubuntu Firewall)
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
sudo ufw status
```

### 8.2 Proteger .env y archivos sensibles

```bash
# Ya lo hicimos en permisos, pero verifica:
sudo chmod 600 /var/www/quickbite/.env
sudo chown www-data:www-data /var/www/quickbite/.env
```

### 8.3 Configurar PHP para producción

```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

**Cambia estas líneas:**

```ini
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
max_execution_time = 300
```

**Reinicia Apache:**

```bash
sudo systemctl restart apache2
```

### 8.4 Optimizar MySQL

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

**Agrega al final:**

```ini
[mysqld]
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 32M
query_cache_type = 1
```

**Reinicia MySQL:**

```bash
sudo systemctl restart mysql
```

### 8.5 Habilitar compresión Gzip

```bash
sudo nano /etc/apache2/mods-available/deflate.conf
```

**Agrega:**

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

```bash
sudo a2enmod deflate
sudo systemctl restart apache2
```

---

## 9. Post-Migración

### 9.1 Checklist de verificación

Abre `https://tudominio.com` y verifica:

- [ ] ✅ El sitio carga correctamente
- [ ] ✅ Login funciona
- [ ] ✅ Registro de usuarios funciona
- [ ] ✅ Registro de negocios funciona
- [ ] ✅ Subida de imágenes funciona (logo, portada)
- [ ] ✅ AI de menús procesa imágenes
- [ ] ✅ Carrito agrega productos
- [ ] ✅ Wallet API responde (prueba: `/api/wallet_api.php?action=balance`)
- [ ] ✅ Imágenes se muestran correctamente
- [ ] ✅ SSL funciona (candado verde)
- [ ] ✅ No hay errores en consola del navegador

### 9.2 Probar endpoints críticos

```bash
# Desde tu Mac o el VPS
curl https://tudominio.com
curl https://tudominio.com/api/wallet_api.php?action=balance
```

### 9.3 Revisar logs

**En el VPS:**

```bash
# Logs de Apache
sudo tail -f /var/log/apache2/quickbite_error.log

# Logs de PHP
sudo tail -f /var/log/php_errors.log

# Logs de la app
tail -f /var/www/quickbite/logs/php_errors.log
```

### 9.4 Monitoreo

**Configurar monitoreo básico con uptime:**

```bash
# Instalar uptimerobot o similar (gratis)
# https://uptimerobot.com

# O crear un cronjob simple de monitoreo:
crontab -e
```

**Agrega:**

```bash
*/5 * * * * curl -s https://tudominio.com > /dev/null || echo "Site DOWN" | mail -s "QuickBite Alert" tu_email@example.com
```

---

## 📊 Resumen de Comandos Rápidos

### En tu Mac (localhost):

```bash
# 1. Exportar BD
cd /Applications/XAMPP/xamppfiles/htdocs/QuickBite/proyecto_quickbite
php export_database.php

# 2. Comprimir proyecto
cd ..
tar -czf quickbite_produccion.tar.gz proyecto_quickbite/

# 3. Transferir a VPS
scp quickbite_produccion.tar.gz usuario@vps_ip:/tmp/
scp proyecto_quickbite/database_export_*.sql usuario@vps_ip:/tmp/
```

### En el VPS:

```bash
# 1. Instalar LAMP
sudo apt update && sudo apt install apache2 mysql-server php8.1 php8.1-{mysql,gd,curl,mbstring,xml} -y

# 2. Descomprimir proyecto
sudo mkdir -p /var/www/quickbite
cd /var/www/quickbite
sudo tar -xzf /tmp/quickbite_produccion.tar.gz --strip-components=1

# 3. Importar BD
sudo mysql -u root -p -e "CREATE DATABASE app_delivery; CREATE USER 'quickbite_user'@'localhost' IDENTIFIED BY 'password123'; GRANT ALL ON app_delivery.* TO 'quickbite_user'@'localhost';"
sudo mysql -u root -p app_delivery < /tmp/database_export_*.sql

# 4. Configurar permisos
sudo chown -R www-data:www-data /var/www/quickbite
sudo chmod -R 755 /var/www/quickbite
sudo chmod 600 /var/www/quickbite/.env

# 5. Configurar Apache
sudo a2ensite quickbite.conf
sudo a2enmod rewrite ssl
sudo systemctl restart apache2

# 6. SSL
sudo certbot --apache -d tudominio.com
```

---

## 🆘 Troubleshooting

### Problema: "500 Internal Server Error"

```bash
# Ver logs
sudo tail -f /var/log/apache2/quickbite_error.log

# Verificar permisos
ls -la /var/www/quickbite
sudo chown -R www-data:www-data /var/www/quickbite
```

### Problema: "Database connection failed"

```bash
# Verificar credenciales en .env
cat /var/www/quickbite/.env

# Probar conexión manual
mysql -u quickbite_user -p app_delivery
```

### Problema: "Images not loading"

```bash
# Verificar permisos de uploads
sudo chmod -R 775 /var/www/quickbite/assets/img/restaurants/
sudo chown -R www-data:www-data /var/www/quickbite/assets/img/
```

### Problema: "AI menu parser not working"

```bash
# Verificar extensiones PHP
php -m | grep -E 'gd|curl'

# Verificar API key en .env
grep GEMINI_API_KEY /var/www/quickbite/.env
```

---

## 📞 Contacto y Soporte

Si encuentras problemas:
1. Revisa logs: `/var/log/apache2/quickbite_error.log`
2. Verifica configuración: `sudo apache2ctl configtest`
3. Revisa permisos: `ls -la /var/www/quickbite`

---

**✅ ¡Migración completada!**

Tu proyecto QuickBite ahora está en producción con:
- ✅ SSL/HTTPS configurado
- ✅ Base de datos migrada
- ✅ Permisos correctos
- ✅ Seguridad hardening aplicada
- ✅ Optimizaciones de performance
