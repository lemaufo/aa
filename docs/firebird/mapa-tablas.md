# Mapa de tablas de Microsip

Generado con `php artisan aa:firebird --mapa` el 2026-10-26 19:21 sobre la copia local (Firebird 5.0.3, ODS 13.1, charset NONE).
Confirmar con IBExpert antes de escribir cualquier servicio.

## Cómo usa la app estas tablas

- **Ticket** (`FirebirdVentas::consultar`): `DOCTOS_PV` con `TIPO_DOCTO` V (ticket) o F (factura) y `FOLIO`; `ESTATUS` C = cancelado, `APLICADO` <> 'S' = pendiente. Artículos en `DOCTOS_PV_DET` (sin `ROL` = 'C', componentes de juego) + `ARTICULOS.NOMBRE`.
- **Entrega** (`FirebirdVentas::marcarEntregada`): igual que "Control de Entregas", `LIBRES_VTA_PV.ENTREGADO` = 'S' en la fila del `DOCTO_PV_ID`. La tabla no tiene triggers ni dependencias sobre esa columna. Es la única escritura de la app; no inserta filas ni toca `POR_RECOGER` u `OBSERVACIONES`.
- **Traspaso** (`FirebirdTraspasos`): `EXP_TRASPASOS` por `FOLIO` (el más reciente), `ESTATUS` C = cancelado; partidas en `EXP_TRASPASOS_DET`, destino en `EXP_SYNC_SUCURSALES`. Los cambios de estatus quedan en `EXP_TRASPASOS_BITACORA`.

