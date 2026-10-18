<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$config = require $root . '/benchmarks/config.php';
$options = getopt('', ['output-dir:','seed::']);
$out = $options['output-dir'] ?? null;
$seed = isset($options['seed']) ? (int)$options['seed'] : (int)$config['default_seed'];
if (!is_string($out) || $out === '' || $seed < 0) { fwrite(STDERR,"Argumentos inválidos\n"); exit(2); }
if (!is_dir($out) && !mkdir($out,0777,true) && !is_dir($out)) { fwrite(STDERR,"No se creó salida\n"); exit(2); }
function fixture_html(string $name,int $sections,int $seed): array {
    $lines = ['<!DOCTYPE html>','<html>','<head>','<title>F3-001 '.$name.'</title>','</head>','<body>','<main id="root" data-seed="'.$seed.'">'];
    for ($i=0; $i<$sections; $i++) {
        $bucket = ($i + $seed) % 7;
        $parity = ($i % 2) === 0 ? 'even' : 'odd';
        $token = substr(hash('sha256',$seed.':'.$name.':'.$i),0,16);
        $lines[] = '<section class="group group-'.$bucket.'" data-index="'.$i.'">';
        $lines[] = '<h2>Section '.$i.'</h2>';
        $lines[] = '<p class="item '.$parity.'" data-key="'.$seed.'-'.$i.'">Text '.$name.' '.$i.' '.$token.'</p>';
        $lines[] = '<ul class="links">';
        for ($j=1; $j<=3; $j++) { $lines[] = '<li><a href="/item/'.$i.'/'.$j.'" data-ref="'.$i.'-'.$j.'">Link '.$i.'-'.$j.'</a></li>'; }
        $lines[] = '</ul>';
        $lines[] = '</section>';
    }
    $lines[]='</main>'; $lines[]='</body>'; $lines[]='</html>';
    $html = implode("\n",$lines)."\n";
    return ['html'=>$html,'node_count_expected'=>5+($sections*10)];
}
$fixtures=[];
foreach ($config['fixtures'] as $name=>$definition) {
    $built=fixture_html((string)$name,(int)$definition['sections'],$seed);
    $file=(string)$name.'.html'; $path=rtrim($out,'/\\').DIRECTORY_SEPARATOR.$file;
    if (file_put_contents($path,$built['html'])===false) { fwrite(STDERR,"No se escribió fixture\n"); exit(2); }
    $fixtures[]=['name'=>(string)$name,'relative_path'=>'benchmarks/fixtures/'.$file,'size_bytes'=>strlen($built['html']),'sha256'=>hash('sha256',$built['html']),'node_count_expected'=>$built['node_count_expected'],'seed'=>$seed,'generator_version'=>(string)$config['generator_version']];
}
$implementations=[];
foreach ($config['implementations'] as $name=>$definition) { $implementations[]=['name'=>(string)$name,'relative_path'=>(string)$definition['relative_path']]; }
$manifest=['schema_version'=>1,'generator_version'=>(string)$config['generator_version'],'seed'=>$seed,'implementations'=>$implementations,'fixtures'=>$fixtures];
$json=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if (file_put_contents(rtrim($out,'/\\').DIRECTORY_SEPARATOR.'manifest.json',$json)===false) { fwrite(STDERR,"No se escribió manifest\n"); exit(2); }
echo json_encode(['status'=>'PASS','seed'=>$seed,'fixtures'=>count($fixtures)]).PHP_EOL;
