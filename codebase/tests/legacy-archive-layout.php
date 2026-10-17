<?php
declare(strict_types=1);
/**
 * Verify historical document relocation without modifying the original Git
 * blobs, production checkout, database, or media.
 */
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
$idx = $root.'/docs/releases/LEGACY-ROOT-INDEX.md';
$failures = [];
$assert = static function (bool $ok, string $why) use (&$failures): void {
    if (!$ok) $failures[] = $why;
};
if (!is_file($idx)) {
    fwrite(STDERR, "FAIL archive: missing index\n");
    exit(1);
}
$lines = file($idx, FILE_IGNORE_NEW_LINES);
$seenFrom = []; $seenTo = [];
$actionCount = ['deduplicated'=>0, 'archived'=>0, 'archived-backup'=>0];
$matched = 0;
foreach ($lines as $line) {
    if (!str_starts_with($line, '| '.chr(96))) continue;
    if (!preg_match('~^\| \x60([^\x60]+)\x60 \| \[\x60([^\x60]+)\x60\]\(\.\./\.\./([^)]*)\) \| (deduplicated|archived|archived-backup) \| \x60([0-9a-f]{40})\x60 \|$~D', $line, $m)) {
        $failures[] = 'unparseable archive index row';
        continue;
    }
    [, $old, $label, $new, $action, $gitSha] = $m;
    $matched++;
    $actionCount[$action]++;
    $assert($label === $new, 'incorrect destination link');
    $assert(!isset($seenFrom[$old]), 'duplicate historical path');
    $assert(!isset($seenTo[$new]), 'duplicate archive target');
    $seenFrom[$old] = true;
    $seenTo[$new] = true;
    foreach ([$old, $new] as $p) {
        if ($p === '' || str_starts_with($p, '/') || str_contains($p, '\\')
            || preg_match('~(^|/)\.\.?(/|$)~', $p)) {
            $failures[] = 'unsafe index path';
            continue 2;
        }
    }
    $assert(!file_exists($root.'/'.$old) && !is_link($root.'/'.$old), 'legacy path still exists');
    $path = $root.'/'.$new;
    $assert(is_file($path) && !is_link($path), 'archive target missing or symlinked');
    if (is_file($path) && !is_link($path)) {
        $data = file_get_contents($path);
        $blob = is_string($data) ? sha1('blob '.strlen($data)."\0".$data) : '';
        $assert(hash_equals($gitSha, $blob), 'archived blob digest changed');
    }
}
$assert($matched === 181, 'expected 181 historic files');
$assert($actionCount['deduplicated'] === 83, 'deduplication count mismatch');
$assert($actionCount['archived'] === 87, 'archived document count mismatch');
$assert($actionCount['archived-backup'] === 11, 'archived backup count mismatch');

$readmePath = $root.'/docs/releases/archive/references/LEGACY-PROJECT-README.md';
$oldReadme = is_file($readmePath) ? file_get_contents($readmePath) : false;
$assert(is_string($oldReadme) &&
    sha1('blob '.strlen($oldReadme)."\0".$oldReadme) === 'bb932f20dbddd4e91e6a3b33939a9d5f6edb3906',
    'previous project README is not identical to original');
foreach (['app','bin','config','database','docs','public_html','release','storage','tests','vendor'] as $dir) {
    $assert(is_dir($root.'/'.$dir), 'missing runtime/metadata directory');
}
foreach (['app/bootstrap.php','public_html/index.php','bin/scheduler.php','database/schema.sql',
          'VERSION','composer.json','composer.lock','SHA256SUMS',
          'BUILD-MANIFEST-2.0.2.1.md','RELEASE-CHECK-2.0.2.1.txt','UPDATE-2.0.2.1.md'] as $name) {
    $assert(is_file($root.'/'.$name), 'required runtime/release file absent');
}
foreach (['app', 'public_html'] as $area) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/'.$area, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $f) {
        if (preg_match('/\.bak(?:$|[-.])/i', $f->getFilename())) {
            $failures[] = 'backup copy left in executable folder';
        }
    }
}
$topFiles = glob($root.'/*', GLOB_NOSORT) ?: [];
$assert(count($topFiles) <= 42, 'root becoming cluttered again');
if ($failures) {
    foreach (array_unique($failures) as $why) fwrite(STDERR, "FAIL archive: ".$why."\n");
    exit(1);
}
echo "PASS historic archive: {$matched} original blobs verified; root organized\n";
