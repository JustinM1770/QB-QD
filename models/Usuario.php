<?php
require_once dirname(__FILE__) . '/../config/database.php';

class Usuario {
    private $conn;
    private $table = 'usuarios';
    
    // Propiedades del usuario
    public $id_usuario;
    public $email;
    public $password;
    public $nombre;
    public $apellido;
    public $telefono;
    public $foto_perfil;
    public $fecha_creacion;
    public $fecha_actualizacion;
    public $tipo_usuario;
    public $verification_code;
    public $is_verified;
    public $activo;
    
    // Constructor con conexión a BD
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Función helper para sanitizar datos de forma segura
     * @param mixed $data
     * @return string
     */
    private function sanitize($data) {
        if ($data === null || $data === '') {
            return '';
        }
        return htmlspecialchars(strip_tags(trim((string)$data)));
    }
    
    /**
     * Iniciar sesión: verificar si el email existe y la contraseña es correcta
     * MEJORADO: Incluye verificación de email verificado
     * @return array
     */
    public function login() {
        try {
            // Query para buscar usuario por email
            $query = "SELECT 
                        id_usuario, email, password, nombre, apellido, telefono, 
                        foto_perfil, fecha_creacion, fecha_actualizacion, tipo_usuario,
                        verification_code, is_verified, activo
                      FROM " . $this->table . "
                      WHERE email = :email 
                      AND (activo = 1 OR activo IS NULL)
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $this->email = $this->sanitize($this->email);
            $stmt->bindParam(':email', $this->email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar contraseña
                if (password_verify($this->password, $row['password'])) {
                    // Verificar si el email está verificado
                    if ($row['is_verified'] == 0) {
                        return array(
                            'success' => false,
                            'error' => 'email_not_verified',
                            'message' => 'Tu cuenta no ha sido verificada. Por favor verifica tu email.',
                            'email' => $this->email
                        );
                    }
                    
                    // Asignar todas las propiedades del usuario
                    $this->id_usuario = $row['id_usuario'];
                    $this->email = $row['email'];
                    $this->nombre = $row['nombre'];
                    $this->apellido = $row['apellido'] ?? '';
                    $this->telefono = $row['telefono'] ?? '';
                    $this->foto_perfil = $row['foto_perfil'] ?? '';
                    $this->fecha_creacion = $row['fecha_creacion'] ?? '';
                    $this->fecha_actualizacion = $row['fecha_actualizacion'] ?? '';
                    $this->tipo_usuario = $row['tipo_usuario'] ?? 'cliente';
                    $this->verification_code = $row['verification_code'] ?? null;
                    $this->is_verified = $row['is_verified'] ?? 1;
                    $this->activo = $row['activo'] ?? 1;
                    
                    return array(
                        'success' => true,
                        'user_data' => $row
                    );
                } else {
                    return array(
                        'success' => false,
                        'error' => 'invalid_credentials',
                        'message' => 'Email o contraseña incorrectos.'
                    );
                }
            }
            
            return array(
                'success' => false,
                'error' => 'user_not_found',
                'message' => 'No se encontró una cuenta con ese email.'
            );
            
        } catch (PDOException $e) {
            error_log("Error en login Usuario: " . $e->getMessage());
            return array(
                'success' => false,
                'error' => 'database_error',
                'message' => 'Error del sistema. Inténtalo de nuevo.'
            );
        }
    }
    
