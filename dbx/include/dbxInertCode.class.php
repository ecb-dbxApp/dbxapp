<?php

declare(strict_types=1);

/**
 * Schützt dargestellten Code vor den dbxTPL- und dbxInterpreter-Pipelines.
 *
 * Alles innerhalb semantischer Codeelemente, Markdown-Codeblöcke und explizit
 * als inert markierter Dokumentationsbereiche bleibt bytegetreu erhalten.
 */
final class dbxInertCode
{
    private string $nonce;

    public function __construct()
    {
        $this->nonce = bin2hex(random_bytes(8));
    }

    /**
     * @param string $content Zu schützender Dokumentationsinhalt
     * @param array $blocks Ablage fuer geschuetzte Codebloecke
     * @param bool $protect_textarea Auch Textarea-Inhalte als inerten Code behandeln
     */
    public function protect(string $content, array &$blocks, bool $protect_textarea = true): string
    {
        if ($content === '' || !$this->may_contain_inert_code($content)) {
            return $content;
        }

        $semantic_elements = 'pre|code|kbd|samp|script|style|dbx-code'
            . ($protect_textarea ? '|textarea' : '');
        $patterns = array(
            '/<(' . $semantic_elements . ')\b[^>]*>[\s\S]*?<\/\1\s*>/i',
            '/<([a-z][a-z0-9:-]*)\b(?=[^>]*(?:\bdata-dbx-inert(?:\s*=\s*(?:["\'][^"\']*["\']|[^\s>]+))?|\bclass\s*=\s*["\'][^"\']*\bdbx-code-inert\b[^"\']*["\']))[^>]*>[\s\S]*?<\/\1\s*>/i',
            '/(^|\R)([ \t]*)(`{3,}|~{3,})[^\r\n]*\R[\s\S]*?\R[ \t]*\3[ \t]*(?=\R|$)/m',
        );

        foreach ($patterns as $pattern) {
            $protected = preg_replace_callback(
                $pattern,
                function (array $match) use (&$blocks): string {
                    $token = $this->token(count($blocks));
                    while (isset($blocks[$token])) {
                        $token = $this->token(count($blocks) + 1);
                    }
                    $blocks[$token] = (string)$match[0];
                    return $token;
                },
                $content
            );
            if (is_string($protected)) {
                $content = $protected;
            }
        }

        return $content;
    }

    /**
     * @param string $content Inhalt mit Schutzmarken
     * @param array $blocks Zuvor abgelegte Codebloecke
     */
    public function restore(string $content, array $blocks): string
    {
        foreach (array_reverse($blocks, true) as $token => $block) {
            $content = str_replace($token, $block, $content);
        }
        return $content;
    }

    private function may_contain_inert_code(string $content): bool
    {
        return str_contains($content, '<')
            || str_contains($content, '```')
            || str_contains($content, '~~~');
    }

    private function token(int $index): string
    {
        return '<!--dbx-inert-code-' . $this->nonce . '-' . $index . '-->';
    }
}
