# Precios y tarifas de la tienda

## Cómo se calcula el precio de un producto

Esta guía explica, para administradores de tienda, de dónde sale el precio de un
producto, qué datos se usan y en qué momento se aplican los beneficios y el IVA.
No es una guía fiscal ni sustituye a la configuración que corresponda en
idirecto.

Los importes que aparecen en los ejemplos son **ilustrativos**. No son precios
reales de clientes ni una configuración activa.

---

## 1. Los conceptos que hay que distinguir

### Tarifa de idirecto

Es el precio que idirecto asigna a la cuenta de la tienda para un producto. En
la base de datos es el precio de la tarifa de esa cuenta y **no incluye el IVA**.

La tarifa puede ser distinta para cada producto y para cada cuenta. No es un
precio único de toda la tienda.

### Coste interno

Es el coste que el mayorista conserva para sus almacenes. **No es la base de la
fórmula del precio de venta** que se muestra al cliente. Puede intervenir en
otros procesos internos del pedido y del envío al mayorista, pero no se suma en
la fórmula comercial de la tienda.

### Beneficio fijo

Es un importe en euros que la tienda suma a la tarifa antes de aplicar el
porcentaje. En el panel aparece como **Beneficio fijo (EUR)**.

### Beneficio porcentual

Es el porcentaje que se aplica sobre la tarifa más el beneficio fijo. En el
panel aparece como **Beneficio porcentual (%)**. La columna interna se llama
`markup`.

### Precio base y precio final

- **Precio base:** resultado de aplicar los dos beneficios, todavía sin IVA.
- **Precio final:** precio base más el IVA que corresponda al producto. Es el
  precio que se muestra en la web pública.

---

## 2. De dónde obtiene el precio el sistema

### Catálogo central de idirecto

El precio de partida se obtiene del catálogo del mayorista:

- producto: `productos`;
- tarifa del producto: `precios.precio`;
- tarifa de la cuenta: `precios.id_margen` y `precios.sucursal_id`;
- almacén y existencias: `precios.id_almacen`, `precios.id_stock`, `stock` y
  `almacenes`;
- relación entre almacén y tarifa: `tarifas`;
- impuesto del producto: `productos.impuestos`.

No se toma simplemente el precio más barato de todas las tarifas. El sistema
elige un almacén válido para la cuenta de la tienda, con stock, activo y con una
tarifa disponible. Si hay varias opciones, aplica las reglas de prioridad de
idirecto.

### Productos propios de la tienda

Los productos propios están en `mt_own_products` y no pasan por la fórmula del
catálogo central.

- Si tienen precio de oferta, se usa ese precio.
- Si no, se usa el precio normal.
- El IVA se toma del campo `tax_rate` del producto propio.
- Los beneficios fijo y porcentual de la tienda **no se aplican** a estos
  productos.

### Otras fuentes que no cambian el precio

- Las **ofertas** del catálogo central generan la etiqueta «Oferta», pero no
  modifican el importe calculado.
- No hay precios distintos por nivel de cliente para los compradores de la
  tienda.
- Los gastos de envío se calculan aparte; no forman parte del precio del
  producto.
- El canon y los datos de sujeto pasivo se conservan para el envío al
  mayorista, pero no se suman en la fórmula del precio mostrado.

---

## 3. Cómo identifica el sistema la tienda

En la web pública, la tienda se resuelve por la dirección desde la que se entra:

- el subdominio de la plataforma;
- un dominio propio previamente verificado.

En el panel, la tienda se identifica por la sesión del usuario que ha iniciado
sesión. El identificador de la tienda no se toma de un parámetro que pueda
cambiar el visitante.

Una vez identificada la tienda, el sistema busca su cuenta de idirecto y usa:

- `tiendas.id_margen`: tarifa de la cuenta;
- `tiendas.sucursal_id`: sucursal de la cuenta;
- `mt_stores.markup`: beneficio porcentual;
- `mt_stores.markup_fixed`: beneficio fijo;
- `mt_stores.tax_rate`: IVA por defecto de la tienda.

La copia local `mt_stores.id_margen` no decide el precio actual. La tarifa
efectiva se lee de la cuenta de idirecto.

---

## 4. Dónde se configura

La configuración comercial está en:

**Ajustes → Venta y cobro**

Ruta del panel:

