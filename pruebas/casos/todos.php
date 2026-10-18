<?php
declare(strict_types=1);

return [
    [
        'id'=>'F1-001','name'=>'Sin propiedad dinámica optional_closing_array','category'=>'fase1','mandatory'=>true,
        'expected'=>['dynamic'=>false,'deprecated'=>false],
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom = new simple_html_dom('<p>A</p><p>B</p>', true, false);
            $dynamic = property_exists($dom, 'optional_closing_array');
            $declared = property_exists($dom, 'optional_closing_tags');
            $count = count($dom->find('p'));
            $dom->clear();
            return ['dynamic'=>$dynamic,'declared'=>$declared,'p_count'=>$count];
        },
        'assert'=>fn($a,$errors)=>$a['dynamic']===false && $a['declared']===true && $a['p_count']===2 && no_error_matching($errors,'dynamic property'),
    ],
    [
        'id'=>'F1-002','name'=>'Contenido literal 0','category'=>'fase1','mandatory'=>true,'expected'=>['str_get_html_false'=>false,'text'=>'0','attribute'=>'0'],
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $plain = str_get_html('0');
            $doc = file_get_html($e->httpBase.'/content-zero', false, $e->insecureContext(), 0, 600000);
            $result = ['str_get_html_false'=>$plain===false,'plain'=>is_object($plain)?$plain->plaintext:null,'text'=>is_object($doc)?$doc->find('#zero-text',0)->plaintext:null,'attribute'=>is_object($doc)?$doc->find('#zero-attribute',0)->getAttribute('data-value'):null];
            if (is_object($plain)) $plain->clear(); if (is_object($doc)) $doc->clear(); return $result;
        },
        'assert'=>fn($a,$errors)=>$a['str_get_html_false']===false && $a['plain']==='0' && $a['text']==='0' && $a['attribute']==='0',
    ],
    [
        'id'=>'F1-003A','name'=>'Cadena HTML mayor de 600000 bytes','category'=>'fase1','mandatory'=>true,'expected'=>['after_limit'=>true,'end'=>true],
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $html = '<div id="begin-marker">BEGIN</div><div>'.str_repeat('A',610000).'</div><div id="after-limit">CONTENT_AFTER_600000_BYTES</div><div id="end-marker">END</div>';
            $dom = str_get_html($html);
            $r=['input_bytes'=>strlen($html),'dom_false'=>$dom===false,'after_limit'=>is_object($dom)&&$dom->find('#after-limit',0)!==null,'end'=>is_object($dom)&&$dom->find('#end-marker',0)!==null,'nodes'=>dom_node_count($dom)];
            if(is_object($dom))$dom->clear(); return $r;
        },
        'assert'=>fn($a,$errors)=>$a['dom_false']===false && $a['after_limit']===true && $a['end']===true,
    ],
    [
        'id'=>'F1-003B','name'=>'Carga remota grande sin truncado silencioso','category'=>'fase1','mandatory'=>true,'expected'=>'parse_complete_or_explicit_rejection',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom = file_get_html($e->httpBase.'/large', false, $e->insecureContext(), 0, 600000);
            $explicit=false; foreach($errors as $er){if(stripos($er['message'],'exceeds the configured limit')!==false)$explicit=true;}
            $r=['dom_false'=>$dom===false,'explicit_rejection'=>$explicit,'after_limit'=>is_object($dom)&&$dom->find('#after-limit',0)!==null,'end'=>is_object($dom)&&$dom->find('#end-marker',0)!==null];
            if(is_object($dom))$dom->clear(); return $r;
        },
        'assert'=>fn($a,$errors)=>(($a['dom_false']===false && $a['after_limit'] && $a['end']) || ($a['dom_false']===true && $a['explicit_rejection']===true)),
    ],
    [
        'id'=>'F1-004','name'=>'UTF-8 válido conservado','category'=>'fase1','mandatory'=>true,'expected'=>'Unicode válido preservado y entidad HTML representable',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom=file_get_html($e->httpBase.'/utf8-valid',false,$e->insecureContext()); $text=is_object($dom)?$dom->find('#utf8',0)->plaintext:null; $r=['text'=>$text,'valid'=>is_string($text)&&preg_match('//u',$text)===1]; if(is_object($dom))$dom->clear(); return $r;
        },
        'assert'=>fn($a,$errors)=>$a['valid']===true && str_contains($a['text'],'Murcia, programación, niño, €, Ελληνικά, Русский, 日本語, 🚀') && (str_ends_with($a['text'],'& café') || str_ends_with($a['text'],'&amp; café')),
    ],
    [
        'id'=>'F1-005','name'=>'UTF-8 inválido controlado','category'=>'fase1','mandatory'=>true,'expected'=>'no_fatal_no_iconv_warning_markers_survive',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $raw=file_get_contents($e->httpBase.'/utf8-invalid',false,$e->insecureContext()); $dom=is_string($raw)?str_get_html($raw):false;
            $r=['raw_bytes'=>is_string($raw)?strlen($raw):0,'dom_false'=>$dom===false,'before'=>is_object($dom)&&$dom->find('#before',0)!==null,'after'=>is_object($dom)&&$dom->find('#after',0)!==null,'iconv_clean'=>no_error_matching($errors,'iconv')]; if(is_object($dom))$dom->clear(); return $r;
        },
        'assert'=>fn($a,$errors)=>$a['dom_false']===false && $a['before'] && $a['after'] && $a['iconv_clean'],
    ],
    [
        'id'=>'F1-006','name'=>'Charset inválido sin pérdida destructiva','category'=>'fase1','mandatory'=>true,'expected'=>'content_preserved_no_iconv_warning',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom=file_get_html($e->httpBase.'/charset-invalid',false,$e->insecureContext()); $text=is_object($dom)&&$dom->find('#charset-invalid-text',0)?$dom->find('#charset-invalid-text',0)->plaintext:null;
            $r=['dom_false'=>$dom===false,'text'=>$text,'preserved'=>is_string($text)&&str_contains($text,'CONTENIDO_CONSERVADO'),'iconv_clean'=>no_error_matching($errors,'iconv')]; if(is_object($dom))$dom->clear(); return $r;
        },
        'assert'=>fn($a,$errors)=>$a['dom_false']===false && $a['preserved']===true && $a['iconv_clean']===true,
    ],
    [
        'id'=>'F1-007A','name'=>'Constructor reconoce HTTPS','category'=>'fase1','mandatory'=>true,'expected'=>'TRANSPORT_OK',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom=new simple_html_dom($e->httpsBase.'/https-test'); $node=$dom->find('#transport-marker',0); $r=['marker'=>$node?$node->plaintext:null,'literal_url_node'=>$dom->find($e->httpsBase.'/https-test',0)!==null,'nodes'=>dom_node_count($dom)]; $dom->clear(); return $r;
        },
        'assert'=>fn($a,$errors)=>$a['marker']==='TRANSPORT_OK',
    ],
    [
        'id'=>'F1-007B','name'=>'file_get_html carga HTTPS','category'=>'fase1','mandatory'=>true,'expected'=>'TRANSPORT_OK',
        'run'=>function(TestEnvironment $e, array &$errors): array { $dom=file_get_html($e->httpsBase.'/https-test',false,$e->insecureContext()); $r=['marker'=>is_object($dom)&&$dom->find('#transport-marker',0)?$dom->find('#transport-marker',0)->plaintext:null]; if(is_object($dom))$dom->clear(); return $r; },
        'assert'=>fn($a,$errors)=>$a['marker']==='TRANSPORT_OK',
    ],
    [
        'id'=>'F1-008','name'=>'loadFile con archivo HTTP HTTPS y fallo','category'=>'fase1','mandatory'=>true,'expected'=>'all_sources_load_and_missing_controlled',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $sources=['local'=>$e->fixture,'http'=>$e->httpBase.'/http-test','https'=>$e->httpsBase.'/https-test']; $ok=[]; $returns=[];
            foreach($sources as $k=>$src){$d=new simple_html_dom();$ret=$d->loadFile($src);$ok[$k]=$k==='local'?($d->find('#local-marker',0)!==null):($d->find('#transport-marker',0)!==null);$returns[$k]=is_object($ret)?get_class($ret):$ret;$d->clear();}
            $d=new simple_html_dom();$missing=$d->loadFile($e->root.'/no-existe-'.uniqid().'.html');$missingState=['return'=>$missing,'nodes'=>dom_node_count($d)];$d->clear();
            return ['ok'=>$ok,'returns'=>$returns,'missing'=>$missingState];
        },
        'assert'=>fn($a,$errors)=>$a['ok']['local']===true && $a['ok']['http']===true && $a['ok']['https']===true && $a['missing']['return']===false,
    ],
    [
        'id'=>'F1-009','name'=>'Funciones de depuración sin avisos','category'=>'fase1','mandatory'=>true,'expected'=>'no_warnings',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $d=str_get_html('<div id="debug"><span>A</span></div>'); ob_start(); dump_html_tree($d->find('#debug',0),true,0); $out=ob_get_clean(); $d->clear(); return ['output_bytes'=>strlen((string)$out)];
        },
        'assert'=>fn($a,$errors)=>count($errors)===0 && $a['output_bytes']>0,
    ],
    [
        'id'=>'F1-010','name'=>'Sin operadores de supresión en código modificado','category'=>'fase1','mandatory'=>true,'expected'=>0,
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $code=file_get_contents($e->libraryPath);$tokens=token_get_all($code);$count=0;$lines=[];foreach($tokens as $t){if(is_string($t)&&$t==='@')$count++;}foreach(preg_split('/\R/',$code) as $i=>$line){if(preg_match('/(?<![\w])@(?![\w])/', $line))$lines[]=$i+1;}return ['token_count'=>$count,'candidate_lines'=>$lines];
        },
        'assert'=>fn($a,$errors)=>$a['token_count']===0,
    ],
    [
        'id'=>'F1-011','name'=>'API pública idéntica mediante Reflection','category'=>'fase1','mandatory'=>true,'expected'=>'identical_public_api',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $op=$e->root.'/resultados/original/api_publica.json';$fp=$e->root.'/resultados/fase1/api_publica.json';
            if(!is_file($op)||!is_file($fp))return ['available'=>false,'equal'=>false];
            $o=json_decode((string)file_get_contents($op),true);$f=json_decode((string)file_get_contents($fp),true);unset($o['version'],$f['version']);return ['available'=>true,'equal'=>$o==$f];
        },
        'assert'=>fn($a,$errors)=>$a['available']===true&&$a['equal']===true,
    ],
    [
        'id'=>'F1-012','name'=>'Ejemplos locales equivalentes','category'=>'fase1','mandatory'=>true,'expected'=>'selectors_callback_modify_scrape',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom=file_get_html($e->httpBase.'/basic',false,$e->insecureContext());$basic=count($dom->find('li.item'))===3 && $dom->find('#main-title',0)!==null;$advanced=count($dom->find('p.lead[data-kind=intro]'))===1;
            $callbackCount=0;$dom->set_callback(function($node)use(&$callbackCount){$callbackCount++;});$serialized=$dom->save();$dom->remove_callback();$node=$dom->find('#intro',0);$node->innertext='MODIFICADO';$modified=$node->plaintext==='MODIFICADO';$scrape=$dom->find('#product-image',0)->getAttribute('src')==='/assets/product.svg';$dom->clear();return compact('basic','advanced','callbackCount','modified','scrape')+['serialized_bytes'=>strlen($serialized)];
        },
        'assert'=>fn($a,$errors)=>$a['basic']&&$a['advanced']&&$a['callbackCount']>0&&$a['modified']&&$a['scrape'],
    ],
    [
        'id'=>'R-001','name'=>'Regresión de API DOM básica','category'=>'regression','mandatory'=>true,'expected'=>'basic_api_ok',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $d=str_get_html('<div id="root" class="box"><p id="a" class="x">A</p><p id="b">B</p></div>');$root=$d->find('#root',0);$a=$d->find('#a',0);$b=$d->find('#b',0);
            $r=['tag'=>count($d->find('p')),'id'=>$a->plaintext,'class'=>count($d->find('.x')),'inner'=>str_contains($root->innertext,'id="a"'),'outer'=>str_contains($root->outertext,'id="root"'),'plain'=>preg_replace('/\s+/u','',$root->plaintext)==='AB','parent'=>$a->parent()->id==='root','first'=>$root->first_child()->id==='a','last'=>$root->last_child()->id==='b','next'=>$a->next_sibling()->id==='b','prev'=>$b->prev_sibling()->id==='a'];$a->setAttribute('data-v','0');$r['set_attr']=$a->getAttribute('data-v')==='0';$a->innertext='AA';$r['modify']=$a->plaintext==='AA';$saved=$d->save();$r['save']=str_contains($saved,'AA');$d->clear();return $r;
        },
        'assert'=>fn($a,$errors)=>!in_array(false,$a,true)&&$a['tag']===2&&$a['id']==='A'&&$a['class']===1,
    ],
    [
        'id'=>'R-002','name'=>'Carga de archivo y múltiples instancias','category'=>'regression','mandatory'=>true,'expected'=>'stable_multiple_runs',
        'run'=>function(TestEnvironment $e, array &$errors): array {$ok=[];for($i=0;$i<20;$i++){$d=file_get_html($e->fixture);$ok[]=is_object($d)&&$d->find('#local-marker',0)!==null;if(is_object($d))$d->clear();}return ['all'=>!in_array(false,$ok,true),'runs'=>count($ok)];},
        'assert'=>fn($a,$errors)=>$a['all']===true&&$a['runs']===20,
    ],
    [
        'id'=>'R-003','name'=>'Estados HTTP redirecciones y timeout local','category'=>'regression','mandatory'=>false,'expected'=>'controlled_http_behaviour',
        'run'=>function(TestEnvironment $e, array &$errors): array {$r=[];foreach([200,301,302,404,500] as $s){$raw=file_get_contents($e->httpBase.'/status/'.$s,false,$e->insecureContext(['ignore_errors'=>true]));$r[(string)$s]=is_string($raw)&&strlen($raw)>0;}$start=microtime(true);$slow=file_get_contents($e->httpBase.'/slow?seconds=1',false,$e->insecureContext(['timeout'=>2]));$r['slow_elapsed']=round(microtime(true)-$start,2);$r['slow_ok']=is_string($slow)&&str_contains($slow,'slow-seconds');return $r;},
        'assert'=>fn($a,$errors)=>$a['200']&&$a['301']&&$a['302']&&$a['404']&&$a['500']&&$a['slow_ok'],
    ],
    [
        'id'=>'K-001',
        'name'=>'F2-001 Selector de hermanos generales ~',
        'category'=>'phase2',
        'mandatory'=>true,
        'expected'=>[
            'p1_sibling_p'=>['p2','p3'],
            'p1_sibling_span'=>['s1'],
            's1_sibling_p'=>['p2','p3'],
            'p_sibling_p'=>['p2','p3','n2'],
            'class_siblings'=>['s1','p2','p3'],
            'attribute_siblings'=>['p2'],
            'none'=>[],
            'nested'=>['n2'],
            'index0'=>'p2',
            'index1'=>'p3',
            'index2'=>null,
        ],
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $html = '<div id="grupo-1">'
                . '<p id="p1" class="clase-a" data-x="1">Uno</p>'
                . '<span id="s1" class="clase-b">Separador</span>'
                . '<p id="p2" class="clase-b" data-x="2">Dos</p>'
                . '<p id="p3" class="clase-b">Tres</p>'
                . '<div id="nested">'
                . '<p id="n1">N1</p>'
                . '<p id="n2">N2</p>'
                . '</div>'
                . '</div>'
                . '<div id="grupo-2">'
                . '<p id="p4" class="clase-b" data-x="2">Cuatro</p>'
                . '</div>';

            $dom = str_get_html($html);

            $ids = static fn(array $nodes): array =>
                array_map(
                    static fn($node): string => (string)$node->id,
                    $nodes
                );

            $index0 = $dom->find('#p1 ~ p', 0);
            $index1 = $dom->find('#p1 ~ p', 1);
            $index2 = $dom->find('#p1 ~ p', 2);

            $result = [
                'p1_sibling_p'=>$ids($dom->find('#p1 ~ p')),
                'p1_sibling_span'=>$ids($dom->find('#p1 ~ span')),
                's1_sibling_p'=>$ids($dom->find('#s1 ~ p')),
                'p_sibling_p'=>$ids($dom->find('p ~ p')),
                'class_siblings'=>$ids(
                    $dom->find('.clase-a ~ .clase-b')
                ),
                'attribute_siblings'=>$ids(
                    $dom->find('[data-x="1"] ~ [data-x="2"]')
                ),
                'none'=>$ids($dom->find('#p3 ~ span')),
                'nested'=>$ids($dom->find('#n1 ~ p')),
                'index0'=>$index0 ? $index0->id : null,
                'index1'=>$index1 ? $index1->id : null,
                'index2'=>$index2 ? $index2->id : null,
            ];

            $dom->clear();

            return $result;
        },
        'assert'=>fn($actual,$errors)=>$actual===[
            'p1_sibling_p'=>['p2','p3'],
            'p1_sibling_span'=>['s1'],
            's1_sibling_p'=>['p2','p3'],
            'p_sibling_p'=>['p2','p3','n2'],
            'class_siblings'=>['s1','p2','p3'],
            'attribute_siblings'=>['p2'],
            'none'=>[],
            'nested'=>['n2'],
            'index0'=>'p2',
            'index1'=>'p3',
            'index2'=>null,
        ],
    ],    [
        'id'=>'K-002',
        'name'=>'F2-002 Selector de atributo [lang|=en]',
        'category'=>'phase2',
        'mandatory'=>true,
        'expected'=>'exact_or_hyphen_prefix',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $html = '<div id="contenedor">'
                . '<div id="lang-en" lang="en"></div>'
                . '<div id="lang-en-us" lang="en-US"></div>'
                . '<div id="lang-en-gb" lang="en-GB"></div>'
                . '<div id="lang-en-lower" lang="en-us"></div>'
                . '<div id="lang-uppercase" lang="EN"></div>'
                . '<div id="lang-english" lang="english"></div>'
                . '<div id="lang-enus" lang="enUS"></div>'
                . '<div id="lang-underscore" lang="en_US"></div>'
                . '<div id="lang-enough" lang="enough"></div>'
                . '<div id="lang-trailing" lang="en-"></div>'
                . '<div id="lang-double" lang="en--US"></div>'
                . '<div id="lang-prefix" lang="x-en"></div>'
                . '<div id="lang-empty" lang=""></div>'
                . '<div id="lang-none"></div>'
                . '</div>';
            $dom = str_get_html($html);
            $ids = static fn(array $nodes): array => array_map(
                static fn($node): string => (string)$node->id,
                $nodes
            );
            $index0 = $dom->find('[lang|=en]', 0);
            $index5 = $dom->find('[lang|=en]', 5);
            $index6 = $dom->find('[lang|=en]', 6);
            $result = [
                'default'=>$ids($dom->find('[lang|=en]')),
                'tag'=>$ids($dom->find('div[lang|=en]')),
                'descendant'=>$ids($dom->find('#contenedor [lang|=en]')),
                'double_quoted'=>$ids($dom->find('[lang|="en"]')),
                'single_quoted'=>$ids($dom->find("[lang|='en']")),
                'sensitive'=>$ids($dom->find('[lang|=en s]')),
                'insensitive'=>$ids($dom->find('[lang|=en i]')),
                'uppercase_pattern'=>$ids($dom->find('[lang|=EN]')),
                'index0'=>$index0 ? $index0->id : null,
                'index5'=>$index5 ? $index5->id : null,
                'index6'=>$index6 ? $index6->id : null,
            ];
            $dom->clear();
            return $result;
        },
        'assert'=>fn($actual,$errors)=>$actual===[
            'default'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'],
            'tag'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'],
            'descendant'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'],
            'double_quoted'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'],
            'single_quoted'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'],
            'sensitive'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-trailing','lang-double'],
            'insensitive'=>['lang-en','lang-en-us','lang-en-gb','lang-en-lower','lang-uppercase','lang-trailing','lang-double'],
            'uppercase_pattern'=>['lang-uppercase'],
            'index0'=>'lang-en',
            'index5'=>'lang-double',
            'index6'=>null,
        ],
    ],
