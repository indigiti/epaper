<?php
declare(strict_types=1);

/** Real PHP CLI session roundtrip with a private disposable session directory. */
require dirname(__DIR__).'/app/Core/Auth.php';
require dirname(__DIR__).'/app/Core/Http.php';
use Digiti\Core\Auth;
use Digiti\Core\Http;

$path=sys_get_temp_dir().'/epdi-session-regression-'.bin2hex(random_bytes(8));
if (!mkdir($path,0700)) throw new RuntimeException('Could not create isolated session directory');
$errors=[];
try {
    ini_set('session.save_path',$path);
    ini_set('session.use_strict_mode','1');
    $_SERVER['HTTPS']='on';
    $_SERVER['REQUEST_URI']='/login';

    $token=Http::csrf();
    if (session_name()!=='digiti_platform') $errors[]='wrong session namespace';
    if (session_status()!==PHP_SESSION_ACTIVE) $errors[]='session not active';
    if (strlen($token)!==48) $errors[]='missing expected 48-character CSRF token';
    $id=session_id();
    session_write_close();
    $_SESSION=[];
    session_id($id);

    Auth::start();
    if (Http::csrf()!==$token) $errors[]='CSRF did not persist in the same PHP session';
    $_POST['_csrf']=$token;
    Http::verifyCsrf(); // Must not exit with HTTP 419.
    session_write_close();
} finally {
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    foreach (glob($path.'/*')?:[] as $file) if (is_file($file)) unlink($file);
    rmdir($path);
}
if ($errors) {
    fwrite(STDERR,'FAIL '.implode(', ',$errors)."\n");
    exit(1);
}
echo "PASS Platform session namespace, on-disk persistence and CSRF GET-to-POST validation\n";
