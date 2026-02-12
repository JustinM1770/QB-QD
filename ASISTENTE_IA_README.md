# 🤖 Asistente IA QuickBite - Documentación

## ✨ Características Profesionales

### 1. Análisis de Ventas con IA
- 📊 **Estadísticas en Tiempo Real**: Pedidos, ingresos, ticket promedio
- ⭐ **Top Productos**: Los 3 productos más vendidos
- �� **Análisis por Categoría**: Rendimiento de cada categoría
- 👥 **Métricas de Clientes**: Clientes únicos y recurrentes

### 2. Recomendaciones Personalizadas
- 💡 **IA Gemini 2.0 Flash**: Recomendaciones basadas en datos reales
- 🎯 **Específicas y Accionables**: No genéricas, adaptadas a tu negocio
- 📊 **Categorizadas**: Menu, Marketing, Precios, Operaciones
- 🚀 **Nivel de Impacto**: Alto, Medio, Bajo

### 3. Insights del Negocio
- ⏰ **Horarios Pico**: Cuándo vendes más
- 📅 **Días Populares**: Mejores días de la semana
- 🍽️ **Combos Frecuentes**: Productos que se compran juntos
- 💯 **Tasa de Retención**: % de clientes que regresan

### 4. Chat Inteligente
El asistente puede responder preguntas como:
- "¿Qué puedo hacer para vender más?"
- "¿Cuál es mi plato más vendido?"
- "¿En qué horario vendo más?"
- "¿Cómo están mis ingresos este mes?"
- "¿Qué productos debo destacar?"

### 5. Subida de Menú con IA
- 📸 **Análisis Automático**: Sube una foto del menú
- 🤖 **Extracción con Gemini**: Productos, precios, descripciones, calorías
- 💾 **Guardado Automático**: Directo a base de datos
- ✏️ **Editable**: Revisa antes de guardar

### 6. Optimización de Menú
- ❌ **Productos a Eliminar**: Bajo rendimiento
- ⭐ **Productos a Destacar**: Alto potencial
- 💰 **Ajustes de Precio**: Recomendaciones basadas en ventas
- ✨ **Nuevos Productos**: Sugerencias personalizadas

---

## 🚀 Cómo Usar

### Acceso
```
https://quickbite.com.mx/admin/ai_assistant.html?negocio_id=TU_ID
```

### Flujo de Trabajo

1. **Primera Vez**
   - Ingresa el ID de tu negocio
   - El asistente carga tus datos automáticamente

2. **Dashboard Lateral**
   - Métricas rápidas actualizadas
   - Pedidos últimos 30 días
   - Ingresos totales
   - Top producto

3. **Interacción por Chat**
   - Escribe preguntas naturales
   - O usa botones de acción rápida
   - El asistente detecta tu intención

4. **Análisis Avanzados**
   - Click en "Análisis Rápido"
   - Recibe reporte completo
   - Visualiza estadísticas

5. **Recomendaciones IA**
   - Click en "Recomendaciones"
   - Gemini analiza tus datos
   - Recibe 5 recomendaciones específicas

---

## 📁 Archivos del Sistema

### Frontend
- `/admin/ai_assistant.html` - Interfaz principal del asistente
- `/admin/chat_menu.html` - Redirección (legacy)

### Backend (APIs)
- `/admin/ai_assistant_api.php` - API principal del asistente
  - `analyze_sales` - Análisis de ventas
  - `get_recommendations` - Recomendaciones con IA
  - `chat` - Chat conversacional
  - `get_insights` - Insights del negocio
  - `optimize_menu` - Optimización de menú

- `/admin/menu_parser_endpoint.php` - Parser de imágenes de menú
- `/admin/save_menu_to_db.php` - Guardar menú en BD
- `/admin/get_menu_from_db.php` - Obtener menú actual
- `/admin/gemini_menu_parser.php` - Clase GeminiMenuParser

---

## 🔑 Configuración

### API de Gemini
La API key está configurada en:
```php
// /admin/ai_assistant_api.php
$GEMINI_API_KEY = getenv('GEMINI_API_KEY') ?: '';
```

**Gratis**: 15 requests/min, 1M tokens/día  
**Modelo**: gemini-2.0-flash-exp (el más rápido)

