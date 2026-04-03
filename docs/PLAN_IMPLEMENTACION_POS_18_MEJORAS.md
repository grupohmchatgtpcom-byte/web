# Plan de Implementación POS - 18 Mejoras (Roadmap + Prompt Operativo)

Fecha: 2026-04-03  
Proyecto: `Replica Valery/prueba.grupohmvenezuela.com`

## 1) Resumen Ejecutivo
Este documento define un plan por fases para ejecutar 18 mejoras de negocio y técnicas en el POS, priorizando:

- Integridad transaccional (evitar facturas duplicadas y doble descuento de inventario).
- Aislamiento por tienda/sede con trazabilidad completa.
- UX de cobro y captura de cliente más rápida.
- Reportería por sede con tendencia mensual.
- Automatización de mensajería por WhatsApp vía n8n.

Se propone una estrategia incremental (sin reescritura total), reutilizando lo ya avanzado en roles, ubicaciones y módulos existentes del sistema.

## 2) Diagnóstico Actual Aprovechable
### 2.1 Base funcional ya existente
- POS y cierre de venta en:
  - `app/Http/Controllers/SellPosController.php`
  - `resources/views/sale_pos/create.blade.php`
  - `resources/views/sale_pos/partials/payment_modal.blade.php`
  - `public/js/pos.js`
- Roles/permisos con alcance por ubicación ya implementados en gran parte.
- Concepto de "nota de entrega" ya aparece en traducciones y flujo de impresión parcial.
- Dashboard/configuración visual con base previa (editable por permisos).

### 2.2 Gaps clave
- No existe arquitectura offline-first robusta con cola local + sincronización idempotente.
- Modal de multipago actual es funcional, pero no optimizado para lectura rápida ni flujo lineal por método.
- Formulario de cliente tiene demasiados campos visibles para operación de caja rápida.
- Reportes por captación/facturación por sede y tendencia mensual requieren consolidación dedicada.

## 3) Arquitectura Objetivo (Offline + Idempotencia + Sync)
### 3.1 Principios
- Cada venta y cada pago deben tener una llave idempotente única.
- El servidor debe aceptar reintentos sin crear duplicados.
- Inventario se afecta una sola vez por transacción confirmada.
- Todo evento offline debe tener estado de sincronización trazable.

### 3.2 Diseño de datos recomendado
- `sales`:
  - `offline_uuid` (nullable, unique por negocio).
  - `sync_status` (`pending`, `synced`, `conflict`, `failed`).
  - `origin_device_id`.
  - `origin_location_id`.
- `transaction_payments`:
  - `payment_uuid` (unique).
  - `sync_status`.
- `sync_events` (nueva tabla):
  - `event_uuid`, `entity_type`, `entity_id`, `payload_json`, `status`, `retry_count`, `last_error`, `processed_at`.
- `inventory_ledger` (si no existe granular):
  - traza por movimiento con `source_txn_uuid` para reconciliación.
- `contacts`:
  - `origin_location_id` (captación), además de tienda de venta actual.
- `contact_reminders` (nueva tabla opcional):
  - `contact_id`, `reminder_days` (default 20), `next_run_at`, `channel`, `status`.

### 3.3 Flujo anti-duplicados
1. POS genera `offline_uuid` local antes de enviar.
2. API valida idempotencia por `business_id + offline_uuid`.
3. Si existe, responde venta existente (HTTP 200 con `already_processed=true`).
4. Si no existe, crea venta + pagos + movimientos inventario en transacción DB atómica.
5. Registra resultado de sync/evento.

## 4) Plan por Fases
## Fase 0 - Descubrimiento y Hardening (1 sprint)
Objetivo: asegurar base técnica antes de cambios visibles masivos.

Backend:
- Auditoría de endpoints críticos de venta/pago.
- Definir contrato idempotente para venta/pago.
- Añadir logs estructurados para duplicados y reconexión.

Frontend:
- Inventario de componentes POS y puntos de extensión JS.

BD:
- Migraciones para llaves idempotentes y estados sync.

Pruebas:
- Casos de reintento de POST con timeout y reconexión.

Riesgos:
- Colisiones por UUID mal generado en clientes legacy.

Definition of Done:
- Diseño validado + migraciones preparadas + pruebas base verdes.

## Fase 1 - UX/Branding Quick Wins (1 sprint)
Objetivo: impacto visual y usabilidad inmediata.

