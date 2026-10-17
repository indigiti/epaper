<?php
namespace EpaperSaas\Services;

/**
 * Minimal QR Code Version 5 / EC-L encoder used for short same-origin URLs.
 * Kept dependency-free so clipping branding does not require an external API.
 */
final class QrCode
{
    public static function matrixV5L(string $text): array
    {
        $bytes=array_values(unpack('C*',$text)?:[]);
        if(count($bytes)>106)throw new \InvalidArgumentException('QR URL is too long');
        $bits=[];
        $append=static function(int $value,int $len)use(&$bits):void{for($i=$len-1;$i>=0;$i--)$bits[]=($value>>$i)&1;};
        $append(4,4); // byte mode
        $append(count($bytes),8);
        foreach($bytes as$b)$append($b,8);
        $capacity=108*8;
        for($i=0;$i<min(4,$capacity-count($bits));$i++)$bits[]=0;
        while(count($bits)%8)$bits[]=0;
        $data=[];
        for($i=0;$i<count($bits);$i+=8){$b=0;for($j=0;$j<8;$j++)$b=($b<<1)|($bits[$i+$j]??0);$data[]=$b;}
        for($pad=0;count($data)<108;$pad++)$data[]=$pad%2?0x11:0xEC;

        $exp=array_fill(0,512,0);$log=array_fill(0,256,0);$gx=1;
        for($i=0;$i<255;$i++){$exp[$i]=$gx;$log[$gx]=$i;$gx<<=1;if($gx&0x100)$gx^=0x11d;}
        for($i=255;$i<512;$i++)$exp[$i]=$exp[$i-255];
        $mul=static fn(int $a,int $b):int=>($a&&$b)?$exp[$log[$a]+$log[$b]]:0;

        $degree=26;$div=array_fill(0,$degree,0);$div[$degree-1]=1;$rsRoot=1;
        for($i=0;$i<$degree;$i++){
            $next=$div;
            for($j=0;$j<$degree;$j++){
                $next[$j]=$mul($div[$j],$rsRoot);
                if($j+1<$degree)$next[$j]^=$div[$j+1];
            }
            $div=$next;$rsRoot=$mul($rsRoot,2);
        }
        $rem=array_fill(0,$degree,0);
        foreach($data as$b){$factor=$b^$rem[0];array_shift($rem);$rem[]=0;for($j=0;$j<$degree;$j++)$rem[$j]^=$mul($div[$j],$factor);}
        $code=array_merge($data,$rem);

        $size=37;$m=array_fill(0,$size,array_fill(0,$size,false));$fn=array_fill(0,$size,array_fill(0,$size,false));
        $setF=static function(int $x,int $y,$d)use(&$m,&$fn,$size):void{if($x>=0&&$y>=0&&$x<$size&&$y<$size){$m[$y][$x]=(bool)$d;$fn[$y][$x]=true;}};
        for($i=0;$i<$size;$i++){$setF(6,$i,$i%2===0);$setF($i,6,$i%2===0);}
        $finder=static function(int $cx,int $cy)use($setF):void{for($dy=-4;$dy<=4;$dy++)for($dx=-4;$dx<=4;$dx++){$dist=max(abs($dx),abs($dy));$setF($cx+$dx,$cy+$dy,$dist!==2&&$dist!==4);}};
        $finder(3,3);$finder($size-4,3);$finder(3,$size-4);
        $align=static function(int $cx,int $cy)use($setF):void{for($dy=-2;$dy<=2;$dy++)for($dx=-2;$dx<=2;$dx++)$setF($cx+$dx,$cy+$dy,max(abs($dx),abs($dy))!==1);};
        $align(30,30);
        $formatBits=static function():int{$dataBits=8;$r=$dataBits;for($i=0;$i<10;$i++)$r=($r<<1)^(((($r>>9)&1))*0x537);return (($dataBits<<10)|$r)^0x5412;};
        $drawFormat=static function()use($formatBits,$setF,$size):void{$b=$formatBits();for($i=0;$i<=5;$i++)$setF(8,$i,($b>>$i)&1);$setF(8,7,($b>>6)&1);$setF(8,8,($b>>7)&1);$setF(7,8,($b>>8)&1);for($i=9;$i<15;$i++)$setF(14-$i,8,($b>>$i)&1);for($i=0;$i<8;$i++)$setF($size-1-$i,8,($b>>$i)&1);for($i=8;$i<15;$i++)$setF(8,$size-15+$i,($b>>$i)&1);$setF(8,$size-8,true);};
        $drawFormat();
        $all=[];foreach($code as$b)for($i=7;$i>=0;$i--)$all[]=($b>>$i)&1;$k=0;
        for($right=$size-1;$right>=1;$right-=2){if($right===6)$right--;for($vert=0;$vert<$size;$vert++){$upward=(($right+1)&2)===0;$y=$upward?$size-1-$vert:$vert;for($j=0;$j<2;$j++){$x=$right-$j;if($fn[$y][$x])continue;$bit=$k<count($all)?$all[$k]:0;$k++;if((($x+$y)&1)===0)$bit^=1;$m[$y][$x]=(bool)$bit;}}}
        $drawFormat();
        return $m;
    }

    public static function drawImagick(string $text,int $targetPx): \Imagick
    {
        if(!class_exists('Imagick'))throw new \RuntimeException('Imagick unavailable');
        $m=self::matrixV5L($text);$quiet=4;$modules=count($m)+$quiet*2;$module=max(1,(int)floor($targetPx/$modules));$px=$modules*$module;
        $qr=new \Imagick();$qr->newImage($px,$px,new \ImagickPixel('#ffffff'),'png');
        $draw=new \ImagickDraw();$draw->setFillColor(new \ImagickPixel('#111111'));
        foreach($m as$y=>$row)foreach($row as$x=>$on)if($on){$x1=($x+$quiet)*$module;$y1=($y+$quiet)*$module;$draw->rectangle($x1,$y1,$x1+$module-1,$y1+$module-1);}
        $qr->drawImage($draw);$draw->clear();$qr->setImageFormat('png');return $qr;
    }
}
