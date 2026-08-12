<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

/** @return string */
function docs_review_read(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        throw new RuntimeException('Prüfquelle fehlt oder ist leer: ' . $path);
    }
    return $content;
}

/**
 * Bewertet eine unabhängige Prüfliste und erzwingt die Freigabeschwelle.
 *
 * @param string             $reviewer Bezeichnung der Prüfperspektive.
 * @param array              $criteria Benannte, beobachtbare Pflichtkriterien.
 */
function docs_review_score(string $reviewer, array $criteria): float
{
    $failed = array_keys(array_filter($criteria, static fn(bool $passed): bool => !$passed));
    $score = count($criteria) > 0 ? (count($criteria) - count($failed)) * 10 / count($criteria) : 0.0;
    if ($failed !== array() || $score < 9.5) {
        throw new RuntimeException(
            $reviewer . ' erreicht nur ' . number_format($score, 1, ',', '.')
            . '/10. Fehlend: ' . implode(', ', $failed)
        );
    }
    return $score;
}

$contentRoot = $root . '/dbx/modules/dbxDocs/content';
$generatedRoot = $contentRoot . '/generated';
$home = docs_review_read($contentRoot . '/dbxapp_home.html');
$user = docs_review_read($generatedRoot . '/dbxapp_user_docs.html');
$start = docs_review_read($generatedRoot . '/dbxapp_user_start.html');
$template = docs_review_read($root . '/dbx/modules/dbxContent/tpl/htm/c-doku.htm');
$provision = docs_review_read($root . '/dbx/modules/dbxDocs/include/dbxDocsContentProvision.class.php');
$reset = docs_review_read($root . '/dbx/modules/dbxLogin/include/password_reset.class.php');
$routing = docs_review_read($root . '/.htaccess');
$doxyfile = docs_review_read($root . '/Doxyfile');
$qualityGuide = docs_review_read($root . '/30_Dokumentations_Qualitaetsabnahme.md');

$editorial = docs_review_score('Prüfung A – Anwender und Redaktion', array(
    'vier Zielgruppen' => substr_count($home, 'dbxdocs-home-card is-') === 4,
    'Registrierung' => str_contains($user, 'Benutzer → Registrieren'),
    'Aktivierung' => str_contains($user, 'Bestätigungslink'),
    'Login' => str_contains($user, 'Benutzer → Login'),
    'Login-Nutzen' => str_contains($user, 'Die Anmeldung ist nicht nur eine Zugangssperre'),
    'Passwort vergessen' => str_contains($user, 'Passwort vergessen?'),
    'Einmal-Link' => str_contains($start, 'Einmal-Link'),
    'Sitzungswiderruf' => str_contains($start, 'frühere Sitzungen beendet'),
    'eigene Daten' => str_contains($user, 'eigenen Daten aufrufen'),
    'Owner-Prinzip' => str_contains($user, 'Owner-Prinzip'),
    'Owner-Grenze' => str_contains($user, 'deren Owner er ist'),
    'Logs' => str_contains($user, 'Systemmeldungen protokollieren'),
    'IP' => str_contains($user, 'IP-Adresse'),
    'Trace' => str_contains($user, 'aktiviertem Trace'),
    'Datenschutzgrenze' => str_contains($user, 'Datenschutzvorgaben'),
    'Missbrauchshinweis' => str_contains($user, 'Bei Verdacht auf Missbrauch'),
    'nächster Schritt' => str_contains($user, 'Empfohlener nächster Schritt'),
    'keine doppelte Seitentitel-H1 im CMS-Inhalt' => substr_count($user, '<h1>') === 0,
    'Dokumentstatus' => str_contains($template, 'aria-label="Dokumentstatus"'),
    'kanonische Quelle' => str_contains($template, 'rel="canonical"'),
));

$technical = docs_review_score('Prüfung B – Technik und Sicherheit', array(
    'Hauptdomain' => str_contains($qualityGuide, 'dbxapp.de/dokumentation/'),
    '301-Vertrag' => str_contains($routing, 'R=301'),
    'vier CMS-Bereiche' => preg_match_all("/'(?:user|operations|development|ai)'\s*=>/", $provision) >= 4,
    'c-doku-Zuordnung' => str_contains($provision, 'documentationTemplateForPermalink'),
    'Template-Metadaten' => str_contains($template, '{doc:audience}') && str_contains($template, '{doc:date}'),
    'zentrale Version' => str_contains($template, '{dbx:version}'),
    'generische Reset-Antwort' => str_contains($reset, 'Diese Meldung darf unabhängig vom Ergebnis nicht variieren'),
    'starkes Zufallstoken' => str_contains($reset, 'random_bytes(32)'),
    'nur Token-Hash' => str_contains($reset, "hash('sha256'"),
    'Reset-Ablaufzeit' => str_contains($reset, "'expires' => time() + \$this->tokenLifetime"),
    'Reset-Rate-Limit' => str_contains($reset, 'requested_at'),
    'aktive bestätigte Konten' => str_contains($reset, 'status=1 AND is_confirm=1'),
    'zentrale Passwortregel' => str_contains($reset, 'dbxPasswordPolicy'),
    'Einmalverwendung' => str_contains($reset, "unset(\$settings['password_reset']"),
    'Sitzungswiderruf' => str_contains($reset, "delete('dbxSession'"),
    'Sicherheitsprotokoll' => str_contains($reset, "sys_msg('security', 'password_reset'"),
    'Doxygen HTML' => str_contains($doxyfile, 'GENERATE_HTML') && str_contains($doxyfile, 'YES'),
    'Doxygen XML' => str_contains($doxyfile, 'GENERATE_XML') && str_contains($doxyfile, 'YES'),
    'Suchindex' => is_file($root . '/dbx/modules/dbxDocs/tools/build_docs_search_index.php'),
    'SelfTest-Verträge' => is_file($root . '/dbx/include/tests/dbxDocumentationQuality_contract_test.php')
        && is_file($root . '/dbx/modules/dbxLogin/tests/password_reset_security_contract_test.php'),
));

echo 'OK documentation dual review: Redaktion '
    . number_format($editorial, 1, ',', '.') . '/10; Technik '
    . number_format($technical, 1, ',', '.') . "/10\n";
