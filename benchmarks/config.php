<?php
declare(strict_types=1);
return [
    'schema_version' => 1,
    'generator_version' => '1.0.0',
    'default_seed' => 3001,
    'implementations' => [
        'original' => ['relative_path' => 'librerias/original/simple_html_dom.php'],
        'fase1' => ['relative_path' => 'librerias/fase1/simple_html_dom.php'],
    ],
    'fixtures' => [
        'small' => ['sections' => 8],
        'medium' => ['sections' => 80],
        'large' => ['sections' => 240],
    ],
    'workloads' => [
        'parse','find_by_tag','find_by_class','find_by_attribute',
        'traverse_nodes','read_plaintext','read_outertext',
    ],
    'modes' => [
        'smoke' => ['warmups'=>1,'samples'=>3,'inner_iterations'=>1,'timeout_ms'=>20000],
    ],
];
