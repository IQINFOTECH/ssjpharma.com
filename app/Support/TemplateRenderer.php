<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Safe {{placeholder}} template renderer (Phase 5). Templates are admin-authored
 * text; rendering is pure string substitution — it NEVER evaluates PHP, JS, SQL
 * or shell, and never uses include/eval. Values come from a flat context map of
 * dotted keys (e.g. "lead.name", "site.url").
 *
 *  - HTML bodies: each substituted value is HTML-escaped, so lead-supplied content
 *    cannot inject markup/script into the email or the (sandboxed) admin preview.
 *  - Text bodies / subjects: values are inserted raw; the subject additionally has
 *    CR/LF stripped (email header-injection defence).
 *  - Any placeholder with no matching context key is replaced with an empty string
 *    (whitelist behaviour — unknown tokens never leak literally).
 */
final class TemplateRenderer
{
    /**
     * @param array<string,scalar|null> $context flat map of dotted keys → values
     * @param array<int,string>         $rawKeys keys whose value is ALREADY safe HTML
     *                                           (built server-side, per-field escaped)
     *                                           and must be inserted without escaping —
     *                                           only meaningful when $escapeHtml is true.
     */
    public function render(string $template, array $context, bool $escapeHtml, array $rawKeys = []): string
    {
        $raw = array_fill_keys(array_map('strtolower', $rawKeys), true);
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            function (array $m) use ($context, $escapeHtml, $raw): string {
                $key = strtolower($m[1]);
                $val = array_key_exists($key, $context) ? (string) $context[$key] : '';
                if ($escapeHtml && !isset($raw[$key])) {
                    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                return $val;
            },
            $template
        );
    }

    /** Subject line: substitute (unescaped) then strip CR/LF (header-injection safe). */
    public function renderSubject(string $subject, array $context): string
    {
        return str_replace(["\r", "\n"], ' ', $this->render($subject, $context, false));
    }

    /**
     * Render a full template row into a ready-to-queue message.
     * @param array{subject:string,body_html?:?string,body_text?:?string} $tpl
     * @return array{subject:string,html:string,text:string}
     */
    public function renderTemplate(array $tpl, array $context, array $rawHtmlKeys = []): array
    {
        return [
            'subject' => $this->renderSubject((string) ($tpl['subject'] ?? ''), $context),
            'html'    => $this->render((string) ($tpl['body_html'] ?? ''), $context, true, $rawHtmlKeys),
            'text'    => $this->render((string) ($tpl['body_text'] ?? ''), $context, false),
        ];
    }

    /** Flatten a nested array into dotted keys for use as a render context. */
    public function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[strtolower($key)] = is_scalar($v) || $v === null ? $v : '';
            }
        }
        return $out;
    }
}