```text
/panel/ajustes#venta-y-cobro
```

Allí se editan:

- **Beneficio fijo (EUR)**;
- **Beneficio porcentual (%)**;
- los gastos de envío y las formas de pago, que no forman parte de la fórmula
  del producto.

La tarifa base **no se edita en este panel**. Se gestiona en idirecto. Tampoco
hay un selector para elegir una tarifa distinta por producto desde Ajustes: la
tarifa se obtiene automáticamente de la cuenta y del almacén que corresponda.

Los límites actuales de los campos son:

- beneficio fijo: hasta 999.999,99 €;
- beneficio porcentual: hasta 200 %;
- ambos admiten como máximo dos decimales.

Si un valor no es válido, no se guarda el formulario y se muestra el motivo.

---

## 5. La fórmula exacta

La fórmula vigente es:

```text
precio base = (tarifa + beneficio fijo) × (1 + beneficio porcentual / 100)
```

Después se aplica el IVA:

```text
precio final = redondear(precio base × (1 + IVA / 100), 2)
```

En palabras sencillas:

1. Se toma la tarifa del producto para la cuenta de la tienda.
2. Se suma el beneficio fijo.
3. Se calcula el porcentaje sobre ese resultado y se suma.
4. El resultado es el precio base sin IVA.
5. Se añade el IVA y se redondea a dos decimales.

El porcentaje **también se calcula sobre el beneficio fijo**, porque el fijo se
suma antes de aplicar el porcentaje.

### Sobre el ejemplo de 2,12 €

La fórmula anterior es la implementada actualmente. Con una tarifa de 1 €, un
beneficio fijo de 1 € y un 12 %, el resultado es **2,24 €** sin IVA, no 2,12 €.

El resultado de 2,12 € correspondería a esta otra fórmula:

```text
tarifa + beneficio fijo + (tarifa × porcentaje / 100)
```

Esa segunda fórmula **no es la que aplica el sistema**. El ejemplo de 2,12 € no
debe presentarse como resultado correcto del cálculo actual.

---

## 6. Orden de las operaciones y redondeo

El orden comprobado es:

1. Se resuelve la tarifa válida del producto.
2. La tarifa se normaliza a dos decimales.
3. Se suma el beneficio fijo.
4. Se aplica el porcentaje sobre la suma anterior.
5. El precio base se redondea a dos decimales.
6. Se aplica el IVA del producto.
7. El precio final se vuelve a redondear a dos decimales.

La web muestra el precio con IVA incluido. El pedido guarda la base sin IVA y el
tipo de IVA aplicado; el impuesto se calcula al totalizar el pedido.

Si el producto no trae un IVA propio, se usa el IVA por defecto de la tienda. Si
la tienda tampoco tiene uno configurado, se usa el 21 %.

---

## 7. Ejemplos ilustrativos

Todos los importes de esta sección son inventados y sirven solo para explicar el
cálculo.

### Ejemplo A: producto económico

Datos de ejemplo:

- tarifa: 1,00 €;
- beneficio fijo: 1,00 €;
- beneficio porcentual: 12 %;
- IVA: 21 %.

| Paso | Operación | Resultado |
|---|---|---:|
| 1 | Tarifa | 1,00 € |
| 2 | Sumar el beneficio fijo: `1,00 + 1,00` | 2,00 € |
| 3 | Calcular el 12 % de 2,00: `2,00 × 0,12` | 0,24 € |
| 4 | Precio base: `2,00 + 0,24` | **2,24 €** |
| 5 | IVA: `2,24 × 0,21` | 0,47 € |
| 6 | Precio final: `2,24 + 0,47` | **2,71 €** |

En la web se mostrarían **2,71 €** con IVA incluido. El pedido guardaría una base
de **2,24 €** y el 21 % de IVA.

### Ejemplo B: producto de mayor precio

Se mantienen el beneficio fijo, el porcentaje y el IVA, y se cambia solo la
tarifa:

| Paso | Operación | Resultado |
|---|---|---:|
| 1 | Tarifa | 500,00 € |
| 2 | Sumar el beneficio fijo: `500,00 + 1,00` | 501,00 € |
| 3 | Calcular el 12 % de 501,00: `501,00 × 0,12` | 60,12 € |
| 4 | Precio base: `501,00 + 60,12` | **561,12 €** |
| 5 | IVA: `561,12 × 0,21` | 117,84 € |
| 6 | Precio final: `561,12 + 117,84` | **678,96 €** |

