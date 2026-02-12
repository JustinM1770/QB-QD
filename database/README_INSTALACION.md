# 🗄️ Instalación de Base de Datos QuickBite

Esta carpeta contiene los archivos necesarios para instalar la base de datos de QuickBite.

## 📁 Archivos Disponibles

### Opción 1: Solo Esquema (Recomendado para Desarrollo)
- **`001_schema_completo.sql`** (126 KB)
  - Contiene todas las tablas, índices y relaciones
  - **NO** incluye datos
  - Úsalo si quieres empezar con una base de datos limpia

### Opción 2: Base de Datos Completa con Datos de Prueba
- **`quickbite_completo_con_datos.sql`** (226 KB)
  - Incluye el esquema completo
  - Incluye datos de prueba:
    - 48 usuarios
    - 3 negocios
    - 120 productos
    - 16 pedidos de ejemplo
    - Repartidores de prueba
  - **Recomendado** si quieres probar la app inmediatamente

---

## 🚀 Instalación Rápida

### Prerequisitos

1. MySQL o MariaDB instalado
2. Usuario con permisos para crear bases de datos

### Opción A: Con Datos de Prueba (Recomendado)

```bash
# 1. Crear la base de datos
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Importar el dump completo
mysql -u root -p app_delivery < quickbite_completo_con_datos.sql

# 3. Verificar instalación
mysql -u root -p -e "USE app_delivery; SHOW TABLES;"
```

### Opción B: Solo Esquema (Sin Datos)

```bash
# 1. Crear la base de datos
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Importar solo el esquema
mysql -u root -p app_delivery < 001_schema_completo.sql

# 3. Verificar instalación
mysql -u root -p -e "USE app_delivery; SHOW TABLES;"
```

---

## 🔧 Configuración del Proyecto

Después de instalar la base de datos, configura la conexión en el proyecto:

### PHP Backend

Edita `config/database.php`:

```php
<?php
class Database {
    private $host = "localhost";
    private $db_name = "app_delivery";
    private $username = "root";        // Cambia esto
    private $password = "tu_password"; // Cambia esto
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
```

### Flutter App

Edita `quickbite_repartidor/lib/config/api_config.dart`:

```dart
class ApiConfig {
  static const String baseUrl = 'http://TU_IP:8000/api'; // Cambia por tu IP

  // Endpoints
  static const String login = '$baseUrl/auth/login.php';
  static const String pedidosDisponibles = '$baseUrl/pedidos/disponibles.php';
  // ... resto de endpoints
}
```

---

## 📊 Estructura de la Base de Datos

La base de datos contiene las siguientes tablas principales:

### Usuarios y Autenticación
- `usuarios` - Usuarios del sistema (clientes, negocios, repartidores)
- `repartidores` - Información específica de repartidores
- `negocios` - Locales y restaurantes

### Pedidos
- `pedidos` - Pedidos principales
- `detalles_pedido` - Productos en cada pedido
- `estados_pedido` - Catálogo de estados (pendiente, confirmado, en_camino, etc.)
- `historial_estados_pedido` - Registro de cambios de estado

### Productos y Catálogo
- `productos` - Productos de los negocios
- `categorias` - Categorías de productos

### Direcciones y Ubicación
- `direcciones_usuario` - Direcciones de entrega de los usuarios

### Sistema de Pagos y Finanzas
- `metodos_pago` - Métodos de pago disponibles
- `wallet` - Billetera digital (si está implementada)

---

## ✅ Verificación de Instalación

### Verificar que todas las tablas se crearon

```bash
mysql -u root -p app_delivery -e "SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'app_delivery';"
```

Deberías ver aproximadamente **90+ tablas**.

### Verificar datos de prueba (si usaste la opción con datos)

```bash
# Ver usuarios
mysql -u root -p app_delivery -e "SELECT COUNT(*) FROM usuarios;"

# Ver negocios
mysql -u root -p app_delivery -e "SELECT id_negocio, nombre FROM negocios;"

# Ver pedidos
mysql -u root -p app_delivery -e "SELECT COUNT(*) FROM pedidos;"

# Ver productos
mysql -u root -p app_delivery -e "SELECT COUNT(*) FROM productos;"
```

### Probar endpoints PHP

```bash
# Iniciar servidor PHP (desarrollo)
cd /var/www/html
php -S localhost:8000

# En otra terminal, probar endpoints
curl http://localhost:8000/api/pedidos/disponibles.php
```

---

## 🔐 Usuarios de Prueba (Si instalaste con datos)

### Repartidores de Prueba

```
Email: repartidor1@quickbite.com
Password: (consulta en la tabla repartidores)
```

### Clientes de Prueba

```
Hay 48 usuarios de prueba en la tabla usuarios
```

### Negocios

```
- Cafe (id_negocio: 1)
- Orez Floristería (id_negocio: 9)
- Otros (consulta la tabla negocios)
```

---

## 🐛 Troubleshooting

### Error: "Access denied for user"

```bash
# Crear usuario específico para QuickBite
mysql -u root -p -e "CREATE USER 'quickbite'@'localhost' IDENTIFIED BY 'password_seguro';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON app_delivery.* TO 'quickbite'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

### Error: "Unknown database"

```bash
# Asegúrate de crear la base de datos primero
mysql -u root -p -e "CREATE DATABASE app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Error: "Table doesn't exist"

```bash
# Verifica que el archivo SQL se importó correctamente
mysql -u root -p app_delivery -e "SHOW TABLES;"

# Si no hay tablas, reimporta
mysql -u root -p app_delivery < quickbite_completo_con_datos.sql
```

### Error: "Cannot add foreign key constraint"

Esto generalmente ocurre al importar archivos de migración sin orden. **Usa los archivos de esta carpeta** que ya tienen el orden correcto.

---

## 📦 Exportar la Base de Datos

### Solo esquema (sin datos)

```bash
mysqldump -u root -p app_delivery --no-data > mi_backup_schema.sql
```

### Esquema + Datos

```bash
mysqldump -u root -p app_delivery > mi_backup_completo.sql
```

### Solo datos específicos

```bash
# Solo usuarios
mysqldump -u root -p app_delivery usuarios > usuarios_backup.sql

# Solo pedidos
mysqldump -u root -p app_delivery pedidos detalles_pedido > pedidos_backup.sql
```

---

## 🔄 Actualizar Base de Datos Existente

Si ya tienes una versión anterior y quieres actualizarla:

```bash
# ⚠️ ADVERTENCIA: Esto borrará todos los datos
mysql -u root -p -e "DROP DATABASE IF EXISTS app_delivery;"
mysql -u root -p -e "CREATE DATABASE app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p app_delivery < quickbite_completo_con_datos.sql
```

---

## 📞 Soporte

Si tienes problemas con la instalación:

1. Verifica que MySQL esté corriendo: `systemctl status mysql`
2. Verifica tu versión de MySQL: `mysql --version` (se requiere MySQL 5.7+ o MariaDB 10.2+)
3. Revisa los logs de MySQL: `tail -f /var/log/mysql/error.log`
4. Asegúrate de usar el charset correcto: `utf8mb4`

---

## ✨ Próximos Pasos

Después de instalar la base de datos:

1. ✅ Configura `config/database.php` con tus credenciales
2. ✅ Inicia el servidor PHP: `php -S localhost:8000`
3. ✅ Prueba los endpoints con curl o Postman
4. ✅ Configura la app Flutter con la URL correcta
5. ✅ Ejecuta la app y prueba el flujo completo

¡Listo para desarrollar! 🚀
