<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use App\Models\Activity;

$withId    = Contact::whereNotNull('emelia_contact_id')->count();
$withName  = Contact::whereNull('emelia_contact_id')->whereNotNull('emelia_campaign_name')->count();
$emeliActs = Activity::where('source', 'emelia')->count();
$replied   = Activity::where('source', 'emelia')->where('type', 'email_replied')->count();
$opened    = Activity::where('source', 'emelia')->where('type', 'email_opened')->count();
$tasks     = Activity::where('source', 'emelia')->where('type', 'task')->count();

echo "CONTACTS_WITH_ID:$withId\n";
echo "CONTACTS_WITHOUT_ID:$withName\n";
echo "EMELIA_ACTIVITIES:$emeliActs\n";
echo "REPLIED:$replied\n";
echo "OPENED:$opened\n";
echo "TASKS:$tasks\n";
