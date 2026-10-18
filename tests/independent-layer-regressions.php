<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$pages=new class implements \EpaperSaas\Contracts\PageArticleReader {
 public array $last=[];
 public ?array $page=['width'=>1000,'height'=>500];
 public function dimensions(string $guid):?array{return $this->page;}
 public function coveringBlocks(string $guid,int $x,int $y):array{
  $this->last=[$guid,$x,$y];
  return [['block_guid'=>hex2bin('123456781234423492341234567890ab'),
           'text_content'=>'Headline','x1'=>0,'y1'=>0,'x2'=>1000,'y2'=>500]];
 }
};
$r=new \EpaperSaas\Controllers\ReaderArticleAtController($pages);
[$status,$data]=$r->lookup('page-guid',2,-2);
if($status!==200||$pages->last!==['page-guid',1000,0]||$data['text']!=='Headline'||count($data['blocks'])!==1)
 throw new RuntimeException('Reader article point mapping changed');
$pages->page=null;
[$status,$data]=$r->lookup('missing',0.5,0.5);
if($status!==404||$data['code']!=='PAGE_NOT_FOUND')throw new RuntimeException('Missing Reader page not isolated');
$clips=new class implements \EpaperSaas\Contracts\ClipReader {
 public ?array $row=['visibility'=>'PRIVATE','slug'=>'demo'];
 public bool $allowed=false;
 public function bySlug(string $slug):?array{return $this->row;}
 public function canView(array $clip):bool{return $this->allowed;}
};
$visibility=new \EpaperSaas\Services\ClipVisibilityService($clips);
if($visibility->visible('demo')!==null)throw new RuntimeException('Denied private clipping leaked');
$clips->allowed=true;
if($visibility->visible('demo')!==$clips->row)throw new RuntimeException('Allowed clip not resolved');
$clips->row=null;
if($visibility->visible('missing')!==null)throw new RuntimeException('Missing clip not denied');
$publisher=new \EpaperSaas\Controllers\EditionPublishController(new \EpaperSaas\Services\DefaultEditionPublishPresenter());
foreach(['PUBLISHED'=>'Published','ARCHIVED'=>'Archived','TRASHED'=>'In Trash','DRAFT'=>'Draft'] as $state=>$label){
 if(($publisher->present(['status'=>$state])['label']??null)!==$label)
    throw new RuntimeException('Edition presentation changed: '.$state);
}
echo "Reader/Clipping/Edition injected layer contracts: OK\n";
