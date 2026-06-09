<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 1. Alertes
$alerts = Illuminate\Support\Facades\Cache::get('ai:proactive_alerts', []);
echo "Alertes en cache: " . count($alerts) . "\n";
foreach ($alerts as $a) echo "  [" . $a['severity'] . "] " . $a['title'] . "\n";

// 2. Rendu HTML dashboard avec auth
$admin = \App\Models\User::where('role', 'admin')->first();
if (!$admin) { echo "Pas d'admin\n"; exit(1); }

Illuminate\Support\Facades\Auth::login($admin);

$html = view('pages.dashboard', [
    'kpis' => [
        'pipeline_total' => 0, 'ca_total' => 0, 'ca_lost' => 0,
        'conversion' => 0, 'won_count' => 0, 'won_amount' => 0,
        'won_names' => collect([]), 'lost_count' => 0, 'lost_amount' => 0,
        'lost_names' => collect([]),
    ],
    'activities' => collect([]),
    'stagesData' => [],
    'maxTotal' => 0,
])->render();

echo "\nHTML contient aiAlerts: " . (strpos($html, 'aiAlerts') !== false ? 'YES' : 'NO') . "\n";
echo "HTML contient fetchAlerts: " . (strpos($html, 'fetchAlerts') !== false ? 'YES' : 'NO') . "\n";
preg_match('/assets\/app-[a-zA-Z0-9]+\.js/', $html, $m);
echo "Script src: " . ($m[0] ?? 'NOT FOUND') . "\n";

// 3. Vérifier le JS compilé
$jsFiles = glob('public/build/assets/app-*.js');
echo "\nFichiers JS build:\n";
foreach ($jsFiles as $f) {
    $has = strpos(file_get_contents($f), 'aiAlerts') !== false;
    echo "  " . basename($f) . ": aiAlerts=" . ($has ? 'YES' : 'NO') . "\n";
}

echo "\n=== TERMINE ===\n";
