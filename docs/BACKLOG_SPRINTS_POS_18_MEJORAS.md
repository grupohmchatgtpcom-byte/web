# Backlog Operativo por Sprint - POS 18 Mejoras

Fecha: 2026-04-03  
Proyecto: Replica Valery/prueba.grupohmvenezuela.com

## Objetivo
Convertir el plan maestro en un backlog ejecutable por sprint, con historias listas para desarrollo, criterios de aceptación en Gherkin, archivos candidatos, pruebas y riesgos.

## Cadencia sugerida
- Duración sprint: 2 semanas.
- Total propuesto: 8 sprints.
- Prioridad técnica: primero integridad transaccional y segregación por sede, luego UX y automatizaciones.

## Sprint 1 - Base técnica y observabilidad
### Historias
1. Como líder técnico, quiero una base de idempotencia definida para ventas y pagos para evitar duplicados.
2. Como equipo de soporte, quiero trazas estructuradas de eventos críticos para diagnosticar errores de sincronización.

### Criterios de aceptación (Gherkin)
```gherkin
Feature: Contrato de idempotencia
  Scenario: Reintento de venta con misma llave
    Given un payload de venta con offline_uuid ya procesado
    When el POS reenvía la solicitud
    Then el sistema responde la venta existente
    And no crea una nueva transacción

Feature: Logging de sincronización
  Scenario: Evento fallido de persistencia
    Given una excepción en creación de pago
    When se ejecuta la operación
    Then se registra un log estructurado con correlation_id y contexto
```

### Archivos candidatos
- app/Http/Controllers/SellPosController.php
- app/Http/Controllers/TransactionPaymentController.php
- app/Utils/TransactionUtil.php
- config/logging.php

### Pruebas
- Unitarias de llave idempotente.
- Integración de doble POST con mismo UUID.

### Riesgos
- Decidir formato UUID y alcance por negocio.

## Sprint 2 - Segregación fuerte por sede
### Historias
1. Como usuario de tienda, quiero ver solo productos con stock de mi sede para no vender inventario ajeno.
2. Como administrador, quiero garantizar que consultas y reportes respeten permisos por ubicación.

### Criterios de aceptación
```gherkin
Feature: Catálogo por sede
  Scenario: Usuario sin acceso global
    Given un usuario con ubicación permitida única
    When abre POS
    Then solo visualiza productos con stock en su ubicación

Feature: Seguridad de reportes
  Scenario: Acceso a reporte de otra sede
    Given un usuario sin permiso cross-location
    When intenta consultar una sede no permitida
    Then el sistema deniega u oculta los datos
```

### Archivos candidatos
- app/User.php
- app/Http/Controllers/ProductController.php
- app/Http/Controllers/ReportController.php
- resources/views/sale_pos/create.blade.php
- public/js/pos.js

### Pruebas
- Matriz rol x sede x módulo.
- E2E de venta con sedes múltiples.

### Riesgos
- Consultas legacy sin filtro de ubicación.

## Sprint 3 - Idempotencia transaccional de venta y pago
### Historias
1. Como cajero, quiero reintentar ventas sin generar facturas duplicadas.
2. Como negocio, quiero que inventario se descuente una sola vez por venta.

### Criterios de aceptación
```gherkin
Feature: Venta idempotente
  Scenario: Timeout cliente y reenvío
    Given una venta enviada con offline_uuid
    And el cliente no recibe respuesta por timeout
    When reintenta con el mismo offline_uuid
    Then no se duplica la venta

Feature: Inventario consistente
  Scenario: Reintento de la misma transacción
    Given un movimiento de stock ya aplicado
    When llega replay de misma venta
    Then no se aplica un segundo descuento
```

