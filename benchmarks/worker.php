<?php
declare(strict_types=1);
$root=dirname(__DIR__); $config=require $root.'/benchmarks/config.php';
$o=getopt('', ['implementation:','fixture:','workload:','iterations:']);
$implementation=$o['implementation']??null; $fixture=$o['fixture']??null; $workload=$o['workload']??null; $iterations=isset($o['iterations'])?(int)$o['iterations']:0;
function worker_fail(string $message,int $code=1): never { echo json_encode(['status'=>'ERROR','exit_code'=>$code,'error'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL; exit($code); }
if (!is_string($implementation)||!isset($config['implementations'][$implementation])) worker_fail('Implementación inválida',2);
if (!is_string($fixture)||!isset($config['fixtures'][$fixture])) worker_fail('Fixture inválido',2);
if (!is_string($workload)||!in_array($workload,$config['workloads'],true)||$iterations<1) worker_fail('Workload o iteraciones inválidos',2);
$relative=$config['implementations'][$implementation]['relative_path'];
$library=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
$fixturePath=$root.DIRECTORY_SEPARATOR.'benchmarks'.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.$fixture.'.html';
if (!is_file($library)||!is_file($fixturePath)) worker_fail('Entrada ausente',2);
require_once $library;
$html=file_get_contents($fixturePath); if (!is_string($html)) worker_fail('Lectura de fixture fallida',2);
function parse_fixture(string $html) { $charset=defined('DEFAULT_TARGET_CHARSET')?DEFAULT_TARGET_CHARSET:'UTF-8'; $dom=str_get_html($html,true,true,$charset,true); if ($dom===false) throw new RuntimeException('Parse fallido'); return $dom; }
function execute_case(string $workload,string $html,int $iterations): array {
    $semantic=null;
    for ($n=0;$n<$iterations;$n++) {
        $dom=parse_fixture($html);
        switch ($workload) {
            case 'parse': $nodes=$dom->find('*'); $semantic=['count'=>count($nodes),'sha256'=>hash('sha256',(string)$dom->plaintext)]; break;
            case 'find_by_tag': $nodes=$dom->find('li'); $v=[]; foreach ($nodes as $node) $v[]=(string)$node->plaintext; $semantic=['count'=>count($nodes),'sha256'=>hash('sha256',implode('|',$v))]; break;
            case 'find_by_class': $nodes=$dom->find('.item'); $v=[]; foreach ($nodes as $node) $v[]=(string)$node->getAttribute('data-key'); $semantic=['count'=>count($nodes),'sha256'=>hash('sha256',implode('|',$v))]; break;
            case 'find_by_attribute': $nodes=$dom->find('[data-key]'); $v=[]; foreach ($nodes as $node) $v[]=(string)$node->getAttribute('data-key'); $semantic=['count'=>count($nodes),'sha256'=>hash('sha256',implode('|',$v))]; break;
            case 'traverse_nodes': $nodes=$dom->find('*'); $sum=0; foreach ($nodes as $node) $sum+=strlen((string)$node->tag); $semantic=['count'=>count($nodes),'tag_length'=>$sum]; break;
            case 'read_plaintext': $v=(string)$dom->plaintext; $semantic=['length'=>strlen($v),'sha256'=>hash('sha256',$v)]; break;
            case 'read_outertext': $v=(string)$dom->outertext; $semantic=['length'=>strlen($v),'sha256'=>hash('sha256',$v)]; break;
            default: throw new RuntimeException('Workload no implementado');
        }
        $dom->clear(); unset($dom);
    }
    return ['semantic'=>$semantic,'operations'=>$iterations];
}
try {
    $before=memory_get_usage(true); $start=hrtime(true); $execution=execute_case($workload,$html,$iterations); $elapsed=max(1,hrtime(true)-$start); $after=memory_get_usage(true);
    $semanticJson=json_encode($execution['semantic'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    echo json_encode(['status'=>'PASS','exit_code'=>0,'wall_time_ns'=>$elapsed,'peak_memory_bytes'=>memory_get_peak_usage(true),'memory_delta_bytes'=>max(0,$after-$before),'operations'=>(int)$execution['operations'],'operations_per_second'=>((int)$execution['operations']*1000000000)/$elapsed,'semantic_digest'=>hash('sha256',$semanticJson),'semantic_result'=>$execution['semantic'],'error'=>null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) { worker_fail(get_class($e).': '.$e->getMessage(),1); }
