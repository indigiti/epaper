<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Auth,Http,Store,Uuid,View};
use EpaperSaas\Services\TenantData;

/**
 * Read-only tenant-scoped security event viewer.
 * Uses the existing (tenant_guid, occurred_at) index and bounded keyset pagination.
 * Never exposes audit metadata (which can contain operational context).
 */
final class AdminAuditController
{
    public static function index(string $method): never
    {
        if ($method !== 'GET') {
            header('Allow: GET');
            http_response_code(405);
            View::layout('Method not allowed', '<section class="empty-state"><h1>Method not allowed</h1></section>', ['admin'=>true]);
        }
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if (!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true)) {
            http_response_code(403);
            View::layout('Access denied','<section class="empty-state"><h1>Audit access denied</h1></section>',['admin'=>true]);
        }

        $actions=[''=>'All actions','clip'=>'Clipping','edition'=>'Editions','ai'=>'AI & editorial',
            'system'=>'System','infrastructure'=>'Infrastructure','recovery'=>'Recovery'];
        $action=(string)($_GET['action']??'');
        if (!array_key_exists($action,$actions))$action='';
        $result=(string)($_GET['result']??'');
        if (!in_array($result,['SUCCESS','FAILED','DENIED'],true))$result='';
        $date=trim((string)($_GET['date']??''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
            || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))$date='';
        $requestRaw=trim((string)($_GET['request']??''));
        $request=Uuid::isValid($requestRaw)?strtolower($requestRaw):'';
        $badRequest=$requestRaw!==''&&$request==='';

        $where=['1=1'];
        $args=[TenantData::tenantBin()];
        if ($action!==''){$where[]='action LIKE ?';$args[]=$action.'%';}
        if ($result!==''){$where[]='result=?';$args[]=$result;}
        if ($date!==''){
            $where[]='occurred_at >= ? AND occurred_at < ?';
            $args[]=$date.' 00:00:00';
            $args[]=(new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        }
        if ($request!==''){$where[]='request_guid=?';$args[]=Uuid::toBinary($request);}
        if ($badRequest){$where[]='1=0';}

        $cursor=(string)($_GET['before']??'');
        if (strlen($cursor)>110)$cursor='';
        $decoded=$cursor!==''?base64_decode(strtr($cursor,'-_','+/'),true):false;
        if (is_string($decoded)&&preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6})\|([0-9a-f]{32})$/D',$decoded,$m)){
            $where[]='(occurred_at < ? OR (occurred_at = ? AND audit_event_guid < UNHEX(?)))';
            array_push($args,$m[1],$m[1],$m[2]);
        }else $cursor='';

        $sql='SELECT audit_event_guid,occurred_at,actor_type,action,resource_type,result,request_guid
              FROM ep_audit_events WHERE tenant_guid=? AND '.implode(' AND ',$where).'
              ORDER BY occurred_at DESC,audit_event_guid DESC LIMIT 51';
        $rows=Store::all($sql,$args);
        $hasMore=count($rows)>50;
        if ($hasMore)$rows=array_slice($rows,0,50);
        $e=static fn(mixed $value):string=>View::e($value);
        $params=array_filter(['action'=>$action,'result'=>$result,'date'=>$date,'request'=>$request],
            static fn($v)=>$v!=='');
        $h='<section class="admin-head audit-head"><div><span class="eyebrow">SECURITY & COMPLIANCE</span>'
          .'<h1>Audit Trail</h1><p class="muted">Tenant-scoped changes, permissions and processing actions. '
          .'Filter the event history without loading every record.</p></div>'
          .'<div class="page-head-actions"><a class="btn ghost" href="/admin/errors">Error Center</a>'
          .'<a class="btn ghost" href="/admin/system/health">System Health</a></div></section>'
          .'<section class="panel audit-panel"><form method="get" action="/admin/audit" class="audit-filters" aria-label="Filter audit events">'
          .'<label>Action group<select name="action">';
        foreach ($actions as $key=>$label)
            $h.='<option value="'.$e($key).'"'.($key===$action?' selected':'').'>'.$e($label).'</option>';
        $h.='</select></label><label>Result<select name="result">';
        foreach ([''=>'All results','SUCCESS'=>'Success','FAILED'=>'Failed','DENIED'=>'Denied'] as $key=>$label)
            $h.='<option value="'.$e($key).'"'.($key===$result?' selected':'').'>'.$e($label).'</option>';
        $h.='</select></label><label>Date<input type="date" name="date" value="'.$e($date).'"></label>'
          .'<label>Request ID<input name="request" maxlength="36" value="'.$e($requestRaw).'" '
          .'placeholder="Exact request UUID" autocomplete="off"></label>'
          .'<div class="audit-filter-actions"><button class="btn primary" type="submit">Apply filters</button>'
          .'<a class="btn ghost" href="/admin/audit">Clear</a></div></form>';
        if ($badRequest)$h.='<p role="alert" class="alert warning">Enter a valid request UUID to filter by request.</p>';
        $h.='<div class="audit-results-bar"><div><strong>'.count($rows).' events on this page</strong>'
          .'<span>Newest first · maximum 50 records per page</span></div>'
          .'<a class="text-btn" href="/admin/audit'.($params?'?'.$e(http_build_query($params,'','&',PHP_QUERY_RFC3986)):'').'">Latest events</a></div>'
          .'<div class="table-wrap audit-table-wrap"><table class="audit-table"><thead><tr>'
          .'<th scope="col">Time</th><th scope="col">Action</th><th scope="col">Resource</th>'
          .'<th scope="col">Result</th><th scope="col">Request</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $when=(string)$row['occurred_at'];
            $event=(string)$row['action'];
            $status=(string)$row['result'];
            $requestGuid=$row['request_guid']===null?'':Uuid::fromBinary($row['request_guid']);
            $readable=ucwords(str_replace(['.','_'],' ',$event));
            $h.='<tr><td data-label="Time"><time title="'.$e($when).'">'.$e(substr($when,0,19)).'</time></td>'
              .'<td data-label="Action"><strong>'.$e($readable).'</strong><small class="audit-event-code">'.$e($event).'</small></td>'
              .'<td data-label="Resource">'.$e($row['resource_type']).'</td>'
              .'<td data-label="Result"><span class="audit-result audit-result-'.($status==='SUCCESS'?'success':($status==='FAILED'?'failed':'other')).'">'.$e($status).'</span></td>'
              .'<td data-label="Request">'.($requestGuid!==''?'<code title="'.$e($requestGuid).'">'.$e(substr($requestGuid,0,8)).'…</code><details class="audit-request-detail"><summary>Full ID</summary><code>'.$e($requestGuid).'</code></details>':'—').'</td></tr>';
        }
        if (!$rows)$h.='<tr><td colspan="5" class="audit-empty">No audit events match these filters.</td></tr>';
        $h.='</tbody></table></div>';
        if ($hasMore&&$rows) {
            $last=end($rows);
            $token=rtrim(strtr(base64_encode((string)$last['occurred_at'].'|'.bin2hex($last['audit_event_guid'])),'+/','-_'),'=');
            $next=http_build_query($params+['before'=>$token],'','&',PHP_QUERY_RFC3986);
            $h.='<nav class="audit-pagination" aria-label="Audit Trail pagination">'
              .'<span>Showing up to 50 matching events</span><a class="btn ghost" href="/admin/audit?'.$e($next).'">Older events →</a></nav>';
        }
        $h.='</section>';
        View::layout('Audit Trail',$h,['admin'=>true]);
    }
}
