<?php
declare(strict_types=1);

/**
 * Doxygen-Eingabefilter für alte, doppelt UTF-8-codierte PHP-Kommentare.
 *
 * Der Filter verändert keine Projektdatei. Er korrigiert ausschließlich
 * T_COMMENT und T_DOC_COMMENT in der an Doxygen ausgegebenen Kopie. Strings,
 * Konvertierungstabellen und ausführbarer PHP-Code bleiben bytegenau erhalten.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$file = (string)($argv[1] ?? '');
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Doxygen-UTF-8-Filter: Eingabedatei fehlt.\n");
    exit(1);
}

$source = file_get_contents($file);
if (!is_string($source)) {
    fwrite(STDERR, "Doxygen-UTF-8-Filter: Eingabedatei ist nicht lesbar.\n");
    exit(1);
}

$repairComment = static function (string $comment): string {
    return str_replace(
        array('Ã¤', 'Ã¶', 'Ã¼', 'Ã„', 'Ã–', 'Ãœ', 'ÃŸ', 'Ã¸', 'Â '),
        array('ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß', 'ø', "\u{00A0}"),
        $comment
    );
};

foreach (token_get_all($source) as $token) {
    if (is_array($token)) {
        $text = (string)$token[1];
        if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
            $text = $repairComment($text);
        }
        echo $text;
        continue;
    }
    echo $token;
}
