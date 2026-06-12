<?php

$src = file_get_contents(dirname(__DIR__, 2).'/includes/frontend/class-portal-admin.php');
if (! preg_match('/ob_start\(\);\s*\?>(.*?)<\?php\s+return/s', $src, $m)) {
    fwrite(STDERR, "no match\n");
    exit(1);
}
$html = $m[1];

$replacements = [
    '<?php echo $is_reseller ? \' svp-admin--reseller\' : \'\'; ?>' => '@if($isReseller) svp-admin--reseller@endif',
    '<?php echo esc_attr( (string) $uid ); ?>' => '{{ $admin->id }}',
    '<?php echo esc_attr( $nonce ); ?>' => '{{ $nonce }}',
    '<?php echo esc_url( $ajax ); ?>' => '{{ $apiUrl }}',
    '<?php echo $is_reseller ? \'1\' : \'0\'; ?>' => '{{ $isReseller ? "1" : "0" }}',
    '<?php echo $ipn; ?>' => '{{ $ipnUrl }}',
    '<?php for ( $d = 0; $d <= 7; $d++ ) : ?>' => '@for ($d = 0; $d <= 7; $d++)',
    '<?php echo 0 === $d ? \' is-active\' : \'\'; ?>' => '@if($d === 0) is-active@endif',
    '<?php echo esc_attr( (string) $d ); ?>' => '{{ $d }}',
    '<?php echo 0 === $d ? \'امروز\' : esc_html( \'-\' . $d ); ?>' => '@if($d === 0)امروز@else-{{ $d }}@endif',
    '<?php endfor; ?>' => '@endfor',
];
$html = str_replace(array_keys($replacements), array_values($replacements), $html);

$out = '<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>پنل مدیریت وب</title>
<link rel="stylesheet" href="{{ asset(\'portal/portal.css\') }}?v={{ $assetVersion }}"/>
</head>
<body>
'.$html.'
<script src="{{ asset(\'portal/portal.js\') }}?v={{ $assetVersion }}"></script>
</body>
</html>';

$dest = dirname(__DIR__).'/resources/views/portal/admin.blade.php';
if (! is_dir(dirname($dest))) {
    mkdir(dirname($dest), 0755, true);
}
file_put_contents($dest, $out);
echo "written ".strlen($out)." bytes to $dest\n";
