<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;
use EpaperSaas\Contracts\PageArticleReader;
use EpaperSaas\Core\Uuid;
/** Reader OCR hit test: explicit request-to-service contract; no web globals. */
final class ReaderArticleAtController
{
    public function __construct(private readonly PageArticleReader $articles) {}
    /** @return array{0:int,1:array<string,mixed>} */
    public function lookup(string $pageGuid,float $x,float $y): array
    {
        $page=$this->articles->dimensions($pageGuid);
        if(!$page)return [404,['ok'=>false,'code'=>'PAGE_NOT_FOUND']];
        $px=(int)round(max(0,min(1,$x))*(int)$page['width']);
        $py=(int)round(max(0,min(1,$y))*(int)$page['height']);
        $blocks=$this->articles->coveringBlocks($pageGuid,$px,$py);
        $text=trim(implode("\n",array_column($blocks,'text_content')));
        return [200,[
            'ok'=>(bool)$blocks,'page_guid'=>$pageGuid,'text'=>$text,
            'blocks'=>array_map(static fn(array $b):array=>[
                'block_guid'=>Uuid::fromBinary($b['block_guid']),
                'text'=>$b['text_content'],
                'bbox'=>[(int)$b['x1'],(int)$b['y1'],(int)$b['x2'],(int)$b['y2']]
            ],$blocks)
        ]];
    }
}
