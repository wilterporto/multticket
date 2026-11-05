<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Enumerador de Níveis Hierárquicos de Entidades Suportados
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

abstract class PluginMultticketEntityLevel {
    public const ROOT_LEVEL       = 1; // Entidade Raiz
    public const SUBENTITY_LEVEL  = 2; // Entidades Filhas de 1º Nível (Nível 2)

    public const MAX_ALLOWED_LEVEL = 2;

    public static function getAll(): array {
        return [
            self::ROOT_LEVEL      => __('Nível 1 - Entidade Raiz', 'multticket'),
            self::SUBENTITY_LEVEL => __('Nível 2 - Subentidade', 'multticket'),
        ];
    }

    public static function getLabel(int $level): string {
        $all = self::getAll();
        return $all[$level] ?? sprintf(__('Nível %d', 'multticket'), $level);
    }

    public static function getBadgeInfo(int $level): array {
        if ($level === self::ROOT_LEVEL) {
            return [
                'badge_class' => 'badge bg-teal-lt text-teal',
                'label'       => __('Nível 1 (Raiz)', 'multticket'),
                'icon'        => 'ti ti-building'
            ];
        }
        if ($level === self::SUBENTITY_LEVEL) {
            return [
                'badge_class' => 'badge bg-cyan-lt text-cyan',
                'label'       => __('Nível 2 (Filha)', 'multticket'),
                'icon'        => 'ti ti-corner-down-right'
            ];
        }
        return [
            'badge_class' => 'badge bg-secondary-lt',
            'label'       => sprintf(__('Nível %d', 'multticket'), $level),
            'icon'        => 'ti ti-circle'
        ];
    }
}