    /**
     * Registro de nuevo usuario
     * MEJORADO: Maneja código de verificación
     * @return boolean
     */
    public function registrar() {
        try {
            // Verificar si el email ya existe
            if ($this->emailExiste()) {
                return false;
            }
            
            // Sanitizar datos
            $this->email = $this->sanitize($this->email);
            $this->nombre = $this->sanitize($this->nombre);
            $this->apellido = $this->sanitize($this->apellido);
            $this->telefono = $this->sanitize($this->telefono);
            $this->tipo_usuario = $this->sanitize($this->tipo_usuario ?? 'cliente');
            
            // Hash de la contraseña
            $password_hash = password_hash($this->password, PASSWORD_BCRYPT);
            
            // Query para insertar
            $query = "INSERT INTO " . $this->table . "
                    SET email = :email,
                        password = :password,
                        nombre = :nombre,
                        apellido = :apellido,
                        telefono = :telefono,
                        tipo_usuario = :tipo_usuario,
                        verification_code = :verification_code,
                        is_verified = :is_verified,
                        activo = 1";
            
            $stmt = $this->conn->prepare($query);
            
            // Vincular valores
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':nombre', $this->nombre);
            $stmt->bindParam(':apellido', $this->apellido);
            $stmt->bindParam(':telefono', $this->telefono);
            $stmt->bindParam(':tipo_usuario', $this->tipo_usuario);
            $stmt->bindParam(':verification_code', $this->verification_code);
            $stmt->bindParam(':is_verified', $this->is_verified);
            
            // Ejecutar
            if ($stmt->execute()) {
                $this->id_usuario = $this->conn->lastInsertId();
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en registrar Usuario: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar código de verificación de email
     * @param string $email
     * @param string $codigo
     * @return array
     */
    public function verificarCodigo($email, $codigo) {
        try {
            // Verificar código en la tabla de verificaciones
            $query = "SELECT * FROM email_verifications 
                      WHERE email = :email AND codigo = :codigo AND expira > NOW() AND usado = 0";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Código válido, actualizar usuario como verificado
                $update_query = "UPDATE " . $this->table . " 
                               SET is_verified = 1, verification_code = NULL, fecha_actualizacion = NOW()
                               WHERE email = :email";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':email', $email);
                
                if ($update_stmt->execute()) {
                    // Marcar código como usado
                    $used_query = "UPDATE email_verifications SET usado = 1 WHERE email = :email";
                    $used_stmt = $this->conn->prepare($used_query);
                    $used_stmt->bindParam(':email', $email);
                    $used_stmt->execute();
                    
                    // Obtener datos del usuario
                    $user_query = "SELECT id_usuario, nombre FROM " . $this->table . " WHERE email = :email";
                    $user_stmt = $this->conn->prepare($user_query);
                    $user_stmt->bindParam(':email', $email);
                    $user_stmt->execute();
                    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    return array(
                        'success' => true,
                        'id_usuario' => $user_data['id_usuario'],
                        'nombre' => $user_data['nombre']
                    );
                }
            }
            
            return array('success' => false);
            
        } catch (PDOException $e) {
            error_log("Error en verificarCodigo: " . $e->getMessage());
            return array('success' => false);
        }
    }
    
    /**
     * Reenviar código de verificación
     * @param string $email
     * @return array
     */
    public function reenviarCodigo($email) {
        try {
            // Verificar que el usuario existe y no está verificado
            $query = "SELECT nombre FROM " . $this->table . " 
                      WHERE email = :email AND is_verified = 0 AND activo = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generar nuevo código
                $nuevo_codigo = sprintf("%06d", mt_rand(1, 999999));
                $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Actualizar código en tabla de verificaciones
                $update_query = "INSERT INTO email_verifications (email, codigo, expira) 
                               VALUES (:email, :codigo, :expira) 
                               ON DUPLICATE KEY UPDATE 
                               codigo = VALUES(codigo), 
                               expira = VALUES(expira), 
                               usado = 0";
                
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':codigo', $nuevo_codigo);
                $update_stmt->bindParam(':expira', $expira);
                
                if ($update_stmt->execute()) {
                    // Actualizar código en tabla usuarios también
                    $user_update = "UPDATE " . $this->table . " 
                                  SET verification_code = :codigo WHERE email = :email";
                    $user_stmt = $this->conn->prepare($user_update);
                    $user_stmt->bindParam(':codigo', $nuevo_codigo);
                    $user_stmt->bindParam(':email', $email);
                    $user_stmt->execute();
                    
                    return array(
                        'success' => true,
                        'codigo' => $nuevo_codigo,
                        'nombre' => $user_data['nombre']
                    );
                }
            }
            
            return array('success' => false);
            
        } catch (PDOException $e) {
            error_log("Error en reenviarCodigo: " . $e->getMessage());
            return array('success' => false);
        }
    }
    
    /**
     * Verificar si puede reenviar código (límite de tiempo)
     * @param string $email
     * @return boolean
     */
    public function puedeReenviarCodigo($email) {
        try {
            $query = "SELECT created_at FROM email_verifications 
                      WHERE email = :email 
                      ORDER BY created_at DESC 
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $ultimo_envio = strtotime($row['created_at']);
                $ahora = time();
                
                // Permitir reenvío cada 60 segundos
                if (($ahora - $ultimo_envio) < 60) {
                    return false;
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error en puedeReenviarCodigo: " . $e->getMessage());
            return true; // En caso de error, permitir reenvío
        }
    }
    
    /**
     * Verificar si el email ya existe en la BD
     * @return boolean
     */
    public function emailExiste() {
        try {
            $query = "SELECT id_usuario FROM " . $this->table . " WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $this->email = $this->sanitize($this->email);
            $stmt->bindParam(':email', $this->email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Error en emailExiste: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener datos del usuario por ID
     * @return boolean
     */
    public function obtenerPorId() {
        try {
            if (empty($this->id_usuario)) {
                throw new Exception("ID de usuario no definido");
            }
            
            $query = "SELECT * FROM " . $this->table . " WHERE id_usuario = :id AND (activo = 1 OR activo IS NULL) LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id_usuario);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("Usuario con ID {$this->id_usuario} no encontrado o inactivo");
            }
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($row['email']) || empty($row['nombre'])) {
                throw new Exception("Datos de usuario incompletos");
            }
            
            // Asignar valores
            $this->id_usuario = $row['id_usuario'];
            $this->email = $row['email'];
            $this->nombre = $row['nombre'];
            $this->apellido = $row['apellido'] ?? '';
            $this->telefono = $row['telefono'] ?? '';
            $this->foto_perfil = $row['foto_perfil'] ?? '';
            $this->fecha_creacion = $row['fecha_creacion'] ?? '';
            $this->fecha_actualizacion = $row['fecha_actualizacion'] ?? '';
            $this->tipo_usuario = $row['tipo_usuario'] ?? 'cliente';
            $this->verification_code = $row['verification_code'] ?? null;
            $this->is_verified = $row['is_verified'] ?? 1;
            $this->activo = $row['activo'] ?? 1;
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error en obtenerPorId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener usuario por email
     * @param string $email
     * @return array|false
     */
    public function obtenerPorEmail($email) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE email = :email AND activo = 1 LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en obtenerPorEmail: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar perfil de usuario
     * @return boolean
     */
    public function actualizar() {
        try {
            $query = "UPDATE " . $this->table . "
                    SET nombre = :nombre,
                        apellido = :apellido,
                        telefono = :telefono,
                        fecha_actualizacion = NOW()";
            
            if (!empty($this->foto_perfil)) {
                $query .= ", foto_perfil = :foto_perfil";
            }
            
            $query .= " WHERE id_usuario = :id_usuario";
            
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar datos
            $this->nombre = $this->sanitize($this->nombre);
            $this->apellido = $this->sanitize($this->apellido);
            $this->telefono = $this->sanitize($this->telefono);
            $this->id_usuario = $this->sanitize($this->id_usuario);
            
            // Vincular datos
            $stmt->bindParam(':nombre', $this->nombre);
            $stmt->bindParam(':apellido', $this->apellido);
            $stmt->bindParam(':telefono', $this->telefono);
            $stmt->bindParam(':id_usuario', $this->id_usuario);
            
            if (!empty($this->foto_perfil)) {
                $this->foto_perfil = $this->sanitize($this->foto_perfil);
                $stmt->bindParam(':foto_perfil', $this->foto_perfil);
            }
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error en actualizar: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cambiar contraseña
     * @param string $nueva_password
     * @return boolean
     */
    public function cambiarPassword($nueva_password) {
        try {
            $query = "UPDATE " . $this->table . "
                    SET password = :password, fecha_actualizacion = NOW()
                    WHERE id_usuario = :id_usuario";
            
            $stmt = $this->conn->prepare($query);
            
            $password_hash = password_hash($nueva_password, PASSWORD_BCRYPT);
            $this->id_usuario = $this->sanitize($this->id_usuario);
            
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id_usuario', $this->id_usuario);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error en cambiarPassword: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si el usuario es propietario de negocio
     * @return boolean
     */
    public function esPropietarioNegocio() {
        try {
            $query = "SELECT id_negocio FROM negocios WHERE id_propietario = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id_usuario);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en esPropietarioNegocio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Activar/Desactivar usuario
     * @param boolean $estado
     * @return boolean
     */
    public function cambiarEstado($estado = true) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET activo = :activo, fecha_actualizacion = NOW()
                     WHERE id_usuario = :id";
            
            $stmt = $this->conn->prepare($query);
            $activo = $estado ? 1 : 0;
            $stmt->bindParam(':activo', $activo);
            $stmt->bindParam(':id', $this->id_usuario);
            
            if ($stmt->execute()) {
                $this->activo = $activo;
                return true;
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en cambiarEstado: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar email del usuario
     * @return boolean
     */
    public function verificarEmail() {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET is_verified = 1, verification_code = NULL, fecha_actualizacion = NOW()
                     WHERE id_usuario = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id_usuario);
            
            if ($stmt->execute()) {
                $this->is_verified = 1;
                $this->verification_code = null;
                return true;
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en verificarEmail: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar tipo de usuario
     * @param string $nuevo_tipo
     * @return boolean
     */
    public function actualizarTipo($nuevo_tipo) {
        try {
            $query = "UPDATE " . $this->table . " 
                     SET tipo_usuario = :tipo, fecha_actualizacion = NOW()
                     WHERE id_usuario = :id";
            
            $stmt = $this->conn->prepare($query);
            $nuevo_tipo = $this->sanitize($nuevo_tipo);
            $stmt->bindParam(':tipo', $nuevo_tipo);
            $stmt->bindParam(':id', $this->id_usuario);
            
            if ($stmt->execute()) {
                $this->tipo_usuario = $nuevo_tipo;
                return true;
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en actualizarTipo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpiar códigos de verificación expirados
     * @param PDO $db
     * @return boolean
     */
    public static function limpiarCodigosExpirados($db) {
        try {
            $query = "DELETE FROM email_verifications WHERE expira < NOW()";
            $stmt = $db->prepare($query);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en limpiarCodigosExpirados: " . $e->getMessage());
            return false;
        }
    }
}
?>