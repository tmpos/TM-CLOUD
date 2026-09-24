"use strict";

// src/server/expenseStdio.ts
var import_node_fs = require("node:fs");
var import_node_string_decoder = require("node:string_decoder");
var import_node_crypto = require("node:crypto");

// src/services/gastoContabilidad.ts
function esMovimientoNomina(gasto) {
  return Boolean(gasto?.nomina_pago_id || gasto?.nomina_movimiento_id) || ["NOMINA", "PRESTAMO_EMPLEADO", "ADELANTO_EMPLEADO"].includes(String(gasto?.categoria || "").toUpperCase());
}

// src/domain/expenseFiscal.ts
var tiposBienesServicios606 = [
  ["01", "Gastos de personal"],
  ["02", "Gastos por trabajos, suministros y servicios"],
  ["03", "Arrendamientos"],
  ["04", "Gastos de activos fijos"],
  ["05", "Gastos de representaci\xF3n"],
  ["06", "Otras deducciones admitidas"],
  ["07", "Gastos financieros"],
  ["08", "Gastos extraordinarios"],
  ["09", "Compras y gastos que forman parte del costo de venta"],
  ["10", "Adquisiciones de activos"],
  ["11", "Gastos de seguros"]
].map(([value, nombre]) => ({ value, nombre, label: `${value} - ${nombre}` }));
function normalizarTipo606(value) {
  const code = String(value ?? "").trim().padStart(2, "0");
  return tiposBienesServicios606.some((tipo) => tipo.value === code) ? code : "";
}
var formasPago606 = ["Efectivo", "Cheques / transferencias / dep\xF3sito", "Tarjeta cr\xE9dito / d\xE9bito", "Compra a cr\xE9dito", "Permuta", "Notas de cr\xE9dito", "Mixto"].map((label, i) => ({ value: String(i + 1).padStart(2, "0"), label }));
var tiposRetencionISR606 = ["Alquileres", "Honorarios por servicios", "Otras rentas", "Otras rentas (rentas presuntas)", "Intereses a personas jur\xEDdicas residentes", "Intereses a personas f\xEDsicas residentes", "Proveedores del Estado", "Juegos telef\xF3nicos", "Ganader\xEDa de carne bovina"].map((label, i) => ({ value: String(i + 1).padStart(2, "0"), label }));
var camposMontos606 = ["monto_bienes", "monto_servicios", "descuento", "itbis", "itbis_retenido", "itbis_proporcionalidad", "itbis_costo", "itbis_adelantar", "itbis_percibido", "isr_retenido", "isr_percibido", "isc", "otros_impuestos", "propina_legal"];
function mapFormaPago606(value) {
  const text = String(value ?? "").trim().toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
  if (/^0?[1-7]$/.test(text)) return text.padStart(2, "0");
  return { EFECTIVO: "01", TRANSFERENCIA: "02", CHEQUE: "02", DEPOSITO: "02", TARJETA: "03", CREDITO: "04", PERMUTA: "05", NOTA_CREDITO: "06", MIXTO: "07" }[text] || "";
}
function datosFiscalesGasto(payload, anterior = {}) {
  const value = payload.tipo_bienes_servicios ?? anterior.tipo_bienes_servicios ?? "";
  const tipo = normalizarTipo606(value);
  if (String(value).trim() && !tipo) throw new Error("Selecciona un tipo de bienes y servicios v\xE1lido para el 606");
  const itbis = Number(payload.itbis ?? anterior.itbis ?? 0);
  if (!Number.isFinite(itbis) || itbis < 0 || itbis > Number(payload.cantidad ?? anterior.cantidad ?? 0)) {
    throw new Error("El ITBIS debe estar entre cero y el monto total del gasto");
  }
  const result = {
    tipo_bienes_servicios: tipo,
    rnc: String(payload.rnc ?? anterior.rnc ?? "").trim(),
    ncf: String(payload.ncf ?? anterior.ncf ?? "").trim().toUpperCase(),
    nombre_proveedor: String(payload.nombre_proveedor ?? anterior.nombre_proveedor ?? "").trim(),
    itbis
  };
  for (const campo of camposMontos606) {
    if (campo === "itbis" || campo === "itbis_adelantar") continue;
    const raw = payload[campo] ?? anterior[campo];
    if (raw == null && ["monto_bienes", "monto_servicios"].includes(campo)) continue;
    const value2 = Number(raw ?? 0);
    if (!Number.isFinite(value2) || value2 < 0) throw new Error(`Monto fiscal inv\xE1lido: ${campo}`);
    result[campo] = Math.round(value2 * 100) / 100;
  }
  if (result.itbis_costo > itbis || result.itbis_retenido > itbis || result.itbis_proporcionalidad > itbis) throw new Error("La distribuci\xF3n y retenci\xF3n de ITBIS no puede exceder el ITBIS facturado");
  result.itbis_adelantar = Math.round((itbis - result.itbis_costo) * 100) / 100;
  if (result.isr_retenido + result.itbis_retenido > Number(payload.cantidad ?? anterior.cantidad ?? 0)) throw new Error("Las retenciones no pueden exceder el monto total");
  for (const campo of ["tipo_identificacion", "ncf_modificado", "tipo_retencion_isr", "fecha_pago"]) result[campo] = String(payload[campo] ?? anterior[campo] ?? "").trim().toUpperCase();
  if (result.tipo_retencion_isr) result.tipo_retencion_isr = result.tipo_retencion_isr.padStart(2, "0");
  result.forma_pago_606 = mapFormaPago606(payload.forma_pago_606 ?? anterior.forma_pago_606 ?? payload.metodo_pago ?? anterior.metodo_pago);
  result.incluir_606 = ![false, 0, "0"].includes(payload.incluir_606 ?? anterior.incluir_606 ?? true);
  return result;
}

