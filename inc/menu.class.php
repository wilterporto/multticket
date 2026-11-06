<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Integração de Navegação e Menus do GLPI
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginMultticketMenu extends CommonGLPI {

    public static $rightname = 'ticket';

    public static function getMenuName(): string {
        return __('Chamados Múltiplos', 'multticket');
    }

    /**
     * Define o conteúdo estruturado do menu do plugin integrado ao GLPI
     *
     * @return array
     */
    public static function getMenuContent(): array {
        if (!PluginMultticketProfile::canCreate()) {
            return [];
        }

        $menu = [
            'title'   => __('Chamados Múltiplos', 'multticket'),
            'page'    => '/plugins/multticket/front/multticket.form.php',
            'icon'    => 'ti ti-copy-plus',
            'options' => [
                'create' => [
                    'title' => __('Novo Registro Múltiplo', 'multticket'),
                    'page'  => '/plugins/multticket/front/multticket.form.php',
                    'icon'  => 'ti ti-file-plus',
                ]
            ]
        ];

        // Opções exclusivas para SuperAdmin
        if (PluginMultticketProfile::isSuperAdmin()) {
            $menu['options']['audit'] = [
                'title' => __('Auditoria & Histórico', 'multticket'),
                'page'  => '/plugins/multticket/front/audit.php',
                'icon'  => 'ti ti-history',
            ];

            $menu['options']['config'] = [
                'title' => __('Configurações Gerais', 'multticket'),
                'page'  => '/plugins/multticket/front/config.php',
                'icon'  => 'ti ti-settings',
            ];
        }

        return $menu;
    }
}

