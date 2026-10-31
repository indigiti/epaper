<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$js=(string)file_get_contents($root.'/public_html/assets/reader-editorial-clips.js');
$css=(string)file_get_contents($root.'/public_html/assets/reader-editorial-clips.css');
$compact=(string)file_get_contents($root.'/public_html/assets/clip-ui-compact.css');
foreach(["button.dataset.tooltipBelow='1'","button.dataset.tooltipAlign='end'","window.addEventListener('online',retryFailed)"] as $token)
 if(!str_contains($js,$token))throw new RuntimeException('Marker UI contract missing '.$token);
foreach(['[data-tooltip-below="1"]::after{top:100%;bottom:auto','[data-tooltip-align="end"]::after{left:auto;right:-2px'] as $token)
 if(!str_contains($css,$token))throw new RuntimeException('Marker style contract missing '.$token);
foreach(['.clip-expiry-note strong{font-size:12px;','.clip-expiry-note span{font-size:12px;','.clip-create-submit{','font-size:14px;','max-height:calc(100dvh - 20px)'] as $token)
 if(!str_contains($compact,$token))throw new RuntimeException('Modal readability contract missing '.$token);
echo "Reader marker and modal style contracts: OK\n";
