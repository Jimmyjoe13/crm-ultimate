<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::where('email', 'admin@demo.com')->first();
if ($u) {
    echo "User found. Hash check: ";
    echo \Illuminate\Support\Facades\Hash::check('password', $u->password) ? 'OK' : 'FAIL';
} else {
    echo "User not found.";
}
echo "\n";
