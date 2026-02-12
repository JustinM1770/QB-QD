# ✅ Checklist de Migración a VPS
## QuickBite - Lista de Verificación Completa

---

## 📋 Pre-Migración (En tu Mac)

### Preparación Local
- [ ] ✅ Todas las funcionalidades funcionan en localhost
- [ ] ✅ Login/Registro de usuarios funciona
- [ ] ✅ Carrito agrega múltiples productos
- [ ] ✅ Wallet API responde correctamente
- [ ] ✅ AI de menús procesa imágenes
- [ ] ✅ Sin errores en logs locales

### Exportación
- [ ] ✅ Base de datos exportada (`php export_database.php`)
- [ ] ✅ Archivo SQL verificado (tiene datos)
- [ ] ✅ Proyecto comprimido (`quickbite_produccion.tar.gz`)
- [ ] ✅ Archivo .env respaldado
- [ ] ✅ Credenciales de API anotadas (Gemini API Key)

---

## 🖥️ Configuración del VPS

### Acceso al Servidor
- [ ] ✅ Conexión SSH establecida
- [ ] ✅ Acceso root o sudo disponible
- [ ] ✅ IP del VPS anotada
- [ ] ✅ Dominio apuntando al VPS (DNS configurado)

### Instalación de Software
- [ ] ✅ Sistema actualizado (`apt update && upgrade`)
- [ ] ✅ Apache instalado y corriendo
- [ ] ✅ MySQL instalado y corriendo
- [ ] ✅ PHP 8.1+ instalado
- [ ] ✅ Extensiones PHP necesarias:
  - [ ] php-mysql
  - [ ] php-gd (para imágenes)
  - [ ] php-curl (para APIs)
  - [ ] php-mbstring
  - [ ] php-xml
  - [ ] php-json

### Verificación de Software
```bash
# Copia y pega estos comandos para verificar
apache2 -v    # Debe mostrar versión 2.4+
mysql --version    # Debe mostrar versión 8.0+
php -v    # Debe mostrar versión 8.1+
php -m | grep -E 'gd|curl|pdo_mysql|json'    # Debe mostrar las 4 extensiones
```

- [ ] ✅ Todos los comandos anteriores funcionan

---

## 📦 Transferencia de Archivos

### Subida al VPS
- [ ] ✅ Archivo `quickbite_produccion.tar.gz` transferido
- [ ] ✅ Archivo SQL transferido
- [ ] ✅ Archivos descomprimidos en `/var/www/quickbite`
- [ ] ✅ Permisos configurados:
  ```bash
  sudo chown -R www-data:www-data /var/www/quickbite
  sudo chmod -R 755 /var/www/quickbite
  sudo chmod 600 /var/www/quickbite/.env
  ```

### Estructura de Directorios
- [ ] ✅ `/var/www/quickbite/` existe
- [ ] ✅ `/var/www/quickbite/config/` existe
- [ ] ✅ `/var/www/quickbite/assets/` existe
- [ ] ✅ `/var/www/quickbite/admin/` existe
- [ ] ✅ `/var/www/quickbite/api/` existe
- [ ] ✅ `/var/www/quickbite/logs/` existe con permisos 775

---

## 🗄️ Base de Datos

### Configuración MySQL
- [ ] ✅ Base de datos `app_delivery` creada
- [ ] ✅ Usuario `quickbite_user` creado
- [ ] ✅ Permisos otorgados al usuario
- [ ] ✅ SQL importado sin errores:
  ```bash
  mysql -u root -p app_delivery < /tmp/database_export_*.sql
  ```

### Verificación de Datos
- [ ] ✅ Tablas creadas correctamente:
  ```bash
  mysql -u root -p -e "USE app_delivery; SHOW TABLES;"
  ```
- [ ] ✅ Datos de usuarios presentes
- [ ] ✅ Datos de negocios presentes
- [ ] ✅ Datos de productos presentes

### Archivo .env
- [ ] ✅ Credenciales actualizadas en `/var/www/quickbite/.env`:
  ```env
  DB_HOST=localhost
  DB_NAME=app_delivery
  DB_USER=quickbite_user
  DB_PASS=tu_password_seguro
  ```

---

## 🌐 Configuración Web

### Apache VirtualHost
- [ ] ✅ Archivo `/etc/apache2/sites-available/quickbite.conf` creado
- [ ] ✅ DocumentRoot apunta a `/var/www/quickbite`
- [ ] ✅ ServerName configurado con tu dominio
- [ ] ✅ Directivas de seguridad añadidas (.env protegido)
- [ ] ✅ Sitio habilitado: `sudo a2ensite quickbite.conf`
- [ ] ✅ Sitio default deshabilitado: `sudo a2dissite 000-default.conf`
- [ ] ✅ Módulos habilitados:
  ```bash
  sudo a2enmod rewrite
  sudo a2enmod ssl
  sudo a2enmod headers
  ```
- [ ] ✅ Apache reiniciado sin errores

### Prueba Inicial (HTTP)
- [ ] ✅ `http://tu_dominio.com` carga
- [ ] ✅ No muestra errores 404 o 500
- [ ] ✅ Logs de Apache limpios:
  ```bash
  sudo tail -f /var/log/apache2/quickbite_error.log
  ```

