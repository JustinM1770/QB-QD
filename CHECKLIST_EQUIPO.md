# ✅ Checklist para Trabajar en Equipo

## 📋 Para Ti (Líder del Proyecto)

### Ahora Mismo

- [ ] Exportar la base de datos
  ```bash
  cd /var/www/html
  ./scripts/exportar_database.sh
  ```

- [ ] Crear repositorio en GitHub/GitLab
  ```bash
  git remote add origin https://github.com/tu-usuario/quickbite.git
  git push -u origin main
  ```

- [ ] Compartir con tus amigos:
  - [ ] Link del repositorio
  - [ ] Archivo `.sql` de la base de datos
  - [ ] Decirles que lean `INICIO_RAPIDO.md`

### División del Trabajo

Asigna las apps:

- [ ] **Amigo 1**: QuickBiteApp (Clientes) - Ya está iniciada
- [ ] **Amigo 2**: QuickNegocioApp (Negocios) - Crear desde cero
- [ ] **Amigo 3**: QuickRepartidorApp (Repartidores) - Crear desde cero

---

## 👥 Para cada Desarrollador

### 1. Configuración Inicial

- [ ] Clonar el repositorio
  ```bash
  git clone <URL_REPO>
  cd quickbite
  ```

- [ ] Instalar MySQL (si no lo tienen)
  - **Windows**: XAMPP o WAMP
  - **Mac**: MAMP o Homebrew
  - **Linux**: `sudo apt install mysql-server`

- [ ] Crear base de datos e importar
  ```bash
  mysql -u root -p -e "CREATE DATABASE app_delivery;"
  mysql -u root -p app_delivery < quickbite_database_*.sql
  ```

- [ ] Copiar y configurar .env
  ```bash
  cp .env.example .env
  # Editar con tus credenciales
  ```

- [ ] Verificar que el backend funciona
  ```bash
  php -S localhost:8000
  # Abrir: http://localhost:8000/api/health.php
  ```

### 2. Configurar Android Studio

- [ ] Descargar Android Studio (última versión)
- [ ] Instalar Android SDK (API 24-34)
- [ ] Abrir SOLO tu carpeta de app:
  - Amigo 1: `QuickBiteApp/`
  - Amigo 2: `QuickNegocioApp/`
  - Amigo 3: `QuickRepartidorApp/`

- [ ] Configurar URL de API en `NetworkModule.kt`
  ```kotlin
  private const val BASE_URL = "http://10.0.2.2/api/"
  ```

- [ ] Sincronizar Gradle (puede tardar varios minutos)

### 3. Crear Rama de Trabajo

- [ ] Crear tu rama personal
  ```bash
  # Amigo 1
  git checkout -b feature/cliente-dashboard

  # Amigo 2
  git checkout -b feature/negocio-setup

  # Amigo 3
  git checkout -b feature/repartidor-setup
  ```

### 4. Primer Commit de Prueba

- [ ] Hacer un cambio pequeño (ejemplo: cambiar un color)
- [ ] Guardar cambios
  ```bash
  git add .
  git commit -m "Test: primer commit de [tu-nombre]"
  git push origin [tu-rama]
  ```

- [ ] Verificar que se subió correctamente en GitHub

---

## 🔄 Workflow Diario

### Al Empezar el Día

```bash
# 1. Actualizar código
git checkout main
git pull origin main

# 2. Volver a tu rama
git checkout feature/tu-rama

# 3. Traer cambios nuevos
git merge main

# 4. Levantar backend
php -S localhost:8000

# 5. Abrir Android Studio y trabajar
```

### Al Terminar el Día

```bash
# 1. Guardar cambios
git add .
git commit -m "Implementar [funcionalidad]"

# 2. Subir a tu rama
git push origin feature/tu-rama

# 3. (Opcional) Crear Pull Request en GitHub
```

---

## 📱 Desarrollo por Prioridad

### Amigo 1: QuickBiteApp (Clientes)

**Semana 1:**
- [ ] Pantalla de Login funcional
- [ ] Pantalla de Registro
- [ ] Listar negocios (Home)
- [ ] Ver productos de un negocio

**Semana 2:**
- [ ] Carrito de compras
- [ ] Proceso de checkout
- [ ] Ver pedidos activos

**Semana 3:**
- [ ] Perfil de usuario
- [ ] Historial de pedidos
- [ ] Seguimiento en tiempo real