El precio mostrado sería **678,96 €**. La base guardada en el pedido sería
**561,12 €**.

---

## 8. IVA: mostrado y guardado

La web pública trabaja con el precio final:

- muestra el precio con IVA incluido;
- indica «IVA incluido» junto al precio.

El pedido no guarda ese precio final como base. Guarda:

- la base sin IVA en `price_customer`;
- el tipo de IVA en `tax_rate`;
- los totales del pedido en `subtotal`, `tax_total`, `shipping` y `total`.

Por eso, en un pedido de una unidad, el importe de producto que ve el cliente y
la suma de base más IVA del pedido coinciden normalmente. Las diferencias
posibles de redondeo se explican en el apartado 11.

---

## 9. Cómo influyen stock, ofertas y ausencia de datos

### Stock

Solo se usa un precio asociado a un almacén que cumpla las condiciones de
idirecto:

- con stock suficiente;
- activo;
- con una tarifa válida para la cuenta y la sucursal;
- de un tipo de almacén admitido.

El stock del almacén elegido también limita la cantidad que se puede añadir al
carrito. No se suman existencias de otros almacenes si eso permitiera vender a
un precio distinto.

### Ofertas

La etiqueta «Oferta» no cambia el cálculo. El precio sigue siendo el que resulte
de la tarifa y de los beneficios configurados.

### Si falta la tarifa

Si no hay una tarifa válida para el producto y la cuenta:

- el producto no tiene precio calculable;
- en los listados aparece **«Consultar»**;
- en la ficha aparece un aviso de que el precio no está disponible;
- no se puede añadir al carrito.

No se usa el coste interno como sustituto para inventar un precio de venta.

### Si falta el coste

El coste interno no forma parte de la fórmula. Sin embargo, el catálogo exige
un coste positivo para incluir el producto en los listados, así que un producto
sin coste puede no aparecer o quedar marcado como no disponible.

### Si falta la tarifa de la cuenta

La plataforma puede aplicar una tarifa general. Si aun así no existe un precio
válido para el producto, se muestra «Consultar».

### Si faltan las tablas del catálogo

El catálogo se considera no disponible y devuelve listados vacíos; no se
inventan precios.

---

## 10. En qué pantallas se calcula o se vuelve a consultar

El precio del catálogo **no se guarda en una tabla de productos**. Se calcula al
mostrarlo y se vuelve a resolver en cada petición.

| Pantalla o proceso | Qué ocurre |
|---|---|
| Listados y tarjetas | Se resuelve la tarifa y se aplican los beneficios y el IVA. |
| Buscador | Usa el mismo cálculo que los listados. |
| Portada y destacados | Se guardan solo los identificadores; el precio se lee de nuevo en cada visita. |
| Ficha de producto | Se vuelve a calcular el precio y se muestra con IVA. |
| Carrito | Solo guarda producto y cantidad; el precio y el stock se leen de nuevo cada vez. |
| Checkout | Recalcula los totales en el servidor a partir del carrito actual. |
| Pedido | Guarda una copia de la base y del IVA aplicados. Los pedidos ya creados no cambian si después se modifican los beneficios. |

Los precios no se cachean. Se pueden cachear identificadores, contadores o
listados auxiliares, pero no el importe que se muestra al cliente.

---

## 11. Precio mostrado frente a importe guardado en el pedido

La diferencia principal es intencionada:

- **Cliente:** ve el precio final con IVA incluido.
- **Pedido:** guarda la base sin IVA y el tipo de IVA; el IVA se calcula al
  totalizar.

### Diferencia conocida de redondeo

Hay una diferencia adicional que se ha comprobado en el código actual: el
carrito y el pedido no agrupan el IVA exactamente igual cuando hay varias
unidades.

- El carrito parte del precio con IVA redondeado por unidad y calcula el IVA
  como diferencia.
- El pedido guarda la base sin IVA y calcula el IVA multiplicando la base
  agregada por el tipo.

Ejemplo aritmético ilustrativo, con una base de 2,25 €, IVA del 21 % y 2
unidades:

