# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.0.0] - 2026-01-17

### Added
- **Sistema de Pedidos Completo**
  - Flujo de 7 estados: pendiente, confirmado, en preparación, listo, en camino, entregado, cancelado
  - Seguimiento en tiempo real con WebSockets
  - Pedidos programados para fecha/hora específica
  - Tipo de pedido: delivery o pickup

- **Integración de Pagos**
  - Stripe: Tarjetas de crédito/débito internacionales
  - MercadoPago: Tarjetas mexicanas, OXXO, transferencia
  - Wallet interno con saldo prepagado
  - Pago en efectivo contra entrega

- **Panel de Administración para Negocios**
  - Dashboard con estadísticas en tiempo real
  - Gestión completa de menú y categorías
  - Parser de menú con IA (Google Gemini)
  - Sistema de wallet y retiro de ganancias
  - Promociones y cupones

- **Panel de Repartidores**
  - Vista de pedidos disponibles cercanos
  - Navegación GPS integrada (Mapbox)
  - Sistema de gamificación con niveles y bonos
  - Wallet y solicitud de retiros

- **Sistema de Membresías**
  - QuickBite Club para clientes ($49/mes): envío gratis, sin cargo de servicio
  - Membresía Premium para negocios ($199/mes): comisión reducida 8%

- **Programa de Puntos y Recompensas**
  - Acumulación de puntos por pedidos
  - Sistema de referidos con códigos únicos
  - Bonos por primera compra

- **Progressive Web App (PWA)**
  - Instalable en dispositivos móviles
  - Soporte offline con Service Worker
  - Notificaciones push

- **Bot de WhatsApp**
  - Notificaciones automáticas de estado de pedido
  - Integración con Baileys (WhatsApp Web)

- **Autenticación**
  - Registro con verificación de email
  - Login con Google OAuth 2.0
  - Recuperación de contraseña

### Security
- Protección CSRF en todos los formularios
- Prepared statements en todas las queries (prevención SQL Injection)
- Password hashing con bcrypt
- Rate limiting en APIs públicas
- Headers de seguridad HTTP (X-Frame-Options, HSTS, X-XSS-Protection)
- Sesiones seguras (HttpOnly, SameSite=Strict, Secure)
- Variables de entorno para credenciales sensibles

### Infrastructure
- Configuración para Apache con mod_rewrite
- Soporte para Cloudflare (headers CORS)
- Scripts de migración SQL versionados
- GitHub Actions CI/CD pipeline
- PHPUnit para tests automatizados
- Health check endpoint para monitoreo

### Documentation
- README.md profesional con instrucciones de instalación
- .env.example con todas las variables documentadas
- Constantes de estados de pedido centralizadas
- Comentarios en código crítico

---

## Tipos de Cambios

- `Added` para nuevas funcionalidades
- `Changed` para cambios en funcionalidades existentes
- `Deprecated` para funcionalidades que serán removidas
- `Removed` para funcionalidades removidas
- `Fixed` para corrección de bugs
- `Security` para vulnerabilidades corregidas

---

## Roadmap Futuro

### [1.1.0] - Planificado
- [ ] Integración con más pasarelas de pago (PayPal)
- [ ] App nativa iOS/Android con React Native
- [ ] Sistema de reseñas con fotos
- [ ] Chat en tiempo real cliente-repartidor

### [1.2.0] - Planificado
- [ ] Marketplace multi-tenant
- [ ] API pública para integraciones
- [ ] Dashboard de analytics avanzado
- [ ] Sistema de suscripciones recurrentes para negocios
