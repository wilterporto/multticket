<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Enumerador de Tipos de Chamado (Incidente / Requisição)
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

abstract class PluginMultticketTicketType {
    public const INCIDENT = 1;
    public const DEMAND   = 2;

    /**
     * Retorna todas as opções disponíveis
     *
     * @return array [value => label]
     */
    public static function getAll(): array {
        return [
            self::INCIDENT => __('Incidente', 'multticket'),
            self::DEMAND   => __('Requisição', 'multticket'),
        ];
    }

    /**
     * Retorna o rótulo textual do tipo
     *
     * @param int $value
     * @return string
     */
    public static function getLabel(int $value): string {
        $all = self::getAll();
        return $all[$value] ?? __('Desconhecido', 'multticket');
    }

    /**
     * Retorna as opções para compor um elemento HTML <select>
     *
     * @param int|null $selected
     * @return string
     */
    public static function getHtmlOptions(?int $selected = null): string {
        $html = '';
        foreach (self::getAll() as $value => $label) {
            $isSelected = ($selected !== null && (int)$selected === (int)$value) ? 'selected' : '';
            $html .= sprintf('<option value="%d" %s>%s</option>', $value, $isSelected, htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
        }
        return $html;
    }

    /**
     * Retorna a classe e ícone visual para apresentação em badges
     *
     * @param int $value
     * @return array [badge_class => string, icon => string]
     */
    public static function getBadgeInfo(int $value): array {
        if ($value === self::INCIDENT) {
            return [
                'badge_class' => 'bg-danger text-white',
                'icon'        => 'ti ti-alert-triangle'
            ];
        }
        return [
            'badge_class' => 'bg-info text-white',
            'icon'        => 'ti ti-help'
        ];
    }
}