Incluye requerimientos:
1. Rediseño visual `/pos` y `/pos/create` con paleta/logo/fondo.
2. Branding de login (logo + fondo).
18. Footer actualizado a: `Sistema POS | Copyright © 2026 All rights reserved.`

Backend:
- Sin impacto funcional crítico.

Frontend:
- Ajustes en vistas Blade y CSS del tema POS/login.
- Estándar de contraste/accesibilidad en botones primarios de cobro.

Pruebas:
- Smoke visual desktop/móvil.

DoD:
- UI aprobada por negocio + sin ruptura funcional en caja.

## Fase 2 - Tienda/Roles/Inventario por Sede (1-2 sprints)
Objetivo: cerrar brechas de segregación operativa.

Incluye requerimientos:
3. Usuario tienda ve solo inventario de su sede.
4. Reporte faltantes/sobrantes por sede.
17. Validar/extender edición visual de app por rol.

Backend:
- Endurecer filtros por `permitted_locations()` en consultas de producto/stock.
- Nuevo reporte de diferencias (teórico vs físico) por sede.
- Revisar permisos granulares para “editar app” por rol.

Frontend:
- En POS, mostrar solo productos con stock en sede activa.
- Mensajes claros cuando no hay disponibilidad local.

Pruebas:
- Matriz rol x sede x módulo.

DoD:
- No hay fuga de datos de stock entre sedes.

## Fase 3 - Offline-First Transaccional (2-3 sprints)
Objetivo: eliminar duplicados y robustecer operación sin internet.

Incluye requerimientos:
5. Evitar factura duplicada + doble rebaja inventario por caída de internet.
6. Operación local offline con auto-sync.
7. Estado inactivo/sincronización visible para supervisor.

Backend:
- Idempotencia estricta en venta/pago.
- Endpoint de reconciliación/confirmación de sync.
- Cola de eventos y reintentos exponenciales.

Frontend:
- Buffer local (IndexedDB o localStorage estructurado) para ventas pendientes.
- Banner de estado: online/offline/syncing/conflict.
- Vista supervisor de transacciones pendientes/fallidas.

Pruebas:
- Cortes de red en puntos críticos (antes/después de submit).
- Reenvío múltiple del mismo payload.

DoD:
- Cero duplicados en pruebas de resiliencia controladas.

## Fase 4 - Clientes y Reportería Comercial por Sede (1-2 sprints)
Objetivo: mejorar captura y análisis comercial.

Incluye requerimientos:
8. Cliente conserva tienda de captación aunque compre en otra.
9. Reportes de captación/facturación por sede + tendencia mensual.
10. Navegación con Enter entre campos del formulario cliente.
11. Simplificación de alta cliente y email opcional.
15. Campo de recordatorio de cliente a 20 días.
16. Ocultar campos personalizados visibles.

Backend:
- Persistir `origin_location_id` en cliente.
- Agregados mensuales por sede para dashboard.
- Regla de email opcional en validación.
- Modelo/tabla de recordatorios.

Frontend:
- Flujo de formulario cliente rápido (orden tab/enter predecible).
- Colapsar/ocultar campos avanzados y custom fields.

Pruebas:
- Alta cliente en caja en < 20 segundos objetivo operativo.

DoD:
- Reportes por sede y tendencia visibles y validados.

## Fase 5 - Multipago + Nota de Entrega + n8n/WhatsApp (1-2 sprints)
Objetivo: mejorar cierre de cobro y comunicación automática.

Incluye requerimientos:
12. Rediseño completo del modal multipago.
13. Renombrar factura aplicable a Nota de entrega.
14. Integración n8n + WhatsApp (saludo + imagen nota de entrega tras pago).

Backend:
- Endpoint de webhook saliente o job queue hacia n8n.
- Plantilla de payload con metadatos de venta/cliente/nota.

Frontend:
- Multipago en flujo lineal:
  - selección método (radio),
  - campos condicionales por método,
  - resumen acumulado parcial,
  - saldo/restante en tiempo real.

Documentos:
- Revisar labels/plantillas para usar “Nota de entrega” donde aplique legal y operativamente.

Pruebas:
- Pruebas end-to-end de cierre + envío WhatsApp + reintentos.

DoD:
- Cobro multipago más rápido y legible, con confirmación de envío automatizado.

