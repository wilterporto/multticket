<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin para GLPI 10 & 11
 * Permite o registro de múltiplos chamados, sendo um para cada entidade selecionada (até o nível 2).
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

define('PLUGIN_MULTTICKET_VERSION', '1.0.0');
define('PLUGIN_MULTTICKET_MIN_GLPI', '10.0.0');
define('PLUGIN_MULTTICKET_MAX_GLPI', '11.9.99');

// Carregamento dos enumeradores do plugin
require_once __DIR__ . '/inc/enum/tickettype.class.php';
require_once __DIR__ . '/inc/enum/ticketurgency.class.php';
require_once __DIR__ . '/inc/enum/ticketimpact.class.php';
require_once __DIR__ . '/inc/enum/ticketpriority.class.php';
require_once __DIR__ . '/inc/enum/auditaction.class.php';
require_once __DIR__ . '/inc/enum/entitylevel.class.php';

/**
 * Inicialização dos hooks e registros do plugin no ciclo de vida do GLPI
 *
 * @return void
 */
function plugin_init_multticket(): void {
    global $PLUGIN_HOOKS;

    // Conformidade com proteção de tokens CSRF do GLPI
    $PLUGIN_HOOKS['csrf_compliant']['multticket'] = true;

    // Registra classes sob o padrão de autoload do GLPI
    Plugin::registerClass('PluginMultticketProfile', ['addtabon' => 'Profile']);
    Plugin::registerClass('PluginMultticketTicket');
    Plugin::registerClass('PluginMultticketEntity');
    Plugin::registerClass('PluginMultticketAudit');
    Plugin::registerClass('PluginMultticketConfig');
    Plugin::registerClass('PluginMultticketMenu');

    // Executa apenas se o usuário estiver autenticado
    if (Session::getLoginUserID()) {
        // Página de configuração e auditoria nas configurações do GLPI
        $PLUGIN_HOOKS['config_page']['multticket'] = 'front/config.php';

        // Entrada no menu de Assistência (Helpdesk) apenas para perfis com permissão explícita
        if (PluginMultticketProfile::canCreate()) {
            $PLUGIN_HOOKS['menu_toadd']['multticket'] = [
                'helpdesk' => 'PluginMultticketMenu'
            ];
        }

        // Recursos de estilo (CSS) e scripts (JavaScript) com suporte a temas Claro e Escuro
        $PLUGIN_HOOKS['add_javascript']['multticket'] = [
            'js/multticket.js'
        ];
        $PLUGIN_HOOKS['add_css']['multticket'] = [
            'css/multticket.css'
        ];
    }
}

/**
 * Informações e metadados do plugin
 *
 * @return array
 */
function plugin_version_multticket(): array {
    return [
        'name'           => 'Mult Ticket - Chamados em Múltiplas Entidades',
        'version'        => PLUGIN_MULTTICKET_VERSION,
        'author'         => 'Wilter Porto',
        'license'        => 'GPLv3+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_MULTTICKET_MIN_GLPI,
                'max' => PLUGIN_MULTTICKET_MAX_GLPI
            ]
        ]
    ];
}

/**
 * Validação de pré-requisitos antes da ativação do plugin
 *
 * @return boolean
 */
function plugin_multticket_check_prerequisites(): bool {
    if (version_compare(GLPI_VERSION, PLUGIN_MULTTICKET_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_MULTTICKET_MAX_GLPI, 'gt')) {
        echo sprintf(
            __('Este plugin requer GLPI entre %1$s e %2$s (Versão detectada: %3$s)', 'multticket'),
            PLUGIN_MULTTICKET_MIN_GLPI,
            PLUGIN_MULTTICKET_MAX_GLPI,
            GLPI_VERSION
        );
        return false;
    }

    if (version_compare(PHP_VERSION, '7.4.0', 'lt')) {
        echo sprintf(__('Este plugin requer PHP >= 7.4.0 (Versão atual: %s)', 'multticket'), PHP_VERSION);
        return false;
    }

    return true;
}

/**
 * Verificação do estado de configuração do plugin
 *
 * @param boolean $verbose
 * @return boolean
 */
function plugin_multticket_check_config(bool $verbose = false): bool {
    return true;
}

