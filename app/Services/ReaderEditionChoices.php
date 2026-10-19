<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/**
 * Keep the current edition in the Reader's date choices even if the cached
 * history is empty, stale, or contains fewer than two matching published dates.
 * Never infer or invent historical editions.
 */
final class ReaderEditionChoices
{
    public static function withCurrent(array $rows,array $current,bool $ascending=false): array
    {
        $choices=[];
        foreach($rows as $row){
            if(!is_array($row))continue;
            $date=(string)($row['edition_date']??'');
            $slug=trim((string)($row['slug']??''));
            if(!self::validDate($date)||$slug==='')continue;
            if(!isset($choices[$date]))$choices[$date]=$row;
        }
        $date=(string)($current['edition_date']??'');
        $slug=trim((string)($current['slug']??''));
        if(self::validDate($date)&&$slug!==''){
            $choices[$date]=[
                'edition_date'=>$date,
                'slug'=>$slug,
                'page_count'=>(int)($current['page_count']??0),
            ];
        }
        if($ascending)ksort($choices,SORT_STRING);
        else krsort($choices,SORT_STRING);
        return array_values($choices);
    }

    private static function validDate(string $date): bool
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))return false;
        [$year,$month,$day]=array_map('intval',explode('-',$date));
        return checkdate($month,$day,$year);
    }
}