## DOCTOS_PV

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| DOCTO_PV_ID | INTEGER | no |  |
| CAJA_ID | INTEGER | no |  |
| TIPO_DOCTO | CHAR(1) | no |  |
| SUCURSAL_ID | INTEGER | no |  |
| FOLIO | CHAR(9) | no |  |
| FECHA | DATE | no | 'TODAY' |
| HORA | TIME | no | 'NOW' |
| CAJERO_ID | INTEGER | sí |  |
| CLAVE_CLIENTE | VARCHAR(20) | sí |  |
| CLIENTE_ID | INTEGER | sí |  |
| CLAVE_CLIENTE_FAC | VARCHAR(20) | sí |  |
| CLIENTE_FAC_ID | INTEGER | sí |  |
| DIR_CLI_ID | INTEGER | sí |  |
| ALMACEN_ID | INTEGER | sí |  |
| LUGAR_EXPEDICION_ID | INTEGER | sí |  |
| MONEDA_ID | INTEGER | sí |  |
| IMPUESTO_INCLUIDO | CHAR(1) | sí |  |
| TIPO_CAMBIO | NUMERIC(18,6) | sí |  |
| TIPO_DSCTO | CHAR(1) | sí |  |
| DSCTO_PCTJE | NUMERIC(9,6) | sí |  |
| DSCTO_IMPORTE | NUMERIC(15,2) | sí |  |
| ESTATUS | CHAR(1) | sí |  |
| APLICADO | CHAR(1) | sí |  |
| FECHA_VIGENCIA | DATE | sí |  |
| IMPORTE_NETO | NUMERIC(15,2) | sí |  |
| TOTAL_IMPUESTOS | NUMERIC(15,2) | sí |  |
| TOTAL_RETENCIONES | NUMERIC(15,2) | sí |  |
| PESO_EMBARQUE | NUMERIC(12,3) | no | 0 |
| IMPORTE_DONATIVO | NUMERIC(15,2) | sí |  |
| TOTAL_FPGC | NUMERIC(15,2) | sí |  |
| TICKET_EMITIDO | CHAR(1) | sí |  |
| FORMA_GLOBAL_EMITIDA | CHAR(1) | sí |  |
| FORMA_EMITIDA | CHAR(1) | sí |  |
| CONTABILIZADO | CHAR(1) | sí |  |
| SISTEMA_ORIGEN | CHAR(2) | no |  |
| PROCESO_ORIGEN | CHAR(1) | no | 'N' |
| VENDEDOR_ID | INTEGER | sí |  |
| CARGAR_SUN | CHAR(1) | sí |  |
| PERSONA | VARCHAR(50) | sí |  |
| TIPO_GEN_FAC | CHAR(1) | sí |  |
| ES_FAC_GLOBAL | CHAR(1) | no |  |
| PERIODICIDAD_FAC_GLOBAL | CHAR(2) | sí |  |
| FECHA_INI_FAC_GLOBAL | DATE | sí |  |
| FECHA_FIN_FAC_GLOBAL | DATE | sí |  |
| INCL_FACTURADOS_FAC_GLOBAL | CHAR(1) | sí |  |
| ALMACEN_ID_FAC_GLOBAL | INTEGER | sí |  |
| REFER_RETING | VARCHAR(50) | sí |  |
| UNID_COMPROM | CHAR(1) | sí |  |
| DESCRIPCION | VARCHAR(200) | sí |  |
| IMPUESTO_SUSTITUIDO_ID | INTEGER | sí |  |
| IMPUESTO_SUSTITUTO_ID | INTEGER | sí |  |
| ES_CFD | CHAR(1) | sí |  |
| MODALIDAD_FACTURACION | VARCHAR(10) | sí |  |
| ENVIADO | CHAR(1) | sí |  |
| EMAIL_ENVIO | VARCHAR(200) | sí |  |
| USUARIO_CREADOR | VARCHAR(31) | sí |  |
| CFDI_CERTIFICADO | CHAR(1) | no | 'N' |
| USO_CFDI | CHAR(4) | sí |  |
| METODO_PAGO_SAT | CHAR(3) | sí |  |
| PARTIDA_AJUSTE_ID | INTEGER | sí |  |
| PRECIO_ORIG_PARTIDA_AJUSTE | NUMERIC(18,6) | sí |  |
| TERCERO_CO_ID | INTEGER | sí |  |
| FECHA_HORA_CREACION | TIMESTAMP | sí |  |
| USUARIO_ULT_MODIF | VARCHAR(31) | sí |  |
| USUARIO_AUT_CREACION | VARCHAR(31) | sí |  |
| FECHA_HORA_ULT_MODIF | TIMESTAMP | sí |  |
| USUARIO_CANCELACION | VARCHAR(31) | sí |  |
| USUARIO_AUT_MODIF | VARCHAR(31) | sí |  |
| FECHA_HORA_CANCELACION | TIMESTAMP | sí |  |
| FECHA_HORA_ENVIO | TIMESTAMP | sí |  |
| USUARIO_AUT_CANCELACION | VARCHAR(31) | sí |  |

Triggers: DOCTOS_PV_AFTALL_SNUBE, DOCTOS_PV_AFTDEL_0, DOCTOS_PV_AFTUPD_0, DOCTOS_PV_BEFDEL_0, DOCTOS_PV_BEFINS, DOCTOS_PV_BEFINSUPD_RI, DOCTOS_PV_BEFUPD_0, DOCTOS_PV_AFTUPD_1, DOCTOS_PV_BEFINSUPD_1, TR_SDEL_DOCTOS_PV, TR_SYNC_DOCTOS_PV, TR_SYNC_DOCTOS_PV_INS

