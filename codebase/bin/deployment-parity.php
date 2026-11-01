<?php
/**
 * Safe, read-only staging/production parity report. Never prints runtime secrets.
 *
 * php bin/deployment-parity.php --webroot=/absolute/path/to/public_html --strict
 * php bin/deployment-parity.php --compare=/path/to/staging-parity.json --strict
 */
declare(strict_types=1);

$root=dirname(__DIR__);
$args=array_slice($argv,1);
$get=function(string $prefix)use($args): ?string {
    foreach($args as $arg)if(str_starts_with($arg,$prefix.'='))return substr($arg,strlen($prefix)+1);
    return null;
};
$strict=in_array('--strict',$args,true);
$webroot=$get('--webroot')?:($_SERVER['DOCUMENT_ROOT']??'');
$webroot=$webroot!==''?(realpath($webroot)?:''):'';
$storage=realpath($root.'/storage')?:'';
$privateConfig=trim((string)(getenv('EPDI_PRIVATE_CONFIG_DIR')?:''));
$config=realpath($privateConfig!==''?$privateConfig:$root.'/config')?:'';
$within=static function(string $path,string $parent): bool {
    return $path!==''&&$parent!==''&&($path===$parent||str_starts_with($path,$parent.DIRECTORY_SEPARATOR));
};
$required=['mbstring','pdo_mysql','json','openssl','fileinfo','zip'];
$missing=array_values(array_filter($required,static fn($ext)=>!extension_loaded($ext)));
$release=is_file($root.'/VERSION')?trim((string)file_get_contents($root.'/VERSION')):'unknown';
$data=[
    'schema'=>'epdi.parity.v1',
    'release'=>$release,
    'php_major'=>PHP_MAJOR_VERSION,
    'php_minor'=>PHP_MINOR_VERSION,
    'required_extensions_missing'=>$missing,
    'docroot_is_public_html'=>$webroot!==''&&$webroot===realpath($root.'/public_html'),
    'storage_outside_docroot'=>$storage!==''&&$webroot!==''&&!$within($storage,$webroot),
    'config_outside_docroot'=>$config!==''&&$webroot!==''&&!$within($config,$webroot),
    'storage_present'=>$storage!=='',
    'config_present'=>$config!=='',
    'runtime_config_present'=>$config!==''&&is_readable($config.'/config.php'),
    'private_config_dir_enabled'=>$privateConfig!=='',
    'storage_is_external_mount'=>is_link($root.'/storage'),
];
$issues=[];
if($strict){
    foreach(['docroot_is_public_html','storage_outside_docroot','config_outside_docroot','storage_present','config_present','runtime_config_present']as$key)if(!$data[$key])$issues[]=$key;
    if($missing)$issues[]='required_extensions_missing';
    if(!$data['runtime_config_present'])$issues[]='runtime_config_missing_or_unreadable';
}
if($compare=$get('--compare')){
    $expected=is_file($compare)?json_decode((string)file_get_contents($compare),true):null;
    if(!is_array($expected)||($expected['schema']??'')!==$data['schema']){$issues[]='comparison_file_invalid';}
    else foreach(['release','php_major','php_minor','required_extensions_missing','docroot_is_public_html','storage_outside_docroot','config_outside_docroot']as$key)if(($expected[$key]??null)!==$data[$key])$issues[]='parity_mismatch:'.$key;
}
$data['checks_passed']=count($issues)===0;
$data['issues']=$issues;
echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($issues?1:0);
