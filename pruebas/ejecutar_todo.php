<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$php=PHP_BINARY;
function run_cmd(array $cmd,string $cwd):int{$parts=array_map('escapeshellarg',$cmd);passthru(implode(' ',$parts),$code);return $code;}
$preSteps=[
 [$php,$root.'/pruebas/generar_hashes.php'],
 [$php,$root.'/pruebas/api_snapshot.php','original'],
 [$php,$root.'/pruebas/api_snapshot.php','fase1'],
];
foreach($preSteps as $step){if(run_cmd($step,$root)!==0)exit(2);}
// Los fallos del original son evidencia esperada y no invalidan por sí solos la fase 1.
run_cmd([$php,$root.'/pruebas/ejecutar_original.php'],$root);
run_cmd([$php,$root.'/pruebas/ejecutar_fase1.php'],$root);
if(run_cmd([$php,$root.'/pruebas/generar_hashes.php'],$root)!==0)exit(3);
exit(run_cmd([$php,$root.'/pruebas/comparar_resultados.php'],$root));
