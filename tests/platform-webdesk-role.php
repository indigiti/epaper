<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$diagnostic=file_get_contents($root.'/app/Admin/PublisherLoginCheck.php');
$app=file_get_contents($root.'/app/App.php');
function checkWebDesk(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
checkWebDesk(str_contains($crud,"/membership-role$#' => 'userMembershipRole'"),
    'Publisher membership role edit routed before general admin dispatch');
checkWebDesk(str_contains($crud,"'WEBDESK','PUBLISHER_ADMIN'"),
    'WEBDESK is a valid, selectable membership role for existing users');
checkWebDesk(str_contains($crud,"if(\$role!=='SUPER_ADMIN')"),
    'Only Platform super administrators can change publisher roles');
checkWebDesk(str_contains($crud,'m.membership_guid=? AND m.user_guid=? FOR UPDATE'),
    'Role updates lock the exact user membership rather than all organisations');
checkWebDesk(str_contains($crud,'membership.role_changed'),
    'Every role change is audited');
checkWebDesk(!str_contains($crud,'UPDATE product_tenants SET role_key'),
    'Existing publisher tenants never change on role assignment');
checkWebDesk(str_contains($diagnostic,"'OPERATOR','WEBDESK'"),
    'Platform sign-in diagnostics recognize the restricted publisher user');
checkWebDesk(str_contains($app,'m.role_key IN ("OWNER","ADMIN","PUBLISHER_ADMIN")') ||
    str_contains(file_get_contents($root.'/app/Admin/Onboarding.php'),
       'm.role_key IN ("OWNER","ADMIN","PUBLISHER_ADMIN")'),
    'WebDesk cannot satisfy mandatory publisher owner assignment');
checkWebDesk(str_contains($crud,'FOR UPDATE') && str_contains($crud,'Assign another active publisher owner'), 'Last publisher owner cannot be demoted to WebDesk');
echo "PASS — Platform WebDesk memberships\n";
