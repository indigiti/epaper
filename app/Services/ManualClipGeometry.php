<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/** Validates the exact normalized rectangular crop accepted from editorial forms. */
final class ManualClipGeometry
{
    /** @return array{x:float,y:float,w:float,h:float} */
    public static function parse(array $input): array
    {
        $box=[];
        foreach(['x','y','w','h'] as $key){
            $value=$input[$key]??null;
            if(!is_string($value)&&!is_int($value)&&!is_float($value))
                throw new \InvalidArgumentException('A clipping rectangle is required');
            if(!preg_match('/^(?:0(?:\.\d+)?|1(?:\.0+)?)$/D',(string)$value))
                throw new \InvalidArgumentException('Clipping coordinates must be between 0 and 1');
            $number=(float)$value;
            if(!is_finite($number)||$number<0||$number>1)
                throw new \InvalidArgumentException('Invalid clipping coordinates');
            $box[$key]=$number;
        }
        if($box['w']<.02||$box['h']<.02||
           $box['x']+$box['w']>1.0000001||$box['y']+$box['h']>1.0000001)
            throw new \InvalidArgumentException('Select at least 2% of the page, within its boundaries');
        return $box;
    }
}
