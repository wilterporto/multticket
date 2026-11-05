<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Modelo de Configurações Multitenant do Plugin
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginMultticketConfig extends CommonDBTM {

    public static $rightname = 'config';

    public function getTypeName($nb = 0) {
        return __('Configurações do Mult Ticket', 'multticket');
    }

    /**
     * Retorna a configuração aplicável para uma entidade (com herança recursiva)
     *
     * @param int $entityId ID da entidade
     * @return array Configurações normalizadas
     */
    public static function getConfigForEntity(int $entityId = 0): array {
        global $DB;

        // Busca configuração específica da entidade
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_multticket_configs',
            'WHERE' => [
                'entities_id' => (int)$entityId,
                'is_deleted'  => 0
            ],
            'LIMIT' => 1
        ]);

        if (count($iterator) > 0) {
            return $iterator->current();
        }

        // Se for subentidade, busca nos ancestrais ou na raiz (0)
        if ($entityId > 0) {
            $ancestorIterator = $DB->request([
                'FROM'  => 'glpi_plugin_multticket_configs',
                'WHERE' => [
                    'entities_id'  => 0,
                    'is_deleted'   => 0
                ],
                'LIMIT' => 1
            ]);

            if (count($ancestorIterator) > 0) {
                return $ancestorIterator->current();
            }
        }

        // Padrão do sistema
        return [
            'id'              => 0,
            'entities_id'     => $entityId,
            'is_recursive'    => 1,
            'default_type'    => PluginMultticketTicketType::DEMAND,
            'default_urgency' => PluginMultticketUrgency::MEDIUM,
            'default_impact'  => PluginMultticketImpact::MEDIUM,
            'link_tickets'    => 0,
            'is_deleted'      => 0
        ];
    }

    /**
     * Salva ou atualiza as configurações para uma entidade
     *
     * @param int $entityId
     * @param array $data
     * @return boolean
     */
    public static function saveConfig(int $entityId, array $data): bool {
        global $DB;

        $entityId = (int)$entityId;

        $payload = [
            'default_type'    => isset($data['default_type']) ? (int)$data['default_type'] : PluginMultticketTicketType::DEMAND,
            'default_urgency' => isset($data['default_urgency']) ? (int)$data['default_urgency'] : PluginMultticketUrgency::MEDIUM,
            'default_impact'  => isset($data['default_impact']) ? (int)$data['default_impact'] : PluginMultticketImpact::MEDIUM,
            'link_tickets'    => !empty($data['link_tickets']) ? 1 : 0,
            'date_mod'        => date('Y-m-d H:i:s')
        ];

        // Verifica se registro já existe
        $check = $DB->request([
            'FROM'  => 'glpi_plugin_multticket_configs',
            'WHERE' => ['entities_id' => $entityId],
            'LIMIT' => 1
        ]);

        if (count($check) > 0) {
            $current = $check->current();
            $success = $DB->update('glpi_plugin_multticket_configs', $payload, ['id' => $current['id']]);
        } else {
            $payload['entities_id']   = $entityId;
            $payload['is_recursive']  = 1;
            $payload['is_deleted']    = 0;
            $payload['date_creation'] = date('Y-m-d H:i:s');
            $success = $DB->insert('glpi_plugin_multticket_configs', $payload);
        }

        if ($success) {
            PluginMultticketAudit::logAction([
                'action'      => PluginMultticketAuditAction::CONFIG_UPDATE,
                'details'     => sprintf('Configurações atualizadas para a entidade ID %d.', $entityId),
                'entities_id' => $entityId
            ]);
        }

        return (bool)$success;
    }
}

