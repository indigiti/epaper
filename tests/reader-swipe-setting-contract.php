<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$catalog=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');
$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$reader=(string)file_get_contents($root.'/public_html/assets/reader.js');
$swirl=(string)file_get_contents($root.'/public_html/assets/reader-swipe-feedback.js');
$setting="\EpaperSaas\Services\SettingsCatalog";
require_once $root.'/app/Services/SettingsCatalog.php';
$field=$setting::group('reader')['fields']['reader.swipe_animation']??null;
if($field!==['Animated swipe effect (Page mode)','select','on',['on','off']])
    throw new RuntimeException('On/Off swipe switch missing from Publisher Reader settings');
if(!in_array('reader.swipe_animation',$setting::keys(),true))
    throw new RuntimeException('Swirl switch missing from validated setting keys');
if(!str_contains($app,"TenantData::effectiveSetting('reader.swipe_animation','on',\$ed['publication_guid'],null)"))
    throw new RuntimeException('Reader must honor publisher/tenant setting without user override');
if(!str_contains($app,"'reader_swipe_animation_enabled'=>\$swipeAnimationEnabled"))
    throw new RuntimeException('Reader setting must reach layout');
if(!str_contains($app,"data-swipe-animation="))
    throw new RuntimeException('Reader HTML missing toggle indication');
if(!str_contains($app,'Animated swipe effect:'))
    throw new RuntimeException('Publisher Reader screen missing switch explanation');
if(!str_contains($view,"\$readerSwipeAnimationEnabled=\$reader&&(bool)(\$opts['reader_swipe_animation_enabled']??true)"))
    throw new RuntimeException('Reader effect setting not validated by page context');
if(!str_contains($view,"\$readerSwipeAnimationEnabled?'<script src="))
    throw new RuntimeException('Swipe animation script must be absent when switched OFF');
if(!str_contains($view,"Build::asset('/assets/reader-swipe-feedback.js')") ||
   !str_contains($view,"Build::asset('/assets/reader.js')"))
    throw new RuntimeException('Existing Reader asset contract changed');
if(!str_contains($reader,'const swipeFx=window.EpdiSwipeFeedback;') ||
   !str_contains($reader,'swipeFx?.show(') ||
   !str_contains($reader,'swipeFx?.hide()') ||
   !str_contains($reader,'swipeFx?.commit(') ||
   !str_contains($reader,"void gotoPage(target,{method:'swipe'})"))
    throw new RuntimeException('Native swipe-to-page navigation must work without animation script');
if(str_contains($swirl,'touchstart')||str_contains($swirl,'touchend')||str_contains($swirl,'location.href'))
    throw new RuntimeException('Swirl must never attach competing gesture handlers or cause reloads');
$tenantData=(string)file_get_contents($root.'/app/Services/TenantData.php');
if(!str_contains($tenantData,'self::invalidateSettingsScope($scopeType, $scopeGuid)') ||
   !str_contains($tenantData,"RuntimeCache::bumpGeneration(RequestContext::tenantGuid(), 'settings')"))
    throw new RuntimeException('Setting save must invalidate tenant cached settings');
echo "Publisher Reader animated swipe toggle with preserved native gestures: OK\n";
