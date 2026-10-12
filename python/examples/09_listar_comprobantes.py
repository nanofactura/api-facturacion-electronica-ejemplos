"""09 · Listar comprobantes

El listado devuelve sólo los comprobantes del entorno de tu API key: una clave TEST_ nunca ve los
de producción, ni al revés.

Los filtros son estilo Django: `campo=valor` o `campo__operador=valor`, y todos se combinan con
AND. ⚠️ Los campos desconocidos se ignoran EN SILENCIO: un filtro mal escrito no da error, sólo
devuelve más resultados de los que esperabas.

    python examples/09_listar_comprobantes.py
"""

from collections.abc import Iterator

from _bootstrap import formatear_numero

from nanofactura import NanoFactura
from nanofactura.constants import TipoComprobante

nanofactura = NanoFactura()

# ── Los últimos 10 ───────────────────────────────────────────────────────────

comprobantes, total = nanofactura.listar_comprobantes({"limit": 10, "ordering": "-created"})

print(f"Últimos {len(comprobantes)} de {total} comprobantes:\n")
for c in comprobantes:
    numero = formatear_numero(c["posNumber"], c["voucherNumber"])
    print(f"  {numero}  {c['voucherDate']}  $ {c['totalAmount']:>12}  {c['docNumber']:<11}  {c['source']}")

# ── Uno por id ───────────────────────────────────────────────────────────────
#
# Con el `id` que devolvió la emisión —el que guardaste junto a tu venta— lo pedís directo, sin
# filtrar el listado. Trae el mismo objeto que una fila del listado; si no existe, o es del otro
# entorno, la API responde 404 NOT_FOUND.

if comprobantes:
    comprobante = nanofactura.obtener_comprobante(comprobantes[0]["id"])
    pdf = "listo" if comprobante["hasPdf"] else "generándose"
    print(f"\nEl último, por id: {comprobante['id']} · CAE {comprobante['cae']} · PDF {pdf}")

# ── Facturación de un mes ────────────────────────────────────────────────────
#
# `date` es la fecha FISCAL (la que declaraste al emitir). `created` es cuándo se emitió realmente.
# Para reportes fiscales usá `date`; para conciliar con tu sistema, `created`.

del_mes, total_mes = nanofactura.listar_comprobantes(
    {"date__gte": "20260801", "date__lte": "20260831", "ordering": "date", "limit": 200}
)

# En el listado los importes viajan como string, para no perder precisión al serializarlos.
# Convertilos antes de operar: sumarlos tal cual corta con TypeError.
facturado = sum(float(c["totalAmount"]) for c in del_mes)
print(f"\nAgosto 2026: {total_mes} comprobantes · $ {facturado:,.2f}")

# ── Buscar por tu propio identificador ───────────────────────────────────────
#
# Para esto sirve `externalReference`: encontrar el comprobante desde tu sistema sin llevar una
# tabla de equivalencias.

del_pedido, _ = nanofactura.listar_comprobantes({"externalReference": "PEDIDO-2026-00042"})
print(f"\nComprobantes del pedido PEDIDO-2026-00042: {len(del_pedido)}")

# ── Sólo notas de crédito ────────────────────────────────────────────────────

tipos_nc = ",".join(
    str(int(t))
    for t in (
        TipoComprobante.NOTA_CREDITO_A,
        TipoComprobante.NOTA_CREDITO_B,
        TipoComprobante.NOTA_CREDITO_C,
    )
)
_, total_nc = nanofactura.listar_comprobantes({"type__in": tipos_nc, "limit": 1})
print(f"Notas de crédito emitidas: {total_nc}")


# ── Paginar todo ─────────────────────────────────────────────────────────────
#
# `total` es cuántos matchean el filtro, no cuántos vinieron en la página: es lo que permite
# paginar sin depender de que la página vuelva llena.


def todos_los_comprobantes(nanofactura: NanoFactura, filtros: dict | None = None) -> Iterator[dict]:
    limit, offset, total = 200, 0, None

    while total is None or offset < total:
        pagina, total = nanofactura.listar_comprobantes(
            {**(filtros or {}), "limit": limit, "offset": offset, "ordering": "id"}
        )
        if not pagina:
            break
        yield from pagina
        offset += limit


contados = sum(1 for _ in todos_los_comprobantes(nanofactura, {"date__gte": "20260101"}))
print(f"\nRecorridos {contados} comprobantes de 2026 paginando de a 200.")
