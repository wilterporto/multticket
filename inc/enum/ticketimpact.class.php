<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Enumerador de Níveis de Impacto
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

abstract class PluginMultticketImpact {
    public const VERY_LOW  = 1;
    public const LOW       = 2;
    public const MEDIUM    = 3;
    public const HIGH      = 4;
    public const VERY_HIGH = 5;

    public static function getAll(): array {
        return [
            self::VERY_LOW  => __('Muito Baixo', 'multticket'),
            self::LOW       => __('Baixo', 'multticket'),
            self::MEDIUM    => __('Médio', 'multticket'),
            self::HIGH      => __('Alto', 'multticket'),
            self::VERY_HIGH => __('Muito Alto', 'multticket'),
        ];
    }

    public static function getLabel(int $value): string {
        $all = self::getAll();
        return $all[$value] ?? __('Médio', 'multticket');
    }

    public static function getHtmlOptions(?int $selected = self::MEDIUM): string {
        $html = '';
        foreach (self::getAll() as $value => $label) {
            $isSelected = ($selected !== null && (int)$selected === (int)$value) ? 'selected' : '';
            $html .= sprintf('<option value="%d" %s>%s</option>', $value, $isSelected, htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
        }
        return $html;
    }
}