## DOCTOS_PV_DET

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| DOCTO_PV_DET_ID | INTEGER | no |  |
| DOCTO_PV_ID | INTEGER | no |  |
| CLAVE_ARTICULO | VARCHAR(20) | sí |  |
| ARTICULO_ID | INTEGER | no |  |
| UNIDADES | NUMERIC(18,5) | sí |  |
| UNIDADES_DEV | NUMERIC(18,5) | sí |  |
| UNIDADES_SURT | NUMERIC(18,5) | sí |  |
| UNIDADES_A_SURTIR | NUMERIC(18,5) | sí |  |
| TIPO_CONTAB_UNID | CHAR(1) | sí | '0' |
| PRECIO_UNITARIO | NUMERIC(18,6) | sí |  |
| PRECIO_UNITARIO_IMPTO | NUMERIC(18,6) | sí |  |
| IMPUESTO_POR_UNIDAD | NUMERIC(18,6) | sí |  |
| PCTJE_DSCTO | NUMERIC(9,6) | sí |  |
| PRECIO_TOTAL_NETO | NUMERIC(15,2) | sí |  |
| PRECIO_MODIFICADO | CHAR(1) | no | 'N' |
| VENDEDOR_ID | INTEGER | sí |  |
| PCTJE_COMIS | NUMERIC(9,6) | sí |  |
| ROL | CHAR(1) | no |  |
| NOTAS | BLOB TEXT | sí |  |
| ES_TRAN_ELECT | CHAR(1) | sí |  |
| ESTATUS_TRAN_ELECT | CHAR(1) | sí |  |
| POSICION | INTEGER | no | 0 |
| DSCTO_ART | NUMERIC(15,2) | sí |  |
| DSCTO_EXTRA | NUMERIC(15,2) | sí |  |

Triggers: DOCTOS_PV_DET_BEFDEL_0, DOCTOS_PV_DET_BEFDEL_RI, DOCTOS_PV_DET_BEFINS, DOCTOS_PV_DET_BEFUPD_0

## ARTICULOS

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| ARTICULO_ID | INTEGER | no |  |
| NOMBRE | VARCHAR(200) | no |  |
| ES_ALMACENABLE | CHAR(1) | sí |  |
| ES_JUEGO | CHAR(1) | sí |  |
| ESTATUS | CHAR(1) | no | 'A' |
| CAUSA_SUSP | VARCHAR(100) | sí |  |
| FECHA_SUSP | DATE | sí |  |
| IMPRIMIR_COMP | CHAR(1) | sí |  |
| PERMITIR_AGREGAR_COMP | CHAR(1) | sí |  |
| LINEA_ARTICULO_ID | INTEGER | sí |  |
| UNIDAD_VENTA | VARCHAR(20) | sí |  |
| UNIDAD_COMPRA | VARCHAR(20) | sí |  |
| CONTENIDO_UNIDAD_COMPRA | NUMERIC(18,5) | sí |  |
| PESO_UNITARIO | NUMERIC(18,5) | sí |  |
| ES_PESO_VARIABLE | CHAR(1) | sí |  |
| SEGUIMIENTO | CHAR(1) | sí | 'N' |
| DIAS_GARANTIA | INTEGER | sí |  |
| ES_IMPORTADO | CHAR(1) | sí |  |
| ES_SIEMPRE_IMPORTADO | CHAR(1) | sí |  |
| PCTJE_ARANCEL | NUMERIC(9,6) | sí |  |
| NOTAS_COMPRAS | BLOB TEXT | sí |  |
| IMPRIMIR_NOTAS_COMPRAS | CHAR(1) | sí |  |
| NOTAS_VENTAS | BLOB TEXT | sí |  |
| IMPRIMIR_NOTAS_VENTAS | CHAR(1) | sí |  |
| ES_PRECIO_VARIABLE | CHAR(1) | sí |  |
| CUENTA_ALMACEN | VARCHAR(30) | sí |  |
| CUENTA_COSTO_VENTA | VARCHAR(30) | sí |  |
| CUENTA_VENTAS | VARCHAR(30) | sí |  |
| CUENTA_DSCTO_VENTAS | VARCHAR(30) | sí |  |
| CUENTA_DEVOL_VENTAS | VARCHAR(30) | sí |  |
| CUENTA_COMPRAS | VARCHAR(30) | sí |  |
| CUENTA_DEVOL_COMPRAS | VARCHAR(30) | sí |  |
| APLICAR_FACTOR_VENTA | CHAR(1) | no |  |
| FACTOR_VENTA | NUMERIC(18,5) | no |  |
| RED_PRECIO_CON_IMPTO | CHAR(1) | no |  |
| FACTOR_RED_PRECIO_CON_IMPTO | NUMERIC(18,6) | sí |  |
| USUARIO_CREADOR | VARCHAR(31) | sí |  |
| FECHA_HORA_CREACION | TIMESTAMP | sí |  |
| USUARIO_AUT_CREACION | VARCHAR(31) | sí |  |
| USUARIO_ULT_MODIF | VARCHAR(31) | sí |  |
| FECHA_HORA_ULT_MODIF | TIMESTAMP | sí |  |
| USUARIO_AUT_MODIF | VARCHAR(31) | sí |  |

