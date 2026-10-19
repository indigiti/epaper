<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$controller=(string)file_get_contents($root.'/app/Controllers/AdminAuditController.php');
$app=(string)file_get_contents($root.'/app/App.php');
$schema=(string)file_get_contents($root.'/database/schema.sql');
$catalog=(string)file_get_contents($root.'/app/Services/AdminFeatureCatalog.php');
$typography=(string)file_get_contents($root.'/public_html/assets/admin-typography.css');
$sidebar=(string)file_get_contents($root.'/public_html/assets/admin-sidebar.js');
foreach ([
 'Auth::currentMembership()',"['OWNER','ADMIN','PUBLISHER_ADMIN']",
 'tenant_guid=?','ORDER BY occurred_at DESC,audit_event_guid DESC LIMIT 51',
 'UNHEX(?)','request_guid=?','Uuid::toBinary($request)',
 'checkdate(', 'array_slice($rows,0,50)','View::e($value)',
 'Filter audit events','audit-request-detail','audit-results-bar',
] as $part) {
 if (!str_contains($controller,$part))throw new RuntimeException('Audit protection or UX missing: '.$part);
}
if (str_contains($controller,'SELECT *')||str_contains($controller,'metadata_json'))
 throw new RuntimeException('Audit viewer must not fetch wide/unreviewed columns');
if (!str_contains($schema,'KEY ix_audit_tenant(tenant_guid,occurred_at)'))
 throw new RuntimeException('Audit pagination index missing');
if (!str_contains($app,'AdminAuditController::index($method)') ||
 str_contains($app,'private function adminAudit(): never'))
 throw new RuntimeException('Legacy unfiltered audit handler remains active');
if (!str_contains($catalog,"['Audit Trail','/admin/audit'") ||
 !str_contains($catalog,"\$groups['ADVANCED']"))
 throw new RuntimeException('Audit route or advanced tools no longer discoverable');
foreach (['.audit-filters','.audit-table','.audit-pagination','@media(max-width:620px)'] as $selector)
 if (!str_contains($typography,$selector))throw new RuntimeException('Responsive audit style missing '.$selector);
if (!str_contains($sidebar,'adminSearchTerms')||!str_contains($sidebar,'sidebar-groups:v2'))
 throw new RuntimeException('Reorganized sidebar search or preference migration missing');
echo "Role-scoped, paginated audit and advanced navigation contracts: OK\n";
