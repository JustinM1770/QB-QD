#!/bin/bash

###############################################################################
# Script para crear paquete de base de datos para compartir
# Genera un archivo ZIP con todo lo necesario para instalar QuickBite
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}📦 Creando Paquete de Base de Datos${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Crear directorio temporal
TEMP_DIR="quickbite_database_package"
mkdir -p "$TEMP_DIR"

echo -e "${YELLOW}📋 Copiando archivos...${NC}"

# Copiar archivos SQL
cp 001_schema_completo.sql "$TEMP_DIR/" 2>/dev/null || echo "⚠️  Advertencia: 001_schema_completo.sql no encontrado"
cp quickbite_completo_con_datos.sql "$TEMP_DIR/" 2>/dev/null || echo "⚠️  Advertencia: quickbite_completo_con_datos.sql no encontrado"
cp README_INSTALACION.md "$TEMP_DIR/" 2>/dev/null || echo "⚠️  Advertencia: README_INSTALACION.md no encontrado"

# Crear script de instalación automática
cat > "$TEMP_DIR/instalar.sh" << 'EOF'
#!/bin/bash

# Script de instalación automática de QuickBite
# Uso: ./instalar.sh

echo "🚀 Instalador de Base de Datos QuickBite"
echo "=========================================="
echo ""

# Verificar que MySQL está instalado
if ! command -v mysql &> /dev/null; then
    echo "❌ Error: MySQL no está instalado"
    echo "Instala MySQL primero:"
    echo "  Ubuntu/Debian: sudo apt install mysql-server"
    echo "  macOS: brew install mysql"
    exit 1
fi

# Pedir credenciales
read -p "Usuario de MySQL [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Password de MySQL: " DB_PASS
echo ""

# Preguntar qué archivo usar
echo ""
echo "¿Qué versión quieres instalar?"
echo "1) Con datos de prueba (recomendado para testing)"
echo "2) Solo esquema (base de datos vacía)"
read -p "Opción [1]: " OPCION
OPCION=${OPCION:-1}

if [ "$OPCION" = "1" ]; then
    SQL_FILE="quickbite_completo_con_datos.sql"
    echo "✅ Instalarás: Base de datos con datos de prueba"
else
    SQL_FILE="001_schema_completo.sql"
    echo "✅ Instalarás: Solo esquema (sin datos)"
fi

# Verificar que el archivo existe
if [ ! -f "$SQL_FILE" ]; then
    echo "❌ Error: No se encuentra $SQL_FILE"
    exit 1
fi

# Crear base de datos
echo ""
echo "📦 Creando base de datos 'app_delivery'..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

if [ $? -ne 0 ]; then
    echo "❌ Error al crear la base de datos. Verifica tus credenciales."
    exit 1
fi

# Importar SQL
echo "📥 Importando estructura y datos..."
mysql -u "$DB_USER" -p"$DB_PASS" app_delivery < "$SQL_FILE" 2>/dev/null

if [ $? -ne 0 ]; then
    echo "❌ Error al importar el archivo SQL"
    exit 1
fi

# Verificar instalación
echo ""
echo "🔍 Verificando instalación..."
TABLES=$(mysql -u "$DB_USER" -p"$DB_PASS" app_delivery -e "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = 'app_delivery';" -N 2>/dev/null)

echo "✅ Base de datos instalada correctamente"
echo "📊 Total de tablas creadas: $TABLES"

if [ "$OPCION" = "1" ]; then
    echo ""
    echo "📋 Datos de prueba instalados:"
    USUARIOS=$(mysql -u "$DB_USER" -p"$DB_PASS" app_delivery -e "SELECT COUNT(*) FROM usuarios;" -N 2>/dev/null)
    NEGOCIOS=$(mysql -u "$DB_USER" -p"$DB_PASS" app_delivery -e "SELECT COUNT(*) FROM negocios;" -N 2>/dev/null)
    PRODUCTOS=$(mysql -u "$DB_USER" -p"$DB_PASS" app_delivery -e "SELECT COUNT(*) FROM productos;" -N 2>/dev/null)
    PEDIDOS=$(mysql -u "$DB_USER" -p"$DB_PASS" app_delivery -e "SELECT COUNT(*) FROM pedidos;" -N 2>/dev/null)

    echo "  - Usuarios: $USUARIOS"
    echo "  - Negocios: $NEGOCIOS"
    echo "  - Productos: $PRODUCTOS"
    echo "  - Pedidos: $PEDIDOS"
fi

echo ""
echo "✨ ¡Instalación completada!"
echo ""
echo "📝 Próximos pasos:"
echo "1. Edita config/database.php con tus credenciales:"
echo "   - host: localhost"
echo "   - database: app_delivery"
echo "   - username: $DB_USER"
echo "   - password: tu_password"
echo ""
echo "2. Inicia el servidor PHP:"
echo "   php -S localhost:8000"
echo ""
echo "3. Prueba los endpoints:"
echo "   curl http://localhost:8000/api/pedidos/disponibles.php"
echo ""
echo "📖 Consulta README_INSTALACION.md para más detalles"
EOF

chmod +x "$TEMP_DIR/instalar.sh"

# Crear archivo de configuración de ejemplo
cat > "$TEMP_DIR/database.php.example" << 'EOF'
<?php
/**
 * Configuración de Base de Datos para QuickBite
 *
 * INSTRUCCIONES:
 * 1. Copia este archivo a: config/database.php
 * 2. Cambia los valores según tu instalación
 * 3. NO subas este archivo a git (ya está en .gitignore)
 */

class Database {
    // Configuración de conexión
    private $host = "localhost";
    private $db_name = "app_delivery";
    private $username = "root";        // CAMBIA ESTO
    private $password = "tu_password"; // CAMBIA ESTO
    private $conn;

    /**
     * Obtener conexión a la base de datos
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            die("Error de conexión a la base de datos. Verifica config/database.php");
        }

        return $this->conn;
    }
}
?>
EOF

echo -e "${GREEN}✓ Archivos copiados${NC}"

# Comprimir todo
echo -e "${YELLOW}🗜️  Comprimiendo...${NC}"
ZIP_NAME="quickbite_database_$(date +%Y%m%d_%H%M%S).zip"
zip -r "$ZIP_NAME" "$TEMP_DIR" > /dev/null 2>&1

# Limpiar
rm -rf "$TEMP_DIR"

# Mostrar resultado
ZIP_SIZE=$(ls -lh "$ZIP_NAME" | awk '{print $5}')

echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}✅ Paquete creado exitosamente${NC}"
echo -e "${GREEN}========================================${NC}\n"
echo -e "📦 Archivo: ${BLUE}$ZIP_NAME${NC}"
echo -e "📏 Tamaño: ${BLUE}$ZIP_SIZE${NC}"
echo -e "\n📋 Contenido del paquete:"
echo -e "  ✓ 001_schema_completo.sql (solo estructura)"
echo -e "  ✓ quickbite_completo_con_datos.sql (estructura + datos)"
echo -e "  ✓ README_INSTALACION.md (instrucciones completas)"
echo -e "  ✓ instalar.sh (script de instalación automática)"
echo -e "  ✓ database.php.example (ejemplo de configuración)"

echo -e "\n${YELLOW}📤 Cómo compartir:${NC}"
echo -e "  1. Envía este archivo a tu amigo: $ZIP_NAME"
echo -e "  2. Tu amigo debe descomprimirlo: unzip $ZIP_NAME"
echo -e "  3. Ejecutar: cd quickbite_database_package && ./instalar.sh"

echo -e "\n${GREEN}¡Todo listo!${NC} 🚀\n"
