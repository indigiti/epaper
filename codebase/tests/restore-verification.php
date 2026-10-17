<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/RestoreInventory.php';
use EpaperSaas\Core\RestoreInventory;
$root=sys_get_temp_dir().'/epdi-restore-'.bin2hex(random_bytes(7));
$backup=$root.'/backup';$restored=$root.'/restored';
$dirs=['media','uploads','clips','cache','logs'];
foreach([$backup,$restored] as$path)foreach($dirs as$dir)mkdir($path.'/'.$dir,0700,true);
$cases=0;$fails=0;
$check=static function(bool $ok,string $label)use(&$cases,&$fails):void{
    $cases++;if(!$ok){fwrite(STDERR,"FAIL restore verifier: $label\n");$fails++;}
};
try{
    foreach([$backup,$restored] as$path){
        file_put_contents($path.'/media/page.webp',"RIFF\x04\x00\x00\x00WEBP");
        file_put_contents($path.'/uploads/source.pdf',"%PDF-1.7\nfake test only");
        file_put_contents($path.'/clips/shared.webp','test clip');
        file_put_contents($path.'/cache/.keep','');
    }
    $a=RestoreInventory::snapshot($backup);
    $b=RestoreInventory::snapshot($restored);
    $same=RestoreInventory::compare($a,$b);
    $check($same['match'],'identical content passes');
    $check($a['categories']['media']['files']===1,'page asset counted');
    $check($a['categories']['uploads']['files']===1,'source PDF counted');
    $check($a['categories']['clips']['files']===1,'clip counted');
    $check($a['categories']['cache']['files']===0,'directory marker ignored');
    $check($same['database_restore_verified']===false,'does not claim database verification');
    file_put_contents($restored.'/media/page.webp','damaged');
    $bad=RestoreInventory::compare($a,RestoreInventory::snapshot($restored));
    $check(!$bad['match'],'changed media byte content fails');
    file_put_contents($restored.'/media/page.webp',"RIFF\x04\x00\x00\x00WEBP");
    unlink($restored.'/uploads/source.pdf');
    $missing=RestoreInventory::compare($a,RestoreInventory::snapshot($restored));
    $check(!$missing['match'],'missing original PDF fails');
    file_put_contents($restored.'/uploads/source.pdf',"%PDF-1.7\nfake test only");
    file_put_contents($restored.'/cache/runtime.cache','different volatile cache');
    $volatile=RestoreInventory::compare($a,RestoreInventory::snapshot($restored));
    $check($volatile['match'],'volatile cache difference does not block');
    $check($volatile['categories']['cache']['match']===false,'cache mismatch visible for review');
    $empty=$root.'/empty';
    foreach($dirs as$dir)mkdir($empty.'/'.$dir,0700,true);
    $blank=RestoreInventory::compare(RestoreInventory::snapshot($empty),RestoreInventory::snapshot($empty));
    $check(!$blank['match'],'empty source checkout is not a successful restore');
    @rmdir($empty.'/media');@rmdir($empty.'/uploads');@rmdir($empty.'/clips');
    @rmdir($empty.'/cache');@rmdir($empty.'/logs');@rmdir($empty);
}finally{
    foreach([$backup,$restored] as$path){
        foreach($dirs as$dir) {
            foreach(glob($path.'/'.$dir.'/*')?:[] as$file)@unlink($file);
            @unlink($path.'/'.$dir.'/.keep');
            @rmdir($path.'/'.$dir);
        }
        @rmdir($path);
    }
    @rmdir($root);
}
if($fails)exit(1);
echo "PASS backup/restore source inventory comparisons ($cases checks)\n";
