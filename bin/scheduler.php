<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use EpaperSaas\Services\{DesktopAuth,DeviceAuth,MobileAuth,PublishingScheduler,ReaderSession,TrustedAppAuth};

$result=PublishingScheduler::runOnce('cron');
if(empty($result['ok'])){echo (($result['message']??'scheduler already running'))."\n";exit(0);}
$mobileMaintenance=['expired'=>0,'deleted'=>0];
try{$mobileMaintenance=MobileAuth::pruneChallenges((int)\EpaperSaas\Core\Config::get('auth.mobile.challenge_retention_days',14));}catch(\Throwable){}
$deviceMaintenance=['expired'=>0,'deleted'=>0];
try{$deviceMaintenance=DeviceAuth::prune((int)\EpaperSaas\Core\Config::get('auth.mobile.device_account_retention_days',30));}catch(\Throwable){}
$desktopMaintenance=['expired'=>0,'deleted'=>0];
try{$desktopMaintenance=DesktopAuth::prune((int)\EpaperSaas\Core\Config::get('auth.desktop.challenge_retention_days',14));}catch(\Throwable){}
$sessionMaintenance=['expired'=>0,'deleted'=>0];
try{$sessionMaintenance=ReaderSession::prune((int)\EpaperSaas\Core\Config::get('auth.desktop.reader_session_retention_days',30));}catch(\Throwable){}
$trustedPruned=0;
try{$trustedPruned=TrustedAppAuth::prune((int)\EpaperSaas\Core\Config::get('auth.desktop.trusted_device_retention_days',90));}catch(\Throwable){}
echo 'scheduler ok · published '.(int)$result['published_now'].' · recovered '.(int)$result['stale_recovered'].' · auth expired '.(int)$mobileMaintenance['expired'].' · auth pruned '.(int)$mobileMaintenance['deleted'].' · device expired '.(int)$deviceMaintenance['expired'].' · device pruned '.(int)$deviceMaintenance['deleted'].' · desktop expired '.(int)$desktopMaintenance['expired'].' · desktop pruned '.(int)$desktopMaintenance['deleted'].' · sessions expired '.(int)$sessionMaintenance['expired'].' · sessions pruned '.(int)$sessionMaintenance['deleted'].' · trusted pruned '.(int)$trustedPruned."\n";
