# Extension incremental compatible - Fase 1

## 1. Analisis del estado actual

### Modulos confirmados en uso

- `index.php`: landing/home con acceso al flujo actual.
- `login.php`, `register.php`, `logout.php`: autenticacion por roles con sesiones y CSRF.
- `cliente.php`: solicitud de servicios, historial, puntos y acceso al chat.
- `barbero.php`: disponibilidad, aceptacion/finalizacion de servicios e historial.
- `admin/dashboard.php`: metricas, gestion de usuarios, productos, verificaciones y configuracion.
- `productos.php`, `carrito.php`, `checkout.php`, `confirmar_pago.php`, `mercadopago_pago.php`: tienda y pagos.
- `chat_servicio.php`, `chat_poll.php`, `chat_send.php`, `classes/ServiceChat.php`: chat transaccional con polling y estados.
- `classes/Service.php`: flujo principal de servicios.
- `classes/Rewards.php`, `classes/LoyaltyManager.php`: puntos, historial y canjes.
- `classes/MonetizationManager.php`, `classes/SystemSettings.php`: base de monetizacion y configuracion global.

### Relaciones principales

- `login.php` autentica con `classes/User.php` y redirige por rol a `cliente.php`, `barbero.php` o `admin/dashboard.php`.
- `cliente.php` solicita/cancela servicios usando `classes/Service.php`.
- `barbero.php` acepta/finaliza servicios usando `classes/Service.php`.
- `classes/Service.php` dispara puntos y monetizacion al completar un servicio.
- `chat_*` y `classes/ServiceChat.php` dependen del `servicio_id` y del estado del servicio.
- `admin/dashboard.php` ya consume configuracion global, monetizacion y puntos.

### Tablas observadas o inferidas por el codigo

- Existentes base: `users`, `clientes`, `barberos`, `servicios`, `productos`, `producto_imagenes`, `pedidos`, `pedido_detalles`, `documentos_verificacion`, `recompensas`, `canjes_recompensas`, `puntos_historial`.
- Extensiones ya contempladas: `system_settings`, `barber_monetization_profiles`, `barber_subscriptions`, `service_commissions`, `service_payment_states`.
- Chat y confianza: `service_chats`, `service_chat_messages`, `service_chat_reports`, `barber_client_blocks`, `client_behavior_flags`, `client_behavior_events`, `service_chat_incidents`.

### Autenticacion actual

- Email/password contra `users`.
- Validacion de `is_active`.
- Sesion PHP tradicional con CSRF.
- Redireccion por rol sin middleware complejo.

### Flujo actual de servicios

1. Cliente crea solicitud en `servicios` con estado `pendiente`.
2. Barbero acepta y el servicio pasa a `aceptado`.
3. Se abre chat transaccional por `servicio_id`.
4. Barbero finaliza y el servicio pasa a `completado`.
5. Se asignan puntos y se registra monetizacion si las tablas existen.

### Puntos seguros de integracion

- `system_settings` para feature flags y parametros configurables.
- `classes/Service.php` para hooks de monetizacion, puntos, zonas y alertas.
- `classes/ServiceChat.php` para realtime, badges, sonido, vibracion y reportes.
- `admin/dashboard.php` para configuracion y metricas.
- SQL incremental independiente para no tocar datos viejos.

### Riesgos de compatibilidad detectados

- Las pantallas principales mezclan HTML, logica y SQL en el mismo archivo.
- Hay dependencias directas a nombres de tablas/campos actuales.
- El matching por zona aun no aparece integrado en consultas de cliente/barbero.
- El realtime actual es polling; migrarlo agresivamente a WebSocket si seria riesgoso hoy.

## 2. Mapa de impacto

### Archivos nuevos propuestos

- `database_phase1_global_settings_extension.sql`
- `docs/extension_roadmap_phase1.md`
- Futuras fases sugeridas:
  - `classes/ZoneManager.php`
  - `classes/NotificationManager.php`
  - `classes/TrustSafetyManager.php`
  - `database_phase2_zone_matching.sql`
  - `database_phase3_notifications.sql`

