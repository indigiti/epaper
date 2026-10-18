<?php
declare(strict_types=1);
function epdiAppSource(string $root): string
{
    $source=@file_get_contents($root.'/app/App.php');
    if($source===false)throw new RuntimeException('Missing ePaper dispatcher');
    foreach(['ApiActions','ClippingActions','ReaderActions','AccountActions','PublishingActions','AdministrationActions','OperationsActions','SupportActions'] as $name){
        if(substr_count($source,'use \\EpaperSaas\\Controllers\\'.$name.';')!==1)throw new RuntimeException('Controller binding missing: '.$name);
        $part=@file_get_contents($root.'/app/Controllers/'.$name.'.php');
        if($part===false||!str_contains($part,'trait '.$name))throw new RuntimeException('Controller implementation missing: '.$name);
        $source.="\n".$part;
    }
    return $source;
}
