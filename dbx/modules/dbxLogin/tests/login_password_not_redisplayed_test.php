<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/include/login.class.php');

$assert = static function (bool $condition, string $message): void {
   if (!$condition) {
      throw new RuntimeException($message);
   }
};

$assert(
   str_contains($source, '$submitted = $o_form->submit();'),
   'Der Submit-Status wird nicht eindeutig erfasst.'
);
$assert(
   str_contains($source, "if (\$submitted && !\$ok)")
      && str_contains($source, "\$o_form->set_fld_val('password', '');"),
   'Ein fehlgeschlagenes Login darf das Passwort nicht erneut anzeigen.'
);
$assert(
   strpos($source, "\$o_form->set_fld_val('password', '');")
      < strpos($source, '$content= $o_form->run();'),
   'Das Passwort wird erst nach dem Rendern geleert.'
);

echo "OK login password is never redisplayed\n";