Triggers: ARTICULOS_AFTALL_SNUBE (inactivo), ARTICULOS_AFTDEL_0, ARTICULOS_BEFALL_SNUBE (inactivo), ARTICULOS_BEFDEL_0, ARTICULOS_BEFINS, ARTICULOS_BEFINSUPD (inactivo), ARTICULOS_BEFUPDDEL_RI, ARTICULOS_BEFUPD_0, ARTICULOS_BEFUPD_1, TR_SDEL_ARTICULOS, TR_SYNC_ARTICULOS

## LIBRES_VTA_PV

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| DOCTO_PV_ID | INTEGER | no |  |
| ENTREGADO | CHAR(1) | sí |  |
| POR_RECOGER | CHAR(1) | sí |  |
| OBSERVACIONES | VARCHAR(99) | sí |  |

## EXP_TRASPASOS

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| EXP_TRASP_ID | INTEGER | no |  |
| ORIGEN | INTEGER | no |  |
| DESTINO | INTEGER | no |  |
| FOLIO | VARCHAR(9) | no |  |
| FOLIO_RECEPCION | CHAR(9) | sí |  |
| TIPO | CHAR(1) | sí |  |
| ESTATUS | CHAR(1) | sí |  |
| FECHA | DATE | sí |  |
| OBSERVACIONES | VARCHAR(200) | sí |  |
| ALMACEN_ID | INTEGER | sí |  |
| F_ALTA | TIMESTAMP | sí |  |
| U_ALTA | VARCHAR(31) | sí |  |
| F_MODIFICA | TIMESTAMP | sí |  |
| U_MODIFICA | VARCHAR(31) | sí |  |
| F_CANCELA | TIMESTAMP | sí |  |
| U_CANCELA | VARCHAR(31) | sí |  |
| TIPO_DOCTO | CHAR(3) | sí |  |
| DOCTO_IN_ID | INTEGER | sí |  |
| CENTRO_COSTO_ID | INTEGER | sí |  |
| CONS_TRASP | INTEGER | sí |  |
| TOTAL_IMPUESTOS | NUMERIC(15,6) | sí |  |
| DSCTO_IMPORTE | NUMERIC(15,2) | sí |  |
| TIPO_DSCTO | CHAR(1) | sí |  |
| DSCTO_PCTJE | NUMERIC(9,6) | sí |  |
| FOLIO_CM | VARCHAR(9) | sí |  |
| F_ENTRADA | DATE | sí |  |
| OTROS_GASTOS | NUMERIC(18,6) | sí |  |

Triggers: TR_EXP_TRASPASOS_IU, TR_SYNC_EXP_TRASPASOS, TR_EXP_TRASPASOS_BITACORA, TR_SYNC_EXP_TRASPASOS_UPD

