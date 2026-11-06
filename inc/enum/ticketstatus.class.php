<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Enumerador de Status do Chamado
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

abstract class PluginMultticketTicketStatus {
    public const INCOMING = 1; // Novo
    public const ASSIGNED = 2; // Em atendimento (atribuído)
    public const PLANNED  = 3; // Em atendimento (planejado)
    public const WAITING  = 4; // Pendente
    public const SOLVED   = 5; // Solucionado
    public const CLOSED   = 6; // Fechado

    /**
     * Retorna todos os status permitidos para criação no Mult Ticket
     *
     * @return array<int, string> [value => label]
     */
    public static function getAll(): array {
        return [
            self::INCOMING => _x('status', 'New'),
            self::ASSIGNED => _x('status', 'Processing (assigned)'),
            self::PLANNED  => _x('status', 'Processing (planned)'),
            self::WAITING  => __('Pending'),
            self::SOLVED   => _x('status', 'Solved'),
            self::CLOSED   => _x('status', 'Closed'),
        ];
    }

    /**
     * Retorna o rótulo textual do status
     *
     * @param int $value
     * @return string
     */
    public static function getLabel(int $value): string {
        $all = self::getAll();
        return $all[$value] ?? __('Desconhecido', 'multticket');
    }

    /**
     * Verifica se o status exige comentário obrigatório (Solucionado ou Fechado)
     *
     * @param int $value
     * @return bool
     */
    public static function requiresComment(int $value): bool {
        return in_array($value, [self::SOLVED, self::CLOSED], true);
    }

    /**
     * Retorna as opções para compor um elemento HTML <select>
     *
     * @param int|null $selected
     * @return string
     */
    public static function getHtmlOptions(?int $selected = null): string {
        $html = '';
        if ($selected === null) {
            $selected = self::INCOMING;
        }
        foreach (self::getAll() as $value => $label) {
            $isSelected = ((int)$selected === (int)$value) ? 'selected' : '';
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
        switch ($value) {
            case self::SOLVED:
                return [
                    'badge_class' => 'bg-success text-white',
                    'icon'        => 'ti ti-circle-check'
                ];
            case self::CLOSED:
                return [
                    'badge_class' => 'bg-dark text-white',
                    'icon'        => 'ti ti-lock'
                ];
            case self::WAITING:
                return [
                    'badge_class' => 'bg-orange text-white',
                    'icon'        => 'ti ti-clock-pause'
                ];
            case self::ASSIGNED:
            case self::PLANNED:
                return [
                    'badge_class' => 'bg-warning text-dark',
                    'icon'        => 'ti ti-progress'
                ];
            case self::INCOMING:
            default:
                return [
                    'badge_class' => 'bg-info text-white',
                    'icon'        => 'ti ti-sparkles'
                ];
        }
    }
}

