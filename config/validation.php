<?php
/**
 * Sistema de Validacion de Entrada para QuickBite
 *
 * Proporciona funciones para validar y sanitizar datos de entrada
 * siguiendo las mejores practicas de seguridad OWASP.
 *
 * USO:
 * require_once 'config/validation.php';
 *
 * $email = validate_email($_POST['email']);
 * $phone = validate_phone($_POST['telefono']);
 * $price = validate_decimal($_POST['precio'], 0, 999999.99);
 */

/**
 * Validar y sanitizar email
 * @param string $email Email a validar
 * @return string|false Email sanitizado o false si es invalido
 */
function validate_email($email) {
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    // Validar longitud maxima
    if (strlen($email) > 254) {
        return false;
    }

    return strtolower($email);
}

/**
 * Validar telefono mexicano
 * @param string $phone Telefono a validar
 * @return string|false Telefono sanitizado (10 digitos) o false
 */
function validate_phone($phone) {
    // Remover todo excepto digitos
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Mexico: 10 digitos
    if (strlen($phone) === 10) {
        return $phone;
    }

    // Con codigo de pais +52
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '52') {
        return substr($phone, 2);
    }

    return false;
}

/**
 * Validar entero dentro de rango
 * @param mixed $value Valor a validar
 * @param int $min Minimo permitido
 * @param int $max Maximo permitido
 * @return int|false Entero validado o false
 */
function validate_int($value, $min = PHP_INT_MIN, $max = PHP_INT_MAX) {
    $options = [
        'options' => [
            'min_range' => $min,
            'max_range' => $max
        ]
    ];

    return filter_var($value, FILTER_VALIDATE_INT, $options);
}

/**
 * Validar decimal/precio
 * @param mixed $value Valor a validar
 * @param float $min Minimo permitido
 * @param float $max Maximo permitido
 * @return float|false Decimal validado o false
 */
function validate_decimal($value, $min = 0, $max = PHP_FLOAT_MAX) {
    $value = filter_var($value, FILTER_VALIDATE_FLOAT);

    if ($value === false) {
        return false;
    }

    if ($value < $min || $value > $max) {
        return false;
    }

    return round($value, 2);
}

/**
 * Sanitizar texto para HTML (prevenir XSS)
 * @param string $text Texto a sanitizar
 * @param int $max_length Longitud maxima (0 = sin limite)
 * @return string Texto sanitizado
 */
function sanitize_text($text, $max_length = 0) {
    $text = trim($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if ($max_length > 0 && mb_strlen($text) > $max_length) {
        $text = mb_substr($text, 0, $max_length);
    }

    return $text;
}

/**
 * Sanitizar texto plano (sin HTML)
 * @param string $text Texto a sanitizar
 * @param int $max_length Longitud maxima
 * @return string Texto limpio
 */
function sanitize_plain_text($text, $max_length = 0) {
    $text = strip_tags($text);
    $text = trim($text);

    // Remover caracteres de control excepto espacios y saltos de linea
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    if ($max_length > 0 && mb_strlen($text) > $max_length) {
        $text = mb_substr($text, 0, $max_length);
    }

    return $text;
}

/**
 * Validar nombre (persona/negocio)
 * @param string $name Nombre a validar
 * @param int $min_length Longitud minima
 * @param int $max_length Longitud maxima
 * @return string|false Nombre sanitizado o false
 */
function validate_name($name, $min_length = 2, $max_length = 100) {
    $name = sanitize_plain_text($name, $max_length);

    if (mb_strlen($name) < $min_length) {
        return false;
    }

    // Permitir letras (incluyendo acentos), espacios, guiones y apostrofes
    if (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $name)) {
        return false;
    }

    return $name;
}

/**
 * Validar password (requisitos de seguridad)
 * @param string $password Password a validar
 * @param int $min_length Longitud minima (default 8)
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password($password, $min_length = 8) {
    $errors = [];

    if (strlen($password) < $min_length) {
        $errors[] = "La contrasena debe tener al menos $min_length caracteres";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Debe contener al menos una letra mayuscula";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Debe contener al menos una letra minuscula";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Debe contener al menos un numero";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validar URL
 * @param string $url URL a validar
 * @param array $allowed_schemes Esquemas permitidos
 * @return string|false URL validada o false
 */
function validate_url($url, $allowed_schemes = ['http', 'https']) {
    $url = trim($url);

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parsed = parse_url($url);
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], $allowed_schemes)) {
        return false;
    }

    return $url;
}

/**
 * Validar codigo postal mexicano
 * @param string $cp Codigo postal
 * @return string|false CP validado (5 digitos) o false
 */