## Fase 6 - QA Integral, Piloto y Despliegue (1 sprint)
Objetivo: salida controlada y estable.

- Piloto por 1 o 2 sedes.
- Feature flags por módulo crítico (offline, multipago nuevo, n8n).
- Monitoreo post-release:
  - tasa de sync fallido,
  - duplicados detectados,
  - tiempo promedio de cobro,
  - ventas por sede.
- Plan rollback por bandera sin downtime.

DoD:
- Criterios KPI mínimos cumplidos en piloto.

## 5) Matriz de Permisos (Resumen)
Ya existe:
- Permisos por ubicación.
- Permisos globales tipo `access_all_locations`.

A ajustar/crear:
- `pos.sell.only_current_location_stock`
- `reports.location_capture.view`
- `reports.location_trend.view`
- `sync.supervisor.view`
- `app.layout.edit` (revisar granularidad por rol)

## 6) Estrategia de Pruebas
- Unitarias:
  - generación/validación de UUID idempotente,
  - reglas de conciliación de inventario.
- Integración:
  - venta + pago + stock en transacción atómica.
- E2E:
  - flujo POS completo con multipago.
- Resiliencia:
  - modo offline, reconexión, replay de payload.
- Seguridad funcional:
  - aislamiento por sede y permisos de reportes.

## 7) Riesgos y Mitigaciones
- Riesgo: inconsistencias históricas por datos previos sin `origin_location_id`.
  - Mitigación: script de backfill con reglas de negocio.
- Riesgo: rechazo operativo al nuevo multipago.
  - Mitigación: feature flag + capacitación breve + modo legacy temporal.
- Riesgo: fallas de n8n/WhatsApp.
  - Mitigación: cola asíncrona + reintento + estado de entrega.

## 8) Backlog Priorizado (Épica > Historia > Tarea)
### Épica A: Integridad de Venta Offline
- Historia A1: Como cajero, quiero reintentar ventas sin duplicar factura.
- Tareas:
  - migración UUID/sync_status,
  - validación idempotente backend,
  - pruebas replay.

### Épica B: UX de Cobro
- Historia B1: Como cajero, quiero un multipago claro y rápido.
- Tareas:
  - rediseño modal,
  - lógica de parciales por método,
  - pruebas de accesibilidad.

### Épica C: Control por Sede
- Historia C1: Como usuario de tienda, solo veo stock de mi sede.
- Tareas:
  - endurecer queries,
  - validación en endpoints de venta,
  - pruebas rol/sede.

### Épica D: Analítica Comercial
- Historia D1: Como supervisor, veo captación y facturación por sede con tendencia mensual.
- Tareas:
  - consultas agregadas,
  - endpoint/dashboard,
  - filtros por fecha/sede.

### Épica E: Automatización Postventa
- Historia E1: Como negocio, quiero enviar WhatsApp automático al pagar.
- Tareas:
  - payload y job,
  - integración n8n,
  - bitácora de entregas.

## 9) Prompt Operativo para IA (copiar/usar)
Actúa como Líder Técnico Laravel + Arquitecto de Soluciones POS. Con base en este plan, construye un backlog de sprints ejecutable para el repositorio `Replica Valery/prueba.grupohmvenezuela.com`, priorizando Fase 3 (offline/idempotencia) y Fase 2 (segregación por sede), sin romper flujos actuales de caja.

Entrega:
1. Historias con criterios de aceptación en formato Gherkin.
2. Lista de archivos exactos a intervenir por historia.
3. Migraciones SQL propuestas.
4. Casos de prueba unitarios/integración/E2E por historia.
5. Estrategia feature flags y rollback.
6. Riesgos + mitigación por sprint.

## 10) Supuestos y Preguntas Abiertas
Supuestos:
- El negocio aprueba uso de UUID como identificador idempotente de venta.
- Se dispone de n8n operativo con endpoint seguro.
- El flujo legal permite “Nota de entrega” en los documentos objetivo.

Preguntas críticas:
1. ¿Qué documento fiscal debe conservar nomenclatura de "factura" por norma local?
2. ¿El recordatorio a 20 días será por WhatsApp, SMS, email o tarea interna?
3. ¿Qué sedes entran en piloto y cuál es el volumen diario esperado?
4. ¿Cuál es el SLA de sincronización aceptable al volver internet?

---

Documento preparado para ejecución por fases con foco en continuidad operativa y reducción de riesgo.
