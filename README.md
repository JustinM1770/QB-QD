# QuickBite

**Plataforma de delivery de comida para México** - Similar a Uber Eats / Rappi

[![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-Proprietary-red)]()

---

## Descripción

QuickBite es una plataforma completa de delivery de alimentos que conecta restaurantes, repartidores y clientes. Incluye:

- **App Cliente**: Navegación de restaurantes, carrito, checkout, seguimiento en tiempo real
- **Panel Negocios**: Gestión de menú, pedidos, estadísticas, wallet
- **Panel Repartidores**: Pedidos disponibles, navegación GPS, ganancias
- **Panel CEO**: Administración general, validación de repartidores, reportes
- **Bot WhatsApp**: Notificaciones automáticas de pedidos

---

## Stack Tecnológico

| Categoría | Tecnología |
|-----------|------------|
| **Backend** | PHP 8.x (PDO, OOP) |
| **Base de datos** | MySQL 8.0 |
| **Frontend** | Bootstrap 5.3, JavaScript (Vanilla + jQuery) |
| **Pagos** | Stripe, MercadoPago |
| **Mapas** | Mapbox, Google Maps |
| **Notificaciones** | WhatsApp (Baileys), Push Notifications, Email (PHPMailer) |
| **IA** | Google Gemini 2.0 Flash (análisis de menús) |
| **PWA** | Service Worker, Manifest, Offline support |
| **Servidor** | Apache 2.4 / Nginx + PHP-FPM |

---

## Requisitos del Sistema

- **PHP** >= 8.0 con extensiones: pdo_mysql, curl, mbstring, json, gd
- **MySQL** >= 8.0
- **Node.js** >= 18.x (para WhatsApp Bot)
- **Composer** >= 2.x
- **Apache** con mod_rewrite o **Nginx**
- **SSL/HTTPS** (requerido para PWA y pagos)

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/quickbite.git
cd quickbite
```

### 2. Instalar dependencias PHP

```bash
composer install
```

### 3. Instalar dependencias Node.js (WhatsApp Bot)

```bash
cd whatsapp-bot
npm install
cd ..
```

### 4. Configurar variables de entorno

```bash
cp .env.example .env
nano .env  # Editar con tus credenciales
```

### 5. Configurar base de datos

```bash
# Crear la base de datos
mysql -u root -p -e "CREATE DATABASE app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar estructura (ejecutar migraciones en orden)
mysql -u root -p app_delivery < migrations/001_initial_schema.sql
# ... continuar con las demás migraciones
```

### 6. Configurar permisos

```bash
chmod 755 -R /var/www/html
chmod 777 -R /var/www/html/logs
chmod 777 -R /var/www/html/uploads
chmod 600 /var/www/html/.env
```

### 7. Configurar Apache Virtual Host

```apache
<VirtualHost *:443>
    ServerName quickbite.com.mx
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
</VirtualHost>
```

### 8. Iniciar WhatsApp Bot

```bash
cd whatsapp-bot
pm2 start server.js --name quickbite-whatsapp
pm2 save
```

---

## Estructura del Proyecto

```
quickbite/
├── admin/                  # Panel de administración (negocios, CEO)
│   ├── repartidor/         # Panel de repartidores
│   └── api/                # APIs administrativas
├── api/                    # Endpoints API públicos
│   └── mercadopago/        # Procesamiento de pagos MP
├── assets/                 # Recursos estáticos
│   ├── css/                # Estilos
│   ├── js/                 # JavaScript
│   ├── img/                # Imágenes
│   └── icons/              # Iconos PWA
├── config/                 # Configuración de la aplicación
│   ├── database.php        # Conexión a BD
│   ├── env.php             # Carga de variables de entorno
│   ├── csrf.php            # Protección CSRF
│   ├── rate_limit.php      # Rate limiting
│   └── mercadopago.php     # Config MercadoPago
├── includes/               # Componentes PHP reutilizables
├── js/                     # JavaScript principal
├── logs/                   # Archivos de log
├── migrations/             # Scripts SQL de migración
├── models/                 # Modelos de datos (Usuario, Pedido, etc.)
├── services/               # Servicios de negocio
├── tests/                  # Tests del sistema
├── uploads/                # Archivos subidos por usuarios
├── vendor/                 # Dependencias Composer
├── webhook/                # Webhooks de pagos
├── whatsapp-bot/           # Bot de WhatsApp (Node.js)
├── .env                    # Variables de entorno (NO commitear)
├── .env.example            # Plantilla de variables de entorno
├── .htaccess               # Configuración Apache
├── composer.json           # Dependencias PHP
├── manifest.json           # PWA Manifest
├── sw.js                   # Service Worker
└── index.php               # Punto de entrada
```

---

## Variables de Entorno

Ver `.env.example` para la lista completa. Las principales son:

| Variable | Descripción |
|----------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión a MySQL |
| `STRIPE_SECRET_KEY`, `STRIPE_PUBLIC_KEY` | Credenciales Stripe |
| `MP_PUBLIC_KEY`, `MP_ACCESS_TOKEN` | Credenciales MercadoPago |
| `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS` | Configuración de email |
| `MAPBOX_ACCESS_TOKEN` | Token de Mapbox para mapas |
| `AI_API_KEY` | API Key de Google Gemini |

---

## Funcionalidades Principales

### Para Clientes
- Registro/Login (email, Google OAuth)
- Búsqueda y filtrado de restaurantes
- Carrito de compras con personalizaciones
- Checkout con múltiples métodos de pago
- Seguimiento de pedidos en tiempo real
- Historial de pedidos y reordenar
- Sistema de puntos y recompensas
- Favoritos y direcciones guardadas

### Para Negocios
- Dashboard con estadísticas
- Gestión de menú y categorías
- Gestión de pedidos en tiempo real
- Wallet y retiro de ganancias
- Promociones y cupones
- Horarios de operación
- Parser de menú con IA (Gemini)

### Para Repartidores
- Pedidos disponibles cercanos
- Navegación GPS integrada
- Sistema de gamificación y bonos
- Wallet y retiro de ganancias
- Historial de entregas

### Sistema
- PWA (instalable, offline)
- Notificaciones push
- Bot de WhatsApp automático
- Webhooks de pago
- Rate limiting y CSRF protection
- Logs centralizados

---

## Métodos de Pago

| Método | Estado | Notas |
|--------|--------|-------|
| Tarjeta (Stripe) | ✅ Producción | Visa, Mastercard, Amex |
| Tarjeta (MercadoPago) | ✅ Producción | Tarjetas mexicanas |
| OXXO | ✅ Producción | Pago en efectivo |
| Efectivo contra entrega | ✅ Producción | Al repartidor |
| Wallet QuickBite | ✅ Producción | Saldo prepagado |

---

## Estados de Pedido

| ID | Estado | Descripción |
|----|--------|-------------|
| 1 | Pendiente | Pedido recibido, esperando confirmación |
| 2 | Confirmado | Negocio aceptó el pedido |
| 3 | En preparación | Cocinando |
| 4 | Listo para recoger | Esperando repartidor |
| 5 | En camino | Repartidor en ruta |
| 6 | Entregado | Completado |
| 7 | Cancelado | Cancelado |

---

## Ejecutar Tests

```bash
# Tests críticos del sistema
php tests/critical_tests.php