function validate_postal_code($cp) {
    $cp = preg_replace('/[^0-9]/', '', $cp);

    if (strlen($cp) !== 5) {
        return false;
    }

    // Validar rango de CPs mexicanos (01000-99999)
    $cp_int = (int)$cp;
    if ($cp_int < 1000 || $cp_int > 99999) {
        return false;
    }

    return str_pad($cp, 5, '0', STR_PAD_LEFT);
}

/**
 * Validar fecha
 * @param string $date Fecha a validar
 * @param string $format Formato esperado
 * @return string|false Fecha formateada o false
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);

    if ($d && $d->format($format) === $date) {
        return $date;
    }

    return false;
}

/**
 * Validar archivo subido
 * @param array $file Elemento de $_FILES
 * @param array $allowed_types MIME types permitidos
 * @param int $max_size Tamano maximo en bytes
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_upload($file, $allowed_types = [], $max_size = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Parametros de archivo invalidos'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['valid' => false, 'error' => 'El archivo excede el tamano maximo permitido'];
        case UPLOAD_ERR_NO_FILE:
            return ['valid' => false, 'error' => 'No se subio ningun archivo'];
        default:
            return ['valid' => false, 'error' => 'Error desconocido al subir archivo'];
    }

    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'El archivo excede el tamano maximo de ' . round($max_size/1048576, 2) . 'MB'];
    }

    if (!empty($allowed_types)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowed_types)) {
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido'];
        }
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Validar coordenadas geograficas
 * @param float $lat Latitud
 * @param float $lng Longitud
 * @return array|false [lat, lng] validados o false
 */
function validate_coordinates($lat, $lng) {
    $lat = filter_var($lat, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($lng, FILTER_VALIDATE_FLOAT);

    if ($lat === false || $lng === false) {
        return false;
    }

    // Rango valido: lat -90 a 90, lng -180 a 180
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }

    // Validar que esten dentro de Mexico aproximadamente
    // Lat: 14.5 a 32.7, Lng: -118.5 a -86.7
    // (Comentado para permitir cualquier ubicacion)

    return [$lat, $lng];
}

/**
 * Validar ID de base de datos
 * @param mixed $id ID a validar
 * @return int|false ID validado o false
 */
function validate_id($id) {
    return validate_int($id, 1, PHP_INT_MAX);
}

/**
 * Clase validadora para validaciones encadenadas
 */
class Validator {
    private $data;
    private $errors = [];
    private $validated = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Validar campo requerido
     */
    public function required($field, $message = null) {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "El campo $field es requerido";
        }
        return $this;
    }

    /**
     * Validar email
     */
    public function email($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $result = validate_email($this->data[$field]);
            if ($result === false) {
                $this->errors[$field] = $message ?? "El email no es valido";
            } else {
                $this->validated[$field] = $result;
            }
        }
        return $this;
    }

    /**
     * Validar telefono
     */
    public function phone($field, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $result = validate_phone($this->data[$field]);
            if ($result === false) {
                $this->errors[$field] = $message ?? "El telefono no es valido";
            } else {
                $this->validated[$field] = $result;
            }
        }
        return $this;
    }

    /**
     * Validar longitud minima
     */
    public function min($field, $length, $message = null) {
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "El campo $field debe tener al menos $length caracteres";
        }
        return $this;
    }

    /**
     * Validar longitud maxima
     */
    public function max($field, $length, $message = null) {
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? "El campo $field no puede tener mas de $length caracteres";
        }
        return $this;
    }

    /**
     * Validar numero entero
     */
    public function integer($field, $min = null, $max = null, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $result = validate_int($this->data[$field], $min ?? PHP_INT_MIN, $max ?? PHP_INT_MAX);
            if ($result === false) {
                $this->errors[$field] = $message ?? "El campo $field debe ser un numero entero valido";
            } else {
                $this->validated[$field] = $result;
            }
        }
        return $this;
    }

    /**
     * Validar decimal
     */
    public function decimal($field, $min = 0, $max = null, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $result = validate_decimal($this->data[$field], $min, $max ?? PHP_FLOAT_MAX);
            if ($result === false) {
                $this->errors[$field] = $message ?? "El campo $field debe ser un numero decimal valido";
            } else {
                $this->validated[$field] = $result;
            }
        }
        return $this;
    }

    /**
     * Verificar si la validacion paso
     */
    public function passes() {
        return empty($this->errors);
    }

    /**
     * Verificar si la validacion fallo
     */
    public function fails() {
        return !empty($this->errors);
    }

    /**
     * Obtener errores
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Obtener primer error
     */
    public function firstError() {
        return reset($this->errors) ?: null;
    }

    /**
     * Obtener datos validados
     */
    public function validated() {
        return $this->validated;
    }
}

/**
 * Helper para crear validador
 */
function validate(array $data) {
    return new Validator($data);
}
