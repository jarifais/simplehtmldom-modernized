<?php
declare(strict_types=1);
$version = $argv[1] ?? '';
if (!in_array($version, ['original','fase1'], true)) { fwrite(STDERR, "Uso: php run_version.php original|fase1\n"); exit(2); }
require __DIR__ . '/bootstrap.php';
$env = new TestEnvironment($version);
$definitions = require __DIR__ . '/casos/todos.php';
$builder = new ResultBuilder($env);
$results=[];
foreach($definitions as $definition){$results[]=$builder->run($definition);}
$summary=['version'=>$version,'php_version'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,'generated_at'=>date(DATE_ATOM),'total'=>count($results),'pass'=>count(array_filter($results,fn($r)=>$r['status']==='PASS')),'fail'=>count(array_filter($results,fn($r)=>$r['status']==='FAIL')),'known_failure'=>count(array_filter($results,fn($r)=>$r['status']==='KNOWN_FAILURE')),'unexpected_pass'=>count(array_filter($results,fn($r)=>$r['status']==='UNEXPECTED_PASS'))];
$payload=['summary'=>$summary,'results'=>$results];
$out=$env->root.'/resultados/'.$version.'/resultados.json';
file_put_contents($out,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
echo json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($summary['fail']>0?1:0);
