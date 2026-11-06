<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Painel de Auditoria e Histórico Multitenant (Exclusivo SuperAdmin)
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

// Compatibilidade de inicialização: GLPI 11 (Symfony Kernel) e GLPI 10 (Legacy)
if (!defined('GLPI_ROOT')) {
    include ("../../../inc/includes.php");
}

global $CFG_GLPI;

// Verificação estrita: Exclusivo para SuperAdmin
Session::checkLoginUser();
if (!PluginMultticketProfile::isSuperAdmin()) {
    Html::displayRightError();
}

$userId      = (int)Session::getLoginUserID();
$userName    = $_SESSION['glpifriendlyname'] ?? ($_SESSION['glpiname'] ?? 'Superadmin');
$userProfile = $_SESSION['glpiactiveprofile']['name'] ?? 'Super-Admin';

// Obtenção da data e hora do último acesso
$userObj = new User();
$lastLoginText = __('Primeiro acesso registrado', 'multticket');
if ($userObj->getFromDB($userId)) {
    if (!empty($userObj->fields['last_login'])) {
        $lastLoginText = Html::convDateTime($userObj->fields['last_login']);
    } elseif (!empty($_SESSION['glpilast_login'])) {
        $lastLoginText = Html::convDateTime($_SESSION['glpilast_login']);
    }
}

$auditUrl = $CFG_GLPI['root_doc'] . '/plugins/multticket/front/audit.php';

// =========================================================================
// PROCESSAMENTO DE EXCLUSÃO LÓGICA (PROTEÇÃO CONTRA DEPENDÊNCIA DE DADOS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logical_delete') {
    $auditId = isset($_POST['audit_id']) ? (int)$_POST['audit_id'] : 0;
    if ($auditId > 0) {
        $deleted = PluginMultticketAudit::logicalDelete($auditId);
        if ($deleted) {
            Session::addMessageAfterRedirect(__('Registro de auditoria excluído logicamente com sucesso.', 'multticket'), false, INFO);
        }
    }
    Html::redirect($auditUrl);
}

// =========================================================================
// FILTROS E PAGINAÇÃO (RIGOROSAMENTE 30 POR PÁGINA)
// =========================================================================
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage   = 30; // Limitado a 30 por página conforme especificação
$search    = isset($_GET['search']) ? trim(strip_tags($_GET['search'])) : '';
$action    = isset($_GET['action_filter']) ? trim(strip_tags($_GET['action_filter'])) : '';
$entFilter = isset($_GET['entities_id']) ? trim($_GET['entities_id']) : '';
$dateStart = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
$dateEnd   = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';

$filters = [
    'search'      => $search,
    'action'      => $action,
    'entities_id' => $entFilter,
    'date_start'  => $dateStart,
    'date_end'    => $dateEnd
];

$auditData = PluginMultticketAudit::getPaginatedAudits($page, $perPage, $filters);
$rows        = $auditData['rows'];
$totalPages  = $auditData['pages'];
$totalCount  = $auditData['total'];
$currentPage = $auditData['current_page'];

Html::header(__('Auditoria & Histórico - Mult Ticket', 'multticket'), $auditUrl, 'helpdesk', 'PluginMultticketMenu');
?>