### Base de Datos
Tablas utilizadas:
- `negocios` - Información del negocio
- `productos` - Productos del menú
- `categorias` - Categorías de productos
- `pedidos` - Pedidos realizados
- `detalle_pedidos` - Detalles de cada pedido

---

## 📊 Endpoints de la API

### 1. Análisis de Ventas
```javascript
POST /admin/ai_assistant_api.php
{
  "action": "analyze_sales",
  "negocio_id": 123
}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "productos": [...],
    "estadisticas": {
      "total_pedidos": 150,
      "clientes_unicos": 75,
      "ingresos_totales": 45000,
      "ticket_promedio": 300
    },
    "categorias": [...],
    "top_3": [...]
  }
}
```

### 2. Recomendaciones
```javascript
POST /admin/ai_assistant_api.php
{
  "action": "get_recommendations",
  "negocio_id": 123
}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "recomendaciones": [
      {
        "titulo": "Crear combo de desayuno",
        "descripcion": "Combina café con pan...",
        "impacto": "alto",
        "categoria": "menu"
      }
    ]
  }
}
```

### 3. Chat
```javascript
POST /admin/ai_assistant_api.php
{
  "action": "chat",
  "negocio_id": 123,
  "message": "¿Cuál es mi producto más vendido?"
}
```

### 4. Insights
```javascript
POST /admin/ai_assistant_api.php
{
  "action": "get_insights",
  "negocio_id": 123
}
```

### 5. Optimización de Menú
```javascript
POST /admin/ai_assistant_api.php
{
  "action": "optimize_menu",
  "negocio_id": 123
}
```

---

## 🎨 Características de UI/UX

### Diseño
- ✨ **Gradientes Modernos**: Purple/Blue (#667eea → #764ba2)
- 🎯 **Animaciones Fluidas**: Slide-in, hover effects
- 📱 **Responsive**: Mobile-first design
- 🌈 **Color Coding**: Cada tipo de mensaje con su color

### Interacciones
- 💬 **Chat Conversacional**: Avatar bot/usuario
- ⚡ **Acciones Rápidas**: Botones de 1-click
- 📊 **Cards Informativos**: Estadísticas visuales
- 🎨 **Badges de Impacto**: Alto/Medio/Bajo

### Sidebar
- 📈 **Métricas en Vivo**: Actualizadas en tiempo real
- 🖱️ **Click para Detalles**: Cada insight es clickeable
- 🎯 **Acceso Rápido**: Recomendaciones directas

---

## 🔐 Seguridad

- ✅ **Validación de Negocio ID**: Siempre requerido
- ✅ **Prepared Statements**: Prevención SQL Injection
- ✅ **Transacciones BD**: Integridad de datos
- ✅ **Headers CORS**: Configurados correctamente

---

## 📈 Métricas de Rendimiento

### Velocidad
- 🚀 **Análisis de Ventas**: < 1 segundo
- 🤖 **Recomendaciones IA**: 3-5 segundos
- 📸 **Parser de Menú**: 5-10 segundos
- 💬 **Chat Response**: 2-4 segundos

### Límites
- Gemini API: 15 requests/min
- Base de Datos: Sin límite
- Análisis: Últimos 30 días

---

## 🛠️ Troubleshooting

### Error: "negocio_id requerido"
- Asegúrate de pasar `?negocio_id=X` en la URL
- O guárdalo en sessionStorage

### Error: "Error de Gemini"
- Verifica que la API key sea válida
- Revisa límites de rate (15/min)

### No hay datos
- Verifica que el negocio tenga pedidos
- Asegúrate que `estado_actual` no sea 'cancelado'

### Análisis vacío
- Necesitas al menos 1 pedido en los últimos 30 días

---

## 🎯 Roadmap Futuro

- [ ] Gráficas visuales (Chart.js)
- [ ] Exportar reportes PDF
- [ ] Alertas automáticas
- [ ] Comparación de períodos
- [ ] Predicciones de demanda
- [ ] Análisis de competencia
- [ ] Integración con redes sociales

---

## 📞 Soporte

Para dudas o mejoras:
- Email: contacto@quickbite.com.mx
- GitHub Issues: quickbite-name/issues

---

**Desarrollado con ❤️ usando Gemini 2.0 Flash**  
**Versión: 1.0.0**  
**Última actualización: 4 de diciembre de 2025**
