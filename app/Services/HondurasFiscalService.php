<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/** Central CAI allocator. reserve() MUST run inside guardarVenta's BEGIN IMMEDIATE. */
final class HondurasFiscalService
{
    public static function install(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS _hn_ranges (id TEXT PRIMARY KEY, warehouse TEXT NOT NULL, taxpayer TEXT NOT NULL, series TEXT NOT NULL, first INTEGER NOT NULL, last INTEGER NOT NULL, next INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 0, settings TEXT NOT NULL)');
        $db->exec('CREATE TABLE IF NOT EXISTS _hn_issued (operation TEXT PRIMARY KEY, taxpayer TEXT NOT NULL, number TEXT NOT NULL, snapshot TEXT NOT NULL, UNIQUE(taxpayer,number))');
        $db->exec("CREATE TRIGGER IF NOT EXISTS hn_no_rewind BEFORE UPDATE OF next ON _hn_ranges WHEN NEW.next < OLD.next BEGIN SELECT RAISE(ABORT,'No se puede retroceder el correlativo fiscal'); END");
        $db->exec("CREATE TRIGGER IF NOT EXISTS hn_ledger_no_delete BEFORE DELETE ON _hn_issued BEGIN SELECT RAISE(ABORT,'No se puede eliminar el registro fiscal HN'); END");
        $db->exec("CREATE TRIGGER IF NOT EXISTS hn_ledger_no_update BEFORE UPDATE ON _hn_issued BEGIN SELECT RAISE(ABORT,'No se puede modificar el registro fiscal HN'); END");
        $columns = array_column($db->query('PRAGMA table_info(facturas)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (count(array_intersect(['tipo_comprobante','ncf','otro','total','impuesto','productos','nombre_cliente','no_factura'], $columns)) === 8) {
            $db->exec("CREATE TRIGGER IF NOT EXISTS hn_invoice_no_delete BEFORE DELETE ON facturas WHEN OLD.tipo_comprobante='HN_FACTURA' BEGIN SELECT RAISE(ABORT,'La factura CAI debe conservarse en el historial fiscal'); END");
            $db->exec("CREATE TRIGGER IF NOT EXISTS hn_invoice_no_edit BEFORE UPDATE ON facturas WHEN OLD.tipo_comprobante='HN_FACTURA' AND (NEW.tipo_comprobante IS NOT OLD.tipo_comprobante OR NEW.ncf IS NOT OLD.ncf OR NEW.no_factura IS NOT OLD.no_factura OR NEW.total IS NOT OLD.total OR NEW.impuesto IS NOT OLD.impuesto OR NEW.productos IS NOT OLD.productos OR NEW.nombre_cliente IS NOT OLD.nombre_cliente OR NOT json_valid(NEW.otro) OR json_extract(NEW.otro,'$.hn_fiscal') IS NOT json_extract(OLD.otro,'$.hn_fiscal')) BEGIN SELECT RAISE(ABORT,'No se pueden modificar los datos emitidos de una factura CAI'); END");
        }
    }

    private static function warehouse(PDO $db, string $uid): array
    {
        if ($uid === '') throw new InvalidArgumentException('Selecciona una empresa.');
        $stmt = $db->prepare('SELECT * FROM empresa WHERE uid=? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('La empresa no existe en este proyecto.');
        return $row;
    }

    public static function status(PDO $db, string $warehouse): array
    {
        self::warehouse($db, $warehouse);
        self::install($db);
        $stmt = $db->prepare('SELECT * FROM _hn_ranges WHERE warehouse=? ORDER BY active DESC,rowid DESC LIMIT 1');
        $stmt->execute([$warehouse]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['version'=>1,'settings'=>null];
        $settings = json_decode($row['settings'], true, 512, JSON_THROW_ON_ERROR);
        $settings['enabled'] = (bool) $row['active'];
        $settings['nextSequence'] = (int) $row['next'];
        return ['version'=>1,'settings'=>$settings,'remaining'=>max(0,(int)$row['last']-(int)$row['next']+1)];
    }

    public static function save(PDO $db, string $warehouse, array $s): array
    {
        self::warehouse($db, $warehouse);
        self::install($db);
        if (($s['country'] ?? '') !== 'HN') throw new InvalidArgumentException('Selecciona Honduras.');
        foreach (['taxpayerId','legalName','address','cai','rangeStart','rangeEnd','expiresOn'] as $key) {
            $s[$key] = trim((string)($s[$key] ?? ''));
            if ($s[$key] === '' || strlen($s[$key]) > 500) throw new InvalidArgumentException("Revisa el campo $key.");
        }
        $s['cai'] = strtoupper($s['cai']);
        if (!preg_match('/^\d{14}$/D', $s['taxpayerId']) || $s['taxpayerId'] === '00000000000001') throw new InvalidArgumentException('Indica el RTN real del emisor, de 14 digitos.');
        if (!preg_match('/^(?:[A-F0-9]{6}-){5}[A-F0-9]{2}$/D', $s['cai']) || $s['cai'] === 'ABCDEF-123456-ABCDEF-123456-ABCDEF-12') throw new InvalidArgumentException('Indica el CAI real autorizado; no se admite el ejemplo de pruebas.');
        if (!preg_match('/^\d{3}-\d{3}-01-\d{8}$/D', $s['rangeStart']) || !preg_match('/^\d{3}-\d{3}-01-\d{8}$/D', $s['rangeEnd']) || substr($s['rangeStart'],0,11)!==substr($s['rangeEnd'],0,11)) throw new InvalidArgumentException('El rango debe corresponder a facturas (tipo 01), con formato 000-000-01-00000000.');
        $first = (int)substr($s['rangeStart'],-8); $last = (int)substr($s['rangeEnd'],-8);
        $next = filter_var($s['nextSequence'] ?? null, FILTER_VALIDATE_INT);
        if ($first < 1 || $first > $last || $next === false || $next < $first || $next > $last+1) throw new InvalidArgumentException('Proximo correlativo fuera del rango.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $s['expiresOn']);
        if (!$date || $date->format('Y-m-d') !== $s['expiresOn']) throw new InvalidArgumentException('Fecha limite no valida.');
        $enabled = ($s['enabled'] ?? false) === true;
        if ($enabled && (($s['authorized'] ?? false) !== true || $s['expiresOn'] < self::today() || $next > $last)) throw new InvalidArgumentException('Para activar: confirma el CAI autorizado, vigente y con correlativos disponibles.');
        $s = array_intersect_key($s, array_flip(['country','taxpayerId','legalName','address','cai','rangeStart','rangeEnd','expiresOn','authorized']));
        $s['version']=1; $s['enabled']=$enabled; $s['nextSequence']=$next;
        $series = substr($s['rangeStart'],0,11);
        $id = hash('sha256', implode('|',[$warehouse,$s['taxpayerId'],$s['cai'],$s['rangeStart'],$s['rangeEnd']]));
        $db->exec('BEGIN IMMEDIATE');
        try {
            $stmt=$db->prepare('SELECT * FROM _hn_ranges WHERE taxpayer=? AND series=? AND first<=? AND last>=?');
            $stmt->execute([$s['taxpayerId'],$series,$last,$first]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $old) {
                if ($old['id'] !== $id) throw new RuntimeException('El rango se superpone con uno ya registrado, incluso en otra empresa.');
                if ($next < (int)$old['next']) throw new RuntimeException('El correlativo ya avanzo. Recarga la configuracion antes de guardar.');
                $previous=json_decode($old['settings'],true);
                if ($next > $first && $previous['expiresOn'] !== $s['expiresOn']) throw new RuntimeException('No se puede cambiar el vencimiento de un rango utilizado.');
            }
            $db->prepare('UPDATE _hn_ranges SET active=0 WHERE warehouse=?')->execute([$warehouse]);
            $stmt=$db->prepare('INSERT INTO _hn_ranges(id,warehouse,taxpayer,series,first,last,next,active,settings) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET next=excluded.next,active=excluded.active,settings=excluded.settings');
            $stmt->execute([$id,$warehouse,$s['taxpayerId'],$series,$first,$last,$next,(int)$enabled,json_encode($s,JSON_THROW_ON_ERROR)]);
            $db->exec('COMMIT');
        } catch (\Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        return ['version'=>1,'settings'=>$s,'remaining'=>max(0,$last-$next+1)];
    }

    public static function today(): string { return (new \DateTimeImmutable('now',new \DateTimeZone('America/Tegucigalpa')))->format('Y-m-d'); }

    public static function reserve(PDO $db, array $factura, array $request, string $operation): array
    {
        if (strtoupper((string)($factura['tipo_factura'] ?? '')) === 'COTIZACION') throw new InvalidArgumentException('Una cotizacion no consume correlativos CAI.');
        if ($operation === '') throw new InvalidArgumentException('La emision fiscal requiere identificador de operacion.');
        $uid=(string)($factura['almacen_uid'] ?? '');
        self::warehouse($db,$uid);
        $stmt=$db->prepare('SELECT * FROM _hn_ranges WHERE warehouse=? AND active=1'); $stmt->execute([$uid]);
        $r=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new RuntimeException('Activa el rango CAI de esta empresa antes de facturar.');
        $s=json_decode($r['settings'],true,512,JSON_THROW_ON_ERROR);
        if ($s['expiresOn'] < self::today()) throw new RuntimeException('El CAI ha vencido. No se guardo la venta.');
        if ((int)$r['next'] > (int)$r['last']) throw new RuntimeException('El rango CAI esta agotado. No se guardo la venta.');
        $rate=(float)($request['rate'] ?? -1);
        if (!in_array($rate,[0.0,15.0,18.0],true)) throw new InvalidArgumentException('Tasa ISV no admitida.');
        $total=(float)($factura['total'] ?? -1); $tax=(float)($factura['impuesto'] ?? -1);
        if (!is_finite($total) || !is_finite($tax) || $total < 0 || $total >= 1e12 || $tax < 0 || $tax > $total || abs(round(($total-$tax)*$rate/100,2)-$tax)>0.03) throw new InvalidArgumentException('Los totales e ISV de la factura no coinciden. Revisa impuestos y cargos.');
        $customerId=trim((string)($request['customerId'] ?? ''));
        if ($customerId !== '' && !preg_match('/^\d{13,14}$/D',$customerId)) throw new InvalidArgumentException('El RTN/DNI del cliente debe contener 14/13 digitos.');
        $number=$r['series'].str_pad((string)$r['next'],8,'0',STR_PAD_LEFT);
        $snapshot=['version'=>1,'country'=>'HN','currency'=>'HNL','number'=>$number,'cai'=>$s['cai'],'rangeStart'=>$s['rangeStart'],'rangeEnd'=>$s['rangeEnd'],'expiresOn'=>$s['expiresOn'],'taxpayerId'=>$s['taxpayerId'],'legalName'=>$s['legalName'],'address'=>$s['address'],'issuedOn'=>self::today(),'customerId'=>$customerId,'rate'=>$rate,'tax'=>round($tax,2),'base'=>round($total-$tax,2),'total'=>round($total,2)];
        $snapshot['customerName']=(string)($factura['nombre_cliente'] ?? 'CONSUMIDOR FINAL');
        $db->prepare('INSERT INTO _hn_issued(operation,taxpayer,number,snapshot) VALUES(?,?,?,?)')->execute([$operation,$s['taxpayerId'],$number,json_encode($snapshot,JSON_THROW_ON_ERROR)]);
        $db->prepare('UPDATE _hn_ranges SET next=next+1 WHERE id=?')->execute([$r['id']]);
        $other=json_decode((string)($factura['otro'] ?? '{}'),true) ?: [];
        $other['hn_fiscal']=$snapshot;
        $other['facturacion_electronica']=0;
        $factura['otro']=json_encode($other,JSON_THROW_ON_ERROR);
        $factura['ncf']=$number;
        $factura['comprobante']='HN_FACTURA';
        $factura['tipo_comprobante']='HN_FACTURA';
        $factura['comprobante_id']=0;
        $factura['fecha_emision']=self::today();
        return $factura;
    }
}