[
        'id'=>'K-003',
        'name'=>'F2-003 removeAttribute frente a hasAttribute',
        'category'=>'phase2',
        'mandatory'=>true,
        'known_failure'=>false,
        'expected'=>'removed_attributes_are_absent',
        'run'=>function(TestEnvironment $e, array &$errors): array {
            $dom = str_get_html(
                '<div id="x" data-first="A" data-middle="B" '
                . 'data-last="C" data-empty="" data-zero="0" '
                . 'disabled></div>'
            );

            $node = $dom->find('#x', 0);

            $result = [
                'existing'=>$node->hasAttribute('data-middle'),
                'missing'=>$node->hasAttribute('data-missing'),
                'empty'=>$node->hasAttribute('data-empty'),
                'zero'=>$node->hasAttribute('data-zero'),
                'boolean'=>$node->hasAttribute('disabled'),
            ];

            $node->setAttribute('data-false', false);

            $result['false_value_has'] =
                $node->hasAttribute('data-false');

            $result['false_value_serialized_absent'] =
                !str_contains($node->outertext, 'data-false');

            $node->removeAttribute('data-first');

            $result['first_has_after'] =
                $node->hasAttribute('data-first');

            $result['first_outer'] = $node->outertext;

            $node->removeAttribute('data-middle');

            $result['middle_has_after'] =
                $node->hasAttribute('data-middle');

            $result['middle_outer'] = $node->outertext;

            $node->removeAttribute('data-last');

            $result['last_has_after'] =
                $node->hasAttribute('data-last');

            $result['last_outer'] = $node->outertext;

            $node->removeAttribute('data-last');

            $result['repeated_has_after'] =
                $node->hasAttribute('data-last');

            $result['repeated_outer'] = $node->outertext;

            $node->setAttribute(
                'data-middle',
                'RESTORED'
            );

            $result['restored_has'] =
                $node->hasAttribute('data-middle');

            $result['restored_value'] =
                $node->getAttribute('data-middle');

            $result['restored_outer'] = $node->outertext;

            $dom->clear();

            $selectorDom = str_get_html(
                '<section>'
                . '<div id="selector" data-a="1"></div>'
                . '</section>'
            );

            $selectorNode = $selectorDom->find(
                '#selector',
                0
            );

            $ids = static fn(array $nodes): array =>
                array_map(
                    static fn($item): string =>
                        (string)$item->id,
                    $nodes
                );

            $result['selector_before'] = $ids(
                $selectorDom->find('[data-a]')
            );

            $selectorNode->removeAttribute('data-a');

            $result['selector_removed_has'] =
                $selectorNode->hasAttribute('data-a');

            $result['selector_after'] = $ids(
                $selectorDom->find('[data-a]')
            );

            $result['selector_outer'] =
                $selectorNode->outertext;

            $selectorDom->clear();

            return $result;
        },
        'assert'=>fn($actual,$errors)=>$actual===[
            'existing'=>true,
            'missing'=>false,
            'empty'=>true,
            'zero'=>true,
            'boolean'=>true,
            'false_value_has'=>false,
            'false_value_serialized_absent'=>true,
            'first_has_after'=>false,
            'first_outer'=>'<div id="x" data-middle="B" data-last="C" data-empty="" data-zero="0" disabled></div>',
            'middle_has_after'=>false,
            'middle_outer'=>'<div id="x" data-last="C" data-empty="" data-zero="0" disabled></div>',
            'last_has_after'=>false,
            'last_outer'=>'<div id="x" data-empty="" data-zero="0" disabled></div>',
            'repeated_has_after'=>false,
            'repeated_outer'=>'<div id="x" data-empty="" data-zero="0" disabled></div>',
            'restored_has'=>true,
            'restored_value'=>'RESTORED',
            'restored_outer'=>'<div id="x" data-middle="RESTORED" data-empty="" data-zero="0" disabled></div>',
            'selector_before'=>['selector'],
            'selector_removed_has'=>false,
            'selector_after'=>[],
            'selector_outer'=>'<div id="selector"></div>',
        ],
    ],
    [
        'id'=>'K-004',
        'name'=>'F2-004 Conservación exacta en pre',
        'category'=>'phase2',
        'mandatory'=>true,
        'known_failure'=>false,
        'expected'=>'pre_content_exact',

        'run'=>function(
            TestEnvironment $environment,
            array &$errors
        ): array {
            $http = file_get_html(
                $environment->httpBase.'/noise',
                false,
                $environment->insecureContext()
            );

            $pre = $http->find('#pre-one', 0);

            $result = [
                'http_inner'=>
                    (string)$pre->innertext,

                'http_outer'=>
                    (string)$pre->outertext,

                'http_plaintext'=>
                    (string)$pre->plaintext,
            ];

            $http->clear();

            $get = static function(
                string $html,
                string $id,
                bool $strip = true
            ): string {
                $dom = str_get_html(
                    $html,
                    true,
                    true,
                    DEFAULT_TARGET_CHARSET,
                    $strip
                );

                $value = (string)$dom
                    ->find('#'.$id, 0)
                    ->innertext;

                $dom->clear();

                return $value;
            };

            $result['lf_inner'] = $get(
                '<pre id="lf">  A'."\n".'    B  </pre>',
                'lf'
            );

            $result['crlf_inner'] = $get(
                '<pre id="crlf">A'."\r\n".'B</pre>',
                'crlf'
            );

            $result['tabs_inner'] = $get(
                '<pre id="tabs">'."\tA\nB\t".'</pre>',
                'tabs'
            );

            $result['blank_lines_inner'] = $get(
                '<pre id="blank">A'."\n\n".'B</pre>',
                'blank'
            );

            $multiple = str_get_html(
                '<pre id="one">ONE'."\n".'1</pre>'
                . '<pre id="two">TWO'."\n".'2</pre>'
            );

            $result['multiple_first'] =
                (string)$multiple
                    ->find('#one', 0)
                    ->innertext;

            $result['multiple_second'] =
                (string)$multiple
                    ->find('#two', 0)
                    ->innertext;

            $multiple->clear();

            $result['uppercase_inner'] = $get(
                '<PRE class="x" data-a="1" id="upper">X'
                . "\n"
                . 'Y</PRE>',
                'upper'
            );

            $result['empty_inner'] = $get(
                '<pre id="empty"></pre>',
                'empty'
            );

            $outside = str_get_html(
                '<div id="outside">A'."\n".'B</div>'
                . '<pre id="inside">C'."\n".'D</pre>'
            );

            $result['outside_inner'] =
                (string)$outside
                    ->find('#outside', 0)
                    ->innertext;

            $result['outside_pre_inner'] =
                (string)$outside
                    ->find('#inside', 0)
                    ->innertext;

            $outside->clear();

            $result['strip_rn_false_inner'] = $get(
                '<pre id="sf">A'."\r\n".'B</pre>',
                'sf',
                false
            );

            return $result;
        },

        'assert'=>fn(
            $actual,
            $errors
        ): bool=>$actual === [
            'http_inner'=>"  línea 1\n    línea 2  ",

            'http_outer'=>
                '<pre id="pre-one">'
                . "  línea 1\n    línea 2  "
                . '</pre>',

            'http_plaintext'=>"  línea 1\n    línea 2  ",
            'lf_inner'=>"  A\n    B  ",
            'crlf_inner'=>"A\r\nB",
            'tabs_inner'=>"\tA\nB\t",
            'blank_lines_inner'=>"A\n\nB",
            'multiple_first'=>"ONE\n1",
            'multiple_second'=>"TWO\n2",
            'uppercase_inner'=>"X\nY",
            'empty_inner'=>'',
            'outside_inner'=>'A B',
            'outside_pre_inner'=>"C\nD",
            'strip_rn_false_inner'=>"A\r\nB",
        ],
    ],
    [
        'id'=>'K-005','name'=>'Colisión literal ___noise___1000','category'=>'phase2','mandatory'=>true,'known_failure'=>false,'expected'=>'___noise___1000',
        'run'=>function(TestEnvironment $e, array &$errors): array {$d=file_get_html($e->httpBase.'/noise',false,$e->insecureContext());$n=$d->find('#literal-noise',0);$r=['text'=>$n?$n->plaintext:null];$d->clear();return $r;},
        'assert'=>fn($a,$errors)=>$a['text']==='___noise___1000',
    ],
];
