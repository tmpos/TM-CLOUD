<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService,RecordService,WebhookService,CustomerRegistrationService,DocumentSettingsService,SystemRuntimeService,InvoiceSignatureService,SharedDocumentService};
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$storage = sys_get_temp_dir() . '/document-settings-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage'=>$storage, 'database'=>$storage.'/central.sqlite', 'url'=>'https://example.test'];
$db = Database::connect($config['database']); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db,$config,$logs);
$schema = new SchemaService($projects,$logs); $records = new RecordService($schema,$logs);
$project = $projects->create(['name'=>'PDF test']); $other = $projects->create(['name'=>'Other company']);
$settings = new DocumentSettingsService($schema);
$runtime = new SystemRuntimeService($schema,$logs,new SharedDocumentService($db,$config,$logs),new WebhookService($db),new InvoiceSignatureService($db,$config,$logs,$schema),new CustomerRegistrationService($db,$config,$logs,$schema,$records,new WebhookService($db)));
$input = ['channel'=>'documentos:guardarDiseno','args'=>[array_merge($settings::defaults(),['primary_color'=>'#aa2244'])]];
verify($runtime->isWrite('invoke',$input),'Save must require write authorization');
verify(!$runtime->isWrite('invoke',['channel'=>'documentos:obtenerDiseno']),'Read must not be a write');
try { $runtime->handle($project,'invoke',$input,['rol'=>'vendedor']); throw new LogicException('Seller saved design'); } catch (RuntimeException $e) { verify($e->getCode() === 403,'Seller rejection'); }
$result = $runtime->handle($project,'invoke',$input,['rol'=>'administrador']);
verify($result['data']['settings']['primary_color'] === '#aa2244','Runtime save');
verify($runtime->handle($project,'invoke',['channel'=>'documentos:obtenerDiseno'],['rol'=>'vendedor'])['data']['settings']['primary_color'] === '#aa2244','Runtime read');
verify($settings->get($other) === $settings::defaults(),'Tenant isolation');
foreach ([['heading_color'=>'url(evil)'],['show_logo'=>'false'],['logo_width'=>0],['quote_validity_days'=>366]] as $bad) {
  try { $settings->save($project,$bad); throw new LogicException('Invalid design saved'); } catch (InvalidArgumentException) {}
}
verify($settings->get($project)['primary_color'] === '#aa2244','Rejected changes must preserve design');
echo "DOCUMENT_SETTINGS=OK permissions, persistence, validation, tenant isolation\n";
