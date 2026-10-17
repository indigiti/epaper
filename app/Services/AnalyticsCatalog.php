<?php
namespace EpaperSaas\Services;
final class AnalyticsCatalog
{
    public const EVENTS=['page_view','page_dwell','edition_opened','reader_zoom','reader_fit','reader_scroll_zoom','reader_quality_mode','reader_clip_started','reader_clip_saved','reader_search','reader_performance','clip_share','clip_download','article_opened','article_selected','bookmark_created','bookmark_removed','offline_download','share','tts_started'];
    public static function valid(string $event): bool{return in_array($event,self::EVENTS,true);}
}
