<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Módulo de Auditoria Multitenant e Histórico de Ações
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginMultticketAudit extends CommonDBTM {

    public static $rightname = 'config';

    /**
     * Registra uma ação executada no histórico de auditoria
     *
     * @param array $data Dados da ação
     * @return int|false ID inserido ou false em caso de falha
     */
    public static function logAction(array $data) {
        global $DB;

        $userId      = Session::getLoginUserID();
        $userName    = $_SESSION['glpifriendlyname'] ?? ($_SESSION['glpiname'] ?? 'Sistema/Automático');
        $userProfile = $_SESSION['glpiactiveprofile']['name'] ?? 'N/A';
        $ipAddress   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $action           = $data['action'] ?? PluginMultticketAuditAction::BATCH_CREATE;
        $batchId          = $data['batch_id'] ?? null;
        $ticketsCount     = isset($data['tickets_count']) ? (int)$data['tickets_count'] : 0;
        $ticketsCreated   = isset($data['tickets_created']) && is_array($data['tickets_created']) 
            ? json_encode($data['tickets_created']) 
            : ($data['tickets_created'] ?? null);
        $entitiesSelected = isset($data['entities_selected']) && is_array($data['entities_selected'])
            ? json_encode($data['entities_selected'])
            : ($data['entities_selected'] ?? null);
        $details          = $data['details'] ?? '';
        $entitiesId       = isset($data['entities_id']) ? (int)$data['entities_id'] : (int)($_SESSION['glpiactive_entity'] ?? 0);

        try {
            return $DB->insert('glpi_plugin_multticket_audits', [
                'entities_id'       => $entitiesId,
                'users_id'          => (int)$userId,
                'user_name'         => $userName,
                'user_profile'      => $userProfile,
                'action'            => $action,
                'batch_id'          => $batchId,
                'tickets_count'     => $ticketsCount,
                'tickets_created'   => $ticketsCreated,
                'entities_selected' => $entitiesSelected,
                'details'           => $details,
                'ip_address'        => $ipAddress,
                'is_deleted'        => 0, // Exclusão lógica padrão
                'date_creation'     => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao registrar auditoria multticket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna a listagem de registros de auditoria com paginação limitada a 30 por página
     *
     * @param int $page Página atual (iniciando em 1)
     * @param int $perPage Limite por página (30 registros conforme padrão)
     * @param array $filters Filtros opcionais (entities_id, action, search, date_start, date_end)
     * @return array [rows => array, total => int, pages => int, current_page => int, per_page => int]
     */
    public static function getPaginatedAudits(int $page = 1, int $perPage = 30, array $filters = []): array {
        global $DB;

        $page = max(1, $page);
        $perPage = 30; // Forçado estritamente para 30 por página
        $offset = ($page - 1) * $perPage;

        $where = ['is_deleted' => 0];

        // Filtro Multitenant: superadmin pode filtrar por qualquer entidade; demais apenas a permitida
        if (isset($filters['entities_id']) && $filters['entities_id'] !== '' && $filters['entities_id'] !== 'all') {
            $where['entities_id'] = (int)$filters['entities_id'];
        } elseif (!Session::isSuperAdmin()) {
            $activeEntities = $_SESSION['glpiactiveentities'] ?? [];
            if (!empty($activeEntities)) {
                $where['entities_id'] = array_map('intval', $activeEntities);
            } else {
                $where['entities_id'] = (int)($_SESSION['glpiactive_entity'] ?? 0);
            }
        }

        if (!empty($filters['action'])) {
            $where['action'] = $filters['action'];
        }

        if (!empty($filters['date_start'])) {
            $where['date_creation >='] = $filters['date_start'] . ' 00:00:00';
        }

        if (!empty($filters['date_end'])) {
            $where['date_creation <='] = $filters['date_end'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where['OR'] = [
                'user_name LIKE'  => $search,
                'details LIKE'    => $search,
                'batch_id LIKE'   => $search,
                'ip_address LIKE' => $search
            ];
        }

        // Total de registros
        $countIterator = $DB->request([
            'COUNT' => 'total',
            'FROM'  => 'glpi_plugin_multticket_audits',
            'WHERE' => $where
        ]);

        $total = 0;
        foreach ($countIterator as $row) {
            $total = (int)$row['total'];
        }

        $pages = (int)ceil($total / $perPage);
        if ($pages < 1) {
            $pages = 1;
        }

        // Linhas paginadas
        $iterator = $DB->request([
            'FROM'     => 'glpi_plugin_multticket_audits',
            'WHERE'    => $where,
            'ORDERBY'  => 'id DESC',
            'START'    => $offset,
            'LIMIT'    => $perPage
        ]);

        $rows = [];
        foreach ($iterator as $row) {
            $rows[] = $row;
        }

        return [
            'rows'         => $rows,
            'total'        => $total,
            'pages'        => $pages,
            'current_page' => $page,
            'per_page'     => $perPage
        ];
    }

    /**
     * Valida se um registro de auditoria pode ser excluído
     * Regra: Dados só poderão ser excluídos caso não possuam dependência de dados em outras tabelas.
     * Exemplo: Se os chamados vinculados ao lote ainda estiverem ativos/abertos no GLPI.
     *
     * @param int $auditId ID do registro de auditoria
     * @return array [can_delete => bool, reason => string]
     */
    public static function checkDependencyBeforeDelete(int $auditId): array {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_multticket_audits',
            'WHERE' => ['id' => (int)$auditId, 'is_deleted' => 0],
            'LIMIT' => 1
        ]);

        if (count($iterator) === 0) {
            return ['can_delete' => false, 'reason' => __('Registro de auditoria não localizado.', 'multticket')];
        }

        $audit = $iterator->current();

        // Se houver chamados criados associados a este registro, verifica se ainda existem chamados abertos no GLPI
        if (!empty($audit['tickets_created'])) {
            $ticketIds = json_decode($audit['tickets_created'], true);
            if (is_array($ticketIds) && !empty($ticketIds)) {
                $ticketIds = array_map('intval', $ticketIds);

                // Verifica se há chamados ainda existentes e não deletados no GLPI
                $ticketsCheck = $DB->request([
                    'FROM'  => 'glpi_tickets',
                    'WHERE' => [
                        'id'         => $ticketIds,
                        'is_deleted' => 0
                    ]
                ]);

                if (count($ticketsCheck) > 0) {
                    return [
                        'can_delete' => false,
                        'reason'     => sprintf(
                            __('Exclusão bloqueada: este registro possui dependência com %d chamado(s) ainda ativos no GLPI.', 'multticket'),
                            count($ticketsCheck)
                        )
                    ];
                }
            }
        }

        return ['can_delete' => true, 'reason' => ''];
    }

    /**
     * Realiza a exclusão lógica do registro de auditoria
     * Aplica estritamente a exclusão lógica (is_deleted = 1), nunca exclusão física
     *
     * @param int $auditId
     * @return boolean
     */
    public static function logicalDelete(int $auditId): bool {
        global $DB;

        $dependency = self::checkDependencyBeforeDelete($auditId);
        if (!$dependency['can_delete']) {
            Session::addMessageAfterRedirect($dependency['reason'], false, ERROR);
            return false;
        }

        $success = $DB->update('glpi_plugin_multticket_audits', [
            'is_deleted' => 1
        ], [
            'id' => (int)$auditId
        ]);

        if ($success) {
            // Registra a ação de exclusão lógica
            self::logAction([
                'action'      => PluginMultticketAuditAction::LOGICAL_DELETE,
                'details'     => sprintf('Registro de auditoria ID %d marcado como excluído logicamente.', $auditId),
                'entities_id' => (int)($_SESSION['glpiactive_entity'] ?? 0)
            ]);
        }

        return (bool)$success;
    }
}

