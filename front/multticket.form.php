<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Formulário Principal para Criação de Múltiplos Chamados por Entidade
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

include ("../../../inc/includes.php");

// Verificação de permissão de acesso configurada no Perfil
Session::checkLoginUser();
if (!PluginMultticketProfile::canCreate()) {
    Html::displayRightError();
}

$userId      = (int)Session::getLoginUserID();
$userName    = $_SESSION['glpifriendlyname'] ?? ($_SESSION['glpiname'] ?? 'Usuário');
$userProfile = $_SESSION['glpiactiveprofile']['name'] ?? 'Padrão';

// Obtenção da data e hora do último acesso do usuário
$userObj = new User();
$lastLoginText = __('Primeiro acesso registrado', 'multticket');
if ($userObj->getFromDB($userId)) {
    if (!empty($userObj->fields['last_login'])) {
        $lastLoginText = Html::convDateTime($userObj->fields['last_login']);
    } elseif (!empty($_SESSION['glpilast_login'])) {
        $lastLoginText = Html::convDateTime($_SESSION['glpilast_login']);
    }
}

// Configurações padrão para a entidade atual
$currentEntityId = (int)($_SESSION['glpiactive_entity'] ?? 0);
$defaultConfig   = PluginMultticketConfig::getConfigForEntity($currentEntityId);

$processResult = null;

// =========================================================================
// PROCESSAMENTO DO FORMULÁRIO COM PROTEÇÃO CSRF
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_multticket'])) {
    Session::checkCSRF($_POST);

    $selectedEntities = isset($_POST['entities']) && is_array($_POST['entities'])
        ? array_map('intval', $_POST['entities'])
        : [];

    $processResult = PluginMultticketTicket::createMultipleTickets($_POST, $selectedEntities);

    if ($processResult['success']) {
        Session::addMessageAfterRedirect($processResult['message'], false, INFO);
    } else {
        Session::addMessageAfterRedirect($processResult['message'], false, ERROR);
    }
}

// =========================================================================
// CARREGAMENTO DE DADOS (ENTIDADES ATÉ O NÍVEL 2)
// =========================================================================
$availableEntities = PluginMultticketEntity::getEntitiesUpToLevel2(true);
$summaryCount = PluginMultticketEntity::getEntityCountSummary();

// Renderização do cabeçalho oficial do GLPI
Html::header(__('Chamados em Múltiplas Entidades', 'multticket'), $_SERVER['PHP_SELF'], 'helpdesk', 'PluginMultticketMenu');
?>

