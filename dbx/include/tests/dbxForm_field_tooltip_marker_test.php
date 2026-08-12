<?php
declare(strict_types=1);

/**
 * Vertrag fuer FD-/DD-Feldtooltips:
 * dbxForm erzeugt genau einen sicheren Hilfe-Marker, Feld-Templates kennen
 * nur den neutralen Platzhalter `{tooltip}` hinter ihrer Beschriftung.
 */

class dbxObj {
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dbxForm.class.php';

$errors = array();
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$reflection = new ReflectionClass(dbxForm::class);
$form = $reflection->newInstanceWithoutConstructor();
$render = $reflection->getMethod('render_field_tooltip_marker');
$render->setAccessible(true);

$assert($render->invoke($form, '') === '', 'Ein leerer Tooltip muss leer gerendert werden.');

$marker = (string)$render->invoke(
    $form,
    '<strong>Hilfe</strong> mit "Anfuehrung" & Details'
);
$assert(
    str_contains($marker, 'class="dbx-form-tooltip-marker"')
        && str_contains($marker, 'tabindex="0"')
        && str_contains($marker, '>?</span>'),
    'Der fokussierbare Fragezeichen-Marker ist unvollstaendig.'
);
$assert(
    str_contains($marker, 'data-dbx-tooltip="&lt;strong&gt;Hilfe&lt;/strong&gt; mit &quot;Anfuehrung&quot; &amp; Details"'),
    'HTML-faehiger Tooltip-Inhalt ist am Attribut-Rand nicht sicher kodiert.'
);
$assert(
    str_contains($marker, 'aria-label="Hilfe mit &quot;Anfuehrung&quot; &amp; Details"')
        && !str_contains($marker, 'aria-label="&lt;strong&gt;'),
    'Die barrierearme Beschriftung muss HTML-frei sein.'
);

$root = dirname(__DIR__, 2);
$labelTemplates = array(
    'modules/dbx/tpl/htm/auth-password-label.htm',
    'modules/dbx/tpl/htm/auth-text-label.htm',
    'modules/dbx/tpl/htm/auth-select-single-label.htm',
    'modules/dbx/tpl/htm/checkbox-label.htm',
    'modules/dbx/tpl/htm/date-label.htm',
    'modules/dbx/tpl/htm/datetime-label-group-prompt.htm',
    'modules/dbx/tpl/htm/html1-textarea-label.htm',
    'modules/dbx/tpl/htm/html2-textarea-label.htm',
    'modules/dbx/tpl/htm/integer-label.htm',
    'modules/dbx/tpl/htm/multi-select-label.htm',
    'modules/dbx/tpl/htm/multiselect2.htm',
    'modules/dbx/tpl/htm/password-label.htm',
    'modules/dbx/tpl/htm/search-label.htm',
    'modules/dbx/tpl/htm/select-multible-label.htm',
    'modules/dbx/tpl/htm/select-single-label.htm',
    'modules/dbx/tpl/htm/text-label.htm',
    'modules/dbx/tpl/htm/textarea-label.htm',
    'modules/dbxShop/tpl/htm/shop-field-qty.htm',
    'modules/dbxShop/tpl/htm/shop-checkout-check.htm',
    'modules/dbxShop_admin/tpl/htm/shop-settings-check.htm',
    'modules/dbxKi/tpl/htm/ki-bundle-zip-upload.htm',
    'modules/dbxAdmin/tpl/htm/ddedit-rights-select1.htm',
);

foreach ($labelTemplates as $relative) {
    $source = (string)file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    $assert(str_contains($source, '{tooltip}'), $relative . ': `{tooltip}` fehlt.');
    $assert(
        !str_contains($source, 'data-dbx-tooltip="{tooltip}"'),
        $relative . ': Tooltip-Inhalt wird noch direkt als Attribut eingesetzt.'
    );
    $labelPosition = strpos($source, '{label}');
    $tooltipPosition = strpos($source, '{tooltip}');
    $assert(
        $labelPosition !== false && $tooltipPosition !== false && $tooltipPosition > $labelPosition,
        $relative . ': Das Fragezeichen steht nicht hinter der Beschriftung.'
    );
}

foreach (array('dbxapp', 'dbxdocs', 'flowers', 'steal') as $design) {
    $css = (string)file_get_contents($root . '/design/' . $design . '/css/c-tooltip.css');
    $assert(
        str_contains($css, '.dbx-form-tooltip-marker')
            && str_contains($css, '.dbx-form-tooltip-marker:focus-visible'),
        $design . ': Darstellung oder Tastaturfokus des Feld-Tooltips fehlt.'
    );
}

if ($errors !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK: FD-/DD-Feldtooltips erscheinen als sicheres Fragezeichen hinter dem Label.\n";

