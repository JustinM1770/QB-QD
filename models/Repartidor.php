<?php
class Repartidor {
    private $conn;
    private $table_name = 'repartidores'; 

    public int $id_repartidor;
    public int $id_usuario;
    public string $nombre;
    public string $apellido;
    public string $telefono;
    public string $email;
    public ?string $latitud = null;
    public ?string $longitud = null;
    public string $fecha_registro;
    public int $activo;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $password) {
        $query = "SELECT r.id_repartidor, r.activo, u.nombre, u.apellido, u.is_verified 
                  FROM repartidores r
                  JOIN usuarios u ON r.id_usuario = u.id_usuario
                  WHERE u.email = ? AND u.activo = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        if($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $passwordQuery = "SELECT password FROM usuarios WHERE email = ?";
            $passwordStmt = $this->conn->prepare($passwordQuery);
            $passwordStmt->bindParam(1, $email);
            $passwordStmt->execute();
            $passwordData = $passwordStmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $passwordData['password'])) {
                if ($user['is_verified'] == 0) {
                    return array(
                        'success' => false,
                        'error' => 'email_not_verified',
                        'message' => 'Tu cuenta no ha sido verificada. Por favor verifica tu email.',
                        'email' => $email
                    );
                }
                
                return array(
                    'success' => true,
                    'user_data' => $user
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
    }

    public function obtenerPorId() {
        $query = "SELECT 
                    r.id_repartidor,
                    r.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.telefono,
                    u.email,
                    r.latitud_actual AS latitud,
                    r.longitud_actual AS longitud,
                    r.fecha_creacion AS fecha_registro,
                    r.activo
                  FROM " . $this->table_name . " AS r
                  INNER JOIN usuarios AS u ON r.id_usuario = u.id_usuario
                  WHERE r.id_repartidor = :id_repartidor 
                  AND r.activo = 1
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_repartidor', $this->id_repartidor, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id_repartidor = $row['id_repartidor'];
            $this->id_usuario = $row['id_usuario'];
            $this->nombre = $row['nombre'];
            $this->apellido = $row['apellido'];
            $this->telefono = $row['telefono'];
            $this->email = $row['email'];
            $this->latitud = $row['latitud'];
            $this->longitud = $row['longitud'];
            $this->fecha_registro = $row['fecha_registro'];
            $this->activo = $row['activo'];
            
            return $row;
        }
        
        return false;
    }

    public function registrar($email, $password, $telefono, $tipo_vehiculo, $licencia) {
        try {
            $this->conn->beginTransaction();
            
            $verification_code = sprintf("%06d", mt_rand(1, 999999));
            
            $queryUsuario = "INSERT INTO usuarios (email, password, telefono, tipo_usuario, nombre, apellido, verification_code, is_verified, activo) 
                             VALUES (?, ?, ?, 'repartidor', ?, ?, ?, 0, 1)";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $nombre = "Repartidor";
            $apellido = substr($email, 0, strpos($email, '@'));
            $stmtUsuario->execute([$email, $hashedPassword, $telefono, $nombre, $apellido, $verification_code]);
            
            $id_usuario = $this->conn->lastInsertId();
            
            $licenciaValue = ($tipo_vehiculo === 'coche' || $tipo_vehiculo === 'camioneta') ? $licencia : null;
            $queryRepartidor = "INSERT INTO repartidores (id_usuario, tipo_vehiculo, numero_licencia, activo) 
                                VALUES (?, ?, ?, 0)";
            $stmtRepartidor = $this->conn->prepare($queryRepartidor);
            $stmtRepartidor->execute([$id_usuario, $tipo_vehiculo, $licenciaValue]);
            
            $this->conn->commit();
            
            return true;
        } catch(PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error al registrar repartidor: " . $e->getMessage());
            throw $e;
        }
    }

    public function registrarConVerificacion($email, $password, $telefono, $tipo_vehiculo, $licencia, $verification_code) {
        try {
            $this->conn->beginTransaction();
            
            $queryUsuario = "INSERT INTO usuarios (email, password, telefono, tipo_usuario, nombre, apellido, verification_code, is_verified, activo) 
                             VALUES (?, ?, ?, 'repartidor', ?, ?, ?, 0, 1)";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $nombre = "Repartidor";
            $apellido = substr($email, 0, strpos($email, '@'));
            $stmtUsuario->execute([$email, $hashedPassword, $telefono, $nombre, $apellido, $verification_code]);
            
            $id_usuario = $this->conn->lastInsertId();
            
            $licenciaValue = ($tipo_vehiculo === 'coche' || $tipo_vehiculo === 'camioneta') ? $licencia : null;
            $queryRepartidor = "INSERT INTO repartidores (id_usuario, tipo_vehiculo, numero_licencia, activo) 
                                VALUES (?, ?, ?, 0)";
            $stmtRepartidor = $this->conn->prepare($queryRepartidor);
            $stmtRepartidor->execute([$id_usuario, $tipo_vehiculo, $licenciaValue]);
            
            $this->id_repartidor = $this->conn->lastInsertId();
            
            $this->conn->commit();
            
            return true;
        } catch(PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error al registrar repartidor con verificación: " . $e->getMessage());
            return false;
        }
    }

    public function emailExiste($email) {
        try {
            $query = "SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en emailExiste: " . $e->getMessage());
            return false;
        }
    }

    public function verificarCodigo($email, $codigo) {
        try {
            $query = "SELECT * FROM email_verifications_repartidores 
                      WHERE email = ? AND codigo = ? AND expira > NOW() AND usado = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->bindParam(2, $codigo);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $update_query = "UPDATE usuarios 
                                 SET is_verified = 1, verification_code = NULL 
                                 WHERE email = ?";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(1, $email);
                
                if ($update_stmt->execute()) {
                    $used_query = "UPDATE email_verifications_repartidores SET usado = 1 WHERE email = ?";
                    $used_stmt = $this->conn->prepare($used_query);
                    $used_stmt->bindParam(1, $email);
                    $used_stmt->execute();
                    
                    $user_query = "SELECT u.id_usuario, u.nombre, r.id_repartidor 
                                   FROM usuarios u 
                                   JOIN repartidores r ON u.id_usuario = r.id_usuario 
                                   WHERE u.email = ?";
                    $user_stmt = $this->conn->prepare($user_query);
                    $user_stmt->bindParam(1, $email);
                    $user_stmt->execute();
                    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    return array(
                        'success' => true,
                        'id_usuario' => $user_data['id_usuario'],
                        'id_repartidor' => $user_data['id_repartidor'],
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

    public function reenviarCodigo($email) {
        try {
            $query = "SELECT u.nombre FROM usuarios u 
                      JOIN repartidores r ON u.id_usuario = r.id_usuario
                      WHERE u.email = ? AND u.is_verified = 0 AND u.activo = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $nuevo_codigo = sprintf("%06d", mt_rand(1, 999999));
                $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $update_query = "INSERT INTO email_verifications_repartidores (email, codigo, expira) 
                                 VALUES (?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE 
                                 codigo = VALUES(codigo), 
                                 expira = VALUES(expira), 
                                 usado = 0";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(1, $email);
                $update_stmt->bindParam(2, $nuevo_codigo);
                $update_stmt->bindParam(3, $expira);
                
                if ($update_stmt->execute()) {
                    $user_update = "UPDATE usuarios SET verification_code = ? WHERE email = ?";
                    $user_stmt = $this->conn->prepare($user_update);
                    $user_stmt->bindParam(1, $nuevo_codigo);
                    $user_stmt->bindParam(2, $email);
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

    public function puedeReenviarCodigo($email) {
        try {
            $query = "SELECT created_at FROM email_verifications_repartidores 
                      WHERE email = ? 
                      ORDER BY created_at DESC 
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $ultimo_envio = strtotime($row['created_at']);
                $ahora = time();
                
                if (($ahora - $ultimo_envio) < 60) {
                    return false;
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error en puedeReenviarCodigo: " . $e->getMessage());
            return true;
        }
    }

    public function obtenerPedidosActivos($id_repartidor) {
        $query = "SELECT 
                    p.id_pedido, 
                    p.fecha_creacion as fecha, 
                    p.id_estado as estado, 
                    CONCAT(d.calle, ' ', d.numero, ', ', d.colonia, ', ', d.ciudad) as direccion_entrega,
                    n.nombre as restaurante, 
                    u.nombre as cliente
                 FROM pedidos p
                 JOIN negocios n ON p.id_negocio = n.id_negocio
                 JOIN usuarios u ON p.id_usuario = u.id_usuario
                 JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
                 WHERE p.id_repartidor = ? AND p.id_estado IN (3, 4) AND p.tipo_pedido != 'pickup'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_repartidor, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPedidosDisponibles($longitud, $latitud) {
        $query = "SELECT 
                 p.id_pedido, 
                 p.fecha_creacion as fecha, 
                 p.id_negocio,
                 p.tipo_pedido,
                 p.id_estado,
                 CONCAT(d.calle, ' ', d.numero, ', ', d.colonia, ', ', d.ciudad) as direccion_entrega,
                 n.nombre as restaurante, 
                 u.nombre as cliente,
                 ST_Distance_Sphere(
                     POINT(n.longitud, n.latitud),
                     POINT(?, ?)
                 ) as distancia
                 FROM pedidos p
                 JOIN negocios n ON p.id_negocio = n.id_negocio
                 JOIN usuarios u ON p.id_usuario = u.id_usuario
                 JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
                 WHERE p.id_repartidor IS NULL 
                 AND p.id_estado IN (2, 4)
                 AND p.tipo_pedido = 'delivery'
                 ORDER BY distancia ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $longitud);
        $stmt->bindParam(2, $latitud);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerEstadisticas($id_repartidor) {
        $query = "SELECT 
                 COUNT(*) as total_entregas,
                 AVG(TIMESTAMPDIFF(MINUTE, p.fecha_creacion, COALESCE(p.tiempo_entrega_real, p.fecha_creacion))) as tiempo_promedio,
                 IFNULL(AVG(v.calificacion_entrega), 0) as calificacion_promedio
                 FROM pedidos p
                 LEFT JOIN valoraciones v ON p.id_pedido = v.id_pedido
                 WHERE p.id_repartidor = ? AND p.id_estado = 4 AND p.tiempo_entrega_real IS NOT NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_repartidor, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPerfil($id_repartidor) {
        $query = "SELECT u.*, r.tipo_vehiculo, r.numero_licencia, r.activo
                 FROM repartidores r
                 JOIN usuarios u ON r.id_usuario = u.id_usuario
                 WHERE r.id_repartidor = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_repartidor, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarPerfil($id_repartidor, $datos) {
        try {
            $this->conn->beginTransaction();
            
            $queryUsuario = "UPDATE usuarios SET 
                             nombre = ?, apellido = ?, telefono = ?, email = ?
                             WHERE id_usuario = (SELECT id_usuario FROM repartidores WHERE id_repartidor = ?)";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $stmtUsuario->execute([
                $datos['nombre'],
                $datos['apellido'],
                $datos['telefono'],
                $datos['email'],
                $id_repartidor
            ]);
            
            $queryRepartidor = "UPDATE repartidores SET 
                                tipo_vehiculo = ?, numero_licencia = ?
                                WHERE id_repartidor = ?";
            $stmtRepartidor = $this->conn->prepare($queryRepartidor);
            $stmtRepartidor->execute([
                $datos['tipo_vehiculo'],
                $datos['numero_licencia'],
                $id_repartidor
            ]);
            
            $this->conn->commit();
            return true;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Error al actualizar perfil: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerUltimasEntregas($id_repartidor, $limit = 10) {
        $query = "SELECT p.id_pedido, p.fecha_entrega, p.tiempo_entrega, p.ganancia, n.nombre as restaurante
                  FROM pedidos p
                  JOIN negocios n ON p.id_negocio = n.id_negocio
                  WHERE p.id_repartidor = ? AND p.id_estado = 4
                  ORDER BY p.fecha_entrega DESC
                  LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_repartidor, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