// src/server/expenseRuntimeHandler.ts
var EXPENSE_CHANNELS = ["gastos:guardarContable", "gastos:anularContable", "gastos:pagarContable", "gastos:revertirPagoContable", "gastos:aprobarContable", "gastos:consultarContabilidad", "gastos:cerrarPeriodo", "gastos:reabrirPeriodo", "gastos:listarCuentas"];
var money = (n) => {
  const v = Number(n ?? 0);
  if (!Number.isFinite(v) || v < 0) throw new Error("Monto inv\xE1lido");
  return Math.round(v * 100) / 100;
};
var assert = (v, message) => {
  if (!v) throw new Error(message);
};
var date = (v) => {
  const s = String(v || "");
  assert(/^\d{4}-\d{2}-\d{2}$/.test(s) && Number.isFinite((/* @__PURE__ */ new Date(`${s}T12:00:00Z`)).getTime()) && (/* @__PURE__ */ new Date(`${s}T12:00:00Z`)).toISOString().slice(0, 10) === s, "Fecha inv\xE1lida");
  return s;
};
var now = () => (/* @__PURE__ */ new Date()).toISOString();
var EXPENSE_DEFAULT_ACCOUNTS = [["1101", "Caja General", "ACTIVO"], ["1102", "Bancos", "ACTIVO"], ["1104", "ITBIS por adelantar", "ACTIVO"], ["2101", "Cuentas por Pagar", "PASIVO"], ["2102", "ITBIS retenido por pagar", "PASIVO"], ["2103", "ISR retenido por pagar", "PASIVO"], ["5201", "Gastos Operativos", "GASTOS"]];
function prepareExpenseSchema(db) {
  const add = (table, columns) => {
    const existing = new Set(db.all(`PRAGMA table_info(${table})`).map((r) => r.name));
    for (const [key, type] of Object.entries(columns)) if (!existing.has(key)) db.run(`ALTER TABLE ${table} ADD COLUMN ${key} ${type}`);
  };
  db.run("CREATE TABLE IF NOT EXISTS catalogo_cuentas (id INTEGER PRIMARY KEY AUTOINCREMENT,codigo TEXT UNIQUE,nombre TEXT,tipo TEXT,naturaleza TEXT DEFAULT 'DEUDORA',estado TEXT DEFAULT 'ACTIVA')");
  for (const [codigo, nombre, tipo] of EXPENSE_DEFAULT_ACCOUNTS) if (!db.all("SELECT id FROM catalogo_cuentas WHERE codigo=?", [codigo]).length) db.run("INSERT INTO catalogo_cuentas(codigo,nombre,tipo,naturaleza,estado) VALUES(?,?,?,?,?)", [codigo, nombre, tipo, tipo === "PASIVO" ? "ACREEDORA" : "DEUDORA", "ACTIVA"]);
  add("gastos", { pagos_contables: "TEXT DEFAULT '[]'", cuenta_contable_id: "INTEGER DEFAULT 0", cuenta_contable_uid: "TEXT DEFAULT ''", contabilidad_json: "TEXT DEFAULT ''", estado_contable: "TEXT DEFAULT ''", proveedor_id: "INTEGER DEFAULT 0", proveedor_uid: "TEXT DEFAULT ''", cuenta_contable_codigo: "TEXT DEFAULT ''", condicion_pago: "TEXT DEFAULT 'CONTADO'", fecha_vencimiento: "TEXT DEFAULT ''", saldo_pendiente: "REAL DEFAULT 0", total_pagado: "REAL DEFAULT 0", fecha_comprobante: "TEXT DEFAULT ''", fecha_pago: "TEXT DEFAULT ''", referencia_pago: "TEXT DEFAULT ''", adjuntos: "TEXT DEFAULT '[]'", tipo_identificacion: "TEXT DEFAULT ''", ncf_modificado: "TEXT DEFAULT ''", forma_pago_606: "TEXT DEFAULT ''", tipo_retencion_isr: "TEXT DEFAULT ''", incluir_606: "INTEGER DEFAULT 1", monto_bienes: "REAL", monto_servicios: "REAL", base_gravada: "REAL DEFAULT 0", base_exenta: "REAL DEFAULT 0", descuento: "REAL DEFAULT 0", itbis_costo: "REAL DEFAULT 0", itbis_proporcionalidad: "REAL DEFAULT 0", itbis_adelantar: "REAL DEFAULT 0", itbis_retenido: "REAL DEFAULT 0", isr_retenido: "REAL DEFAULT 0", itbis_percibido: "REAL DEFAULT 0", isr_percibido: "REAL DEFAULT 0", isc: "REAL DEFAULT 0", otros_impuestos: "REAL DEFAULT 0", propina_legal: "REAL DEFAULT 0", tarjeta: "REAL DEFAULT 0", tipo_bienes_servicios: "TEXT DEFAULT ''", itbis: "REAL DEFAULT 0", rnc: "TEXT DEFAULT ''", ncf: "TEXT DEFAULT ''", nombre_proveedor: "TEXT DEFAULT ''" });
  add("cuentas_pagar", { gasto_id: "INTEGER DEFAULT 0", gasto_uid: "TEXT DEFAULT ''", proveedor_id: "INTEGER DEFAULT 0", proveedor_uid: "TEXT DEFAULT ''", almacen_uid: "TEXT DEFAULT ''" });
  for (const table of ["gasto_pagos", "gasto_asientos", "gasto_historial"]) db.run(`CREATE TABLE IF NOT EXISTS ${table}(id INTEGER PRIMARY KEY AUTOINCREMENT,uid TEXT UNIQUE,gasto_id INTEGER NOT NULL,gasto_uid TEXT DEFAULT '',fecha TEXT NOT NULL,detalle TEXT NOT NULL,created_at TEXT,updated_at TEXT)`);
  db.run("CREATE TABLE IF NOT EXISTS contabilidad_periodos(id INTEGER PRIMARY KEY AUTOINCREMENT,uid TEXT UNIQUE,periodo TEXT UNIQUE,estado TEXT,usuario TEXT,motivo TEXT,created_at TEXT,updated_at TEXT)");
  db.run("CREATE TABLE IF NOT EXISTS _expense_guard(id INTEGER PRIMARY KEY,active INTEGER NOT NULL DEFAULT 0)");
  db.run("INSERT OR IGNORE INTO _expense_guard(id,active) VALUES(1,0)");
  db.run("CREATE TABLE IF NOT EXISTS gasto_operaciones(uid TEXT PRIMARY KEY,request TEXT NOT NULL,resultado TEXT NOT NULL)");
  for (const [table, condition] of [["gastos", "coalesce(OLD.contabilidad_json,'')<>''"], ["cuentas_pagar", "coalesce(OLD.gasto_id,0)>0"]]) {
    for (const action of ["UPDATE", "DELETE"]) db.run(`CREATE TRIGGER IF NOT EXISTS expense_protect_${table}_${action} BEFORE ${action} ON ${table} WHEN ${condition} AND (SELECT active FROM _expense_guard WHERE id=1)=0 BEGIN SELECT RAISE(ABORT,'Usa el modulo contable para modificar este registro'); END`);
  }
  for (const table of ["gasto_pagos", "gasto_asientos", "gasto_historial", "contabilidad_periodos", "gasto_operaciones"]) {
    for (const action of ["INSERT", "UPDATE", "DELETE"]) db.run(`CREATE TRIGGER IF NOT EXISTS expense_guard_${table}_${action} BEFORE ${action} ON ${table} WHEN (SELECT active FROM _expense_guard WHERE id=1)=0 BEGIN SELECT RAISE(ABORT,'Usa el modulo contable'); END`);
  }
}
function runExpenseTransaction(db, actor, channel, payload = {}) {
  db.run("BEGIN IMMEDIATE");
  try {
    prepareExpenseSchema(db);
    db.run("UPDATE _expense_guard SET active=1 WHERE id=1");
    const data = expenseOperation(db, actor, channel, payload);
    db.run("UPDATE _expense_guard SET active=0 WHERE id=1");
    db.run("COMMIT");
    return { success: true, data };
  } catch (error) {
    db.run("ROLLBACK");
    throw error;
  }
}
function expenseOperation(db, actor, channel, payload = {}) {
  assert(EXPENSE_CHANNELS.includes(channel), "Operaci\xF3n contable desconocida");
  const permission = channel === "gastos:pagarContable" ? "gastos-pagar" : ["gastos:anularContable", "gastos:revertirPagoContable"].includes(channel) ? "gastos-anular" : channel === "gastos:aprobarContable" ? "gastos-aprobar" : /Periodo$/.test(channel) ? "contabilidad-periodos" : "gastos";
  assert(actor.can(permission), "No tienes permiso para esta operaci\xF3n contable");
  const one = (sql2, p = []) => db.all(sql2, p)[0];
  const insert = (table, values) => {
    const keys = new Set(db.all(`PRAGMA table_info(${table})`).map((r) => r.name));
    const entries = Object.entries(values).filter(([k, v]) => keys.has(k) && v !== void 0).map(([k, v]) => [k, typeof v === "boolean" ? Number(v) : v && typeof v === "object" ? JSON.stringify(v) : v]);
    db.run(`INSERT INTO ${table} (${entries.map(([k]) => `"${k}"`).join(",")}) VALUES(${entries.map(() => "?").join(",")})`, entries.map(([, v]) => v));
    return Number(one("SELECT last_insert_rowid() AS id").id);
  };
  const update = (table, id, values) => {
    const keys = new Set(db.all(`PRAGMA table_info(${table})`).map((r) => r.name));
    const entries = Object.entries(values).filter(([k, v]) => keys.has(k) && v !== void 0).map(([k, v]) => [k, typeof v === "boolean" ? Number(v) : v && typeof v === "object" ? JSON.stringify(v) : v]);
    db.run(`UPDATE ${table} SET ${entries.map(([k]) => `"${k}"=?`).join(",")} WHERE id=?`, [...entries.map(([, v]) => v), id]);
  };
  const record = (table, g2, detail) => insert(table, { uid: crypto.randomUUID(), gasto_id: g2.id, gasto_uid: g2.uid, fecha: detail.fecha || now(), detalle: JSON.stringify(detail), created_at: now(), updated_at: now() });
  const audit = (g2, accion, motivo = "") => record("gasto_historial", g2, { fecha: now(), accion, usuario: actor.usuario, motivo });
  const open = (fecha) => assert(!one("SELECT id FROM contabilidad_periodos WHERE periodo=? AND estado='CERRADO'", [fecha.slice(0, 7)]), "El per\xEDodo contable est\xE1 cerrado");
  const account = (code) => {
    const a = one("SELECT * FROM catalogo_cuentas WHERE codigo=? AND estado IN ('ACTIVO','ACTIVA')", [code]);
    assert(a, `Cuenta contable inactiva o inexistente: ${code}`);
    return a;
  };
  const journal = (g2, fecha, concepto, lines, pago_uid = "") => {
    let d = 0, c = 0;
    const lineas = lines.filter((l) => l.debito || l.credito).map((l) => {
      const a = account(l.cuenta_codigo);
      d += l.debito || 0;
      c += l.credito || 0;
      return { ...l, cuenta_nombre: a.nombre };
    });
    assert(Math.abs(d - c) < 5e-3, "El asiento contable no est\xE1 balanceado");
    record("gasto_asientos", g2, { fecha, concepto, lineas, pago_uid });
  };
  if (channel === "gastos:listarCuentas") return db.all("SELECT * FROM catalogo_cuentas ORDER BY codigo");
  if (channel === "gastos:consultarContabilidad") {
    const id = Number(payload.id || 0);
    if (id) {
      const expense = one("SELECT * FROM gastos WHERE id=?", [id]);
      assert(expense, "El gasto no existe");
      assert(!actor.canWarehouse || actor.canWarehouse(expense.almacen_uid || "", Number(expense.almacen_id || 0)), "Almacen no autorizado");
    }
    const uid = id ? one("SELECT uid FROM gastos WHERE id=?", [id]).uid : "";
    const read = (t) => db.all(`SELECT * FROM ${t} ${id ? "WHERE gasto_uid=?" : ""} ORDER BY id`, id ? [uid] : []).filter((r) => {
      if (!actor.canWarehouse || r.gasto_id === 0) return true;
      const expense = one("SELECT almacen_uid,almacen_id FROM gastos WHERE uid=?", [r.gasto_uid]);
      return expense && actor.canWarehouse(expense.almacen_uid || "", Number(expense.almacen_id || 0));
    }).map((r) => ({ ...JSON.parse(r.detalle), ...r }));
    const historial = read("gasto_historial");
    return { adjuntos: id ? JSON.parse(one("SELECT adjuntos FROM gastos WHERE id=?", [id])?.adjuntos || "[]") : [], pagos: read("gasto_pagos"), asientos: read("gasto_asientos"), historial, auditoria: historial, periodos: db.all("SELECT * FROM contabilidad_periodos ORDER BY periodo") };
  }
  const op = String(payload.operacion_uid || "");
  assert(op.length >= 8 && op.length <= 150, "Falta el identificador de la operaci\xF3n");
  const request = JSON.stringify({ channel, payload, usuario: actor.usuario });
  const previous = one("SELECT * FROM gasto_operaciones WHERE uid=?", [op]);
  if (previous) {
    assert(previous.request === request, "Identificador de operaci\xF3n reutilizado con otros datos");
    return JSON.parse(previous.resultado);
  }
  const done = (result) => {
    db.run("INSERT INTO gasto_operaciones(uid,request,resultado) VALUES(?,?,?)", [op, request, JSON.stringify(result)]);
    return result;
  };
  if (/Periodo$/.test(channel)) {
    const periodo = String(payload.periodo || "");
    assert(/^\d{4}-(0[1-9]|1[0-2])$/.test(periodo), "Per\xEDodo inv\xE1lido");
    assert(String(payload.motivo || "").trim(), "Indica el motivo");
    const estado = channel === "gastos:cerrarPeriodo" ? "CERRADO" : "ABIERTO";
    const p = one("SELECT id FROM contabilidad_periodos WHERE periodo=?", [periodo]);
    const values = { periodo, estado, usuario: actor.usuario, motivo: payload.motivo, updated_at: now() };
    if (p) update("contabilidad_periodos", p.id, values);
    else insert("contabilidad_periodos", { ...values, uid: crypto.randomUUID(), created_at: now() });
    audit({ id: 0, uid: "" }, estado, `${periodo}: ${payload.motivo}`);
    return done({ periodo, estado });
  }
  let g = payload.gasto_uid ? one("SELECT * FROM gastos WHERE uid=?", [String(payload.gasto_uid)]) : payload.id ? one("SELECT * FROM gastos WHERE id=?", [Number(payload.id)]) : null;
  if (channel === "gastos:guardarContable") {
    assert(!payload.id, "Un gasto contabilizado no se edita. An\xFAlalo y registra el comprobante corregido.");
    const fecha = date(payload.fecha_comprobante || payload.fecha);
    open(fecha);
    assert(!actor.canWarehouse || actor.canWarehouse(String(payload.almacen_uid || ""), Number(payload.almacen_id || 0)), "Almacen no autorizado");
    const cantidad = money(payload.cantidad);
    assert(cantidad > 0, "El total debe ser mayor que cero");
    const fiscal = datosFiscalesGasto({ ...payload, cantidad });
    assert(!fiscal.incluir_606 || fiscal.tipo_bienes_servicios, "Selecciona la clasificaci\xF3n 606");
    const proveedor = payload.proveedor_uid ? one("SELECT * FROM proveedores WHERE uid=?", [payload.proveedor_uid]) : one("SELECT * FROM proveedores WHERE id=?", [Number(payload.proveedor_id || 0)]);
    assert(proveedor, "Selecciona un proveedor existente");
    assert(!actor.canWarehouse || !proveedor.almacen_uid && !Number(proveedor.almacen_id || 0) || actor.canWarehouse(proveedor.almacen_uid || "", Number(proveedor.almacen_id || 0)), "Proveedor de otro almacen");
    if (fiscal.incluir_606) {
      assert(/^(\d{9}|\d{11})$/.test(fiscal.rnc.replace(/[-\s]/g, "")), "Completa el RNC o cedula del proveedor");
      assert(/^(B(01|03|04|11|13|14|15|16|17)\d{8}|E(31|33|34|41|43|44|45|46|47)\d{10})$/.test(fiscal.ncf), "Completa un NCF valido");
    }
    assert(!payload.adjuntos || Array.isArray(payload.adjuntos) && payload.adjuntos.length <= 20 && payload.adjuntos.every((a) => typeof a.uid === "string" && /^(fil_[A-Za-z0-9]+|https:\/\/)/.test(a.uid) && typeof a.nombre === "string"), "Adjuntos invalidos");
    const gross = money(fiscal.monto_bienes) + money(fiscal.monto_servicios) + money(fiscal.itbis) + money(fiscal.isc) + money(fiscal.otros_impuestos) + money(fiscal.propina_legal) + money(fiscal.itbis_percibido) + money(fiscal.isr_percibido);
    assert(Math.abs(gross - cantidad) < 0.011, "El desglose fiscal no coincide con el total");
    assert(fiscal.monto_bienes != null && fiscal.monto_servicios != null, "Completa el desglose de bienes y servicios");
    const cuenta = account(String(payload.cuenta_contable_codigo || ""));
    assert(["GASTOS", "ACTIVO"].includes(cuenta.tipo), "Selecciona una cuenta de gasto o activo");
    if (fiscal.ncf) {
      assert(!one("SELECT id FROM gastos WHERE upper(ncf)=? AND replace(replace(rnc,'-',''),' ','')=? AND coalesce(estado_contable,'')<>'ANULADO'", [fiscal.ncf, fiscal.rnc.replace(/[- ]/g, "")]), "El comprobante ya est\xE1 registrado en Gastos");
      if (db.all("SELECT name FROM sqlite_master WHERE type='table' AND name='compras'").length) {
        const cols = new Set(db.all("PRAGMA table_info(compras)").map((r) => r.name));
        if (cols.has("ncf") && cols.has("rnc")) assert(!one("SELECT id FROM compras WHERE upper(ncf)=? AND replace(replace(rnc,'-',''),' ','')=?", [fiscal.ncf, fiscal.rnc.replace(/[- ]/g, "")]), "El comprobante ya est\xE1 registrado en Compras");
      }
    }
    if (fiscal.ncf && db.all("SELECT name FROM sqlite_master WHERE type='table' AND name='facturas'").length) {
      const cols = new Set(db.all("PRAGMA table_info(facturas)").map((r) => r.name));
      if (cols.has("ncf") && cols.has("cod_cliente") && cols.has("tipo_factura")) assert(!one("SELECT id FROM facturas WHERE tipo_factura='FACTURA_COMPRA' AND upper(ncf)=? AND replace(replace(cod_cliente,'-',''),' ','')=?", [fiscal.ncf, fiscal.rnc.replace(/[- ]/g, "")]), "El comprobante ya esta registrado en Compras");
    }
    const condicion = String(payload.condicion_pago || "CONTADO");
    assert(["CONTADO", "CREDITO"].includes(condicion), "Condici\xF3n de pago inv\xE1lida");
    if (condicion === "CREDITO") assert(date(payload.fecha_vencimiento) >= fecha, "El vencimiento no puede ser anterior al comprobante");
    const neto2 = money(cantidad - money(fiscal.itbis_retenido) - money(fiscal.isr_retenido));
    assert(neto2 > 0, "El neto a pagar debe ser mayor que cero");
    const state = payload.requiere_aprobacion || payload.estado === "BORRADOR" || !actor.can("gastos-aprobar") ? "PENDIENTE_APROBACION" : "CONTABILIZADO";
    if (state === "CONTABILIZADO" && condicion === "CONTADO") assert(actor.can("gastos-pagar"), "No tienes permiso para pagar gastos");
    g = { ...payload, ...fiscal, id: void 0, uid: crypto.randomUUID(), fecha, fecha_comprobante: fecha, cantidad, proveedor_id: proveedor.id, proveedor_uid: proveedor.uid || "", nombre_proveedor: proveedor.nombre || fiscal.nombre_proveedor, cuenta_contable_codigo: cuenta.codigo, cuenta_contable_tipo: cuenta.tipo, condicion_pago: condicion, estado_contable: state, saldo_pendiente: neto2, total_pagado: 0, efectivo: 0, transferencia: 0, tarjeta: 0, created_at: now(), updated_at: now() };
    g.contabilidad_json = JSON.stringify({ ...g, solicitud_pago: { metodo_pago: payload.metodo_pago, banco_id: payload.banco_id, banco_uid: payload.banco_uid, efectivo: payload.efectivo, transferencia: payload.transferencia, referencia_pago: payload.referencia_pago }, neto: neto2 });
    g.id = insert("gastos", g);
    audit(g, "REGISTRADO");
    if (state === "PENDIENTE_APROBACION") return done({ id: g.id, uid: g.uid, estado_contable: state });
  } else {
    assert(g, "El gasto no existe");
    assert(!esMovimientoNomina(g), "Los movimientos de nomina se administran desde Nomina");
    assert(!actor.canWarehouse || actor.canWarehouse(g.almacen_uid || "", Number(g.almacen_id || 0)), "Almacen no autorizado");
    assert(g.contabilidad_json, "Este gasto es anterior al m\xF3dulo contable; requiere conciliaci\xF3n antes de procesarlo");
    assert(g.estado_contable !== "ANULADO", "El gasto est\xE1 anulado");
    if (!["gastos:pagarContable", "gastos:revertirPagoContable"].includes(channel)) open(g.fecha_comprobante || g.fecha);
  }
  const snapshot = JSON.parse(g.contabilidad_json);
  const neto = money(snapshot.neto);
  const allPayments = () => db.all("SELECT * FROM gasto_pagos WHERE gasto_uid=? ORDER BY fecha,id", [g.uid]).map((r) => ({ ...JSON.parse(r.detalle), ...r }));
  const cashMovement = (amount, fecha, reversal = false) => {
    if (!amount) return;
    assert(db.all("SELECT name FROM sqlite_master WHERE type='table' AND name='caja_turnos'").length, "Abre un turno de caja para registrar el efectivo");
    const turn = db.all("SELECT * FROM caja_turnos WHERE lower(estado)='abierto' ORDER BY id DESC").find((t) => g.almacen_uid ? t.almacen_uid === g.almacen_uid : Number(t.almacen_id || 0) === Number(g.almacen_id || 0));
    assert(turn, "Abre un turno de caja en el almacen del gasto");
    assert(String(turn.created_at || turn.fecha_apertura || fecha).slice(0, 10) <= fecha, "La fecha del pago es anterior al turno abierto");
    insert("caja_movimientos", { uid: crypto.randomUUID(), turno_id: turn.id, tipo: reversal ? "ENTRADA" : "RETIRO", monto: amount, descripcion: `${reversal ? "Reversion" : "Pago"} gasto ${g.ncf || g.id}`, almacen_id: g.almacen_id, almacen_uid: g.almacen_uid, created_at: fecha + " " + (/* @__PURE__ */ new Date()).toISOString().slice(11, 19), updated_at: now() });
  };
  const refreshPayments = () => {
    const payments = allPayments().filter((p) => !p.anulado);
    g.total_pagado = money(payments.reduce((n, p) => n + p.monto, 0));
    g.saldo_pendiente = money(neto - g.total_pagado);
    const sums = { efectivo: 0, transferencia: 0, tarjeta: 0 };
    for (const p of payments) for (const k of Object.keys(sums)) sums[k] += Number(p[k] || 0);
    Object.assign(g, sums);
    g.fecha_pago = payments.length ? payments[payments.length - 1].fecha : "";
    update("gastos", g.id, { ...sums, total_pagado: g.total_pagado, saldo_pendiente: g.saldo_pendiente, fecha_pago: g.fecha_pago, pagos_contables: payments, updated_at: now() });
    const cxp = one("SELECT * FROM cuentas_pagar WHERE gasto_uid=?", [g.uid]);
    if (cxp) update("cuentas_pagar", cxp.id, { abonado: g.total_pagado, saldo: g.saldo_pendiente, estado: g.saldo_pendiente ? "ACTIVA" : "PAGADA", pagos: payments.map((p) => ({ ...p, cantidad: p.monto })), updated_at: now() });
  };
  const pay = (p) => {
    const fecha = date(p.fecha || g.fecha);
    open(fecha);
    assert(fecha >= g.fecha, "El pago no puede preceder al comprobante");
    const monto = money(p.monto);
    assert(monto > 0 && monto <= money(g.saldo_pendiente), "El abono excede el saldo pendiente");
    const metodo = String(p.metodo_pago || "EFECTIVO");
    assert(["EFECTIVO", "TRANSFERENCIA", "TARJETA", "MIXTO", "CHEQUE"].includes(metodo), "Metodo de pago invalido");
    const efectivo = metodo === "EFECTIVO" ? monto : metodo === "MIXTO" ? money(p.efectivo) : 0;
    const bancoMonto = money(monto - efectivo);
    assert(efectivo <= monto && (metodo !== "MIXTO" || efectivo > 0 && bancoMonto > 0), "Distribucion del pago invalida");
    let bank = null;
    if (bancoMonto) {
      bank = p.banco_uid ? one("SELECT * FROM bancos WHERE uid=?", [p.banco_uid]) : one("SELECT * FROM bancos WHERE id=?", [Number(p.banco_id || 0)]);
      assert(bank, "Selecciona un banco");
      assert(money(bank.saldo) >= bancoMonto, "Fondos insuficientes en el banco");
      update("bancos", bank.id, { saldo: money(bank.saldo - bancoMonto), updated_at: now(), fecha_transaccion: now() });
    }
    cashMovement(efectivo, fecha);
    const prior = allPayments().filter((p2) => !p2.anulado);
    const retained = (key) => money(Math.min(money(g[key]) - prior.reduce((n, p2) => n + Number(p2[key] || 0), 0), money(g[key]) * monto / neto));
    const final = monto === money(g.saldo_pendiente);
    const retention = (key) => final ? money(money(g[key]) - prior.reduce((n, p2) => n + Number(p2[key] || 0), 0)) : retained(key);
    const pago = { fecha, monto, metodo_pago: metodo, efectivo, transferencia: metodo === "TARJETA" ? 0 : bancoMonto, tarjeta: metodo === "TARJETA" ? bancoMonto : 0, banco_id: bank?.id || 0, banco_uid: bank?.uid || "", referencia_pago: String(p.referencia_pago || ""), usuario: actor.usuario, itbis_retenido: retention("itbis_retenido"), isr_retenido: retention("isr_retenido") };
    const pagoId = record("gasto_pagos", g, pago);
    const pagoUid = one("SELECT uid FROM gasto_pagos WHERE id=?", [pagoId]).uid;
    journal(g, fecha, `Pago de gasto #${pagoId}`, [{ cuenta_codigo: "2101", debito: money(monto + pago.itbis_retenido + pago.isr_retenido), credito: 0 }, { cuenta_codigo: "1101", debito: 0, credito: efectivo }, { cuenta_codigo: "1102", debito: 0, credito: bancoMonto }, { cuenta_codigo: "2102", debito: 0, credito: pago.itbis_retenido }, { cuenta_codigo: "2103", debito: 0, credito: pago.isr_retenido }], pagoUid);
    refreshPayments();
    audit(g, "PAGADO", `${monto}: ${pago.referencia_pago}`);
  };
  const reversePayment = (p, fecha, motivo) => {
    assert(!p.anulado, "El pago ya fue anulado");
    open(p.fecha);
    const amount = money(p.transferencia + p.tarjeta);
    if (amount) {
      const b = p.banco_uid ? one("SELECT * FROM bancos WHERE uid=?", [p.banco_uid]) : one("SELECT * FROM bancos WHERE id=?", [p.banco_id]);
      assert(b, "No existe el banco del pago");
      update("bancos", b.id, { saldo: money(b.saldo + amount), updated_at: now() });
    }
    cashMovement(money(p.efectivo), fecha, true);
    const entry = db.all("SELECT detalle FROM gasto_asientos WHERE gasto_uid=?", [g.uid]).map((r) => JSON.parse(r.detalle)).find((a) => a.pago_uid ? p.uid === a.pago_uid : a.concepto === `Pago de gasto #${p.id}`);
    assert(entry, "No existe el asiento del pago");
    journal(g, fecha, `Reversion pago #${p.id}`, entry.lineas.map((l) => ({ ...l, debito: l.credito, credito: l.debito })));
    update("gasto_pagos", p.id, { detalle: { ...p, anulado: true, fecha_anulacion: fecha, motivo_anulacion: motivo }, updated_at: now() });
    audit(g, "PAGO_ANULADO", motivo);
  };
  if (channel === "gastos:pagarContable") {
    assert(g.estado_contable === "CONTABILIZADO", "El gasto debe estar aprobado");
    pay(payload);
    return done({ id: g.id, saldo_pendiente: g.saldo_pendiente });
  }
  if (channel === "gastos:revertirPagoContable") {
    assert(String(payload.motivo || "").trim(), "Indica el motivo");
    const fecha = date(payload.fecha);
    open(fecha);
    const p = allPayments().find((p2) => payload.pago_uid ? p2.uid === payload.pago_uid : p2.id === Number(payload.pago_id));
    assert(p, "El pago no existe");
    reversePayment(p, fecha, payload.motivo);
    refreshPayments();
    return done({ id: g.id, saldo_pendiente: g.saldo_pendiente });
  }
  if (channel === "gastos:anularContable") {
    assert(String(payload.motivo || "").trim(), "Indica el motivo de anulacion");
    const fecha = date(payload.fecha || (/* @__PURE__ */ new Date()).toISOString().slice(0, 10));
    open(fecha);
    for (const p of allPayments().filter((p2) => !p2.anulado)) reversePayment(p, fecha, payload.motivo);
    const entry = db.all("SELECT detalle FROM gasto_asientos WHERE gasto_uid=?", [g.uid]).map((r) => JSON.parse(r.detalle)).find((a) => a.concepto === "Registro de gasto");
    if (entry) journal(g, fecha, "Anulacion del gasto", entry.lineas.map((l) => ({ ...l, debito: l.credito, credito: l.debito })));
    refreshPayments();
    update("gastos", g.id, { estado_contable: "ANULADO", saldo_pendiente: 0, updated_at: now() });
    const cxp = one("SELECT id FROM cuentas_pagar WHERE gasto_uid=?", [g.uid]);
    if (cxp) update("cuentas_pagar", cxp.id, { estado: "ANULADA", saldo: 0, updated_at: now() });
    audit(g, "ANULADO", payload.motivo);
    return done({ id: g.id, estado_contable: "ANULADO" });
  }
  if (channel === "gastos:aprobarContable") {
    if (g.condicion_pago === "CONTADO") assert(actor.can("gastos-pagar"), "No tienes permiso para pagar gastos");
    assert(g.estado_contable === "PENDIENTE_APROBACION", "El gasto ya est\xE1 aprobado");
    g.estado_contable = "CONTABILIZADO";
    update("gastos", g.id, { estado_contable: g.estado_contable, updated_at: now() });
    audit(g, "APROBADO");
  }
  journal(g, g.fecha, "Registro de gasto", [{ cuenta_codigo: g.cuenta_contable_codigo, debito: money(g.cantidad - money(g.itbis_adelantar)), credito: 0 }, { cuenta_codigo: "1104", debito: money(g.itbis_adelantar), credito: 0 }, { cuenta_codigo: "2101", debito: 0, credito: money(g.cantidad) }]);
  if (g.condicion_pago === "CREDITO") insert("cuentas_pagar", { uid: crypto.randomUUID(), gasto_id: g.id, gasto_uid: g.uid, proveedor_id: g.proveedor_id, proveedor_uid: g.proveedor_uid, cod_proveedor: g.proveedor_uid || String(g.proveedor_id), nombre_proveedor: g.nombre_proveedor, no_factura: g.ncf, total: neto, abonado: 0, saldo: neto, fecha_compra: g.fecha, fecha_vencimiento: g.fecha_vencimiento, estado: "ACTIVA", notas: g.comentario, pagos: "[]", almacen_id: g.almacen_id, almacen_uid: g.almacen_uid, rnc: g.rnc, ncf: g.ncf, created_at: now(), updated_at: now() });
  else pay({ ...snapshot.solicitud_pago, monto: neto, fecha: snapshot.fecha_pago || g.fecha });
  return done({ id: g.id, uid: g.uid, estado_contable: g.estado_contable, saldo_pendiente: g.saldo_pendiente });
}
function expenseActorForUser(user) {
  const active = user && ["ACTIVO", "ACTIVADO"].includes(String(user.estado || "").toUpperCase());
  const admin = [user?.rol, user?.nivel_seguridad].some((r) => ["administrador", "admin", "soporte"].includes(String(r || "").toLowerCase()));
  let permissions = [];
  try {
    permissions = typeof user?.permisos === "string" ? JSON.parse(user.permisos) : user?.permisos || [];
  } catch {
  }
  return {
    usuario: String(user?.usuario || user?.email || ""),
    can: (key) => Boolean(active && (admin || (Array.isArray(permissions) ? permissions.some((p) => p === key || p?.ver !== false && [p?.key, p?.tabla, p?.permiso].includes(key)) : permissions[key] === true))),
    canWarehouse: (uid, id) => Boolean(active && (admin || (user.almacen_uid ? uid === user.almacen_uid : Number(user.almacen_id || 0) === id)))
  };
}