# Tests funcionales
php tests/functional_tests.php

# Verificación completa
php tests/complete_system_check.php
```

---

## Despliegue en Producción

### Checklist pre-deploy

- [ ] Variables de entorno configuradas (`.env`)
- [ ] `ENVIRONMENT=production` en `.env`
- [ ] SSL/HTTPS configurado
- [ ] Migraciones de BD ejecutadas
- [ ] Permisos de carpetas configurados
- [ ] WhatsApp Bot iniciado con PM2
- [ ] Webhooks de pago configurados en Stripe/MercadoPago
- [ ] Cron jobs configurados

### Cron Jobs recomendados

```cron
# Procesar timeouts de pedidos cada 5 minutos
*/5 * * * * php /var/www/html/cron/procesar_timeouts.php

# Limpiar logs antiguos cada día
0 3 * * * find /var/www/html/logs -name "*.log" -mtime +30 -delete
```

---

## Seguridad

- CSRF Protection en todos los formularios
- Prepared Statements (previene SQL Injection)
- Password hashing con bcrypt
- Rate limiting en APIs
- Headers de seguridad (X-Frame-Options, HSTS, etc.)
- Validación de inputs
- Sesiones seguras (HttpOnly, SameSite, Secure)

---

## Soporte

- **Email**: contacto@quickbite.com.mx
- **Teléfono**: +52 446 477 6241
- **Ubicación**: Aguascalientes, México

---

## Licencia

Software propietario. Todos los derechos reservados.

---

*Última actualización: Enero 2026*
