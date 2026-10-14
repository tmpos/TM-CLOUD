<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class StorefrontPaymentSettingsService
{
    private const PROVIDERS = ['stripe','paypal','azul','bank_transfer','cash','card_on_delivery','external_link'];
    public function __construct(private PDO $db, private CredentialCipher $cipher, private StorefrontCommerceService $commerce, private StorefrontService $stores) {}
    private function row(string $project, string $provider): array
    {
        $stmt=$this->db->prepare('SELECT * FROM storefront_payment_methods WHERE project_uid=? AND provider=?');
        $stmt->execute([$project,$provider]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new \RuntimeException('Método de pago no encontrado.');
    }
    private function revision(array $row): string { return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)); }
    public function state(string $project): array
    {
        $this->commerce->adminMethods($project);
        $methods=[];
        foreach (self::PROVIDERS as $provider) {
            $row=$this->row($project,$provider);
            $public=json_decode($row['public_config']?:'{}',true,512,JSON_THROW_ON_ERROR);
            $methods[]=['provider'=>$provider,'display_name'=>$row['display_name'],'enabled'=>(bool)$row['enabled'],'test_mode'=>(bool)$row['test_mode'],
                'public_key'=>(string)($public[$provider==='stripe'?'publishable_key':'client_id']??''),'credentials_configured'=>!empty($row['credentials_encrypted']), 'revision'=>$this->revision($row),
                'instructions'=>(string)($row['instructions']??''),'payment_url'=>(string)($public['payment_url']??'')];
        }
        return ['version'=>1,'methods'=>$methods];
    }
    public function save(string $project, mixed $request): array
    {
        if (!is_array($request) || !in_array($request['provider']??'', self::PROVIDERS,true)) throw new \InvalidArgumentException('Proveedor inválido.');
        $provider=$request['provider'];
        foreach (['enabled','test_mode','clear_credentials'] as $key) if (!is_bool($request[$key]??null)) throw new \InvalidArgumentException('Opción de pago inválida.');
        foreach (['display_name'=>80,'public_key'=>220,'secret'=>500,'revision'=>64] as $key=>$limit) if (!is_string($request[$key]??null) || strlen($request[$key])>$limit) throw new \InvalidArgumentException('Configuración de pago inválida.');
        foreach (['instructions'=>1000,'payment_url'=>2000] as $key=>$limit) if (isset($request[$key]) && (!is_string($request[$key]) || mb_strlen($request[$key])>$limit)) throw new \InvalidArgumentException('Instrucciones o enlace de pago inválidos.');
        $this->commerce->adminMethods($project);
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $row=$this->row($project,$provider);
            if (!hash_equals($this->revision($row),$request['revision'])) throw new \RuntimeException('La configuración de pagos cambió. Actualiza antes de guardar.',409);
            $public=trim($request['public_key']); $secret=trim($request['secret']); $name=trim($request['display_name']);
            if ($name==='') throw new \InvalidArgumentException('Escribe el nombre del método de pago.');
            $oldPublic=json_decode($row['public_config']?:'{}',true,512,JSON_THROW_ON_ERROR);
            $instructions=trim($request['instructions']??(string)($row['instructions']??''));
            if (!in_array($provider,['stripe','paypal'],true)) {
                if ($secret!=='' || $public!=='' || $request['test_mode']) throw new \InvalidArgumentException('Este método no utiliza claves ni modo de prueba.');
                if ($request['clear_credentials'] && $request['enabled']) throw new \InvalidArgumentException('Desactiva el método antes de eliminar credenciales.');
                if (in_array($provider,['azul','external_link'],true)) {
                    $url=trim($request['payment_url']??(string)($oldPublic['payment_url']??''));
                    $parsed=parse_url($url);
                    if ($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || ($parsed['scheme']??'')!=='https' || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['fragment']))) throw new \InvalidArgumentException('Introduce un enlace de pago HTTPS sin credenciales ni fragmentos.');
                    if ($request['enabled'] && $url==='') throw new \InvalidArgumentException('Configura el enlace antes de activar el proveedor.');
                    $oldPublic['payment_url']=$url;
                }
                if ($provider==='bank_transfer' && $request['enabled'] && $instructions==='') throw new \InvalidArgumentException('Indica los datos bancarios y las instrucciones para transferir.');
                $this->db->prepare('UPDATE storefront_payment_methods SET display_name=?,enabled=?,test_mode=0,public_config=?,credentials_encrypted=?,instructions=?,updated_at=? WHERE project_uid=? AND provider=?')->execute([
                    $name,(int)$request['enabled'],json_encode($oldPublic,JSON_THROW_ON_ERROR),$request['clear_credentials']?null:($row['credentials_encrypted']??null),$instructions,gmdate('Y-m-d H:i:s'),$project,$provider]);
            } else {
            $key=$provider==='stripe'?'publishable_key':'client_id';
            $encrypted=(string)($row['credentials_encrypted']??'');
            if ($request['clear_credentials']) {
                if ($request['enabled'] || $secret!=='') throw new \InvalidArgumentException('Desactiva el método y deja el secreto vacío para eliminar las credenciales.');
                $encrypted=''; $public='';
            } else {
                if ($encrypted!=='' && ($request['test_mode']!==(bool)$row['test_mode'] || $public!==($oldPublic[$key]??'')) && $secret==='') throw new \InvalidArgumentException('Al cambiar la cuenta o el ambiente debes introducir también su secreto.');
                if ($provider==='stripe') {
                    $mode=$request['test_mode']?'test':'live';
                    if (($public!=='' && !preg_match('/^pk_'.$mode.'_[A-Za-z0-9]+$/D',$public)) || ($secret!=='' && !preg_match('/^sk_'.$mode.'_[A-Za-z0-9]+$/D',$secret))) throw new \InvalidArgumentException('Las claves Stripe deben corresponder al ambiente seleccionado.');
                } elseif (($public!=='' && !preg_match('/^[A-Za-z0-9_-]{10,220}$/D',$public)) || ($secret!=='' && !preg_match('/^[A-Za-z0-9_-]{10,500}$/D',$secret))) throw new \InvalidArgumentException('Client ID o secreto PayPal inválido.');
                if ($secret!=='') {
                    if ($public==='') throw new \InvalidArgumentException('Completa la clave pública o Client ID.');
                    $credentials=$provider==='stripe'?['secret_key'=>$secret]:['client_id'=>$public,'client_secret'=>$secret];
                    $encrypted=$this->cipher->encrypt(json_encode($credentials,JSON_THROW_ON_ERROR));
                }
                if ($request['enabled'] && ($encrypted==='' || $public==='')) throw new \InvalidArgumentException('Completa las credenciales antes de activar el método.');
                $store=$this->stores->findForProject($project);
                if ($provider==='paypal' && $request['enabled'] && !in_array($store['currency'],['USD','EUR'],true)) throw new \InvalidArgumentException('PayPal requiere USD o EUR entre las monedas disponibles de esta tienda. Publica primero la moneda correcta; no se convierte el importe automáticamente.');
            }
            $this->db->prepare('UPDATE storefront_payment_methods SET display_name=?,enabled=?,test_mode=?,public_config=?,credentials_encrypted=?,instructions=?,updated_at=? WHERE project_uid=? AND provider=?')->execute([
                $name,(int)$request['enabled'],(int)$request['test_mode'],json_encode([$key=>$public],JSON_THROW_ON_ERROR),$encrypted?:null,$instructions,gmdate('Y-m-d H:i:s'),$project,$provider]);
            }
            $this->db->exec('COMMIT');
        } catch (\Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
        return $this->state($project);
    }
}
