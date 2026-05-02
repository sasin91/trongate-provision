<?php
if (!function_exists('wizard_step_dots')) {
    function wizard_step_dots(array $classes, string $container_class = 'steps'): string {
        $html = '<div class="' . htmlspecialchars($container_class) . '">';
        foreach ($classes as $c) {
            $attr = $c ? ' ' . htmlspecialchars((string) $c) : '';
            $html .= '<div class="step-dot' . $attr . '"></div>';
        }
        return $html . '</div>';
    }
    function wizard_step_classes(int $total, int $current): array {
        $out = [];
        for ($i = 1; $i <= $total; $i++) {
            $out[] = $i < $current ? 'completed' : ($i === $current ? 'active' : '');
        }
        return $out;
    }
}
