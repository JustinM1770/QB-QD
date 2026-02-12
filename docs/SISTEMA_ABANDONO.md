# Sistema de Abandono Automático de Pedidos y Reembolsos

## 📋 Descripción

Sistema automatizado que detecta y procesa pedidos no entregados por repartidores, procesando reembolsos automáticos y aplicando penalizaciones.

## 🎯 Características

- ✅ **Detección automática** de pedidos atrasados cada 5 minutos
- ✅ **Reembolsos automáticos** vía MercadoPago
- ✅ **Notificaciones** a usuarios afectados
- ✅ **Penalizaciones** a repartidores con abandono
- ✅ **Panel de administración** para gestión manual
- ✅ **Logs detallados** de todas las operaciones
- ✅ **Vista de reportes** con estadísticas

## 🚀 Instalación

### 1. Ejecutar script de instalación

```bash
cd /var/www/html
sudo bash scripts/install_sistema_abandono.sh
```

El script automáticamente:
- Aplica migraciones SQL
- Configura cron job
- Establece permisos
- Crea archivos de configuración

### 2. Verificar variables de entorno

Asegúrate de tener en tu `.env`:

```env
MERCADOPAGO_ACCESS_TOKEN=tu_token_aqui
MAPBOX_ACCESS_TOKEN=tu_token_mapbox
```

### 3. Verificar instalación

```bash
# Ver logs del cron
tail -f logs/cron_abandono.log

# Ejecutar manualmente para probar
sudo -u www-data php cron/abandonar_pedidos_atrasados.php
```

## ⚙️ Configuración

### Tiempos Límite

Edita `/var/www/html/config/abandono_config.php`:

```php
define('TIMEOUT_ENTREGA', 60);    // Minutos para entregar después de recoger
define('TIMEOUT_RECOGIDA', 30);   // Minutos para recoger pedido listo
define('TIMEOUT_EN_CAMINO', 45);  // Minutos máximo en camino
```

### Cron Job

El cron se ejecuta cada 5 minutos:

```cron
*/5 * * * * /usr/bin/php /var/www/html/cron/abandonar_pedidos_atrasados.php >> /var/www/html/logs/cron_abandono.log 2>&1
```

Para modificar la frecuencia:

```bash
crontab -u www-data -e
```

## 📊 Flujo de Trabajo

### 1. Detección de Pedidos Atrasados

El sistema verifica:

```
┌─────────────────────────────────────────┐
│ PEDIDO EN CAMINO (id_estado = 5)       │
├─────────────────────────────────────────┤
│ ¿Recogido hace > 60 min?                │
│ ¿En camino hace > 45 min sin entregar? │
└─────────────────────────────────────────┘
         ↓ SÍ
┌─────────────────────────────────────────┐
│ MARCAR COMO ABANDONADO (id_estado = 8) │
└─────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────┐
│ PEDIDO LISTO (id_estado = 4)           │
├─────────────────────────────────────────┤
│ ¿Asignado a repartidor?                 │
│ ¿Sin recoger > 30 min?                  │
└─────────────────────────────────────────┘
         ↓ SÍ
┌─────────────────────────────────────────┐
│ MARCAR COMO ABANDONADO                  │
└─────────────────────────────────────────┘
```

### 2. Proceso de Reembolso

```
┌───────────────────┐
│ Pedido Abandonado │
└─────────┬─────────┘
          ↓
┌─────────────────────────┐
│ ¿Método de pago?        │
├─────────────────────────┤
│ • MercadoPago → API     │
│ • Efectivo → N/A        │
│ • Otros → Manual        │
└─────────┬───────────────┘
          ↓
┌─────────────────────────┐
│ Registrar en tabla      │
│ reembolsos              │
└─────────┬───────────────┘
          ↓
┌─────────────────────────┐
│ Notificar usuario       │
└─────────────────────────┘
```

### 3. Penalización a Repartidor

```sql
UPDATE repartidores 
SET 
  pedidos_abandonados = pedidos_abandonados + 1,
  calificacion = calificacion - 0.5
WHERE id_repartidor = X
```

## � Lógica de Reembolsos

