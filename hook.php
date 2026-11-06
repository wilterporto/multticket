<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Arquivo de Instalação, Desinstalação e Hooks do Ciclo de Vida
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

/**
 * Instalação do plugin Mult Ticket
 * Criação das tabelas de auditoria (Multitenant) e configurações do sistema
 *
 * @return boolean
 */
function plugin_multticket_install(): bool {
    global $DB;

    // 1. Tabela de Auditoria e Histórico de Ações (Multitenant com suporte a exclusão lógica)
    if (!$DB->tableExists('glpi_plugin_multticket_audits')) {
        $queryAudit = "CREATE TABLE `glpi_plugin_multticket_audits` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `users_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `user_name` VARCHAR(255) DEFAULT NULL,
            `user_profile` VARCHAR(255) DEFAULT NULL,
            `action` VARCHAR(50) NOT NULL,
            `batch_id` VARCHAR(64) DEFAULT NULL,
            `tickets_count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `tickets_created` LONGTEXT DEFAULT NULL,
            `entities_selected` LONGTEXT DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `action` (`action`),
            KEY `batch_id` (`batch_id`),
            KEY `is_deleted` (`is_deleted`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($queryAudit);
    }

    // 2. Tabela de Configurações do Plugin por Entidade (Multitenant)
    if (!$DB->tableExists('glpi_plugin_multticket_configs')) {
        $queryConfig = "CREATE TABLE `glpi_plugin_multticket_configs` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `entities_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `is_recursive` TINYINT(1) NOT NULL DEFAULT 1,
            `default_type` INT(11) UNSIGNED NOT NULL DEFAULT 2,
            `default_urgency` INT(11) UNSIGNED NOT NULL DEFAULT 3,
            `default_impact` INT(11) UNSIGNED NOT NULL DEFAULT 3,
            `link_tickets` TINYINT(1) NOT NULL DEFAULT 0,
            `displayed_additional_fields` LONGTEXT DEFAULT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            `date_creation` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`entities_id`),
            KEY `is_deleted` (`is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($queryConfig);
    } elseif (!$DB->fieldExists('glpi_plugin_multticket_configs', 'displayed_additional_fields', false)) {
        $DB->doQuery("ALTER TABLE `glpi_plugin_multticket_configs` ADD COLUMN `displayed_additional_fields` LONGTEXT DEFAULT NULL AFTER `link_tickets`;");
        if (method_exists($DB, 'clearSchemaCache')) {
            $DB->clearSchemaCache();
        }
    }

    // Inicialização da configuração padrão na entidade raiz (ID 0)
    $checkConfig = $DB->request([
        'FROM'  => 'glpi_plugin_multticket_configs',
        'WHERE' => ['entities_id' => 0]
    ]);

    if (count($checkConfig) === 0) {
        $DB->insert('glpi_plugin_multticket_configs', [
            'entities_id'     => 0,
            'is_recursive'    => 1,
            'default_type'    => PluginMultticketTicketType::DEMAND,
            'default_urgency' => PluginMultticketUrgency::MEDIUM,
            'default_impact'  => PluginMultticketImpact::MEDIUM,
            'link_tickets'    => 0,
            'is_deleted'      => 0,
            'date_creation'   => date('Y-m-d H:i:s')
        ]);
    }

    // 3. Inicialização e vinculação de permissões por perfil (Aba Mult Ticket em Perfis)
    PluginMultticketProfile::install();

    // Registra a instalação na auditoria do sistema
    PluginMultticketAudit::logAction([
        'action'       => PluginMultticketAuditAction::PLUGIN_INSTALL,
        'details'      => 'Plugin Mult Ticket instalado com sucesso. Autor: Wilter Porto.',
        'entities_id'  => 0
    ]);

    return true;
}

/**
 * Desinstalação do plugin Mult Ticket
 *
 * @return boolean
 */
function plugin_multticket_uninstall(): bool {
    global $DB;

    // Registra evento antes da limpeza
    PluginMultticketAudit::logAction([
        'action'       => PluginMultticketAuditAction::PLUGIN_UNINSTALL,
        'details'      => 'Plugin Mult Ticket desinstalado.',
        'entities_id'  => 0
    ]);

    // Limpeza de permissões dos perfis
    PluginMultticketProfile::uninstall();

    // Exclusão das tabelas criadas pelo plugin
    $tables = [
        'glpi_plugin_multticket_configs',
        'glpi_plugin_multticket_audits'
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->dropTable($table);
        }
    }

    return true;
}

