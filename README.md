# CUTS & STYLES

Sistema web de barberia a domicilio desarrollado en PHP con PDO, sesiones y MySQL. El proyecto ya cuenta con flujo operativo para clientes, barberos y administrador, tienda de productos, recompensas y un chat transaccional por servicio.

## Estado actual del sistema

El sistema hoy funciona sobre una base PHP tradicional, no sobre una migracion completa a Laravel. Se conservan archivos y carpetas heredadas de intentos anteriores, pero el flujo real operativo se apoya en paginas PHP, clases en `classes/`, configuracion central en `config/` y una base de datos MySQL.

Funcionalidades ya logradas y operativas:

- Login por roles funcionando
- Dashboard admin funcionando
- Dashboard cliente funcionando
- Dashboard barbero funcionando
- Creacion de clientes y barberos desde admin
- Registro de usuarios desde frontend
- Solicitud, aceptacion y finalizacion de servicios
- Sistema de puntos y recompensas
- Tienda, carrito y checkout
- Flujo de pago simulado protegido
- Subida de documentos para verificacion
- Chat transaccional por servicio
- Chat con actualizacion automatica por polling
- Estados visuales de mensajes: enviado, entregado y leido
- Reportes de abuso, bloqueo de clientes y score basico de comportamiento

## Arquitectura real del proyecto

La estructura principal actualmente usada es:

```text
cuts-styles-php/
├── admin/                      Panel administrativo
├── api/                        Endpoints API heredados y auxiliares
├── app/                        Carpeta legacy/incompleta de una migracion previa
├── assets/                     CSS, JS, imagenes y uploads
├── classes/                    Logica principal reutilizable
├── config/                     Configuracion, sesiones, helpers y DB
├── logs/                       Logs del sistema
├── storage/                    Sesiones y soporte interno
├── actualizar_perfil.php
├── actualizar_perfil_barbero.php
├── barbero.php
├── carrito.php
├── chat_poll.php
├── chat_send.php
├── chat_servicio.php
├── checkout.php
├── cliente.php
├── confirmar_pago.php
├── crear_preferencia_mp.php
├── index.php
├── login.php
├── logout.php
├── mercadopago_pago.php
├── productos.php
├── register.php
├── subir_documento.php
├── ver_barbero.php
├── ver_producto.php
└── ver_servicio.php
```

## Modulos principales

### 1. Autenticacion y sesiones

Archivos clave:

- [login.php](./login.php)
- [register.php](./register.php)
- [logout.php](./logout.php)
- [config/config.php](./config/config.php)
- [classes/User.php](./classes/User.php)

Capacidades:

- login por email y contraseña
- sesiones seguras
- CSRF en formularios sensibles
- roles `admin`, `cliente` y `barbero`
- manejo de errores y logs

### 2. Panel de administrador

Archivo principal:

- [admin/dashboard.php](./admin/dashboard.php)

Capacidades:

- metricas generales
- listado de clientes
- listado de barberos
- verificacion de barberos
- activacion y desactivacion de disponibilidad
- gestion de productos
- creacion manual de clientes y barberos desde el panel

### 3. Panel de cliente

Archivo principal:

- [cliente.php](./cliente.php)

Capacidades:

- solicitar nuevos servicios
- ver barberos disponibles
- ver servicios activos
- cancelar servicios permitidos
- consultar historial
- ver puntos y recompensas
- editar perfil
- abrir chat del servicio cuando el servicio esta activo

### 4. Panel de barbero

Archivo principal:

- [barbero.php](./barbero.php)

Capacidades:

- activar o desactivar disponibilidad
- ver servicios pendientes
- aceptar servicios
- finalizar servicios
- revisar historial
- editar perfil profesional
- abrir chat del servicio con clientes activos

### 5. Servicios a domicilio

Clase principal:

- [classes/Service.php](./classes/Service.php)

Flujo actual:

