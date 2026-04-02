# Checklist de Cierre Preproduccion (BCV + Bs + Despacho)

## 1. Base de datos

1. Ejecutar SQL base:
   - `sql/001_ghm_pre_mods.sql`
2. Verificar tablas creadas:
   - `ghm_dispatch_settings`
   - `ghm_dispatch_policies`
   - `ghm_bcv_rates`
   - `ghm_transaction_fx`
3. Validar al menos una tasa BCV cargada:

```sql
SELECT rate_date, usd_to_ves_rate, source, is_fallback
FROM ghm_bcv_rates
ORDER BY rate_date DESC
LIMIT 5;
```

## 2. Variables y cron

1. Confirmar `.env`:

```env
BCV_RATE_URL=https://www.bcv.org.ve/estadisticas/tipo-cambio-de-referencia-smc
```

2. Probar comando manual:

```bash
php artisan pos:updateBcvRate
```

3. Configurar cron de Laravel Scheduler en cPanel:

```bash
* * * * * /usr/local/bin/php /home/USUARIO/public_html/RUTA_PROYECTO/artisan schedule:run >> /dev/null 2>&1
```

4. Confirmar que se actualiza `ghm_bcv_rates` al correr el comando.

## 3. Transferencias (Despacho inteligente)

1. Ir a crear transferencia.
2. Probar boton `Descargar sugerencia de despacho (Excel)`.
3. Validar en el Excel:
   - cantidad sugerida por politica min/max,
   - limite por stock disponible en almacen origen,
   - orden de prioridad aplicado.

## 4. Tasas y trazabilidad

1. Crear una transferencia y guardar.
2. Verificar registro en `ghm_transaction_fx`:

```sql
SELECT transaction_id, rate_date, usd_to_ves_rate, source, is_fallback
FROM ghm_transaction_fx
ORDER BY id DESC
LIMIT 10;
```

3. Editar una transferencia y confirmar que actualiza/crea registro FX.

## 5. Vistas con monto en Bs

Validar visualmente que aparece tasa BCV y equivalente en Bs en:

1. Transferencias (crear).
2. Ventas (listado y totales).
3. Reporte compra/venta.
4. Dashboard (tarjetas principales y dues).
5. Reporte contactos.
6. Reporte cuentas de pago.
7. Detalle de venta (modal).
8. Detalle de compra (modal).

## 6. Impresion/PDF

Validar que la impresion/PDF incluya linea BCV y total estimado Bs en layouts:

1. `classic`
2. `detailed`
3. `elegant`

Nota: si el negocio usa otro layout de recibo, replicar el bloque BCV en ese template.

## 7. Casos de fallback BCV

1. Simular falla BCV (o ejecutar fuera de horario de publicacion).
2. Confirmar comportamiento:
   - mantiene ultima tasa conocida,
   - `is_fallback = 1`,
   - notas de fallback en BD.

## 8. Prueba de regresion rapida

1. Crear venta normal.
2. Registrar pago parcial y total.
3. Crear compra normal.
4. Verificar que no se rompen:
   - calculos USD originales,
   - filtros de reportes,
   - impresion de factura.

## 9. Criterio de salida (Go/No-Go)

Marcar GO solo si:

1. Cron BCV operativo.
2. Tasa diaria visible y consistente.
3. No hay errores de UI ni calculos incoherentes en Bs.
4. Exportacion Excel de despacho correcta.
5. Modales, reportes e impresion verificados por usuario de negocio.