| Pantalla | Cálculo | Total |
|---|---|---:|
| Precio unitario | `2,25 × 1,21 = 2,7225`, redondeado | 2,72 € |
| Carrito | `2,72 × 2` | **5,44 €** |
| Pedido | base `4,50 €`; IVA `4,50 × 21 % = 0,945`, redondeado a 0,95 € | **5,45 €** |

La diferencia es de **1 céntimo** en este ejemplo. Puede aparecer en pedidos con
varias unidades y determinados importes. No es una alteración manual del IVA ni
un descuento: es el resultado actual de los dos redondeos.

Mientras se revise esta diferencia, no se deben corregir los importes de un
pedido a mano. Si aparece una diferencia de este tipo, conviene conservar el
pedido y consultarlo con soporte.

---

## 12. Qué puede variar según la tienda

Puede cambiar de una tienda a otra:

- la cuenta de idirecto y, por tanto, la tarifa base;
- la sucursal de la cuenta;
- el beneficio fijo;
- el beneficio porcentual;
- el IVA por defecto;
- sus productos propios y el precio que tengan asignado.

No cambia con la tienda la fórmula general ni el hecho de que la tarifa base se
lea de idirecto.

---

## 13. Hechos comprobados y limitaciones

### Comprobado en el código

- La fórmula actual es `(tarifa + beneficio fijo) × (1 + porcentaje / 100)`.
- El IVA se aplica después del beneficio.
- La tarifa base se lee de la cuenta de idirecto, no de `mt_stores.id_margen`.
- La web muestra el precio con IVA incluido.
- El pedido guarda la base sin IVA y el tipo de IVA.
- Los precios del catálogo no se guardan ni se cachean.
- Los productos propios no usan los beneficios del catálogo.
- Las ofertas del catálogo no cambian el precio calculado.
- El sistema no usa el coste interno como sustituto cuando falta la tarifa.

### Limitación pendiente

- El redondeo del IVA puede hacer que el total del carrito y el total del pedido
  difieran en céntimos cuando hay varias unidades. Está documentado en el
  apartado 11 y no se ha modificado el código.

### Fuera del alcance de esta guía

- La tarifa base se gestiona en idirecto; esta guía no explica cómo cambiarla
  allí.
- No se han usado precios ni datos personales de clientes reales.
- Las cuestiones fiscales deben consultarse con una persona o asesoría
  especializada.

---

## 14. Referencias técnicas para soporte

Esta sección es para soporte técnico. No es necesaria para usar el panel.

- `app/Core/TenantResolver.php`: resolución de la tienda por hostname.
- `app/Core/Controller.php`: fija el contexto de precios de la tienda.
- `app/Core/Idirecto/Account.php`: obtiene `id_margen` y `sucursal_id` de la
  cuenta de idirecto.
- `app/Core/Idirecto/PriceResolver.php`: selecciona el precio y el almacén
  válidos.
- `app/Core/Idirecto/Pricing.php`: tarifa, coste e IVA de una línea que se
  enviará al mayorista.
- `app/Models/Catalog.php`: aplica la fórmula, el IVA y el redondeo; resuelve
  listados, fichas y búsquedas.
- `app/Core/Cart.php`: recarga los precios vivos y calcula los totales del
  carrito.
- `app/Core/Checkout.php`: crea las líneas del pedido con la base sin IVA.
- `app/Models/Order.php`: crea el pedido, recalcula los totales y guarda la
  tarifa del mayorista.
- `app/Views/panel/settings.php`: campos de venta y cobro del panel.
- `mt_stores`: `markup`, `markup_fixed`, `tax_rate`, `id_tienda_idirecto`.
- `tiendas`: `id_margen`, `sucursal_id`.
- `precios`: `precio`, `id_margen`, `sucursal_id`, `id_almacen`, `id_stock`.
- `stock`: `costo`, `stock`, `activo`, `id_almacen`.
- `almacenes` y `tarifas`: tipos de almacén y tarifas aplicables.
- `mt_own_products`: `price`, `sale_price`, `tax_rate`.
- `mt_order_items`: `price_customer`, `tax_rate`, `price_idirecto`,
  `cost_idirecto`.
- `mt_orders`: `subtotal`, `tax_total`, `shipping`, `total`.

Ruta de configuración: `/panel/ajustes#venta-y-cobro`.

Guía relacionada del Centro de Ayuda:
`/panel/ayuda/productos/configurar-calcular-tarifa`.