### Amigo 2: QuickNegocioApp (Negocios)

**Semana 1:**
- [ ] Crear proyecto Android desde cero
- [ ] Pantalla de Login funcional
- [ ] Dashboard básico
- [ ] Listar pedidos pendientes

**Semana 2:**
- [ ] Aceptar/Rechazar pedidos
- [ ] Actualizar estado de pedidos
- [ ] Listar productos del negocio

**Semana 3:**
- [ ] Agregar/Editar productos
- [ ] Estadísticas de ventas
- [ ] Perfil del negocio

### Amigo 3: QuickRepartidorApp (Repartidores)

**Semana 1:**
- [ ] Crear proyecto Android desde cero
- [ ] Pantalla de Login funcional
- [ ] Dashboard básico
- [ ] Ver pedidos disponibles

**Semana 2:**
- [ ] Aceptar pedido
- [ ] Integrar Google Maps
- [ ] Mostrar ruta en mapa
- [ ] Actualizar estado de entrega

**Semana 3:**
- [ ] Tracking de ubicación en tiempo real
- [ ] Llamar al cliente
- [ ] Historial de entregas
- [ ] Perfil del repartidor

---

## 🚨 Reglas Importantes

### ❌ NO Hacer

- ❌ **NO** trabajar directamente en la rama `main`
- ❌ **NO** hacer `git push --force`
- ❌ **NO** modificar archivos de otra app sin avisar
- ❌ **NO** subir archivos `.env` o credenciales
- ❌ **NO** subir archivos grandes (imágenes sin optimizar, PDFs, etc.)

### ✅ SÍ Hacer

- ✅ **SÍ** trabajar en tu propia rama
- ✅ **SÍ** hacer commits frecuentes con mensajes claros
- ✅ **SÍ** hacer `git pull` antes de empezar a trabajar
- ✅ **SÍ** avisar al equipo si modificas el backend (API)
- ✅ **SÍ** pedir ayuda si te atoras

---

## 💬 Comunicación

### Grupo de WhatsApp/Telegram

Crear grupo y usarlo para:
- Avisar cuando terminas una funcionalidad
- Compartir problemas que encuentres
- Coordinar reuniones de seguimiento
- Celebrar logros

### Reuniones Sugeridas

- **Lunes**: Planear la semana (30 min)
- **Miércoles**: Checkpoint de avance (15 min)
- **Viernes**: Demo de lo que hicieron (30 min)

---

## 🎯 Meta Final

**Objetivo**: Tener 3 apps funcionales que trabajen juntas

**Hitos**:
1. **Semana 1**: Login funcional en las 3 apps
2. **Semana 2**: Flujo básico de pedido (Cliente → Negocio → Repartidor)
3. **Semana 3**: Funcionalidades completas y pulidas
4. **Semana 4**: Testing, corrección de bugs, preparar para producción

---

## 📚 Recursos

- [Kotlin Docs](https://kotlinlang.org/docs/home.html)
- [Jetpack Compose](https://developer.android.com/jetpack/compose/tutorial)
- [Retrofit](https://square.github.io/retrofit/)
- [Material Design 3](https://m3.material.io/)
- [Git Cheat Sheet](https://education.github.com/git-cheat-sheet-education.pdf)

---

## ❓ Preguntas Frecuentes

**P: ¿Qué hago si tengo un conflicto en Git?**
```bash
# 1. Ver qué archivos tienen conflicto
git status

# 2. Abrir el archivo y resolver manualmente
# 3. Marcar como resuelto
git add archivo_conflictivo.kt

# 4. Completar el merge
git commit -m "Resolver conflicto en archivo_conflictivo"
```

**P: ¿Cómo pruebo en dispositivo físico en vez de emulador?**
1. Activar "Opciones de desarrollador" en tu Android
2. Activar "Depuración USB"
3. Conectar con cable USB
4. Seleccionar tu dispositivo en Android Studio
5. Cambiar URL de API a tu IP local (ej: `http://192.168.1.5/api/`)

**P: ¿Puedo usar Flutter o React Native en vez de Kotlin?**
Sí, pero tendrían que rehacerlo todo. Kotlin nativo es más rápido para empezar si ya tienen código Android.

---

¡Éxito con el proyecto! 🚀🎉
