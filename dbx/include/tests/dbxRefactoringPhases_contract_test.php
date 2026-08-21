<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = array();
$read = static fn(string $file): string => (string)file_get_contents($file);

$api = $read($root . '/include/dbxApi.php');
$pipeline = $read($root . '/include/dbxRequestPipeline.class.php');
$index = $read(dirname($root) . '/index.php');
if (str_contains($api, 'run_web_app_request') || !str_contains($pipeline, 'public function run(): void')) {
    $errors[] = 'Request-Ablauf ist nicht vollständig im dbxRequestPipeline-Service.';
}
if (!str_contains($index, "get_system_obj('dbxRequestPipeline')->run()")) {
    $errors[] = 'Frontcontroller verwendet die Request-Pipeline nicht.';
}

$dd = $read($root . '/include/dbxDDSynchronization.trait.php');
foreach (array(
    'run_sync_dd_to_db_phase', 'prepare_dd_to_db_phase',
    'create_dd_to_db_table_phase', 'add_dd_to_db_fields_phase',
    'add_dd_to_db_indexes_phase', 'run_sync_db_to_dd_phase',
    'merge_db_to_dd_table', 'merge_db_to_dd_fields', 'merge_db_to_dd_indexes',
) as $method) {
    if (!str_contains($dd, 'function ' . $method . '(')) {
        $errors[] = 'DD-Synchronisationsphase fehlt: ' . $method;
    }
}
if (!str_contains($dd, 'get_table_exist($server, $table, false)')) {
    $errors[] = 'DD-Synchronisationsplanung protokolliert erwartete fehlende DBs/Tabellen als Systemfehler.';
}

$wizard = $read($root . '/modules/dbxAdmin/include/dbxWizard.class.php');
if (!str_contains($wizard, '$this->service_class_template()')
    || !str_contains($wizard, 'private function service_class_template(): string')) {
    $errors[] = 'Wizard trennt Eingabedaten und Service-Grundgerüst nicht.';
}

$catalog = $read($root . '/modules/dbxShop/include/dbxShopRepositoryCatalogService.trait.php');
foreach (array('load_product_decoration_context', 'apply_product_decoration', 'apply_product_decoration_row') as $method) {
    if (!str_contains($catalog, 'function ' . $method . '(')) {
        $errors[] = 'Shop-Produktphase fehlt: ' . $method;
    }
}

$channels = $read($root . '/modules/dbxShop_admin/include/dbxShopAdminChannelService.trait.php');
foreach (array('render_channel_row', 'render_channels_page') as $method) {
    if (!str_contains($channels, 'function ' . $method . '(')) {
        $errors[] = 'Shop-Channelphase fehlt: ' . $method;
    }
}

if ($errors !== array()) {
    fwrite(STDERR, "Refactoring-Vertrag verletzt:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "OK Request-, DD-, Wizard- und Shop-Abläufe bleiben in benannten Phasen getrennt.\n";
