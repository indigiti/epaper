<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$fail=static function(string $message):never{throw new RuntimeException($message);};
$view=(string)file_get_contents($root.'/app/Core/View.php');
$app=(string)file_get_contents($root.'/app/App.php');
$css=(string)file_get_contents($root.'/public_html/assets/orbit-ambient.css');
$settings=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');
foreach(['appearance.frontend_orbit','appearance.reader_orbit'] as $key){
  if(!str_contains($settings,$key))$fail('Missing tenant setting '.$key);
  if(!str_contains($view,$key))$fail('Missing tenant gating '.$key);
}
foreach(['epdi-orbit-enabled','epdi-orbit-ambient','epdi-orbit-cloud--pink','epdi-orbit-cloud--peach','epdi-orbit-cloud--teal','epdi-orbit-cloud--lavender','assets/orbit-ambient.css'] as $term){
  if(!str_contains($view,$term))$fail('Missing rendered layer or asset '.$term);
}
foreach(['epdi-cloud-orbit-cw','epdi-cloud-orbit-ccw','prefers-reduced-motion:reduce','@media print','pointer-events:none','body.reader-page.epdi-orbit-enabled:not(.dark) .reader-stage'] as $term){
  if(!str_contains($css,$term))$fail('Missing motion/safety rule '.$term);
}
if(!str_contains($css,'body.epdi-orbit-enabled.dark .epdi-orbit-ambient{display:none}'))$fail('Dark mode safeguard missing');
if(!str_contains($app,"TenantData::setting('appearance.frontend_orbit','off')"))$fail('Existing Vanta effect precedence not preserved');
if(str_contains($css,'url(')||str_contains($css,'@import'))$fail('No external assets are permitted');
if(!str_contains($view,"if($orbitEnabled)echo"))$fail('Missing SSR opt-in layer');
echo "PASS Opt-in orbital background contracts\n";
