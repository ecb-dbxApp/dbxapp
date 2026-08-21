<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/include/login.class.php');
$template_root = dirname(__DIR__, 2) . '/dbx/tpl/htm';
$username_template = (string)file_get_contents($template_root . '/auth-text-label.htm');
$password_template = (string)file_get_contents($template_root . '/auth-password-label.htm');

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
$assert(
   str_contains($username_template, 'autocomplete="username"'),
   'Das Login-Feld muss Browsern die Benutzerkennung semantisch ausweisen.'
);
$assert(
   str_contains($password_template, 'autocomplete="current-password"'),
   'Das Login-Passwort muss Browsern als bestehendes Passwort ausgewiesen werden.'
);

echo "OK login password is never redisplayed\n";
