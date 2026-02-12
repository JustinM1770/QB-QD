# 🚀 QuickBite - Guía de Desarrollo en Equipo

## 📱 Estructura del Proyecto

```
quickbite-platform/
├── backend/                    # API PHP + MySQL (YA HECHO)
├── QuickBiteApp/              # App Android para CLIENTES (YA INICIADA)
├── QuickNegocioApp/           # App Android para NEGOCIOS (POR HACER)
└── QuickRepartidorApp/        # App Android para REPARTIDORES (POR HACER)
```

---

## 👥 División de Trabajo Recomendada

### **Amigo 1: QuickBiteApp (Clientes)**
- Login/Registro de clientes
- Ver restaurantes y productos
- Carrito de compras
- Hacer pedidos
- Ver historial de pedidos
- Seguimiento en tiempo real

### **Amigo 2: QuickNegocioApp (Negocios)**
- Login para negocios
- Dashboard con estadísticas
- Gestión de productos
- Recibir y gestionar pedidos
- Actualizar estado de pedidos
- Ver historial de ventas

### **Amigo 3: QuickRepartidorApp (Repartidores)**
- Login para repartidores
- Ver pedidos disponibles
- Aceptar pedidos
- Navegación GPS
- Actualizar estado de entrega
- Historial de entregas

---

## 🗄️ PASO 1: Configurar la Base de Datos

### **Exportar la Base de Datos**

En el servidor, ejecuta:

```bash
# Exportar estructura y datos
mysqldump -u root -p app_delivery > quickbite_database.sql

# O si solo quieres la estructura (sin datos)
mysqldump -u root -p --no-data app_delivery > quickbite_schema.sql
```

### **Importar en tu Computadora Local**

Cada desarrollador debe:

```bash
# 1. Crear la base de datos
mysql -u root -p -e "CREATE DATABASE app_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Importar el dump
mysql -u root -p app_delivery < quickbite_database.sql

# 3. Crear usuario (opcional)
mysql -u root -p -e "CREATE USER 'quickbite'@'localhost' IDENTIFIED BY 'tu_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON app_delivery.* TO 'quickbite'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

### **Configurar archivo .env**

Crea un archivo `.env` en la raíz del proyecto:

```env
# Base de datos
DB_HOST=localhost
DB_NAME=app_delivery
DB_USER=quickbite
DB_PASS=tu_password

# Ambiente
ENVIRONMENT=development

# API URL (cambia según tu configuración local)
API_URL=http://localhost/quickbite/api
```

---

## 📲 PASO 2: Configurar Android Studio

### **Requisitos**
- Android Studio (última versión)
- JDK 17 o superior
- SDK de Android (API 24 mínimo, API 34 recomendado)

### **Clonar el Repositorio**

```bash
git clone <URL_DEL_REPO> quickbite
cd quickbite
```

### **Abrir SOLO tu App**

Cada desarrollador abre SOLO su carpeta:

- **Amigo 1**: Abre `QuickBiteApp/` en Android Studio
- **Amigo 2**: Abre `QuickNegocioApp/` en Android Studio
- **Amigo 3**: Abre `QuickRepartidorApp/` en Android Studio

---

## 🔧 PASO 3: Configurar la API en cada App

En cada app, edita el archivo de configuración de red:

**Archivo**: `app/src/main/java/com/quickbite/app/data/api/NetworkModule.kt`

```kotlin
object NetworkModule {
    // CAMBIAR ESTA URL según tu configuración local
    private const val BASE_URL = "http://10.0.2.2/api/"  // Para emulador Android
    // private const val BASE_URL = "http://192.168.1.X/api/"  // Para dispositivo físico
    // private const val BASE_URL = "https://tudominio.com/api/"  // Para producción

    // ... resto del código
}
```

**URLs según donde pruebes:**
- **Emulador Android**: `http://10.0.2.2/` (apunta a localhost de tu PC)
- **Dispositivo físico**: `http://192.168.1.X/` (IP local de tu PC)
- **Producción**: `https://tudominio.com/`

---

## 🌐 PASO 4: Levantar el Backend (API PHP)

Cada desarrollador necesita el backend corriendo:

```bash
# Opción 1: Usar servidor PHP integrado
cd backend
php -S localhost:8000

# Opción 2: Usar XAMPP/WAMP/MAMP
# Copiar la carpeta a htdocs/ y acceder via http://localhost/quickbite
```

