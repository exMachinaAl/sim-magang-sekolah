<?php
// Read-only check of the production query shape against the configured local DB.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment(['local','testing'])) throw new RuntimeException('Local/testing only.');
$actor=App\Models\User::where('role','koordinator')->firstOrFail();
$request=Illuminate\Http\Request::create('/timeline');
$request->setUserResolver(fn()=>$actor);
$view=app(App\Http\Controllers\TimelineController::class)->index($request);
$data=$view->getData();
$all=App\Support\Access::rosters($actor)->where('internship_period_id',$data['chosen'])->get();
foreach ($data['majorDirectory'] as $group) {
    $rows=$all->where('major',$group->major);
    if ($rows->count()!==(int)$group->total) throw new RuntimeException('Major total mismatch.');
    $phases=$rows->countBy(fn($row)=>$row->lifecycleState($data['today']));
    foreach (array_keys(App\Models\InternshipRoster::lifecycleLabels()) as $state) {
        if ($phases->get($state,0)!==(int)$group->{$state.'_count'}) throw new RuntimeException('Phase count mismatch: '.$state);
    }
}
if ($data['studentRows']->count()!==0) throw new RuntimeException('Root must not render the entire student list.');
echo 'Timeline SQL/model counts agree; '.$all->count().' scoped rosters; '.$data['majorDirectory']->total().' majors. No writes.'.PHP_EOL;
