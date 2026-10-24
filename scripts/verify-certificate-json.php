<?php
// Read-only native MySQL JSON compatibility check. Does not capture, approve, or modify certificates.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName()!=='mysql') throw new RuntimeException('This read-only check targets MySQL JSON normalization.');
$archive=app(App\Support\CertificateArchive::class);
$count=0;
foreach (App\Models\Certificate::orderBy('id')->limit(20)->get() as $certificate) {
    $source=$archive->source($certificate);
    $row=$db->selectOne('SELECT CAST(? AS JSON) AS normalized',[json_encode($source,JSON_THROW_ON_ERROR)]);
    $normalized=json_decode($row->normalized,true,512,JSON_THROW_ON_ERROR);
    if (!$archive->sourcesMatch($normalized,$source)) throw new RuntimeException('JSON comparison failed for certificate ID '.$certificate->id);
    $count++;
}
echo 'Native MySQL JSON round-trip passed for '.$count.' certificate sources. No writes.'.PHP_EOL;
