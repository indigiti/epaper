<?php
declare(strict_types=1);
$auth=(string)file_get_contents(dirname(__DIR__).'/app/Core/Auth.php');
$fails=0;
if(!str_contains($auth,"View::e(\$brand).'</span></div><span class=\"eyebrow\">SIGN IN")){
    fwrite(STDERR,"FAIL login tenant brand is not safely HTML-escaped\n");
    $fails++;
}
if(str_contains($auth,"\$brand.'</span></div><span class=\"eyebrow\">SIGN IN")){
    fwrite(STDERR,"FAIL login tenant brand is concatenated unescaped\n");
    $fails++;
}
if($fails)exit(1);
echo "PASS login tenant-brand HTML output guard\n";
