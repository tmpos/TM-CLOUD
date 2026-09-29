<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService,CompanySignatureService,DocumentSettingsService};
function check(bool $ok, string $why): void { if (!$ok) throw new LogicException($why); }
function rejected(callable $action): void { try { $action(); } catch (RuntimeException|InvalidArgumentException $e) { return; } throw new LogicException('Expected rejection'); }
$storage=sys_get_temp_dir().'/company-signature-'.bin2hex(random_bytes(5));
mkdir($storage.'/projects',0775,true);
$config=['storage'=>$storage,'database'=>$storage.'/central.sqlite','url'=>'https://example.test'];
$db=Database::connect($config['database']); Database::migrate($db);
$logs=new LogService($db); $projects=new ProjectService($db,$config,$logs); $schema=new SchemaService($projects,$logs);
$p=$projects->create(['name'=>'Signature test']); $other=$projects->create(['name'=>'Other']);
$s=new CompanySignatureService($schema,$config);
check($s->status($p)['status']==='none','Initial state');
$a=$s->create($p,['representative_name'=>'Ana']); $token=basename($a['url']);
rejected(fn()=>$s->resolve($other,$token));
$b=$s->create($p,[]); rejected(fn()=>$s->resolve($p,$token)); $token=basename($b['url']);
$s->cancel($p); rejected(fn()=>$s->resolve($p,$token));
$c=$s->create($p,[]); $token=basename($c['url']);
$im=imagecreatetruecolor(900,285); imagefill($im,0,0,imagecolorallocate($im,255,255,255));
imagesetthickness($im,5); imageline($im,100,100,700,160,imagecolorallocate($im,0,0,0)); ob_start(); imagepng($im); $png=ob_get_clean(); imagedestroy($im);
$input=['signer_name'=>'Ana Torres','signature'=>'data:image/png;base64,'.base64_encode($png),'consent'=>true];
rejected(fn()=>$s->sign($p,$token,array_merge($input,['consent'=>false])));
rejected(fn()=>$s->sign($p,$token,array_merge($input,['signature'=>'invalid'])));
$s->sign($p,$token,$input); check($s->status($p)['status']==='signed','Signed state');
$request=$s->resolve($p,$token); $project=$p; $error='';
ob_start(); require dirname(__DIR__).'/app/Views/company-sign.php'; $receipt=ob_get_clean();
check(str_contains($receipt,'Firma guardada correctamente en el servidor.'),'Receipt confirmation');
check(str_contains($receipt,'alt="Firma guardada"') && str_contains($receipt,$input['signature']),'Receipt image preview');
rejected(fn()=>$s->sign($p,$token,$input));
check(!(new DocumentSettingsService($schema))->get($p)['show_company_signature'],'Capture must not enable signature');
check($s->status($other)['status']==='none','Tenant isolation');
$d=$s->create($p,[]); $token=basename($d['url']);
$schema->connection($p)->exec("UPDATE _company_signature_requests SET expires_at='2000-01-01 00:00:00' WHERE signed_at IS NULL");
rejected(fn()=>$s->sign($p,$token,$input)); check($s->status($p)['status']==='expired','Expiry');
$runtime = new \App\Services\SystemRuntimeService($schema,$logs,new \App\Services\SharedDocumentService($db,$config,$logs),new \App\Services\WebhookService($db),new \App\Services\InvoiceSignatureService($db,$config,$logs,$schema),new \App\Services\CustomerRegistrationService($db,$config,$logs,$schema,new \App\Services\RecordService($schema,$logs),new \App\Services\WebhookService($db)),null,null,null,null,$s);
foreach (['create'=>'documentos:crearEnlaceFirmaRepresentante','status'=>'documentos:estadoFirmaRepresentante','cancel'=>'documentos:cancelarFirmaRepresentante'] as $operation=>$channel) {
    $payload=['channel'=>$channel,'args'=>[]];
    check($runtime->isWrite('invoke',$payload) === ($operation !== 'status'),'Write authorization classification');
    rejected(fn()=>$runtime->handle($p,'invoke',$payload,['rol'=>'Vendedor']));
    check($runtime->handle($p,'invoke',$payload,['rol'=>'Administrador'])['success'],'Admin access');
    foreach ([['email'=>'api-key','authentication'=>'project-secret'], ['rol'=>'CEO'], ['rol'=>'usuario','nivel_seguridad'=>' CEO '], ['rol'=>'ADMIN'], ['nivel_seguridad'=>'Administrador'], ['rol'=>'Soporte']] as $actor) {
        check($runtime->handle($p,'invoke',$payload,$actor)['success'],'Recognized administrative role');
    }
    foreach ([[], ['email'=>'api-key'], ['rol'=>'CEO','nivel_seguridad'=>'Usuario'], ['rol'=>'Administrador','nivel_seguridad'=>'Cajero'], ['rol'=>'Gerente']] as $actor) {
        $spoofed = array_replace($payload,['args'=>[['usuario'=>'admin','rol'=>'Administrador','nivel_seguridad'=>'CEO','authentication'=>'project-secret']]]);
        rejected(fn()=>$runtime->handle($p,'invoke',$spoofed,$actor));
    }

}
echo "COMPANY_SIGNATURE=OK isolation, consent, validation, replacement, cancellation, expiry, single use\n";
