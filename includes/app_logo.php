<?php

if (!function_exists('render_app_logo')) {
    function render_app_logo(string $variant = 'default', string $label = 'Cuts & Styles'): string
    {
        $safeVariant = preg_replace('/[^a-z0-9_-]/i', '', $variant) ?: 'default';
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $logoRelativePath = 'assets/img/logo.svg';
        if (!is_file(BASE_PATH . $logoRelativePath)) {
            $logoRelativePath = 'assets/img/logo.png';
        }
        $logoUrl = htmlspecialchars(BASE_URL . $logoRelativePath, ENT_QUOTES, 'UTF-8');

        return '<span class="app-logo app-logo--' . $safeVariant . '">'
            . '<img src="' . $logoUrl . '" alt="' . $safeLabel . '" class="app-logo__image">'
            . '</span>';
    }
}