---

## 🔒 SSL/HTTPS

### Certbot
- [ ] ✅ Certbot instalado
- [ ] ✅ Certificado SSL obtenido:
  ```bash
  sudo certbot --apache -d tudominio.com -d www.tudominio.com
  ```
- [ ] ✅ Redirección HTTP → HTTPS configurada
- [ ] ✅ Auto-renovación configurada:
  ```bash
  sudo systemctl status certbot.timer
  ```

### Verificación SSL
- [ ] ✅ `https://tudominio.com` carga con candado verde
- [ ] ✅ Sin advertencias de certificado en navegador
- [ ] ✅ Calificación SSL A+ en https://www.ssllabs.com/ssltest/

---

## 🔐 Seguridad

### Firewall
- [ ] ✅ UFW habilitado
- [ ] ✅ Puerto 22 (SSH) abierto
- [ ] ✅ Puerto 80 (HTTP) abierto
- [ ] ✅ Puerto 443 (HTTPS) abierto
- [ ] ✅ Otros puertos bloqueados

### Permisos de Archivos
- [ ] ✅ `.env` tiene permisos 600
- [ ] ✅ `logs/` tiene permisos 775 y es propiedad de www-data
- [ ] ✅ `assets/img/restaurants/` tiene permisos 775
- [ ] ✅ Otros archivos tienen permisos 644
- [ ] ✅ Directorios tienen permisos 755

### PHP Production Settings
- [ ] ✅ `display_errors = Off` en `/etc/php/8.1/apache2/php.ini`
- [ ] ✅ `log_errors = On`
- [ ] ✅ `error_log` configurado
- [ ] ✅ Límites de subida configurados:
  - upload_max_filesize = 10M
  - post_max_size = 10M
  - max_execution_time = 300

---

## ✅ Funcionalidades - Testing

### Páginas Públicas
- [ ] ✅ Home page carga: `https://tudominio.com`
- [ ] ✅ Listado de negocios carga
- [ ] ✅ Página de negocio individual carga
- [ ] ✅ Imágenes de productos se muestran
- [ ] ✅ CSS y JS cargan correctamente (sin errores en consola)

### Registro y Login
- [ ] ✅ Registro de nuevo usuario funciona
- [ ] ✅ Login con credenciales correctas funciona
- [ ] ✅ Sesión persiste después de login
- [ ] ✅ Logout funciona
- [ ] ✅ Redirecciones post-login funcionan

### Carrito de Compras
- [ ] ✅ Agregar producto al carrito funciona
- [ ] ✅ Agregar 2+ productos funciona
- [ ] ✅ Eliminar producto del carrito funciona
- [ ] ✅ Cantidad de productos se actualiza
- [ ] ✅ Total se calcula correctamente

### Registro de Negocios
- [ ] ✅ Formulario de registro carga
- [ ] ✅ Subida de logo funciona
- [ ] ✅ Logo se redimensiona a 500x500px
- [ ] ✅ Subida de portada funciona
- [ ] ✅ Portada se redimensiona a 1200x400px
- [ ] ✅ Imágenes se guardan en `assets/img/restaurants/`

### AI de Menús
- [ ] ✅ Campo de subida de imagen del menú aparece
- [ ] ✅ Subir imagen del menú funciona
- [ ] ✅ Gemini API procesa la imagen (10-30 segundos)
- [ ] ✅ Productos extraídos se insertan en BD
- [ ] ✅ Mensaje de éxito se muestra con cantidad de productos
- [ ] ✅ Productos aparecen en el panel del negocio

### Wallet API
- [ ] ✅ Endpoint de balance funciona:
  ```bash
  curl https://tudominio.com/api/wallet_api.php?action=balance
  ```
- [ ] ✅ Responde con JSON válido
- [ ] ✅ No muestra errores PHP
- [ ] ✅ Datos de wallet se muestran correctamente

### Panel de Administración
- [ ] ✅ Login de admin funciona
- [ ] ✅ Dashboard carga
- [ ] ✅ Gestión de productos funciona
- [ ] ✅ Gestión de pedidos funciona
- [ ] ✅ Estadísticas se muestran

---

## 📊 Performance y Optimización

### Velocidad de Carga
- [ ] ✅ Home page carga en < 3 segundos
- [ ] ✅ Imágenes optimizadas (< 500KB cada una)
- [ ] ✅ Compresión Gzip habilitada
- [ ] ✅ Cache de navegador configurado

### Base de Datos
- [ ] ✅ Queries responden rápido (< 100ms)
- [ ] ✅ Índices en tablas principales
- [ ] ✅ Buffer pool de MySQL configurado

### Recursos del Servidor
- [ ] ✅ Uso de CPU < 50%
- [ ] ✅ Uso de RAM < 70%
- [ ] ✅ Espacio en disco > 10GB libre
- [ ] ✅ Verificar con:
  ```bash
  top
  df -h
  free -h
  ```

---

## 📱 Testing Cross-Browser

