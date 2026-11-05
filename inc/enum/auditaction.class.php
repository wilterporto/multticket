<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Enumerador de Ações de Auditoria
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

abstract class PluginMultticketAuditAction {
    public const BATCH_CREATE     = 'BATCH_CREATE';
    public const CONFIG_UPDATE    = 'CONFIG_UPDATE';
    public const PLUGIN_INSTALL   = 'PLUGIN_INSTALL';
    public const PLUGIN_UNINSTALL = 'PLUGIN_UNINSTALL';
    public const LOGICAL_DELETE   = 'LOGICAL_DELETE';

    public static function getAll(): array {
        return [
            self::BATCH_CREATE     => __('Criação de Chamados em Lote', 'multticket'),
            self::CONFIG_UPDATE    => __('Atualização de Configurações', 'multticket'),
            self::PLUGIN_INSTALL   => __('Instalação do Plugin', 'multticket'),
            self::PLUGIN_UNINSTALL => __('Desinstalação do Plugin', 'multticket'),
            self::LOGICAL_DELETE   => __('Exclusão Lógica de Registro', 'multticket'),
        ];
    }

    public static function getLabel(string $action): string {
        $all = self::getAll();
        return $all[$action] ?? $action;
    }

    public static function getBadgeInfo(string $action): array {
        switch ($action) {
            case self::BATCH_CREATE:
                return ['badge_class' => 'bg-success text-white', 'icon' => 'ti ti-copy-plus'];
            case self::CONFIG_UPDATE:
                return ['badge_class' => 'bg-primary text-white', 'icon' => 'ti ti-settings'];
            case self::PLUGIN_INSTALL:
                return ['badge_class' => 'bg-azure text-white', 'icon' => 'ti ti-download'];
            case self::PLUGIN_UNINSTALL:
                return ['badge_class' => 'bg-danger text-white', 'icon' => 'ti ti-trash'];
            case self::LOGICAL_DELETE:
                return ['badge_class' => 'bg-warning text-dark', 'icon' => 'ti ti-trash-x'];
            default:
                return ['badge_class' => 'bg-secondary text-white', 'icon' => 'ti ti-activity'];
        }
    }
}

