<?php
/**
 * FormRenderer — emits form controls with their programmatic label association.
 *
 * A placeholder is not a label: it disappears on focus and is not exposed as an
 * accessible name. Every control needs a <label for=""> bound to the control's
 * id (WCAG 2.2 SC 1.3.1 / 4.1.2, EN 301 549 §9).
 */
class FormRenderer {

    /**
     * @param array $attrs extra input attributes, e.g. ['required'=>'required']
     */
    public static function field(
        string $name,
        string $label,
        string $type = 'text',
        string $value = '',
        string $placeholder = '',
        array  $attrs = []
    ): string {
        $id   = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $esc  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $extra = '';
        foreach ($attrs as $k => $v) {
            $extra .= ' ' . $esc($k) . '="' . $esc($v) . '"';
        }

        $input = sprintf(
            '<input type="%s" id="%s" name="%s" value="%s" placeholder="%s" class="form-control"%s>',
            $esc($type), $esc($id), $esc($name), $esc($value), $esc($placeholder), $extra
        );

        // Defect: missing_form_labels — the control ships with a placeholder only,
        // leaving it without an accessible name for assistive technology.
        if (defectActive('missing_form_labels')) {
            return $input;
        }

        return sprintf('<label for="%s" class="form-label">%s</label>%s', $esc($id), $esc($label), $input);
    }

    /**
     * Accessible name a user agent would compute for the markup produced above.
     * Returns null when the control has none.
     */
    public static function accessibleName(string $html): ?string {
        if (preg_match('/<label[^>]*for="([^"]+)"[^>]*>(.*?)<\/label>/is', $html, $m)
            && preg_match('/<input[^>]*id="' . preg_quote($m[1], '/') . '"/i', $html)) {
            return trim(strip_tags($m[2]));
        }
        if (preg_match('/<input[^>]*aria-label="([^"]+)"/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