### Métodos de Pago

| Método de Pago | Reembolso Automático | Razón |
|----------------|---------------------|--------|
| **MercadoPago / Tarjeta** | ✅ SÍ | Ya se procesó el pago, se devuelve automáticamente |
| **Efectivo** | ❌ NO | Pago contra entrega, no hubo cargo previo |
| **Otros métodos** | ⚠️ Manual | Requiere aprobación manual del admin |

### Estados de Reembolso
- `pendiente` - Esperando procesamiento
- `procesando` - En proceso por el gateway de pago
- `aprobado` - Reembolso completado (3-5 días hábiles)
- `rechazado` - Rechazado por el admin
- `error` - Error técnico, requiere revisión

## 🚫 Cancelación Manual por Usuario

### Estados que permiten cancelación

| Estado | ID | Cancelable | Razón |
|--------|-----|-----------|--------|
| Pendiente | 1 | ✅ SÍ | Negocio aún no confirmó |
| Confirmado | 2 | ✅ SÍ | Negocio confirmó pero no empezó a preparar |
| En preparación | 3 | ❌ NO | Ya está preparando el pedido |
| Listo para recoger | 4 | ❌ NO | Pedido ya está listo |
| En camino | 5 | ❌ NO | Repartidor ya salió |
| Entregado | 6 | ❌ NO | Ya fue entregado |
| Cancelado | 7 | ❌ NO | Ya cancelado |

### API de Cancelación

**Endpoint:** `POST /api/cancelar_pedido.php`

**Request:**
```json
{
  "id_pedido": 12345,
  "motivo": "Cambié de opinión"
}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Pedido cancelado exitosamente",
  "reembolso": {
    "aplica": true,
    "procesado": true,
    "mensaje": "Reembolso procesado exitosamente",
    "es_efectivo": false
  }
}
```

**Respuesta error (estado no cancelable):**
```json
{
  "success": false,
  "message": "No puedes cancelar porque el negocio ya está preparando tu pedido",
  "estado_actual": "en_preparacion",
  "puede_cancelar": false
}
```

## �📱 Panel de Administración

Accede a: `https://tudominio.com/admin/reembolsos.php`

### Funciones disponibles:

- **Ver todos los reembolsos** con filtros
- **Aprobar/Rechazar** reembolsos pendientes
- **Estadísticas** de últimos 30 días
- **Detalles completos** de cada caso
- **Búsqueda** por usuario, pedido o negocio

### Estadísticas mostradas:

- Total de reembolsos
- Pendientes de aprobación
- Aprobados
- Monto total involucrado

## 🗄️ Base de Datos

### Tabla: `reembolsos`

```sql
CREATE TABLE reembolsos (
  id_reembolso INT PRIMARY KEY AUTO_INCREMENT,
  id_pedido INT NOT NULL,
  id_usuario INT NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  motivo TEXT NOT NULL,
  estado ENUM('pendiente','procesando','aprobado','rechazado','error'),
  fecha_solicitud DATETIME NOT NULL,
  fecha_aprobacion DATETIME NULL,
  payment_id_original VARCHAR(100),
  refund_id VARCHAR(100),
  metodo_reembolso VARCHAR(50),
  notas_admin TEXT,
  procesado_automaticamente TINYINT(1) DEFAULT 0
);
```

### Vista: `vista_pedidos_abandonados`

Consulta rápida de todos los pedidos abandonados con información completa:

```sql
SELECT * FROM vista_pedidos_abandonados 
WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY fecha_creacion DESC;
```

## 📝 Logs

### Ubicación

```
/var/www/html/logs/cron_abandono.log
```

### Formato de log

```
=== CRON Abandono de Pedidos - 2026-01-24 14:30:00 ===
Conexión establecida correctamente
Pedidos en camino atrasados encontrados: 2
Pedidos listos no recogidos encontrados: 1

--- Procesando pedido #12345 ---
Usuario: Juan Pérez (juan@example.com)
Repartidor: Carlos Gómez
Monto: $250.00
✓ Pedido marcado como abandonado
Procesando reembolso MercadoPago...
✓ Reembolso MercadoPago aprobado - ID: ref_123456
✓ Reembolso registrado en base de datos
✓ Notificación enviada al usuario
✓ Repartidor penalizado
✓ Pedido #12345 procesado exitosamente

=== RESUMEN ===
Pedidos encontrados: 3
Abandonados exitosamente: 3
Reembolsos procesados: 3
Errores: 0
=== FIN CRON - 2026-01-24 14:30:15 ===
```