1. el cliente solicita un servicio
2. el sistema lo guarda como `pendiente`
3. el barbero acepta el servicio
4. el servicio pasa a `aceptado`
5. se habilita el chat transaccional
6. el barbero finaliza el servicio
7. el servicio pasa a `completado`
8. se suman puntos al cliente
9. el chat se cierra automaticamente

Tambien existe cancelacion por cliente en estados compatibles, con impacto en el score de comportamiento.

### 6. Tienda y productos

Archivos y clase principal:

- [productos.php](./productos.php)
- [carrito.php](./carrito.php)
- [checkout.php](./checkout.php)
- [classes/Product.php](./classes/Product.php)

Capacidades:

- listar productos
- carrito en sesion
- checkout con validacion
- descuento de stock
- gestion de imagenes de producto
- administracion desde dashboard

### 7. Recompensas y puntos

Clase principal:

- [classes/Rewards.php](./classes/Rewards.php)

Capacidades:

- acumulacion de puntos por servicio
- historial de puntos
- recompensas canjeables

### 8. Verificacion de barberos

Archivos y clase principal:

- [subir_documento.php](./subir_documento.php)
- [classes/Verification.php](./classes/Verification.php)

Capacidades:

- subir documentos
- marcar barbero en revision
- verificar o rechazar desde admin

### 9. Chat transaccional

Archivos principales:

- [chat_servicio.php](./chat_servicio.php)
- [chat_poll.php](./chat_poll.php)
- [chat_send.php](./chat_send.php)
- [classes/ServiceChat.php](./classes/ServiceChat.php)

Capacidades actuales:

- chat asociado a `servicio_id`
- acceso solo para cliente y barbero vinculados al servicio
- respuestas rapidas
- texto libre limitado preparado
- actualizacion automatica sin refrescar pagina
- estados visuales del mensaje
- cierre automatico del chat al finalizar o cancelar
- reporte de abuso
- bloqueo de cliente por barbero
- incidentes y score interno

## Seguridad y estabilidad incorporadas

Mejoras ya aplicadas al proyecto:

- CSRF en formularios sensibles
- validacion de roles y sesiones
- uso de PDO con consultas preparadas
- normalizacion de email en autenticacion
- mensajes de error mas claros para creacion de usuarios
- proteccion de endpoints auxiliares del chat
- proteccion del detalle de servicio por permisos
- cierre correcto de sesion en dashboards
- fallback de sesiones si `C:\xampp\tmp` no es escribible

## Base de datos

Base por defecto:

- `cuts_styles_db`

Scripts SQL relevantes incluidos en el proyecto:

- [database_full.sql](./database_full.sql)
  Script completo desde cero. Reemplaza estructura y datos.

- [database_safe_update.sql](./database_safe_update.sql)
  Script conservador para actualizar una base existente sin borrar datos.

- [database_login_fix.sql](./database_login_fix.sql)
  Repara usuarios demo, contraseñas y perfiles asociados.

- [database_chat_module.sql](./database_chat_module.sql)
  Crea tablas del modulo de chat, reportes, bloqueos y score.

- [database_chat_realtime_update.sql](./database_chat_realtime_update.sql)
  Agrega soporte de estados `delivered_at`, `read_at` y `updated_at` para tiempo real.

## Tablas importantes

Tablas principales del sistema:

- `users`
- `clientes`
- `barberos`
- `servicios`
- `productos`
- `producto_imagenes`
- `pedidos`
- `pedido_detalles`
- `documentos_verificacion`
- `recompensas`
- `canjes_recompensas`
- `puntos_historial`

Tablas nuevas del modulo de chat:

- `service_chats`
- `service_chat_messages`
- `service_chat_reports`
- `barber_client_blocks`
- `client_behavior_flags`
- `client_behavior_events`
- `service_chat_incidents`

## Requisitos del entorno

- PHP 8.x
- MySQL / MariaDB
- XAMPP o entorno equivalente
- Composer