## EXP_TRASPASOS_DET

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| EXP_TRASP_ID | INTEGER | no |  |
| PARTIDA | INTEGER | no |  |
| ARTICULO_ID | INTEGER | sí |  |
| CANTIDAD | NUMERIC(18,4) | sí |  |
| RECIBIDO | NUMERIC(18,4) | sí |  |
| COSTO | NUMERIC(18,6) | sí |  |
| IMPUESTO | NUMERIC(18,6) | sí |  |
| PCTJE_DSCTO | NUMERIC(9,6) | sí | 0 |
| COSTO_ENT | NUMERIC(18,6) | sí |  |
| DOCTO_IN_DET_ID | INTEGER | sí |  |
| NOTAS | VARCHAR(250) | sí |  |
| PEDIMENTO_ID | INTEGER | sí |  |
| CLAVE_ARTICULO | VARCHAR(20) | sí |  |
| UNIDAD | VARCHAR(20) | sí |  |

Triggers: TR_EXP_TRASPASOS_DET_INS

## EXP_TRASPASOS_BITACORA

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| BIT_ID | INTEGER | no |  |
| EXP_TRASP_ID | INTEGER | no |  |
| ORIGEN | INTEGER | no |  |
| DESTINO | INTEGER | no |  |
| FOLIO | CHAR(9) | no |  |
| FECHA_DOC | DATE | no |  |
| TIPO_DOC | CHAR(1) | no |  |
| ESTATUS_DOC | CHAR(1) | no |  |
| TIPO_CAMBIO | CHAR(1) | no |  |
| FECHA_CAMBIO | TIMESTAMP | sí |  |
| USUARIO_CAMBIO | VARCHAR(31) | sí |  |
| IP_CAMBIO | VARCHAR(255) | sí |  |
| PROC_CAMBIO | VARCHAR(255) | sí |  |

## EXP_SYNC_SUCURSALES

| Columna | Tipo | Nulo | Default |
|---|---|---|---|
| SUCURSAL_ID | INTEGER | no |  |
| NOMBRE | VARCHAR(50) | no |  |
| ESTATUS | CHAR(1) | sí | 'A' |
| FECHA_ALTA | TIMESTAMP | sí | CURRENT_TIMESTAMP |
| ULTIMA_SINCRONIZACION | TIMESTAMP | sí |  |
| ULTIMA_CONEXION | TIMESTAMP | sí |  |
| ULTIMA_TABLA | VARCHAR(31) | sí |  |
| IP | VARCHAR(50) | sí |  |
| ALMACEN_ID | INTEGER | sí |  |
| CLIENTE_ID | INTEGER | sí |  |
| VERSION | VARCHAR(10) | sí |  |
| FLOCAL | TIMESTAMP | sí |  |
| PROVEEDOR_ID | INTEGER | sí |  |
| SIG_FOLIOE | CHAR(9) | sí |  |
| SIG_FOLIOS | CHAR(9) | sí |  |
| F_CONCEPTO | CHAR(1) | sí |  |
| SIG_REQUE | CHAR(9) | sí |  |
| ZONA_ID | INTEGER | sí |  |
| LICENCIA | VARCHAR(37) | sí |  |
| NET_ADAPTERS | BLOB TEXT | sí |  |
| SUCURSAL_MSP_ID | INTEGER | sí |  |
| SERIE_GRAL | VARCHAR(5) | sí |  |
| SYSDBAPASS | BLOB TEXT | sí |  |
| ULTIMA_CONFIG | TIMESTAMP | sí |  |
| SUCURSAL_PADRE_ID | INTEGER | sí |  |
| TIPO | CHAR(1) | sí |  |
| MSP_VERSION | VARCHAR(20) | sí |  |
| AUTORIZACION | VARCHAR(100) | sí |  |
| SIG_FOLIOCM | CHAR(9) | sí |  |
| SIG_FOLIOOR | CHAR(9) | sí |  |
| SIG_FOLIORE | CHAR(9) | sí |  |
| SYNC | BLOB TEXT | sí |  |

Triggers: EXP_SYNC_SUCURSALES_BI, EXP_SYNC_SUCURSALES_BU, TR_EXP_SYNC_SUCURSALES_BD