### Archivos candidatos
- database/migrations/*_add_offline_uuid_to_transactions.php
- app/Transaction.php
- app/TransactionPayment.php
- app/Utils/TransactionUtil.php
- app/Http/Controllers/SellPosController.php

### Pruebas
- Integración DB transaccional.
- Concurrencia con requests paralelos.

### Riesgos
- Deadlocks si no se ajustan índices y orden de bloqueo.

## Sprint 4 - Offline local + sincronización supervisor
### Historias
1. Como cajero, quiero operar sin internet y sincronizar al reconectar.
2. Como supervisor, quiero ver pendientes/fallidos de sincronización.

### Criterios de aceptación
```gherkin
Feature: Cola local offline
  Scenario: Venta sin internet
    Given el POS está offline
    When el cajero finaliza venta
    Then la venta queda en cola local con estado pending

Feature: Vista supervisor
  Scenario: Seguimiento de sincronización
    Given existen transacciones pending o failed
    When supervisor consulta panel
    Then visualiza conteo, detalle y último error
```

### Archivos candidatos
- public/js/pos.js
- resources/views/sale_pos/create.blade.php
- app/Http/Controllers/SellPosController.php
- routes/web.php
- resources/views/report/* (o nueva vista supervisor)

### Pruebas
- E2E con desconexión/reconexión.
- Reintento exponencial y manejo de conflictos.

### Riesgos
- Compatibilidad navegador para almacenamiento local.

## Sprint 5 - Cliente rápido y trazabilidad comercial
### Historias
1. Como cajero, quiero registrar cliente en menos pasos, con Enter y email opcional.
2. Como negocio, quiero conservar sede de captación del cliente y reportarla.
3. Como operación, quiero ocultar campos personalizados no necesarios.

### Criterios de aceptación
```gherkin
Feature: Alta rápida de cliente
  Scenario: Registro mínimo en caja
    Given formulario simplificado
    When se ingresa nombre y móvil
    And se navega con Enter
    Then el cliente se guarda sin requerir email

Feature: Captación por sede
  Scenario: Compra en sede distinta
    Given cliente captado en sede A
    When compra en sede B
    Then el sistema conserva origin_location_id de captación
```

### Archivos candidatos
- app/Http/Controllers/ContactController.php
- resources/views/contact/create.blade.php
- resources/views/contact/index.blade.php
- public/js/app.js
- public/js/pos.js
- database/migrations/*_add_origin_location_id_to_contacts.php

### Pruebas
- Unitarias validación contacto.
- E2E alta rápida desde POS.

### Riesgos
- Dependencias en módulos CRM o formularios compartidos.

## Sprint 6 - Reportes por sede y tendencia mensual
### Historias
1. Como supervisor, quiero reporte de captación por sede por mes.
2. Como gerente, quiero tendencia mensual de facturación por sede.
3. Como inventario, quiero reporte faltante/sobrante por sede.

### Criterios de aceptación
```gherkin
Feature: Tendencia mensual por sede
  Scenario: Consulta de rango anual
    Given datos de ventas por 12 meses
    When usuario filtra por sede
    Then visualiza serie mensual y totales

Feature: Faltante/sobrante
  Scenario: Corte de inventario
    Given existencias teóricas y físicas
    When se ejecuta reporte
    Then se muestran diferencias por producto y sede
```

### Archivos candidatos
- app/Http/Controllers/ReportController.php
- resources/views/report/*
- public/js/report.js
- routes/web.php

### Pruebas
- Validación de agregados y performance.
- Pruebas de autorización por permisos.

### Riesgos
- Consultas pesadas sin índices por fecha/sede.

## Sprint 7 - Multipago rediseñado + Nota de entrega
### Historias
1. Como cajero, quiero un modal multipago lineal y legible para cobrar más rápido.
2. Como negocio, quiero mostrar “Nota de entrega” en documentos aplicables.

### Criterios de aceptación
```gherkin
Feature: Multipago lineal
  Scenario: Pago combinado
    Given total de venta
    When el usuario agrega métodos con radio y campos condicionales
    Then el sistema calcula parciales y saldo en tiempo real

Feature: Documento de entrega
  Scenario: Impresión de venta
    Given una venta finalizada
    When se imprime documento aplicable
    Then muestra etiqueta Nota de entrega
```

### Archivos candidatos
- resources/views/sale_pos/partials/payment_modal.blade.php
- resources/views/sale_pos/partials/payment_row.blade.php
- public/js/pos.js
- lang/es/lang_v1.php
- lang/es/invoice.php

### Pruebas
- E2E de cobro mixto (efectivo + transferencia + tarjeta).
- Pruebas de regresión de impresión.

### Riesgos
- Impacto en hábitos del cajero (curva de adopción).

## Sprint 8 - n8n/WhatsApp + recordatorios + rollout
### Historias
1. Como negocio, quiero enviar saludo y nota de entrega por WhatsApp al pagar.
2. Como asesor/comercial, quiero recordatorios de cliente a 20 días.
3. Como equipo de despliegue, quiero activar por feature flags y piloto controlado.

### Criterios de aceptación
```gherkin
Feature: Envío WhatsApp automatizado
  Scenario: Venta pagada
    Given venta con pago confirmado
    When se dispara integración
    Then n8n recibe payload válido
    And se registra estado de entrega

Feature: Recordatorio 20 días
  Scenario: Cliente con seguimiento
    Given cliente activo
    When se cumple ventana de 20 días
    Then se genera recordatorio pendiente
```

### Archivos candidatos
- app/Jobs/*
- app/Http/Controllers/SellPosController.php
- app/Http/Controllers/ContactController.php
- routes/web.php
- config/services.php
- database/migrations/*_create_contact_reminders_table.php

### Pruebas
- Integración con endpoint n8n de staging.
- Reintentos y gestión de errores de entrega.

### Riesgos
- Caída de proveedor externo o timeout en webhook.

## Definición de terminado transversal
- Sin duplicados en pruebas de replay offline.
- Sin fuga de datos entre sedes.
- Cobro multipago sin regresión en caja.
- Reportes por sede disponibles con permisos correctos.
- Integraciones externas con trazabilidad y reintento.

## KPIs de éxito
- Duplicados de venta: 0 en piloto.
- Tiempo promedio de cobro: reducción >= 20%.
- Alta de cliente rápida: <= 20 segundos en caja.
- Tasa de sincronización exitosa: >= 99% en reconexión.
- Tasa de entrega WhatsApp confirmada: >= 95%.

## Dependencias externas
1. Definición legal del uso de “Factura” vs “Nota de entrega”.
2. Endpoint n8n productivo y credenciales seguras.
3. Aprobación de diseño visual (paleta, logo, fondo).
4. Selección de sedes para piloto.

## Notas de ejecución
- Aplicar feature flags desde Sprint 4 para módulos de mayor riesgo.
- Mantener opción de fallback visual/funcional en multipago durante 1 sprint.
- Publicar checklist de smoke tests por release de caja.