<div class="container-xl my-3">
    <!-- BARRA SUPERIOR: Título da Tela e Informações do Usuário no Canto Superior Direito -->
    <div class="page-header d-print-none mb-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">
                    <i class="ti ti-plug me-1"></i> Mult Ticket &bull; <?php echo __('GLPI 10 & 11', 'multticket'); ?>
                </div>
                <h2 class="page-title text-primary">
                    <i class="ti ti-copy-plus me-2"></i> <?php echo __('Registro de Chamados Múltiplos por Entidade', 'multticket'); ?>
                </h2>
                <div class="text-muted small mt-1">
                    <?php echo __('Crie chamados padronizados simultaneamente em entidades até o nível 2.', 'multticket'); ?>
                </div>
            </div>

            <!-- Canto superior direito: Usuário, Perfil e Último Acesso -->
            <div class="col-auto ms-auto">
                <div class="multticket-user-header d-flex align-items-center">
                    <div class="avatar bg-primary-lt text-primary rounded-circle me-3">
                        <i class="ti ti-user fs-3"></i>
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
                                <?php if (Session::isSuperAdmin()): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/audit.php">
                                            <i class="ti ti-history me-2 text-warning"></i> <?php echo __('Auditoria do Plugin (Superadmin)', 'multticket'); ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/config.php">
                                            <i class="ti ti-settings me-2 text-secondary"></i> <?php echo __('Configurações Gerais', 'multticket'); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
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

    <!-- RESULTADOS DO PROCESSAMENTO RECENTE -->
    <?php if ($processResult !== null && !empty($processResult['created'])): ?>
        <div class="card card-success mb-4 shadow-sm border-success">
            <div class="card-header bg-success-lt d-flex justify-content-between align-items-center">
                <h3 class="card-title text-success mb-0">
                    <i class="ti ti-circle-check me-2"></i> <?php echo __('Lote de Chamados Criado com Sucesso!', 'multticket'); ?>
                </h3>
                <span class="badge bg-success text-white">
                    <?php echo sprintf(__('%d chamados gerados', 'multticket'), count($processResult['created'])); ?>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter table-striped card-table mb-0">
                        <thead>
                            <tr>
                                <th><?php echo __('ID Chamado', 'multticket'); ?></th>
                                <th><?php echo __('Entidade Alvo', 'multticket'); ?></th>
                                <th><?php echo __('Nível Hierárquico', 'multticket'); ?></th>
                                <th class="text-end"><?php echo __('Ações', 'multticket'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processResult['created'] as $tkt): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/ticket.form.php?id=<?php echo $tkt['ticket_id']; ?>" target="_blank" class="fw-bold text-primary">
                                            <i class="ti ti-ticket me-1"></i> #<?php echo $tkt['ticket_id']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <i class="ti ti-building me-1 text-muted"></i>
                                        <?php echo htmlspecialchars($tkt['entity_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php
                                            $badge = PluginMultticketEntityLevel::getBadgeInfo((int)$tkt['entity_level']);
                                            echo sprintf(
                                                '<span class="badge %s"><i class="%s me-1"></i>%s</span>',
                                                $badge['badge_class'],
                                                $badge['icon'],
                                                $badge['label']
                                            );
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/ticket.form.php?id=<?php echo $tkt['ticket_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-external-link me-1"></i> <?php echo __('Abrir Chamado', 'multticket'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ALERTA DE ERRO DE VALIDAÇÃO (JS) -->
    <div id="multticket-validation-alert" class="alert alert-danger d-none mb-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <i class="ti ti-alert-circle fs-2 me-2"></i>
            <div>
                <strong><?php echo __('Atenção:', 'multticket'); ?></strong> <?php echo __('Por favor, selecione ao menos uma entidade na lista abaixo para prosseguir com a criação dos chamados.', 'multticket'); ?>
            </div>
        </div>
    </div>

    <!-- FORMULÁRIO PRINCIPAL -->
    <form id="multticket-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="row row-cards">
            <!-- COLUNA ESQUERDA: DADOS DO CHAMADO -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <h3 class="card-title text-primary">
                            <i class="ti ti-file-text me-2"></i> <?php echo __('Conteúdo do Chamado', 'multticket'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- TÍTULO DO CHAMADO (OBRIGATÓRIO COM ASTERISCO VERMELHO) -->
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <?php echo __('Título do Chamado', 'multticket'); ?> <span class="required-asterisk">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name" required placeholder="<?php echo __('Ex: Atualização preventiva de segurança / Manutenção programada', 'multticket'); ?>" maxlength="255">
                        </div>

                        <!-- DESCRIÇÃO / CONTEÚDO (OBRIGATÓRIO COM ASTERISCO VERMELHO) -->
                        <div class="mb-3">
                            <label for="content" class="form-label">
                                <?php echo __('Descrição / Conteúdo', 'multticket'); ?> <span class="required-asterisk">*</span>
                            </label>
                            <textarea class="form-control" id="content" name="content" rows="8" required placeholder="<?php echo __('Descreva os detalhes da solicitação que serão replicados para cada entidade selecionada...', 'multticket'); ?>"></textarea>
                        </div>

                        <!-- PROPRIEDADES ITIL COM ENUMERADORES -->
                        <div class="row g-3 mb-3">
                            <!-- TIPO (ENUMERADOR - OBRIGATÓRIO COM ASTERISCO VERMELHO) -->
                            <div class="col-sm-6">
                                <label for="type" class="form-label">
                                    <?php echo __('Tipo do Chamado', 'multticket'); ?> <span class="required-asterisk">*</span>
                                </label>
                                <select class="form-select" id="type" name="type" required>
                                    <?php echo PluginMultticketTicketType::getHtmlOptions((int)$defaultConfig['default_type']); ?>
                                </select>
                            </div>

                            <!-- URGÊNCIA (ENUMERADOR) -->
                            <div class="col-sm-6">
                                <label for="urgency" class="form-label">
                                    <?php echo __('Urgência', 'multticket'); ?>
                                </label>
                                <select class="form-select" id="urgency" name="urgency">
                                    <?php echo PluginMultticketUrgency::getHtmlOptions((int)$defaultConfig['default_urgency']); ?>
                                </select>
                            </div>

                            <!-- IMPACTO (ENUMERADOR) -->
                            <div class="col-sm-6">
                                <label for="impact" class="form-label">
                                    <?php echo __('Impacto', 'multticket'); ?>
                                </label>
                                <select class="form-select" id="impact" name="impact">
                                    <?php echo PluginMultticketImpact::getHtmlOptions((int)$defaultConfig['default_impact']); ?>
                                </select>
                            </div>

                            <!-- PRIORIDADE (ENUMERADOR) -->
                            <div class="col-sm-6">
                                <label for="priority" class="form-label">
                                    <?php echo __('Prioridade', 'multticket'); ?>
                                </label>
                                <select class="form-select" id="priority" name="priority">
                                    <?php echo PluginMultticketPriority::getHtmlOptions(PluginMultticketPriority::MEDIUM); ?>
                                </select>
                            </div>
                        </div>

                        <!-- ITIL CATEGORY & ORIGEM -->
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="itilcategories_id" class="form-label">
                                    <?php echo __('Categoria ITIL', 'multticket'); ?>
                                </label>
                                <?php
                                    ITILCategory::dropdown([
                                        'name'   => 'itilcategories_id',
                                        'value'  => 0,
                                        'entity' => $currentEntityId
                                    ]);
                                ?>
                            </div>
                            <div class="col-sm-6">
                                <label for="requesttypes_id" class="form-label">
                                    <?php echo __('Origem da Requisição', 'multticket'); ?>
                                </label>
                                <?php
                                    RequestType::dropdown([
                                        'name'  => 'requesttypes_id',
                                        'value' => 0
                                    ]);
                                ?>
                            </div>
                        </div>

                        <!-- ATRIBUIÇÕES -->
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">
                                    <?php echo __('Requerente', 'multticket'); ?>
                                </label>
                                <?php
                                    User::dropdown([
                                        'name'   => '_users_id_requester',
                                        'value'  => $userId,
                                        'entity' => $currentEntityId,
                                        'right'  => 'all'
                                    ]);
                                ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">
                                    <?php echo __('Técnico Atribuído (Opcional)', 'multticket'); ?>
                                </label>
                                <?php
                                    User::dropdown([
                                        'name'   => '_users_id_assign',
                                        'value'  => 0,
                                        'entity' => $currentEntityId,
                                        'right'  => 'own_ticket'
                                    ]);
                                ?>
                            </div>
                        </div>

                        <!-- OPÇÃO DE LIGAÇÃO ENTRE CHAMADOS GERADOS -->
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="link_tickets" name="link_tickets" value="1" <?php echo !empty($defaultConfig['link_tickets']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="link_tickets">
                                <i class="ti ti-link me-1"></i> <?php echo __('Vincular chamados gerados entre si (Ticket Link)', 'multticket'); ?>
                            </label>
                            <div class="form-text text-muted">
                                <?php echo __('Se marcado, todos os chamados criados neste lote serão interligados como chamados relacionados.', 'multticket'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA: SELEÇÃO DE ENTIDADES ATÉ O NÍVEL 2 -->
            <div class="col-lg-5">
                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title text-primary mb-0">
                            <i class="ti ti-sitemap me-2"></i> <?php echo __('Entidades Alvo', 'multticket'); ?> <span class="required-asterisk">*</span>
                        </h3>
                        <span class="badge bg-success-lt multticket-counter-badge">
                            <span id="multticket-selected-count">0</span> <?php echo __('selecionada(s)', 'multticket'); ?>
                        </span>
                    </div>

                    <div class="card-body pb-2">
                        <!-- INFORMAÇÕES DE HIERARQUIA -->
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="ti ti-info-circle me-1"></i>
                            <?php echo sprintf(
                                __('Listando entidades até o <strong>Nível 2</strong> (%1$d Raiz, %2$d Subentidades). Total: %3$d.', 'multticket'),
                                $summaryCount[1],
                                $summaryCount[2],
                                $summaryCount['total']
                            ); ?>
                        </div>

                        <!-- BARRA DE PESQUISA RÁPIDA -->
                        <div class="input-icon mb-3">
                            <span class="input-icon-addon">
                                <i class="ti ti-search"></i>
                            </span>
                            <input type="text" id="multticket-entity-search" class="form-control" placeholder="<?php echo __('Filtrar entidade por nome...', 'multticket'); ?>">
                        </div>

                        <!-- BOTÕES DE SELEÇÃO RÁPIDA -->
                        <div class="btn-group w-100 mb-3" role="group">
                            <button type="button" id="btn-select-all" class="btn btn-sm btn-outline-primary" title="<?php echo __('Selecionar Todas', 'multticket'); ?>">
                                <i class="ti ti-check-all me-1"></i> <?php echo __('Todas', 'multticket'); ?>
                            </button>
                            <button type="button" id="btn-select-level-1" class="btn btn-sm btn-outline-success" title="<?php echo __('Selecionar apenas Nível 1 (Raiz)', 'multticket'); ?>">
                                <i class="ti ti-building me-1"></i> <?php echo __('Nível 1', 'multticket'); ?>
                            </button>
                            <button type="button" id="btn-select-level-2" class="btn btn-sm btn-outline-info" title="<?php echo __('Selecionar apenas Nível 2 (Subentidades)', 'multticket'); ?>">
                                <i class="ti ti-corner-down-right me-1"></i> <?php echo __('Nível 2', 'multticket'); ?>
                            </button>
                            <button type="button" id="btn-deselect-all" class="btn btn-sm btn-outline-secondary" title="<?php echo __('Desmarcar Todas', 'multticket'); ?>">
                                <i class="ti ti-square-x me-1"></i> <?php echo __('Limpar', 'multticket'); ?>
                            </button>
                        </div>

                        <!-- LISTAGEM SCROLLÁVEL DE ENTIDADES -->
                        <div class="multticket-entities-container p-2">
                            <?php if (empty($availableEntities)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="ti ti-folder-off fs-1 d-block mb-2"></i>
                                    <?php echo __('Nenhuma entidade disponível até o nível 2 para o seu usuário.', 'multticket'); ?>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableEntities as $ent): ?>
                                    <div class="multticket-entity-row d-flex align-items-center">
                                        <div class="form-check m-0 me-2">
                                            <input class="form-check-input multticket-entity-checkbox" type="checkbox" name="entities[]" value="<?php echo $ent['id']; ?>" id="ent_<?php echo $ent['id']; ?>" data-level="<?php echo $ent['level']; ?>">
                                        </div>
                                        <label class="form-check-label flex-grow-1 cursor-pointer" for="ent_<?php echo $ent['id']; ?>">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-semibold text-truncate me-2" title="<?php echo htmlspecialchars($ent['completename'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($ent['completename'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <span class="<?php echo $ent['badge_class']; ?> text-nowrap">
                                                    <i class="<?php echo $ent['badge_icon']; ?> me-1"></i><?php echo $ent['level_label']; ?>
                                                </span>
                                            </div>
                                            <div class="text-muted small">
                                                ID: <?php echo $ent['id']; ?>
                                                <?php if (!empty($ent['comment'])): ?>
                                                    &bull; <?php echo htmlspecialchars(mb_strimwidth($ent['comment'], 0, 40, '...'), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- BOTÃO DE SUBMISSÃO -->
                    <div class="card-footer bg-transparent">
                        <button type="submit" name="submit_multticket" id="btn-submit-multticket" class="btn btn-primary w-100 py-2 fs-5">
                            <i class="ti ti-send me-2"></i> <?php echo __('Criar Chamados em Lote', 'multticket'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php Html::closeForm(); ?>
</div>

<?php
Html::footer();

