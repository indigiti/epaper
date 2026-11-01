<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/**
 * Per-tenant public UI visibility, not a capability or access policy.
 * Keep controls in the DOM for the existing reader event handlers while
 * using display:none on explicitly hidden visual controls.
 */
final class ReaderUiControls
{
    /** @return array<string,array{0:string,1:string,2:string,3:array}> */
    public static function fields(): array
    {
        $onOff=['on','off'];
        return [
            'ui.top_menu'               => ['Top menu · Main navigation links','select','on',$onOff],
            'ui.focus_links'            => ['Top menu · Quick links / focus bar','select','on',$onOff],
            'ui.reader_toolbar'         => ['Reader tools · Entire side toolbar','select','on',$onOff],
            'ui.zoom_in'                => ['Reader tools · Zoom in (+)','select','on',$onOff],
            'ui.zoom_out'               => ['Reader tools · Zoom out (−)','select','on',$onOff],
            'ui.zoom_value'             => ['Reader tools · Zoom percentage','select','on',$onOff],
            'ui.scroll_zoom'            => ['Reader tools · Mouse-scroll zoom','select','on',$onOff],
            'ui.fit_page'               => ['Reader tools · Fit page','select','on',$onOff],
            'ui.fit_width'              => ['Reader tools · Fit width','select','on',$onOff],
            'ui.reset_view'             => ['Reader tools · Reset view','select','on',$onOff],
            'ui.fullscreen'             => ['Reader tools · Focus / fullscreen','select','on',$onOff],
            'ui.smart_article'          => ['Reader tools · Smart Article','select','on',$onOff],
            'ui.page_text'              => ['Reader tools · Read page text (OCR)','select','on',$onOff],
            'ui.clipping'               => ['Reader tools · Clipping button','select','on',$onOff],
            'ui.header_pages'           => ['Reader header · Page thumbnails','select','on',$onOff],
            'ui.header_search'          => ['Reader header · Search','select','on',$onOff],
            'ui.header_quality'         => ['Reader header · Image quality','select','on',$onOff],
            'ui.header_bookmark'        => ['Reader header · Bookmark','select','on',$onOff],
            'ui.header_offline'         => ['Reader header · Save offline','select','on',$onOff],
            'ui.header_share'           => ['Reader header · Share page','select','on',$onOff],
            'ui.header_more'            => ['Reader header · More actions','select','on',$onOff],
            'ui.header_edition_switcher'=> ['Reader header · Edition date switcher','select','on',$onOff],
            'ui.archive_calendar'       => ['Archive · Month and date selector (entire panel)','select','on',$onOff],
            'ui.archive_month'          => ['Archive · Month arrows and heading','select','on',$onOff],
            'ui.archive_dates'          => ['Archive · Horizontal day/date rail','select','on',$onOff],
            'ui.archive_quick_dates'    => ['Archive · Today / yesterday / recent shortcuts','select','on',$onOff],
        ];
    }

    /** Explicit values only; missing and unknown values preserve existing UI. */
    public static function bodyClasses(array $values): string
    {
        $classes='';
        foreach(self::fields() as $key=>$_){
            if(($values[$key]??'on')!=='off')continue;
            $classes.=' ui-hide-'.str_replace('_','-',substr($key,3));
        }
        return $classes;
    }
}
