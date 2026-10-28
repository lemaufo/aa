# Manual corto por rol

Trazabilidad de pedidos especiales de Aceros el Águila. Cada persona entra con su **usuario** (no con correo) y
solo ve lo que le toca. En el **Inicio** aparece arriba lo que tienes por atender, ordenado por fecha prometida:
lo vencido y lo de hoy primero.

## El recorrido de un pedido

```
EN EXPERTOS → COBRADO → PRODUCIDO → ENVIADO A CEDIS → VALIDADO → EN CEDIS / ENVIADO A CLIENTE / ENVIADO A SUCURSAL → (EN SUCURSAL) → ENTREGADO
```

| Estatus | Quién lo mueve | Qué pasó |
|---|---|---|
| EN EXPERTOS | Cajero | El traspaso se registró en la app; falta ligarlo con el ticket cobrado |
| COBRADO | Producción | El ticket se validó; producción ya puede fabricar |
| PRODUCIDO | Producción | Etiqueta, peso y fotos listos; falta despacharlo a CEDIS |
| ENVIADO A CEDIS | CEDIS | Va en camino; CEDIS lo recibe escaneando la etiqueta |
| VALIDADO | CEDIS | Recibido; falta decidir cómo sale |
| EN CEDIS / ENVIADO A CLIENTE / ENVIADO A SUCURSAL | CEDIS o Sucursal | Según la forma de entrega que eligió la caja |
| ENTREGADO | — | Entregado con acuse firmado; la venta queda marcada como entregada en Microsip |
| CANCELADO | — | El traspaso se canceló en AdminExpertos. Solo pasa mientras está EN EXPERTOS |

La app **nunca cancela por su cuenta**: lee la cancelación que se hizo en AdminExpertos (cada 5 minutos y al abrir el pedido).

---

## Cajero

1. **Registra el traspaso.** En *Pedidos → Registrar traspaso de Expertos*, escribe el folio que capturaste en
   AdminExpertos (por ejemplo `TSA013341`; también sirve `tsa13341`) y pulsa *Buscar en Expertos*. Elige el
   artículo del pedido (un pedido = un artículo) y pulsa *Registrar pedido*.
   - Si el traspaso no existe, está cancelado o ya se registró, la app te lo dice y no lo crea.
2. **Liga el traspaso con la venta.** En el pedido, captura el **folio del ticket** de Microsip. Al salir del campo
   la app revisa que el ticket:
   - exista y esté **cobrado** (no cancelado ni pendiente),
   - sea del **mismo artículo** que el traspaso,
   - sea un **ticket independiente**, sin productos de línea,
   - no esté ligado ya a otro traspaso.
3. Completa **cliente, teléfono, fecha prometida, forma de entrega** (CEDIS, a domicilio o sucursal) y los
   **cortes** (piezas × medida; deben cuadrar con los metros del traspaso). Con entrega a domicilio pide quién recibe
   y la dirección. Pulsa *Ligar*.
4. **Cambiar datos de entrega**: se puede hasta que el pedido llegue a CEDIS; pide **tu contraseña** y queda en la bitácora.

Solo ves los pedidos de tu sucursal.

## Producción

Tres pasos, en orden; el siguiente no se habilita hasta terminar el anterior.

1. **Imprime la etiqueta** (es la orden de producción): piezas y medidas de caja, folio y código de barras.
   Las piezas y medidas no se editan aquí.
2. **Pesa.** Captura el peso de la báscula (o usa el **modo balanza** y los botones de ajuste). Se compara contra el
   peso teórico: debe quedar dentro de **±5 %**. Si se sale, revisa piezas y medidas contra la etiqueta y vuelve a
   pesar. Anota el rollo o lote si aplica.
3. **Sube las dos fotos**: el producto terminado con la etiqueta visible y la báscula. Desde el celular se abre la cámara.
4. **Despacha a CEDIS** escaneando la etiqueta (o tecleando el folio).

## CEDIS

1. **Recibe y valida**: escanea la etiqueta y anota la **zona del patio** (obligatoria si el cliente recoge en CEDIS).
2. **Define cómo sale**, según lo que eligió la caja:
   - *Recoge en CEDIS* → queda EN CEDIS.
   - *A domicilio* → captura **camión y repartidor**.
   - *A sucursal* → se envía a la sucursal.
3. **Entrega** (el cliente recoge, o regresa el repartidor): nombre de quien recibe y **foto del acuse firmado**,
   ambos obligatorios. Con repartidor, confirma que regresó con el acuse.
4. Al entregar, la venta se marca como entregada en Microsip. Si Microsip no responde, el pedido queda entregado
   con un aviso rojo y el botón **Reintentar ahora**.

## Sucursal

1. **Recibe** el pedido escaneando la etiqueta.
2. **Avisa al cliente** con *Avisar al cliente por WhatsApp*: abre WhatsApp con el mensaje escrito; tú decides si lo
   envías. (CEDIS tiene el mismo botón cuando el cliente recoge ahí.)
3. **Entrega al cliente**: escanea la etiqueta, nombre de quien recibe y **foto del acuse firmado** (obligatorios).
4. Igual que CEDIS: si Microsip no se actualizó, usa **Reintentar ahora**.

Solo ves los pedidos de tu sucursal.

## Administrador

- Puede hacer **cualquier paso** del flujo, de cualquier sucursal.
- **Usuarios**: *Nuevo usuario* o *Editar*. Usuario sin espacios, rol, sucursal (obligatoria para Cajero y
  Sucursal) y contraseña de mínimo 8. Al editar, la contraseña vacía conserva la actual. No puedes quitarte tu
  propio rol de administrador. Abajo está la matriz de **quién puede hacer qué**.
- **Artículos**: el catálogo de Microsip en vivo, solo de consulta. Busca por nombre o clave (no importan
  acentos ni mayúsculas), filtra por estatus y pulsa un artículo para ver sus unidades, peso, todas sus claves y
  cuántos pedidos de la app lo usan.
- **Equipos**: genera un código de un solo uso para autorizar una computadora o celular, y revoca los que ya no se
  usan (solo cuando la autorización de equipos está encendida).

## Consulta

Solo entra a **Rastreo**: busca un pedido por su **folio exacto** y ve su estatus e historial, sin nombres del
personal, pesos, ventas ni evidencias. No modifica nada.

---

## Preguntas frecuentes

| Problema | Qué hacer |
|---|---|
| "No se pudo consultar Microsip" | Microsip o la red no responde. Espera unos minutos e intenta de nuevo |
| "El ticket es de otro artículo" | El pedido especial se vende en un ticket del mismo artículo del traspaso |
| "Trae productos de línea" | Se vende en un ticket aparte, solo con el pedido especial |
| El peso no pasa | Está fuera de ±5 %: revisa piezas y medidas contra la etiqueta y vuelve a pesar |
| "Ese código no es de este pedido" | La etiqueta escaneada es de otro pedido; revisa el folio |
| Aviso rojo "La venta no se actualizó en Microsip" | El pedido sí se entregó; pulsa *Reintentar ahora* más tarde |
| No veo un pedido | Cajero y Sucursal solo ven su sucursal; pide al administrador revisar tu sucursal |
