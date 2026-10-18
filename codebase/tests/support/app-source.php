<?php
declare(strict_types=1);
/** Return actual dispatcher + every mandatory controller; missing controllers are errors. */
function epdiAppSource(string $root): string
{
    $source=@file_get_contents($root.'/app/App.php');
    if($source===false)throw new RuntimeException('Missing App.php');
    $actions=['ApiActions','ClippingActions','ReaderActions','AccountActions','PublishingActions','AdministrationActions','OperationsActions','SupportActions'];
    foreach($actions as $action){
        $binding='use \\EpaperSaas\\Controllers\\'.$action.';';
        if(substr_count($source,$binding)!==1)throw new RuntimeException('Controller not bound exactly once: '.$action);
        $trait=@file_get_contents($root.'/app/Controllers/'.$action.'.php');
        if($trait===false || !str_contains($trait,'trait '.$action))throw new RuntimeException('Controller implementation missing: '.$action);
        $source.="\n".$trait;
    }
    return $source;
}
