<?php
$secure=env('SESSION_SECURE_COOKIE');
if (is_string($secure) && strtolower($secure)==='auto') $secure=null;
return [
 'driver'=>env('SESSION_DRIVER','file'),'lifetime'=>(int)env('SESSION_LIFETIME',120),'expire_on_close'=>false,'encrypt'=>false,
 'files'=>storage_path('framework/sessions'),'connection'=>env('SESSION_CONNECTION'),'table'=>'sessions',
 'store'=>env('SESSION_STORE'),'lottery'=>[2,100],'cookie'=>env('SESSION_COOKIE','simmag_session'),
 'path'=>'/','domain'=>env('SESSION_DOMAIN'),'secure'=>$secure,'http_only'=>true,'same_site'=>'lax','partitioned'=>false
];