## Configuracion

La conexion usa variables de entorno si existen. Si no, toma estos valores por defecto:

- `DB_HOST=localhost`
- `DB_DATABASE=cuts_styles_db`
- `DB_USERNAME=root`
- `DB_PASSWORD=`

Archivo usado:

- [config/database.php](./config/database.php)

## Instalacion recomendada

1. Clonar o copiar el proyecto en `c:\xampp\htdocs\cuts-styles-php`
2. Instalar dependencias:

```bash
composer install
```

3. Crear o actualizar la base de datos:

```sql
-- opcion completa
database_full.sql

-- o opcion segura
database_safe_update.sql

-- si el chat aun no existe
database_chat_module.sql

-- si falta tiempo real del chat
database_chat_realtime_update.sql
```

4. Verificar configuracion de `.env` si aplica
5. Abrir en navegador:

```text
http://localhost/cuts-styles-php/
```

## Usuarios de prueba

Si ejecutaste los scripts de reparacion o carga completa, puedes usar:

- Admin: `admin@cutsstyles.com` / `Admin123`
- Cliente: `cliente@test.com` / `cliente123`
- Barbero: `barbero@test.com` / `barbero123`

## Flujos principales ya soportados

### Flujo cliente

1. iniciar sesion
2. solicitar servicio
3. esperar aceptacion del barbero
4. abrir chat cuando el servicio este activo
5. ver historial, puntos y recompensas
6. comprar productos en la tienda

### Flujo barbero

1. iniciar sesion
2. revisar servicios pendientes
3. aceptar servicio
4. usar chat transaccional con el cliente
5. finalizar servicio
6. bloquear o reportar clientes si aplica

### Flujo admin

1. iniciar sesion
2. revisar metricas
3. crear clientes y barberos
4. verificar barberos
5. administrar productos
6. supervisar operacion general

## Mejoras implementadas recientemente

Resumen de avances logrados sobre el proyecto existente:

- estabilizacion general del sistema sin rehacer arquitectura
- correccion de helpers, CSRF y sesiones
- endurecimiento del login y registro
- correccion del logout en admin, cliente y barbero
- creacion de clientes y barberos desde admin
- scripts SQL completos y seguros para recuperacion
- modulo de chat transaccional asociado al servicio
- chat en tiempo real por polling sin recargar pagina
- checks visuales de estado de mensaje
- boton visible para volver al dashboard
- reglas basicas anti abuso y score de comportamiento

## Notas de compatibilidad

- No se rehizo el sistema completo.
- No se migro toda la arquitectura a otro framework.
- No se sustituyeron modulos principales existentes.
- Lo nuevo se agrego como extension compatible del sistema actual.
- Los formularios tradicionales siguen funcionando como fallback en varias partes del chat.

## Archivos clave para mantenimiento

- [config/config.php](./config/config.php)
- [config/database.php](./config/database.php)
- [classes/User.php](./classes/User.php)
- [classes/Service.php](./classes/Service.php)
- [classes/ServiceChat.php](./classes/ServiceChat.php)
- [classes/Product.php](./classes/Product.php)
- [classes/Rewards.php](./classes/Rewards.php)
- [classes/Verification.php](./classes/Verification.php)
- [admin/dashboard.php](./admin/dashboard.php)
- [cliente.php](./cliente.php)
- [barbero.php](./barbero.php)
- [chat_servicio.php](./chat_servicio.php)

## Pendientes sugeridos

Mejoras futuras opcionales, no obligatorias:

- panel admin para ver reportes e incidentes del chat
- moderacion visual de clientes restringidos
- optimizacion de polling segun visibilidad de pestaña
- activar texto libre limitado en fase controlada
- notificaciones visuales o badge de mensajes nuevos
- limpieza de carpetas legacy o duplicadas

## Licencia

Uso interno / proyecto privado, salvo que el equipo defina otra licencia posteriormente.