### Archivos existentes modificados en esta fase

- `classes/SystemSettings.php`
- `admin/dashboard.php`

### Tablas nuevas o tocadas minimamente

- En esta fase no se alteran tablas operativas existentes.
- Solo se amplian claves configurables en `system_settings`.

### Partes potencialmente afectadas

- Solo el guardado de configuracion global del admin.
- No se cambian rutas, formularios de login, flujo de servicios, tienda, chat ni autenticacion.

### Garantia de compatibilidad

- Todos los cambios nuevos son opt-in por configuracion.
- Si `system_settings` no existe, `SystemSettings` sigue usando defaults.
- No se eliminan columnas, rutas ni nombres previos.
- No se cambia el comportamiento del servicio, login o chat actual por defecto.

## 3. Estrategia tecnica menos invasiva

### Monetizacion

- Mantener `MonetizationManager` como capa central.
- Guardar snapshots por servicio en `service_commissions`.
- Usar `system_settings` para precios, trial, comisiones y estados futuros.

### Puntos

- Mantener `LoyaltyManager` como fuente de verdad.
- Seguir registrando historial para no recalcular servicios viejos.

### Zona

- Implementar despues con tablas nuevas y fallback a comportamiento actual si no hay zona.
- Modo `preferred`: prioriza zona y cae al listado global.
- Modo `strict`: restringe a zona solo si la feature esta activada.

### Tiempo real y notificaciones

- Mantener polling actual y mejorar alrededor de esa base.
- Preparar flags para sonido, vibracion, badges y futuras push sin romper chat actual.

### Admin extendido

- Usar `system_settings` para configurar paneles y switches.
- Seguir sumando metricas por consultas compatibles con la BD actual.

### Responsive/PWA

- Extender assets y flags primero; ajustes visuales despues por pantalla.

### Seguridad y reportes

- Reutilizar `ServiceChat.php` y tablas de incidentes ya creadas.
- Agregar futuras capas como extensiones, no como reemplazo del flujo actual.

## 4. Implementacion realizada en esta fase

### Modulo 1. Configuracion global del sistema

- Se ampliaron defaults en `SystemSettings` para:
  - zona
  - notificaciones
  - seguridad/confianza
  - PWA/responsive
  - paneles configurables del admin
- Se extendio el formulario de `admin/dashboard.php` para administrar esas claves.
- Se agrego la migracion `database_phase1_global_settings_extension.sql`.

## 5. SQL o migraciones necesarias

- Ejecutar `database_monetization_loyalty_update.sql` si aun no existe `system_settings`.
- Ejecutar `database_phase1_global_settings_extension.sql` para insertar las nuevas claves globales.

## 6. Compatibilidad

- Los cambios no reemplazan la arquitectura PHP actual.
- No se toca `index`, `login`, `cliente`, `barbero`, `tienda`, `chat` ni el flujo principal.
- La nueva configuracion no obliga a activar nada.

## 7. Pruebas minimas recomendadas

- Login de admin, cliente y barbero.
- Solicitud, aceptacion y finalizacion de un servicio.
- Consulta de puntos e historial.
- Apertura de `admin/dashboard.php` y guardado de configuracion.
- Verificar que el chat sigue respondiendo por polling.

## 8. Notas de despliegue

- Respaldar BD antes de ejecutar migraciones.
- Ejecutar primero migraciones existentes y luego la nueva de fase 1.
- Limpiar cache del navegador o recargar fuerte si el admin mantiene HTML antiguo en memoria.

## 9. Riesgos detectados y mitigacion

- Riesgo: activar futuras features sin tablas de soporte.
  Mitigacion: todo queda en flags; por defecto no cambia el comportamiento operativo.
- Riesgo: dashboard admin muy acoplado.
  Mitigacion: se extendio solo el bloque de configuracion ya existente.
- Riesgo: inconsistencia entre ambientes.
  Mitigacion: se dejo migracion SQL separada y documentada.
