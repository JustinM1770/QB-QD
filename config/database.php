<?php
/**
 * Configuración de conexión a la base de datos
 * 
 * SEGURIDAD: Las credenciales se cargan desde variables de entorno (.env)
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Previene SQL Injection
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    public function __construct() {
        // Cargar credenciales desde variables de entorno
        $this->host = env('DB_HOST', 'localhost');
        $this->db_name = env('DB_NAME', 'app_delivery');
        $this->username = env('DB_USER', 'quickbite');
        $this->password = env('DB_PASS', '');
        
        // Validar que la contraseña existe
        if (empty($this->password)) {
            error_log("CRÍTICO: DB_PASS no está definida en las variables de entorno");
        }
    }

    /**
     * Obtener conexión a la base de datos
     * @return PDO
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $this->options);
            
        } catch(PDOException $exception) {
            // Log del error sin exponer credenciales
            error_log("Error de conexión a DB: " . $exception->getMessage());
            
            // En producción, no mostrar detalles del error
            if (env('ENVIRONMENT') === 'production') {
                throw new Exception("No se pudo conectar a la base de datos. Contacte al administrador.");
            } else {
                throw new Exception("Error de conexión: " . $exception->getMessage());
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Cerrar conexión
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
?>