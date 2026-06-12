<?php

$dirs = array_merge(
    glob(__DIR__ . '/../app/Modules/*/Mutations/*.php') ?: [],
    glob(__DIR__ . '/../app/Modules/*/*/Mutations/*.php') ?: [],
);

$ops = [];
foreach ($dirs as $f) {
    $c = file_get_contents($f);
    preg_match_all("/'([a-z][a-z0-9_]+)'\s*=>\s*\[self::class/", $c, $m);
    foreach ($m[1] as $op) {
        $ops[$op] = true;
    }
}

$wp = [];
foreach (file(__DIR__ . '/../../includes/admin/class-dashboard-admin-mutations.php') as $line) {
    if (preg_match("/case '([a-z0-9_]+)':/", $line, $m)) {
        $wp[] = $m[1];
    }
}

$laravel = array_keys($ops);
sort($laravel);
$wp = array_values(array_unique($wp));
sort($wp);

echo 'Laravel: ' . count($laravel) . PHP_EOL;
echo 'WP: ' . count($wp) . PHP_EOL;
echo 'Missing: ' . PHP_EOL . implode(PHP_EOL, array_diff($wp, $laravel)) . PHP_EOL;
echo 'Extra: ' . PHP_EOL . implode(PHP_EOL, array_diff($laravel, $wp)) . PHP_EOL;

if (($argv[1] ?? '') === '--write-catalog') {
    $export = var_export($wp, true);
    $content = <<<PHP
<?php

namespace App\Support;

/** Canonical mutate ops from WP class-dashboard-admin-mutations.php (§15). */
class MutateOpCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        return {$export};
    }
}

PHP;
    file_put_contents(__DIR__ . '/../app/Support/MutateOpCatalog.php', $content);
    echo 'Wrote MutateOpCatalog.php' . PHP_EOL;
}