### Monitorear en tiempo real

```bash
# Ver últimas 50 líneas
tail -n 50 /var/www/html/logs/cron_abandono.log

# Seguir en tiempo real
tail -f /var/www/html/logs/cron_abandono.log

# Buscar errores
grep "ERROR\|Error\|✗" /var/www/html/logs/cron_abandono.log
```

## 🔧 Mantenimiento

### Ejecutar manualmente

```bash
sudo -u www-data php /var/www/html/cron/abandonar_pedidos_atrasados.php
```

### Verificar estado del cron

```bash
# Ver crontab actual
crontab -u www-data -l

# Ver logs del sistema cron
grep CRON /var/log/syslog | tail -20
```

### Limpiar logs antiguos

```bash
# Archivar logs antiguos
cd /var/www/html/logs
tar -czf cron_abandono_$(date +%Y%m).tar.gz cron_abandono.log
echo "" > cron_abandono.log
```

## 🚨 Troubleshooting

### El cron no se ejecuta

1. Verificar que el cron esté activo:
```bash
systemctl status cron
```

2. Verificar permisos:
```bash
ls -la /var/www/html/cron/abandonar_pedidos_atrasados.php
```

3. Verificar logs del sistema:
```bash
grep CRON /var/log/syslog | grep abandono
```

### No se procesan reembolsos

1. Verificar variables de entorno:
```bash
php -r "require 'config/env.php'; echo getenv('MERCADOPAGO_ACCESS_TOKEN');"
```

2. Verificar conexión a MercadoPago:
```bash
curl -H "Authorization: Bearer TU_TOKEN" https://api.mercadopago.com/v1/payments/search
```

### Errores en la base de datos

1. Verificar que exista la tabla:
```sql
SHOW TABLES LIKE 'reembolsos';
```

2. Verificar estructura:
```sql
DESCRIBE reembolsos;
```

3. Re-aplicar migraciones:
```bash
mysql -u root -p quickbite < migrations/create_reembolsos_table.sql
```

## 📈 Reportes y Consultas Útiles

### Pedidos abandonados hoy

```sql
SELECT COUNT(*) as total, SUM(monto_total) as monto_total
FROM pedidos
WHERE id_estado = 8 
AND DATE(fecha_actualizacion) = CURDATE();
```

### Repartidores con más abandonos

```sql
SELECT 
  r.nombre,
  r.pedidos_abandonados,
  r.calificacion,
  COUNT(p.id_pedido) as abandonos_mes
FROM repartidores r
LEFT JOIN pedidos p ON r.id_repartidor = p.id_repartidor_anterior 
  AND p.id_estado = 8
  AND p.fecha_actualizacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY r.id_repartidor
ORDER BY abandonos_mes DESC
LIMIT 10;
```

### Reembolsos pendientes

```sql
SELECT 
  r.*,
  u.nombre as usuario,
  p.monto_total
FROM reembolsos r
JOIN usuarios u ON r.id_usuario = u.id_usuario
JOIN pedidos p ON r.id_pedido = p.id_pedido
WHERE r.estado = 'pendiente'
ORDER BY r.fecha_solicitud DESC;
```

## 🔐 Seguridad

- ✅ Transacciones SQL para integridad de datos
- ✅ Logs detallados de todas las operaciones
- ✅ Validación de permisos en panel admin
- ✅ Tokens de API en variables de entorno
- ✅ Manejo de errores con rollback

## 📞 Soporte

Para problemas o dudas:
1. Revisa los logs: `/var/www/html/logs/cron_abandono.log`
2. Consulta este README
3. Contacta al equipo de desarrollo

## 📄 Licencia

Propiedad de QuickBite © 2026
