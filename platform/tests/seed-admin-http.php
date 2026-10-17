<?php
declare(strict_types=1);
/** CI-only administrator, never executable against non-disposable data. */
if(getenv('EPDI_BILLING_INTEGRATION')!=='disposable-ci') {
    fwrite(STDERR,"Refusing non-CI administrator fixture.\n");exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';
\Digiti\Core\Store::exec(
    'INSERT INTO platform_admins(admin_guid,email,display_name,password_hash,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',
    [\Digiti\Core\Uuid::toBinary(\Digiti\Core\Uuid::v7()),'ci-admin@example.invalid','CI Admin',password_hash('ci-only-fixture-password-2026',PASSWORD_DEFAULT)]
);
echo "Disposable administrator created\n";
