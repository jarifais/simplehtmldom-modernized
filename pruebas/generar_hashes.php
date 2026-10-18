<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function tree_hashes(string $dir):array{$files=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile()){$rel=str_replace('\\','/',substr($f->getPathname(),strlen($dir)+1));$files[$rel]=hash_file('sha256',$f->getPathname());}}ksort($files);return $files;}
$baselineFile=$root.'/HASHES_LIBRERIAS_BASE.json';$current=['original'=>tree_hashes($root.'/librerias/original'),'fase1'=>tree_hashes($root.'/librerias/fase1')];
if(!is_file($baselineFile)){file_put_contents($baselineFile,json_encode($current,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}
$baseline=json_decode((string)file_get_contents($baselineFile),true);$payload=['algorithm'=>'SHA-256','baseline'=>$baseline,'current'=>$current,'validation'=>['original_unchanged'=>$baseline['original']===$current['original'],'fase1_unchanged'=>$baseline['fase1']===$current['fase1']]];file_put_contents($root.'/HASHES_LIBRERIAS.json',json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode($payload['validation']).PHP_EOL;exit(($payload['validation']['original_unchanged']&&$payload['validation']['fase1_unchanged'])?0:1);