// src/server/expenseStdio.ts
if (!globalThis.crypto) Object.defineProperty(globalThis, "crypto", { value: import_node_crypto.webcrypto });
var pending = "";
var decoder = new import_node_string_decoder.StringDecoder("utf8");
function receive() {
  while (!pending.includes("\n")) {
    const chunk = Buffer.alloc(8192), size = (0, import_node_fs.readSync)(0, chunk, 0, chunk.length, null);
    if (!size) throw new Error("El transporte contable se cerro");
    pending += decoder.write(chunk.subarray(0, size));
    if (pending.length > 32 * 1024 * 1024) throw new Error("Respuesta contable demasiado grande");
  }
  const cut = pending.indexOf("\n"), line = pending.slice(0, cut);
  pending = pending.slice(cut + 1);
  return JSON.parse(line);
}
function send(data) {
  (0, import_node_fs.writeSync)(1, JSON.stringify(data) + "\n");
}
function sql(method, query, params = []) {
  send({ type: "sql", method, sql: query, params });
  const result = receive();
  if (result.error) throw new Error(result.error);
  return result.rows || [];
}
try {
  const request = receive();
  const result = runExpenseTransaction({ all: (query, params) => sql("all", query, params), run: (query, params) => {
    sql("run", query, params);
  } }, expenseActorForUser(request.user), request.channel, request.payload);
  send({ type: "result", ...result });
} catch (error) {
  send({ type: "result", success: false, error: error.message || "Error contable" });
}
