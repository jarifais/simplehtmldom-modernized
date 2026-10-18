<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class EnvironmentValidationTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root=dirname(__DIR__); }
    public function testPhaseOneMandatoryResultsPass(): void
    {
        $path=$this->root.'/resultados/fase1/resultados.json';$this->assertFileExists($path,'Ejecute primero pruebas/ejecutar_todo.php');$data=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        foreach($data['results'] as $r){if($r['mandatory'])$this->assertSame('PASS',$r['status'],$r['test'].' '.$r['name']);}
    }
    public function testPublicApiIsIdentical(): void
    {
        $data=json_decode((string)file_get_contents($this->root.'/resultados/comparacion/api_publica.json'),true,512,JSON_THROW_ON_ERROR);$this->assertTrue($data['equal']);
    }
    public function testLibraryHashesRemainUnchanged(): void
    {
        $data=json_decode((string)file_get_contents($this->root.'/HASHES_LIBRERIAS.json'),true,512,JSON_THROW_ON_ERROR);$this->assertTrue($data['validation']['original_unchanged']);$this->assertTrue($data['validation']['fase1_unchanged']);
    }
}
