<?php
return [
 'app'=>[
   'name'=>'ePaper SaaS','environment'=>'production','url'=>'https://epaper.digiti.in','timezone'=>'Asia/Kolkata','key'=>'CHANGE-ME-64-CHAR-RANDOM-KEY',
 ],
 'db'=>['host'=>'127.0.0.1','port'=>3306,'name'=>'digiti_epaper','user'=>'','pass'=>'','charset'=>'utf8mb4'],
 'redis'=>['enabled'=>true,'host'=>'127.0.0.1','port'=>6379,'username'=>null,'password'=>null,'database'=>0,'prefix'=>'digiti:prod:epaper:'],
 'platform'=>[
   'url'=>'https://platform.digiti.in','product_key'=>'EPAPER','application_key'=>'city_epaper','application_guid'=>'','oauth_client_id'=>'','service_token'=>'','default_tenant_guid'=>'','default_organisation_guid'=>'','domain_cache_ttl'=>600,'tenant_cache_ttl'=>900,'local_tenant_sync_ttl'=>600,
 ],
 'auth'=>[
   'admin_roles'=>['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],
   'infrastructure_roles'=>['OWNER','ADMIN'],
   'mobile'=>[
     'enabled'=>false,
     'provider'=>'twilio_verify',
     'default_country_code'=>'+91',
     'challenge_retention_days'=>14,
     'device_account_retention_days'=>30,
     'device_pepper'=>'', // optional dedicated 32+ char secret; falls back to app.key
     // Allowed: sna (silent carrier verification), whatsapp, sms. Start with SMS in India; enable the others only after provider/carrier approval.
     'channels'=>['sms'],
     'twilio'=>[
       'service_sid'=>'',
       // Prefer an API Key SID/Secret in production.
       'api_key_sid'=>'','api_key_secret'=>'',
       // Account SID/Auth Token are supported as a fallback.
       'account_sid'=>'','auth_token'=>'',
     ],
   ],
   'desktop'=>[
     'device_pepper'=>'', // optional dedicated 32+ char secret; falls back to app.key
     'challenge_retention_days'=>14,
     'reader_session_retention_days'=>30,
     'trusted_device_retention_days'=>90,
     'firebase'=>[
       'project_id'=>'',
       // Absolute path outside public_html. Never commit the service-account JSON.
       'service_account_file'=>'',
     ],
   ],
 ],
 'processing'=>['max_upload_mb'=>300,'tile_size'=>512,'ocr_languages'=>'auto','ocr_timeout_seconds'=>300],
 'observability'=>['slow_query_ms'=>200,'db_probe_warn_ms'=>150,'db_probe_bad_ms'=>500,'disk_warn_percent'=>15,'disk_bad_percent'=>8,'log_warn_bytes'=>524288000,'request_sample_rate'=>1.0,'request_sample_limit'=>499,'rum_sample_rate'=>0.10,'vitals_sample_limit'=>499],
 'media'=>['token_bucket_seconds'=>300,'x_accel_prefix'=>''],
 'queue'=>[
   'driver'=>'rabbitmq',
   'worker_max_jobs'=>250,
   'max_attempts'=>['edition_render'=>3,'page_assets'=>3,'page_ocr'=>3,'edition_finalize'=>5],
   'expected_workers'=>['outbox'=>1,'render'=>1,'assets'=>2,'ocr'=>1,'finalize'=>1],
   'rabbitmq'=>[
     'host'=>'127.0.0.1','port'=>5672,'username'=>'','password'=>'','vhost'=>'/',
     'connection_timeout'=>3.0,'read_write_timeout'=>70.0,'heartbeat'=>30,'keepalive'=>true,'confirm_timeout'=>5.0,
     'exchange'=>'epaper.jobs','dead_exchange'=>'epaper.dead','dead_queue'=>'epaper.dead','retry_exchange_prefix'=>'epaper.retry.',
     'queues'=>['render'=>'epaper.render','assets'=>'epaper.assets','ocr'=>'epaper.ocr','finalize'=>'epaper.finalize'],
   ],
 ],
];
