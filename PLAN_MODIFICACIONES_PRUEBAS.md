# Plan de trabajo para entorno de pruebas y modificaciones

## Objetivo
Montar un entorno de pruebas en cPanel, conectado a base de datos independiente, para desarrollar y validar cambios sin afectar producción.

## Contexto actual
- Proyecto (instalador/app): `C:\Users\Usuario\Downloads\tiendas\henry`
- Base en uso actual: `C:\Users\Usuario\Downloads\tiendas\tiendas.sql`
- Base de pruebas (en blanco): `C:\Users\Usuario\Downloads\tiendas\prueba.sql`

## Hallazgos técnicos confirmados
1. La app es Laravel y ya tiene módulo de transferencias de stock operativo.
2. Ya incluye soporte Excel (Maatwebsite Excel), útil para generar descargas.
3. Existe manejo de tipo de cambio (`exchange_rate`) en transacciones.
4. Existe stock por ubicación/sede (útil para calcular despacho entre almacén y tiendas).

---

## Fase 1: Entorno de pruebas aislado en cPanel
1. Crear subdominio o ruta separada para pruebas.
2. Subir copia de la app (`henry`) en la carpeta de pruebas.
3. Crear base de datos y usuario nuevos para pruebas.
4. Importar la base de pruebas (`prueba.sql`) en la BD nueva.
5. Configurar `.env` con credenciales de pruebas (nunca producción).
6. Validar acceso y flujo básico (login, productos, transferencias).

### Entregable
Entorno de pruebas operativo y completamente separado de producción.

---

## Fase 2: Botón de despacho inteligente + Excel

### Necesidad
Desde Almacén GHM, calcular automáticamente cuánto despachar a cada tienda según mínimo, máximo y stock actual.

### Diseño funcional propuesto
1. Agregar configuración de mínimo/máximo por producto y por tienda.
2. Tomar stock actual de cada tienda y stock disponible en Almacén GHM.
3. Calcular sugerencia de despacho.
4. Mostrar resultado y permitir descargar Excel.

### Regla base propuesta
- Si stock tienda < mínimo: sugerir reponer hasta máximo.
- Limitar por disponibilidad en Almacén GHM.
- Si no alcanza para todas las tiendas: priorizar por mayor déficit.

### Excel esperado
Columnas sugeridas:
- Tienda
- Producto
- Stock actual tienda
- Mínimo
- Máximo
- Sugerido a despachar
- Disponible en almacén
- Observaciones

### Entregable
Botón en módulo de transferencias que calcule y descargue Excel con recomendaciones.

---

## Fase 3: Tasa del día (BCV) automática

### Necesidad
Mostrar diariamente tasa BCV y usar tasa anterior cuando no haya publicación (feriados/sábados/domingos).

### Diseño técnico propuesto
1. Crear tabla histórica de tasas (fecha, valor, fuente, estado).
2. Implementar servicio para obtener tasa BCV.
3. Programar tarea diaria (cron cPanel) para actualizar tasa.
4. Aplicar fallback a última tasa disponible si no hay nueva publicación.
5. Mostrar tasa vigente en interfaz.

### Entregable
Tasa BCV visible y actualizada automáticamente con respaldo histórico.

---

## Fase 4: Montos en bolívares además de dólares

### Necesidad
Mantener sistema actual en USD pero mostrar también equivalencia en Bs según tasa vigente.

### Diseño técnico propuesto
1. Mantener USD como moneda base (menor riesgo).
2. Mostrar equivalencia en Bs en vistas clave (ventas, reportes, facturas).
3. Guardar tasa aplicada por transacción para trazabilidad histórica.
4. Etiquetar claramente montos USD y Bs.

### Entregable
Visualización dual USD/Bs sin romper lógica actual.

---

## Fase 5: QA y salida controlada
1. Probar con múltiples tiendas y stock limitado en almacén.
2. Verificar consistencia de cálculo vs inventario real.
3. Validar tasa BCV en día hábil y no hábil.
4. Validar montos Bs en operaciones nuevas e históricas.
5. Preparar checklist de despliegue y rollback.

### Entregable
Cambios validados, documentados y listos para producción.

---

## Orden recomendado de implementación
1. Entorno de pruebas en cPanel.
2. Despacho inteligente + Excel.
3. Tasa BCV automática.
4. Montos en bolívares.
5. Pruebas integrales y despliegue.

---

## Decisiones pendientes (para cerrar antes de codificar)
1. Fórmula exacta de despacho:
   - Opción A: reponer siempre hasta máximo.
   - Opción B: reponer solo si está por debajo de mínimo, y llevar a máximo.
2. Formato del Excel:
   - Consolidado único.
   - Una hoja por tienda.
3. Fuente BCV:
   - Lectura directa del sitio BCV.
   - Fuente intermedia/API propia (más estable).
4. Prioridad de pantallas con Bs:
   - Factura.
   - Venta.
   - Reportes.

---

## Nota operativa cPanel
- Evitar usar el editor web de cPanel para archivos con acentos sensibles en plantillas HTML.
- Para código PHP/Laravel, preferir edición local + subida controlada.
- Tras cambios de backend, limpiar cachés de Laravel cuando aplique y validar permisos de `storage` y `bootstrap/cache`.

---

## Inicio sugerido inmediato
Comenzar por Fase 1 (entorno de pruebas) y dejar checklist de validación funcional antes de tocar módulos de negocio.