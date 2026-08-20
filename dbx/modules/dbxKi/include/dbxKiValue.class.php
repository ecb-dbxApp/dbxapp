<?php
declare(strict_types=1);

namespace dbx\dbxKi;

/** Gemeinsame, zustandslose Werte- und Formularaufbereitung für dbxKi. */
final class dbxKiValue
{
    public static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'ja', 'on'), true);
    }

    public static function slim_page(array $page): array
    {
        return array(
            'id' => (int)($page['id'] ?? 0),
            'title' => (string)($page['title'] ?? ''),
            'permalink' => (string)($page['permalink'] ?? ''),
            'description' => (string)($page['description'] ?? ''),
            'keywords' => (string)($page['keywords'] ?? ''),
            'content' => (string)($page['content'] ?? ''),
        );
    }

    public static function form(string $id, string $template, string $action, array $replacements = array()): \dbxForm
    {
        $form = dbx()->get_system_obj('dbxForm');
        $form->init($id, $template);
        $form->set_action($action);
        $form->_msg_info = '';
        foreach ($replacements as $key => $value) {
            $form->add_rep((string)$key, $value);
        }

        return $form;
    }
}