<div class="container-xl my-3">
    <!-- BARRA SUPERIOR: Cabeçalho da Tela e Informações do Usuário no Canto Superior Direito -->
    <div class="page-header d-print-none mb-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">
                    <i class="ti ti-shield-lock me-1"></i> Mult Ticket &bull; <?php echo __('Painel Exclusivo Superadmin', 'multticket'); ?>
                </div>
                <h2 class="page-title text-primary">
                    <i class="ti ti-history me-2"></i> <?php echo __('Módulo de Auditoria & Histórico de Execuções', 'multticket'); ?>
                </h2>
                <div class="text-muted small mt-1">
                    <?php echo __('Rastreabilidade completa de ações, lotes de chamados criados, acessos e integridade de dados.', 'multticket'); ?>
                </div>
            </div>

            <!-- Canto superior direito: Usuário, Perfil e Último Acesso -->
            <div class="col-auto ms-auto">
                <div class="multticket-user-header d-flex align-items-center">
                    <div class="avatar bg-warning-lt text-warning rounded-circle me-3">
                        <i class="ti ti-shield-check fs-3"></i>
                    </div>
                    <div class="text-end">
                        <div class="dropdown">
                            <a href="#" class="multticket-user-name text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li class="dropdown-header">
                                    <i class="ti ti-id-badge me-1"></i> <?php echo sprintf(__('Perfil: %s', 'multticket'), htmlspecialchars($userProfile, ENT_QUOTES, 'UTF-8')); ?>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/front/user.form.php?id=<?php echo $userId; ?>">
                                        <i class="ti ti-user-circle me-2"></i> <?php echo __('Meu Cadastro / Perfil', 'multticket'); ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/multticket.form.php">
                                        <i class="ti ti-copy-plus me-2 text-primary"></i> <?php echo __('Criar Chamados Múltiplos', 'multticket'); ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/config.php">
                                        <i class="ti ti-settings me-2 text-secondary"></i> <?php echo __('Configurações Gerais', 'multticket'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="multticket-last-login">
                            <i class="ti ti-clock me-1"></i> <?php echo sprintf(__('Último acesso: %s', 'multticket'), htmlspecialchars($lastLoginText, ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD DE FILTROS MULTITENANT -->
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light-lt py-2">
            <h4 class="card-title text-muted mb-0">
                <i class="ti ti-filter me-2"></i> <?php echo __('Filtros de Auditoria', 'multticket'); ?>
            </h4>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($auditUrl, ENT_QUOTES, 'UTF-8'); ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?php echo __('Pesquisa Textual', 'multticket'); ?></label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="<?php echo __('Usuário, lote, IP ou detalhes...', 'multticket'); ?>" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted"><?php echo __('Ação', 'multticket'); ?></label>
                    <select name="action_filter" class="form-select form-select-sm">
                        <option value=""><?php echo __('Todas as Ações', 'multticket'); ?></option>
                        <?php foreach (PluginMultticketAuditAction::getAll() as $actVal => $actLabel): ?>
                            <option value="<?php echo $actVal; ?>" <?php echo ($action === $actVal) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($actLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted"><?php echo __('Data Inicial', 'multticket'); ?></label>
                    <input type="date" name="date_start" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateStart, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted"><?php echo __('Data Final', 'multticket'); ?></label>
                    <input type="date" name="date_end" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateEnd, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="ti ti-search me-1"></i> <?php echo __('Filtrar', 'multticket'); ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($auditUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-refresh me-1"></i> <?php echo __('Limpar', 'multticket'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABELA DE REGISTROS DE AUDITORIA (PAGINADA A 30 POR PÁGINA) -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title text-primary mb-0">
                <i class="ti ti-list me-2"></i> <?php echo __('Histórico de Ações Executadas', 'multticket'); ?>
            </h3>
            <span class="badge bg-secondary-lt">
                <?php echo sprintf(__('%1$d registros no total &bull; Mostrando até %2$d por página', 'multticket'), $totalCount, $perPage); ?>
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-vcenter table-striped card-table">
                    <thead>
                        <tr>
                            <th><?php echo __('Data / Hora', 'multticket'); ?></th>
                            <th><?php echo __('Usuário', 'multticket'); ?></th>
                            <th><?php echo __('Perfil', 'multticket'); ?></th>
                            <th><?php echo __('Ação', 'multticket'); ?></th>
                            <th><?php echo __('Lote / Chamados', 'multticket'); ?></th>
                            <th><?php echo __('IP', 'multticket'); ?></th>
                            <th class="text-end"><?php echo __('Ações', 'multticket'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="ti ti-database-off fs-2 d-block mb-2"></i>
                                    <?php echo __('Nenhum registro de auditoria encontrado com os filtros aplicados.', 'multticket'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    $actionInfo = PluginMultticketAuditAction::getBadgeInfo($row['action']);
                                    $ticketsCreated = !empty($row['tickets_created']) ? json_decode($row['tickets_created'], true) : [];
                                    $ticketsCount   = (int)$row['tickets_count'];
                                ?>
                                <tr>
                                    <td class="text-nowrap small">
                                        <i class="ti ti-calendar me-1 text-muted"></i>
                                        <?php echo Html::convDateTime($row['date_creation']); ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($row['user_name'] ?: 'Sistema', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="text-muted small">
                                            ID: <?php echo $row['users_id']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-blue-lt">
                                            <?php echo htmlspecialchars($row['user_profile'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $actionInfo['badge_class']; ?>">
                                            <i class="<?php echo $actionInfo['icon']; ?> me-1"></i>
                                            <?php echo PluginMultticketAuditAction::getLabel($row['action']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ticketsCount > 0): ?>
                                            <span class="badge bg-success-lt me-1">
                                                <i class="ti ti-ticket me-1"></i> <?php echo sprintf(__('%d chamados', 'multticket'), $ticketsCount); ?>
                                            </span>
                                            <?php if (is_array($ticketsCreated) && !empty($ticketsCreated)): ?>
                                                <div class="mt-1 small">
                                                    <?php
                                                        $links = [];
                                                        foreach (array_slice($ticketsCreated, 0, 5) as $tId) {
                                                            $links[] = sprintf(
                                                                '<a href="%s/front/ticket.form.php?id=%d" target="_blank" class="badge bg-light text-primary border">#%d</a>',
                                                                $CFG_GLPI['root_doc'],
                                                                $tId,
                                                                $tId
                                                            );
                                                        }
                                                        echo implode(' ', $links);
                                                        if (count($ticketsCreated) > 5) {
                                                            echo sprintf(' <span class="text-muted small">+%d</span>', count($ticketsCreated) - 5);
                                                        }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small"><?php echo htmlspecialchars($row['details'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap small text-muted">
                                        <code><?php echo htmlspecialchars($row['ip_address'] ?: '127.0.0.1', ENT_QUOTES, 'UTF-8'); ?></code>
                                    </td>
                                    <td class="text-end">
                                        <!-- Formulário com proteção CSRF para Exclusão Lógica -->
                                        <form method="post" action="<?php echo htmlspecialchars($auditUrl, ENT_QUOTES, 'UTF-8'); ?>" class="d-inline" onsubmit="return confirm('<?php echo __('Confirmar exclusão lógica deste registro de auditoria? (Apenas permitido se não houver dependências ativas)', 'multticket'); ?>');">
                                            <input type="hidden" name="action" value="logical_delete">
                                            <input type="hidden" name="audit_id" value="<?php echo $row['id']; ?>">
                                            <?php Html::closeForm(); ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('Exclusão Lógica (Soft Delete)', 'multticket'); ?>">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONTROLES DE PAGINAÇÃO (30 POR PÁGINA) -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <p class="m-0 text-muted small">
                    <?php echo sprintf(
                        __('Mostrando página <strong>%1$d</strong> de <strong>%2$d</strong> (Total de %3$d registros)', 'multticket'),
                        $currentPage,
                        $totalPages,
                        $totalCount
                    ); ?>
                </p>
                <ul class="pagination m-0 ms-auto">
                    <!-- Página Anterior -->
                    <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars($auditUrl . '?page=' . ($currentPage - 1) . '&search=' . urlencode($search) . '&action_filter=' . urlencode($action) . '&entities_id=' . urlencode($entFilter) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd), ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="ti ti-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage   = min($totalPages, $currentPage + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <li class="page-item <?php echo ($p === $currentPage) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($auditUrl . '?page=' . $p . '&search=' . urlencode($search) . '&action_filter=' . urlencode($action) . '&entities_id=' . urlencode($entFilter) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Próxima Página -->
                    <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars($auditUrl . '?page=' . ($currentPage + 1) . '&search=' . urlencode($search) . '&action_filter=' . urlencode($action) . '&entities_id=' . urlencode($entFilter) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd), ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="ti ti-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
Html::footer();