### **Verificar que funciona:**

Abre en el navegador:
```
http://localhost/api/health.php
```

Deberías ver: `{"status": "ok"}`

---

## 🔄 PASO 5: Workflow de Git

### **Estructura de Ramas**

```
main                    # Código estable
├── feature/cliente-*   # Features de la app de clientes
├── feature/negocio-*   # Features de la app de negocios
└── feature/repartidor-*# Features de la app de repartidores
```

### **Comandos Básicos**

```bash
# 1. Crear tu rama para trabajar
git checkout -b feature/cliente-login    # Amigo 1
git checkout -b feature/negocio-dashboard # Amigo 2
git checkout -b feature/repartidor-pedidos # Amigo 3

# 2. Ver cambios
git status

# 3. Guardar cambios
git add .
git commit -m "Implementar login de clientes"

# 4. Subir cambios
git push origin feature/cliente-login

# 5. Actualizar tu rama con cambios de otros
git checkout main
git pull origin main
git checkout feature/cliente-login
git merge main
```

### **Evitar Conflictos**

- Cada uno trabaja en su carpeta (QuickBiteApp, QuickNegocioApp, QuickRepartidorApp)
- Si modificas el backend (carpeta `api/` o `config/`), avisar al equipo
- Hacer `git pull` frecuentemente

---

## 🏗️ PASO 6: Código Compartido entre Apps

Crear una carpeta `shared/` con código común:

```kotlin
// shared/models/Usuario.kt
data class Usuario(
    val id: Int,
    val nombre: String,
    val email: String,
    val telefono: String
)

// shared/api/ApiConfig.kt
object ApiConfig {
    const val BASE_URL = "http://10.0.2.2/api/"
    const val TIMEOUT = 30L
}
```

Cada app puede copiar estos archivos o usar Git submodules.

---

## 🧪 Testing

### **Probar la API con Postman/cURL**

```bash
# Login
curl -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"123456"}'

# Listar negocios
curl http://localhost/api/negocios/listar.php
```

### **Probar en Android**

1. Ejecutar el emulador o conectar dispositivo físico
2. Click en "Run" (Shift+F10)
3. Seleccionar dispositivo
4. Probar la app

---

## 📝 Convenciones de Código

### **Kotlin**
- Usar camelCase para variables: `nombreUsuario`
- Usar PascalCase para clases: `UsuarioViewModel`
- Archivos separados por funcionalidad: `LoginScreen.kt`, `LoginViewModel.kt`

### **Commits**
```
feat: Agregar pantalla de login
fix: Corregir error en carrito
refactor: Mejorar estructura de ViewModels
docs: Actualizar README
```

---

## 🚨 Problemas Comunes

### **Error de conexión a API**

- Verificar que el backend esté corriendo
- Verificar la URL en `NetworkModule.kt`
- Verificar permisos de internet en `AndroidManifest.xml`:
  ```xml
  <uses-permission android:name="android.permission.INTERNET" />
  ```

### **Gradle Sync Failed**

```bash
# Limpiar proyecto
./gradlew clean

# En Android Studio: File > Invalidate Caches > Invalidate and Restart
```

### **Base de datos no conecta**

- Verificar credenciales en `.env`
- Verificar que MySQL esté corriendo
- Verificar que la base de datos exista: `SHOW DATABASES;`

---

## 🎯 Checklist para Empezar

- [ ] Clonar repositorio
- [ ] Importar base de datos
- [ ] Configurar archivo `.env`
- [ ] Abrir tu app en Android Studio
- [ ] Configurar URL de API en `NetworkModule.kt`
- [ ] Levantar backend PHP
- [ ] Probar que `/api/health.php` funciona
- [ ] Correr app en emulador
- [ ] Crear tu rama de trabajo
- [ ] ¡Empezar a programar!

---

## 📚 Recursos Útiles

- [Documentación Kotlin](https://kotlinlang.org/docs/home.html)
- [Jetpack Compose](https://developer.android.com/jetpack/compose)
- [Retrofit para API calls](https://square.github.io/retrofit/)
- [Material Design 3](https://m3.material.io/)

---

## 💬 Comunicación

Mantener comunicación constante:
- Avisar cuando terminen una funcionalidad importante
- Compartir si cambian algo en el backend
- Preguntar si no están seguros de cómo hacer algo
- Hacer code review de pull requests

---

¡Éxito con el proyecto! 🚀
