<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contact = \App\Models\Contact::find(37);
if ($contact) {
    echo "ID: " . $contact->id . "\n";
    echo "custom_values raw: " . $contact->getRawOriginal('custom_values') . "\n";
    var_dump($contact->custom_values);
} else {
    echo "Contact 37 not found.\n";
}
