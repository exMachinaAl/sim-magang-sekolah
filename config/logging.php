<?php
return [
 'default'=>env('LOG_CHANNEL','stack'),'deprecations'=>['channel'=>env('LOG_DEPRECATIONS_CHANNEL','null'),'trace'=>false],
 'channels'=>[
  'stack'=>['driver'=>'stack','channels'=>['single'],'ignore_exceptions'=>false],
  'single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>env('LOG_LEVEL','debug'),'replace_placeholders'=>true],
  'stderr'=>['driver'=>'monolog','level'=>env('LOG_LEVEL','debug'),'handler'=>Monolog\Handler\StreamHandler::class,'with'=>['stream'=>'php://stderr']],
  'null'=>['driver'=>'monolog','handler'=>Monolog\Handler\NullHandler::class],
 ],
];
