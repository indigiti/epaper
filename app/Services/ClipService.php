<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Http,RequestContext,Store,Uuid};

final class ClipService
{
    public const EXPIRY_DAYS = 7;
    public const EXPIRY_SETTING_COMPAT = 'clipping.expiry_days';
    public const BRANDING_SETTING_COMPAT = 'clipping.watermark';
    public const BRANDING_VERSION = 'source-qr-v1';

    public static function create(string $pageGuid,float $x,float $y,float $w,float $h,?string $title=null,string $visibility='PRIVATE',bool $editorial=false,?string $markGuid=null): array
    {
        foreach([$x,$y,$w,$h] as $v){
            if($v<0||$v>1)throw new \InvalidArgumentException('Clip coordinates must be normalized');
        }
        if($w<.02||$h<.02||$x+$w>1.00001||$y+$h>1.00001)throw new \InvalidArgumentException('Invalid clipping area');

        $visibility=$editorial?'PRIVATE':strtoupper(trim($visibility));
        if(!in_array($visibility,['PRIVATE','PUBLIC'],true))$visibility='PRIVATE';
        $title=trim((string)$title);
        if($title==='')$title='News clipping';
        if(mb_strlen($title)>180)$title=mb_substr($title,0,180);

        if($markGuid!==null&&$markGuid!==''){
            // Validate *before* any crop cache read or database mutation.
            ManualClipMarking::validateClipSource($pageGuid,$markGuid,
                ['x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h]);
        }else $markGuid=null;

        $page=Store::one(
            'SELECT p.page_guid,p.width,p.height,p.page_number,e.edition_date,e.slug edition_slug,pub.name publication_name FROM ep_pages p JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid WHERE p.tenant_guid=? AND p.page_guid=?',
            [TenantData::tenantBin(),Uuid::toBinary($pageGuid)]
        );
        if(!$page)throw new \RuntimeException('Page not found');
        $asset=Store::one('SELECT storage_path FROM ep_page_assets WHERE tenant_guid=? AND page_guid=? AND asset_type="HIGHRES"',[TenantData::tenantBin(),Uuid::toBinary($pageGuid)]);
        if(!$asset)throw new \RuntimeException('High resolution page is unavailable');

        $root=dirname(__DIR__,2);
        $src=$root.'/storage/'.$asset['storage_path'];
        if(!is_file($src))throw new \RuntimeException('Page media missing');

        $clipGuid=Uuid::v7();
        $slug=substr(str_replace('-','',$clipGuid),0,18);
        $dir=$root.'/storage/clips/'.RequestContext::tenantGuid();
        @mkdir($dir,0775,true);
        $dst=$dir.'/'.$clipGuid.'.webp';

        // Cache only immutable crop pixels: no title, reader ID, private link,
        // access token, expiry, branding or personally identifiable metadata.
        $cache=ClipCropCache::materialize($src,$dst,RequestContext::tenantGuid(),$pageGuid,
            ['x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h],
            static function(string $target)use($src,$x,$y,$w,$h,$page):void{
                self::crop($src,$target,$x,$y,$w,$h,(int)$page['width'],(int)$page['height']);
            });
        $u=Auth::user();
        $expiresAt=$editorial?null:date('Y-m-d H:i:s',time()+self::EXPIRY_DAYS*86400);
        $base=[
            Uuid::toBinary($clipGuid),TenantData::tenantBin(),
            $u?Uuid::toBinary($u['user_guid']):null,Uuid::toBinary($pageGuid)
        ];
        $values=[
            $slug,$title,$visibility,$x,$y,$w,$h,
            str_replace($root.'/storage/','',$dst),$expiresAt
        ];
        try{
            if($markGuid!==null&&SchemaDoctor::clipReuseReady()){
                Store::exec(
                    'INSERT INTO ep_clips(clip_guid,tenant_guid,platform_user_guid,page_guid,mark_guid,clip_slug,title,visibility,x,y,width,height,storage_path,expires_at,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(6),NOW(6))',
                    [...$base,Uuid::toBinary($markGuid),...$values]
                );
            }else{
                // Backwards-compatible while the web Upgrade Center has not
                // applied the additive mark_guid migration.
                Store::exec(
                    'INSERT INTO ep_clips(clip_guid,tenant_guid,platform_user_guid,page_guid,clip_slug,title,visibility,x,y,width,height,storage_path,expires_at,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(6),NOW(6))',
                    [...$base,...$values]
                );
            }
        }catch(\Throwable $e){
            @unlink($dst); // The shared immutable crop survives for other readers.
            throw $e;
        }

        $clip=self::bySlug($slug);
        if($clip){
            try{self::ensureBranded($clip);}catch(\Throwable $e){Audit::log('clip.branding.failed','clip',$clipGuid,'FAILED',['message'=>$e->getMessage()]);}
        }

        Audit::log($editorial?'clip.manual_created':'clip.created','clip',$clipGuid,'SUCCESS',[
            'page_guid'=>$pageGuid,'visibility'=>$visibility,'source'=>$editorial?'MANUAL_EDITORIAL':'READER',
            'expires_in_days'=>$editorial?null:self::EXPIRY_DAYS,
            'mark_guid'=>$markGuid,'crop_cache_hit'=>$cache['hit'],
        ]);
        if(!$editorial)PlatformClient::instance()->usage(RequestContext::tenantGuid(),'EPAPER.CLIPPING',1);
        return[
            'clip_guid'=>$clipGuid,
            'slug'=>$slug,
            'url'=>'/c/'.$slug,
            'expires_at'=>$expiresAt,
            'visibility'=>$visibility,
        ];
    }

    /** Private durable editorial clips, authorized independently of reader subscriptions. */
    public static function createEditorial(string $pageGuid,float $x,float $y,float $w,float $h,?string $title=null): array
    {
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true)){
            throw new \RuntimeException('Editorial clipping permission required');
        }
        return self::create($pageGuid,$x,$y,$w,$h,$title,'PRIVATE',true);
    }

    private static function crop(string $src,string $dst,float $x,float $y,float $w,float $h,int $pageW,int $pageH): void
    {
        if(class_exists('Imagick')){
            $im=new \Imagick($src);
            $iw=max(1,$im->getImageWidth());$ih=max(1,$im->getImageHeight());
            $cw=max(1,(int)round($w*$iw));$ch=max(1,(int)round($h*$ih));$cx=max(0,(int)round($x*$iw));$cy=max(0,(int)round($y*$ih));
            $cw=min($cw,$iw-$cx);$ch=min($ch,$ih-$cy);
            if($cw<1||$ch<1){$im->clear();throw new \RuntimeException('Selected clipping area is too small');}
            $im->cropImage($cw,$ch,$cx,$cy);
            $im->setImagePage(0,0,0,0);
            $im->setImageFormat('webp');$im->setImageCompressionQuality(90);$im->stripImage();$im->writeImage($dst);$im->clear();
            return;
        }
        if(!function_exists('exec'))throw new \RuntimeException('ImageMagick is required for clipping');
        $cmd='convert '.escapeshellarg($src).' -crop '.escapeshellarg(round($w*$pageW).'x'.round($h*$pageH).'+'.round($x*$pageW).'+'.round($y*$pageH)).' -quality 90 '.escapeshellarg($dst).' 2>/dev/null';
        exec($cmd,$o,$code);
        if($code!==0||!is_file($dst))throw new \RuntimeException('ImageMagick is required for clipping');
    }

    public static function bySlug(string $slug): ?array
    {
        return TenantData::guidRow(Store::one(
            'SELECT c.*,p.page_number,e.edition_date,e.title edition_title,e.slug edition_slug,pub.name publication_name FROM ep_clips c JOIN ep_pages p ON p.tenant_guid=c.tenant_guid AND p.page_guid=c.page_guid JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid WHERE c.tenant_guid=? AND c.clip_slug=? LIMIT 1',
            [TenantData::tenantBin(),$slug]
        ),['clip_guid','tenant_guid','platform_user_guid','page_guid']);
    }

    public static function canView(array $clip): bool
    {
        if(!empty($clip['expires_at'])&&strtotime((string)$clip['expires_at'])<time())return false;
        $visibility=strtoupper((string)($clip['visibility']??'PRIVATE'));
        if(in_array($visibility,['PUBLIC','UNLISTED'],true))return true; // UNLISTED kept for legacy clips only.
        $u=Auth::user();
        return $u&&!empty($clip['platform_user_guid'])&&hash_equals(strtolower((string)$clip['platform_user_guid']),strtolower((string)$u['user_guid']));
    }

    public static function canManage(array $clip): bool
    {
        $u=Auth::user();
        return $u&&!empty($clip['platform_user_guid'])&&hash_equals(strtolower((string)$clip['platform_user_guid']),strtolower((string)$u['user_guid']));
    }

    public static function rawPath(array $clip): string
    {
        return dirname(__DIR__,2).'/storage/'.ltrim((string)$clip['storage_path'],'/');
    }

    public static function brandedPath(array $clip): string
    {
        $raw=self::rawPath($clip);
        return preg_replace('/\.webp$/i','-branded-'.self::BRANDING_VERSION.'.webp',$raw)?:($raw.'-branded-'.self::BRANDING_VERSION.'.webp');
    }


    public static function sourcePageUrl(array $clip): string
    {
        return Http::origin().'/edition/'.rawurlencode((string)$clip['edition_slug']).'/page/'.max(1,(int)$clip['page_number']);
    }

    public static function previewPath(array $clip): string
    {
        $branded=self::brandedPath($clip);
        if(!is_file($branded)){
            try{self::ensureBranded($clip);}catch(\Throwable){}
        }
        return is_file($branded)?$branded:self::rawPath($clip);
    }

    private static function localLogoPath(): ?string
    {
        $logo=trim((string)TenantData::setting('brand.logo_path',''));
        if($logo==='')return null;
        $path=parse_url($logo,PHP_URL_PATH);
        if(!is_string($path)||$path==='')$path=$logo;
        if(str_contains($path,'..'))return null;
        $root=dirname(__DIR__,2);
        $candidates=[];
        if(str_starts_with($path,'/'))$candidates[]=$root.'/public_html'.$path;
        else{$candidates[]=$root.'/public_html/'.$path;$candidates[]=$root.'/'.$path;}
        foreach($candidates as$file)if(is_file($file))return $file;
        return null;
    }

    private static function ensureBranded(array $clip): string
    {
        $out=self::brandedPath($clip);
        if(is_file($out))return $out;
        $raw=self::rawPath($clip);
        if(!is_file($raw))throw new \RuntimeException('Clipping source image is unavailable');
        if(!class_exists('Imagick')){copy($raw,$out);return $out;}

        $image=new \Imagick($raw);$image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
        $iw=max(1,$image->getImageWidth());$ih=max(1,$image->getImageHeight());
        if($iw<640){$image->resizeImage(640,0,\Imagick::FILTER_LANCZOS,1);$iw=$image->getImageWidth();$ih=$image->getImageHeight();}
        if($iw>1800){$image->resizeImage(1800,0,\Imagick::FILTER_LANCZOS,1);$iw=$image->getImageWidth();$ih=$image->getImageHeight();}
        $pad=max(24,(int)round($iw*.035));$header=max(92,(int)round($iw*.11));$footer=max(112,(int)round($iw*.105));
        $cw=$iw+$pad*2;$ch=$header+$ih+$footer+$pad*2;
        $canvas=new \Imagick();$canvas->newImage($cw,$ch,new \ImagickPixel('#ffffff'),'webp');$canvas->setImageColorspace(\Imagick::COLORSPACE_SRGB);
        $canvas->compositeImage($image,\Imagick::COMPOSITE_OVER,$pad,$header+$pad);

        $draw=new \ImagickDraw();$draw->setFillColor(new \ImagickPixel('#171717'));$draw->setFontSize(max(20,(int)round($cw*.025)));$draw->setFontWeight(700);
        $brand=trim((string)TenantData::setting('brand.site_name','ePaper SaaS'))?:'ePaper SaaS';
        $logo=self::localLogoPath();$textX=$pad;
        if($logo){
            try{$lm=new \Imagick($logo);$lm->setImageBackgroundColor(new \ImagickPixel('transparent'));$targetH=max(34,(int)round($header*.43));$lm->thumbnailImage(0,$targetH,true);$canvas->compositeImage($lm,\Imagick::COMPOSITE_OVER,$pad,max(12,(int)(($header-$lm->getImageHeight())/2)));$textX=$pad+$lm->getImageWidth()+14;$lm->clear();}catch(\Throwable){}
        }
        $canvas->annotateImage($draw,$textX,max(34,(int)round($header*.44)),0,$brand);
        $draw->setFontWeight(400);$draw->setFontSize(max(14,(int)round($cw*.016)));$draw->setFillColor(new \ImagickPixel('#616161'));
        $canvas->annotateImage($draw,$textX,max(58,(int)round($header*.72)),0,(string)$clip['publication_name']);
        $right=(string)date('d M Y',strtotime((string)$clip['edition_date'])).'  ·  Page '.(int)$clip['page_number'];
        $draw->setTextAlignment(\Imagick::ALIGN_RIGHT);$canvas->annotateImage($draw,$cw-$pad,max(58,(int)round($header*.62)),0,$right);$draw->setTextAlignment(\Imagick::ALIGN_LEFT);

        $lineY=$header+$pad-10;$line=new \ImagickDraw();$line->setStrokeColor(new \ImagickPixel('#e5e7eb'));$line->setStrokeWidth(1);$line->line($pad,$lineY,$cw-$pad,$lineY);$canvas->drawImage($line);
        $footerY=$header+$pad+$ih+$footer;
        $line2=new \ImagickDraw();$line2->setStrokeColor(new \ImagickPixel('#e5e7eb'));$line2->setStrokeWidth(1);$line2->line($pad,$header+$pad+$ih+16,$cw-$pad,$header+$pad+$ih+16);$canvas->drawImage($line2);
        $sourceUrl=self::sourcePageUrl($clip);
        $draw->setFillColor(new \ImagickPixel('#5f6368'));$draw->setTextAlignment(\Imagick::ALIGN_LEFT);$draw->setFontWeight(700);$draw->setFontSize(max(11,(int)round($cw*.012)));$canvas->annotateImage($draw,$pad,$footerY-$footer+42,0,'SOURCE PAGE');
        $draw->setFontWeight(400);$draw->setFontSize(max(13,(int)round($cw*.014)));$sourceMeta=(string)$clip['publication_name'].' · '.date('d M Y',strtotime((string)$clip['edition_date'])).' · Page '.(int)$clip['page_number'];$canvas->annotateImage($draw,$pad,$footerY-$footer+68,0,$sourceMeta);
        $draw->setFontSize(max(11,(int)round($cw*.0115)));$canvas->annotateImage($draw,$pad,$footerY-$footer+91,0,$sourceUrl);
        try{
            $qrTarget=max(82,min(116,$footer-18));$qr=QrCode::drawImagick($sourceUrl,$qrTarget);$qx=$cw-$pad-$qr->getImageWidth();$qy=$header+$pad+$ih+24;$canvas->compositeImage($qr,\Imagick::COMPOSITE_OVER,$qx,$qy);$qr->clear();
        }catch(\Throwable $e){
            Audit::log('clip.source_qr.failed','clip',(string)$clip['clip_guid'],'FAILED',['message'=>$e->getMessage()]);
        }
        $draw->clear();$image->clear();
        $canvas->setImageFormat('webp');$canvas->setImageCompressionQuality(91);$canvas->stripImage();$canvas->writeImage($out);$canvas->clear();
        return $out;
    }

    public static function formats(): array
    {
        return [
            'original'=>['label'=>'Original image','ext'=>'webp','mime'=>'image/webp','w'=>0,'h'=>0],
            'pdf'=>['label'=>'PDF','ext'=>'pdf','mime'=>'application/pdf','w'=>0,'h'=>0],
            'instagram-portrait'=>['label'=>'Instagram Portrait','ext'=>'webp','mime'=>'image/webp','w'=>1080,'h'=>1350],
            'instagram-landscape'=>['label'=>'Instagram Landscape','ext'=>'webp','mime'=>'image/webp','w'=>1080,'h'=>566],
            'facebook'=>['label'=>'Facebook Image','ext'=>'webp','mime'=>'image/webp','w'=>1200,'h'=>630],
            'x'=>['label'=>'X / Twitter Image','ext'=>'webp','mime'=>'image/webp','w'=>1200,'h'=>675],
            'whatsapp'=>['label'=>'WhatsApp Image','ext'=>'webp','mime'=>'image/webp','w'=>1080,'h'=>1080],
            'webp'=>['label'=>'WebP','ext'=>'webp','mime'=>'image/webp','w'=>0,'h'=>0],
            'avif'=>['label'=>'AVIF','ext'=>'avif','mime'=>'image/avif','w'=>0,'h'=>0],
        ];
    }

    private static function formatPath(array $clip,string $format): string
    {
        $defs=self::formats();if(!isset($defs[$format]))throw new \InvalidArgumentException('Unsupported clipping format');
        if($format==='original'||$format==='webp')return self::brandedPath($clip);
        $base=preg_replace('/\.webp$/i','',self::rawPath($clip))?:self::rawPath($clip);
        return $base.'-'.self::BRANDING_VERSION.'-'.$format.'.'.$defs[$format]['ext'];
    }

    public static function formatStatus(array $clip): array
    {
        $defs=self::formats();$out=[];
        foreach($defs as$key=>$def){
            $supported=true;
            if($key==='avif'&&class_exists('Imagick'))$supported=(bool)\Imagick::queryFormats('AVIF');
            elseif($key==='avif')$supported=false;
            $path=self::formatPath($clip,$key);$out[$key]=['label'=>$def['label'],'ready'=>$supported&&is_file($path),'supported'=>$supported,'download'=>'/c/'.$clip['clip_slug'].'/download/'.$key];
        }
        return $out;
    }

    public static function prepareFormat(array $clip,string $format): array
    {
        if(!self::canManage($clip))throw new \RuntimeException('Clipping format preparation is restricted to the owner');
        $defs=self::formats();if(!isset($defs[$format]))throw new \InvalidArgumentException('Unsupported clipping format');
        $base=self::ensureBranded($clip);$target=self::formatPath($clip,$format);
        if(is_file($target))return['format'=>$format,'ready'=>true,'supported'=>true];
        if($format==='original'||$format==='webp')return['format'=>$format,'ready'=>is_file($base),'supported'=>true];
        if(!class_exists('Imagick'))throw new \RuntimeException('Imagick is required for clipping share formats');

        if($format==='avif'){
            if(!\Imagick::queryFormats('AVIF'))return['format'=>$format,'ready'=>false,'supported'=>false];
            $im=new \Imagick($base);$im->setImageFormat('avif');$im->setImageCompressionQuality(78);$im->stripImage();$im->writeImage($target);$im->clear();
            return['format'=>$format,'ready'=>is_file($target),'supported'=>true];
        }
        if($format==='pdf'){
            self::writePdf($base,$target);return['format'=>$format,'ready'=>is_file($target),'supported'=>true];
        }

        $def=$defs[$format];self::composeShareCanvas($base,$target,(int)$def['w'],(int)$def['h']);
        return['format'=>$format,'ready'=>is_file($target),'supported'=>true];
    }

    private static function composeShareCanvas(string $src,string $dst,int $w,int $h): void
    {
        $im=new \Imagick($src);$im->setImageColorspace(\Imagick::COLORSPACE_SRGB);$iw=$im->getImageWidth();$ih=$im->getImageHeight();
        $scale=min(($w-64)/max(1,$iw),($h-64)/max(1,$ih));$nw=max(1,(int)round($iw*$scale));$nh=max(1,(int)round($ih*$scale));$im->resizeImage($nw,$nh,\Imagick::FILTER_LANCZOS,1);
        $canvas=new \Imagick();$canvas->newImage($w,$h,new \ImagickPixel('#f5f6f8'),'webp');$x=(int)(($w-$nw)/2);$y=(int)(($h-$nh)/2);$canvas->compositeImage($im,\Imagick::COMPOSITE_OVER,$x,$y);$canvas->setImageFormat('webp');$canvas->setImageCompressionQuality(90);$canvas->stripImage();$canvas->writeImage($dst);$im->clear();$canvas->clear();
    }

    private static function writePdf(string $src,string $dst): void
    {
        $im=new \Imagick($src);$im->setImageBackgroundColor(new \ImagickPixel('#ffffff'));if($im->getImageAlphaChannel())$im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);$im->setImageFormat('jpeg');$im->setImageCompressionQuality(88);$jpg=$im->getImageBlob();$pxW=max(1,$im->getImageWidth());$pxH=max(1,$im->getImageHeight());$im->clear();
        $maxW=595.0;$maxH=842.0;$scale=min($maxW/$pxW,$maxH/$pxH,1.0);$pw=round($pxW*$scale,2);$ph=round($pxH*$scale,2);$x=round(($maxW-$pw)/2,2);$y=round(($maxH-$ph)/2,2);
        $content="q\n{$pw} 0 0 {$ph} {$x} {$y} cm\n/Im0 Do\nQ\n";
        $objects=[];$objects[]='<< /Type /Catalog /Pages 2 0 R >>';$objects[]='<< /Type /Pages /Kids [3 0 R] /Count 1 >>';$objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$maxW.' '.$maxH.'] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>';$objects[]='<< /Type /XObject /Subtype /Image /Width '.$pxW.' /Height '.$pxH.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpg).' >>' . "\nstream\n".$jpg."\nendstream";$objects[]='<< /Length '.strlen($content).' >>' . "\nstream\n".$content.'endstream';
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as$i=>$obj){$offsets[]=strlen($pdf);$n=$i+1;$pdf.=$n." 0 obj\n".$obj."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";$pdf.='trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";file_put_contents($dst,$pdf,LOCK_EX);
    }

    public static function download(array $clip,string $format): array
    {
        $defs=self::formats();if(!isset($defs[$format]))throw new \InvalidArgumentException('Unsupported clipping format');
        $path=self::formatPath($clip,$format);
        if(!is_file($path)&&self::canManage($clip))self::prepareFormat($clip,$format);
        if(!is_file($path))throw new \RuntimeException('Requested clipping format is not ready');
        return['path'=>$path,'mime'=>$defs[$format]['mime'],'filename'=>'clipping-'.$clip['clip_slug'].'-'.$format.'.'.$defs[$format]['ext']];
    }

    public static function track(array $clip,string $event,array $meta=[]): void
    {
        if(!in_array($event,['clip_share','clip_download'],true))return;
        $u=Auth::user();$meta=['clip_guid'=>$clip['clip_guid'],'clip_slug'=>$clip['clip_slug']]+$meta;
        Store::exec('INSERT INTO ep_analytics_events(analytics_event_guid,tenant_guid,platform_user_guid,edition_guid,page_guid,event_name,request_guid,metadata_json,occurred_at) VALUES(?,?,?,?,?,?,?,?,NOW(6))',[Uuid::toBinary(Uuid::v7()),TenantData::tenantBin(),$u?Uuid::toBinary($u['user_guid']):null,null,Uuid::toBinary($clip['page_guid']),$event,Uuid::toBinary(Http::requestGuid()),json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    }

    public static function stats(array $clip): array
    {
        $shares=0;$downloads=0;
        try{
            $rows=Store::all('SELECT event_name,COUNT(*) n FROM ep_analytics_events WHERE tenant_guid=? AND event_name IN ("clip_share","clip_download") AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,"$.clip_guid"))=? GROUP BY event_name',[TenantData::tenantBin(),$clip['clip_guid']]);
            foreach($rows as$r){if($r['event_name']==='clip_share')$shares=(int)$r['n'];elseif($r['event_name']==='clip_download')$downloads=(int)$r['n'];}
        }catch(\Throwable){}
        return['views'=>(int)($clip['view_count']??0),'shares'=>$shares,'downloads'=>$downloads];
    }
}
