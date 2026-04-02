# Actualizacion diaria BCV en cPanel

## 1) Requisitos previos

- Tener ejecutado el SQL base: `sql/001_ghm_pre_mods.sql`
- Confirmar en `.env`:

```
BCV_RATE_URL=https://www.bcv.org.ve/estadisticas/tipo-cambio-de-referencia-smc
```

## 2) Probar manualmente en servidor

Desde terminal del hosting (en la carpeta del proyecto):

```bash
php artisan pos:updateBcvRate
```

Prueba con tasa manual (opcional):

```bash
php artisan pos:updateBcvRate --force-rate=45,7075
```

## 3) Configurar CRON en cPanel

En cPanel -> Cron Jobs, agrega una ejecucion cada 15 minutos (el scheduler de Laravel decide cuando correr cada tarea):

```bash
* * * * * /usr/local/bin/php /home/USUARIO/public_html/RUTA_PROYECTO/artisan schedule:run >> /dev/null 2>&1
```

> Ajusta `USUARIO` y `RUTA_PROYECTO` segun tu hosting.

## 4) Horario de ejecucion configurado

El comando BCV se ejecuta diariamente a las `08:10` (hora del servidor), excepto en entorno `demo`.

## 5) Fallback automatico

Si BCV no publica tasa ese dia o falla la consulta:

- Se reutiliza la ultima tasa disponible.
- Se marca `is_fallback = 1` en `ghm_bcv_rates`.
- Se guarda una nota con la fecha origen del fallback.