### Navegadores de Escritorio
- [ ] ✅ Chrome/Chromium
- [ ] ✅ Firefox
- [ ] ✅ Safari (si tienes Mac)
- [ ] ✅ Edge

### Dispositivos Móviles
- [ ] ✅ iPhone Safari
- [ ] ✅ Android Chrome
- [ ] ✅ Responsive design funciona
- [ ] ✅ Touch events funcionan

---

## 📝 Logs y Monitoreo

### Revisar Logs
- [ ] ✅ Apache error log limpio:
  ```bash
  sudo tail -100 /var/log/apache2/quickbite_error.log
  ```
- [ ] ✅ PHP error log limpio:
  ```bash
  sudo tail -100 /var/log/php_errors.log
  ```
- [ ] ✅ App logs limpios:
  ```bash
  tail -100 /var/www/quickbite/logs/php_errors.log
  ```
- [ ] ✅ MySQL error log limpio:
  ```bash
  sudo tail -100 /var/log/mysql/error.log
  ```

### Configurar Monitoreo
- [ ] ✅ Uptime monitor configurado (uptimerobot.com)
- [ ] ✅ Email alerts configurados
- [ ] ✅ Backups automáticos programados:
  ```bash
  crontab -e
  # Agregar backup diario
  0 2 * * * mysqldump -u quickbite_user -p app_delivery > /backups/db_$(date +\%Y\%m\%d).sql
  ```

---

## 🔄 Backups

### Backup de Base de Datos
- [ ] ✅ Script de backup automático creado
- [ ] ✅ Cronjob programado (diario a las 2 AM)
- [ ] ✅ Backup manual probado:
  ```bash
  mysqldump -u quickbite_user -p app_delivery > backup_manual.sql
  ```
- [ ] ✅ Restauración probada (en ambiente de prueba)

### Backup de Archivos
- [ ] ✅ Script de backup de archivos creado:
  ```bash
  tar -czf /backups/files_$(date +%Y%m%d).tar.gz /var/www/quickbite
  ```
- [ ] ✅ Cronjob programado (semanal)
- [ ] ✅ Backups antiguos se limpian automáticamente

---

## 📧 Comunicación

### Emails
- [ ] ✅ Envío de emails funciona (si aplica)
- [ ] ✅ SMTP configurado correctamente
- [ ] ✅ Email de confirmación de registro se envía
- [ ] ✅ Email de confirmación de pedido se envía

### Notificaciones
- [ ] ✅ Notificaciones push funcionan (si aplica)
- [ ] ✅ WebSocket funciona (si aplica)

---

## 🎯 Post-Migración Inmediata

### Primeras 24 Horas
- [ ] ✅ Monitorear logs constantemente
- [ ] ✅ Verificar que usuarios puedan registrarse
- [ ] ✅ Verificar que pedidos se procesen
- [ ] ✅ Responder a reportes de errores rápidamente

### Primera Semana
- [ ] ✅ Analizar métricas de uso
- [ ] ✅ Optimizar queries lentas
- [ ] ✅ Revisar consumo de recursos
- [ ] ✅ Ajustar configuraciones según necesidad

### Primer Mes
- [ ] ✅ Revisar backups automáticos
- [ ] ✅ Actualizar dependencias de seguridad
- [ ] ✅ Analizar feedback de usuarios
- [ ] ✅ Planear mejoras según uso real

---

## 🆘 Contactos de Emergencia

### Información Importante
```
Dominio: ___________________________
IP del VPS: ___________________________
Usuario SSH: ___________________________
Puerto SSH: ___________________________

MySQL User: quickbite_user
MySQL Pass: ___________________________
MySQL Database: app_delivery

Gemini API Key: ___________________________

Proveedor VPS: ___________________________
Soporte VPS: ___________________________
```

### Comandos de Emergencia

**Reiniciar servicios:**
```bash
sudo systemctl restart apache2
sudo systemctl restart mysql
```

**Ver status:**
```bash
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status certbot.timer
```

**Espacio en disco:**
```bash
df -h
du -sh /var/www/quickbite
du -sh /var/lib/mysql
```

**Procesos:**
```bash
top
htop  # si está instalado
ps aux | grep apache
ps aux | grep mysql
```

---

## ✅ Checklist Final

### Antes de Declarar "Migración Completa"
- [ ] ✅ TODAS las casillas anteriores marcadas
- [ ] ✅ Al menos 3 usuarios de prueba han usado el sistema
- [ ] ✅ Al menos 5 pedidos de prueba procesados
- [ ] ✅ Sin errores en logs en las últimas 4 horas
- [ ] ✅ Backups automáticos verificados
- [ ] ✅ Monitoreo activo
- [ ] ✅ Documentación actualizada
- [ ] ✅ Equipo notificado de la migración

---

## 🎉 ¡Migración Completa!

**Fecha de migración:** _______________________

**Firma:** _______________________

---

## 📚 Referencias

- Guía de migración: `MIGRACION_VPS.md`
- Script de exportación: `export_database.php`
- Reporte de pruebas: `/tmp/reporte_pruebas_registro.txt`

**Notas adicionales:**
_________________________________________________________
_________________________________________________________
_________________________________________________________
