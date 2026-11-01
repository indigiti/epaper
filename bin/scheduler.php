<?php
declare(strict_types=1);

$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{Store,Uuid};

$lock='digiti_platform_scheduler';
$acquired=(int)Store::value('SELECT GET_LOCK(?,0)',[$lock])===1;
if(!$acquired){echo "Scheduler already running.\n";exit(0);}
try{
    // Only enqueue implemented job handlers. Never silently mark unimplemented
    // payment reconciliation or notification delivery complete.
    $existing=Store::value('SELECT COUNT(*) FROM jobs
        WHERE job_type="domain.revalidate" AND status IN ("QUEUED","RUNNING")');
    if((int)$existing===0){
        Store::exec('INSERT INTO jobs(job_guid,queue_name,job_type,payload,status,available_at,created_at)
            VALUES(?,"domains","domain.revalidate",?,"QUEUED",NOW(6),NOW(6))',[
            Uuid::toBinary(Uuid::v7()),json_encode(['scope'=>'all'])
        ]);
    }
    // Auth code removal is local maintenance, not a phantom queue job.
    Store::exec('DELETE FROM auth_codes WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
    // A crashed worker must not retain a permanent RUNNING job.
    Store::exec('UPDATE jobs SET status="QUEUED",locked_at=NULL,locked_by=NULL,
        available_at=NOW(6),last_error="Worker lock expired; queued for retry"
        WHERE status="RUNNING" AND locked_at<DATE_SUB(NOW(),INTERVAL 30 MINUTE) AND attempts<5');
    Store::exec('UPDATE jobs SET status="FAILED",locked_at=NULL,locked_by=NULL,
        last_error="Worker lock expired after maximum attempts"
        WHERE status="RUNNING" AND locked_at<DATE_SUB(NOW(),INTERVAL 30 MINUTE) AND attempts>=5');
    Store::exec('UPDATE outbox_events SET status="FAILED",
        last_error="Delivery worker did not complete; manual review required"
        WHERE status="PROCESSING" AND available_at<DATE_SUB(NOW(),INTERVAL 30 MINUTE)');
    file_put_contents($root.'/storage/scheduler-heartbeat.json',
        json_encode(['at'=>gmdate('c')]),LOCK_EX);
    echo "Scheduler maintenance complete.\n";
}finally{
    Store::value('SELECT RELEASE_LOCK(?)',[$lock]);
}
