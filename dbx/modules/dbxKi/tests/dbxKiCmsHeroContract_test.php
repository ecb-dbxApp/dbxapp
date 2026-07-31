<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxKiCmsService.class.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$class = new ReflectionClass(\dbx\dbxKi\dbxKiCmsService::class);
$service = $class->newInstanceWithoutConstructor();
$validate = $class->getMethod('assert_no_fake_inline_hero');
$validate->setAccessible(true);

$fakeHero = '<div class="position-relative"><img data-cms-media-id="166" src="index.php?dbx_mid=166">'
    . '<div class="position-absolute top-0 start-0 w-100 h-100"><h2>Eine Plattform</h2>'
    . '<p>Dieser umfangreiche Text liegt über dem Bild und bildet damit fälschlich einen Hero im normalen Inhalt nach.</p>'
    . '<a href="demo">Demo ansehen</a></div></div><p>Body</p>';
$rejected = false;
try {
    $validate->invoke($service, $fakeHero);
} catch (ReflectionException $error) {
    throw $error;
} catch (Throwable $error) {
    $cause = $error instanceof ReflectionException ? $error : ($error->getPrevious() ?: $error);
    $rejected = str_contains($cause->getMessage(), 'CMS-Hero')
        || str_contains($error->getMessage(), 'CMS-Hero');
}
$assert($rejected, 'dbxKi accepts an inline fake Hero at the start of content.');

$realHero = '<div class="dbx-home-hero-copy"><h2>Eine Plattform</h2><p>Hero-Text ohne Inline-Bild.</p></div>'
    . '<hr class="dbx-cms-marker" data-dbx-marker="dbx:hero"><p>Body</p>';
$validHeroAccepted = true;
try {
    $validate->invoke($service, $realHero);
} catch (Throwable $error) {
    $validHeroAccepted = false;
}
$assert($validHeroAccepted, 'dbxKi rejects the native Hero marker structure.');

$cardWithBadge = '<div class="card position-relative"><img class="card-img-top" data-cms-media-id="42" src="index.php?dbx_mid=42">'
    . '<span class="position-absolute badge">Neu</span><div class="card-body"><p>Produkt</p></div></div>';
$cardAccepted = true;
try {
    $validate->invoke($service, $cardWithBadge);
} catch (Throwable $error) {
    $cardAccepted = false;
}
$assert($cardAccepted, 'dbxKi incorrectly rejects a small absolute card badge.');

$workflow = $class->getMethod('page_workflows');
$workflow->setAccessible(true);
$workflowText = json_encode($workflow->invoke($service), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$contract = $class->getMethod('content_contract');
$contract->setAccessible(true);
$contractText = json_encode($contract->invoke($service), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$assert(
    str_contains((string)$workflowText, 'Inline-Schein-Hero')
        && str_contains((string)$workflowText, 'slot=hero')
        && str_contains((string)$contractText, 'dbx:hero'),
    'The dbxKi CMS contract does not document the native Hero workflow.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK dbxKi enforces the native CMS Hero workflow.\n";
