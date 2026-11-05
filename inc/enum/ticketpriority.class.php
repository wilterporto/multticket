<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Enumerador de Níveis de Prioridade
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

abstract class PluginMultticketPriority {
    public const VERY_LOW  = 1;
    public const LOW       = 2;
    public const MEDIUM    = 3;
    public const HIGH      = 4;
    public const VERY_HIGH = 5;
    public const MAJOR     = 6;

    public static function getAll(): array {
        return [
            self::VERY_LOW  => __('Muito Baixa', 'multticket'),
            self::LOW       => __('Baixa', 'multticket'),
            self::MEDIUM    => __('Média', 'multticket'),
            self::HIGH      => __('Alta', 'multticket'),
            self::VERY_HIGH => __('Muito Alta', 'multticket'),
            self::MAJOR     => __('Crítica', 'multticket'),
        ];
    }

    public static function getLabel(int $value): string {
        $all = self::getAll();
        return $all[$value] ?? __('Média', 'multticket');
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

