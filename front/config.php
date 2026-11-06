<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Página de Configurações Gerais do Plugin
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

// Verificação de permissões
Session::checkLoginUser();
Session::checkRight("config", READ);

$userId      = (int)Session::getLoginUserID();
$userName    = $_SESSION['glpifriendlyname'] ?? ($_SESSION['glpiname'] ?? 'Usuário');
$userProfile = $_SESSION['glpiactiveprofile']['name'] ?? 'Padrão';

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

$currentEntityId = (int)($_SESSION['glpiactive_entity'] ?? 0);

// =========================================================================
// PROCESSAMENTO DE FORMULÁRIOS COM CSRF
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_multticket_config'])) {
    Session::checkRight("config", UPDATE);

    $saved = PluginMultticketConfig::saveConfig($currentEntityId, $_POST);
    if ($saved) {
        Session::addMessageAfterRedirect(__('Configurações do Mult Ticket salvas com sucesso!', 'multticket'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Erro ao salvar configurações.', 'multticket'), false, ERROR);
    }
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/multticket/front/config.php');
}

$config = PluginMultticketConfig::getConfigForEntity($currentEntityId);
$availableAdditionalFields = PluginMultticketConfig::getAvailableAdditionalFields();
$selectedAdditionalFieldIds = PluginMultticketConfig::getDisplayedAdditionalFieldIds($currentEntityId);

Html::header(__('Configurações - Mult Ticket', 'multticket'), $_SERVER['PHP_SELF'], 'config', 'PluginMultticketMenu');
?>

<div class="container-xl my-3">
    <!-- BARRA SUPERIOR: Informações do Usuário no Canto Superior Direito -->
    <div class="page-header d-print-none mb-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">
                    <i class="ti ti-settings me-1"></i> Mult Ticket &bull; <?php echo __('GLPI 10 & 11', 'multticket'); ?>
                </div>
                <h2 class="page-title text-primary">
                    <i class="ti ti-adjustments me-2"></i> <?php echo __('Parâmetros do Plugin Mult Ticket', 'multticket'); ?>
                </h2>
                <div class="text-muted small mt-1">
                    <?php echo __('Defina as diretrizes padrão para criação de chamados em lote nas entidades até o nível 2.', 'multticket'); ?>
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
                                <li>
                                    <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/multticket.form.php">
                                        <i class="ti ti-copy-plus me-2 text-primary"></i> <?php echo __('Criar Chamados Múltiplos', 'multticket'); ?>
                                    </a>
                                </li>
                                <?php if (PluginMultticketProfile::isSuperAdmin()): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/audit.php">
                                            <i class="ti ti-history me-2 text-warning"></i> <?php echo __('Auditoria (Superadmin)', 'multticket'); ?>
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

    <div class="row row-cards">
        <!-- FORMULÁRIO DE CONFIGURAÇÕES -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <h3 class="card-title text-primary">
                        <i class="ti ti-settings-cog me-2"></i> <?php echo __('Parâmetros Padrão do Formulário de Chamados', 'multticket'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($CFG_GLPI['root_doc'] . '/plugins/multticket/front/config.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <!-- TIPO PADRÃO (ENUMERADOR) -->
                        <div class="mb-3">
                            <label for="default_type" class="form-label">
                                <?php echo __('Tipo Padrão do Chamado', 'multticket'); ?>
                            </label>
                            <select class="form-select" id="default_type" name="default_type">
                                <?php echo PluginMultticketTicketType::getHtmlOptions((int)$config['default_type']); ?>
                            </select>
                            <div class="form-text text-muted">
                                <?php echo __('Tipo inicial pré-selecionado no formulário de abertura.', 'multticket'); ?>
                            </div>
                        </div>

                        <!-- URGÊNCIA PADRÃO (ENUMERADOR) -->
                        <div class="mb-3">
                            <label for="default_urgency" class="form-label">
                                <?php echo __('Urgência Padrão', 'multticket'); ?>
                            </label>
                            <select class="form-select" id="default_urgency" name="default_urgency">
                                <?php echo PluginMultticketUrgency::getHtmlOptions((int)$config['default_urgency']); ?>
                            </select>
                        </div>

                        <!-- IMPACTO PADRÃO (ENUMERADOR) -->
                        <div class="mb-3">
                            <label for="default_impact" class="form-label">
                                <?php echo __('Impacto Padrão', 'multticket'); ?>
                            </label>
                            <select class="form-select" id="default_impact" name="default_impact">
                                <?php echo PluginMultticketImpact::getHtmlOptions((int)$config['default_impact']); ?>
                            </select>
                        </div>

                        <!-- VINCULAR CHAMADOS POR PADRÃO -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="link_tickets" name="link_tickets" value="1" <?php echo !empty($config['link_tickets']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="link_tickets">
                                <i class="ti ti-link me-1"></i> <?php echo __('Habilitar vinculação mútua entre chamados por padrão', 'multticket'); ?>
                            </label>
                            <div class="form-text text-muted">
                                <?php echo __('Quando ativado, os chamados gerados em um mesmo lote serão interligados automaticamente.', 'multticket'); ?>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- SEÇÃO: CAMPOS ADICIONAIS DO CHAMADO (PLUGIN FIELDS) -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <h4 class="text-primary mb-1">
                                    <i class="ti ti-forms me-1"></i> <?php echo __('Campos Adicionais Criados (Exibição no Registro do Chamado)', 'multticket'); ?>
                                </h4>
                                <p class="text-muted small mb-0">
                                    <?php echo __('Selecione abaixo quais campos adicionais criados para Chamados (Ticket) devem ser exibidos para preenchimento na tela de Registro de Chamados Múltiplos.', 'multticket'); ?>
                                </p>
                            </div>
                            <?php if (!empty($availableAdditionalFields)): ?>
                                <div class="btn-group btn-group-sm ms-2">
                                    <button type="button" class="btn btn-outline-primary" id="btn-check-all-addfields">
                                        <i class="ti ti-check me-1"></i> <?php echo __('Marcar Todos', 'multticket'); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btn-uncheck-all-addfields">
                                        <i class="ti ti-x me-1"></i> <?php echo __('Desmarcar Todos', 'multticket'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($availableAdditionalFields)): ?>
                            <div class="alert alert-info mt-3 mb-0">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-info-circle fs-2 me-2"></i>
                                    <div>
                                        <strong><?php echo __('Nenhum campo adicional localizado para Chamados (Ticket).', 'multticket'); ?></strong><br>
                                        <span class="small">
                                            <?php echo __('Crie blocos e campos adicionais associados ao tipo "Chamado (Ticket)" em Configurar > Campos Adicionais para que eles sejam listados aqui.', 'multticket'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive border rounded mt-3">
                                <table class="table table-vcenter table-striped table-hover card-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="w-1 text-center"><?php echo __('Exibir', 'multticket'); ?></th>
                                            <th><?php echo __('Campo Adicional', 'multticket'); ?></th>
                                            <th><?php echo __('Bloco / Container', 'multticket'); ?></th>
                                            <th><?php echo __('Tipo', 'multticket'); ?></th>
                                            <th class="text-center"><?php echo __('Obrigatório', 'multticket'); ?></th>
                                            <th class="text-center"><?php echo __('Status', 'multticket'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($availableAdditionalFields as $fId => $fieldItem): ?>
                                            <?php
                                                $isChecked = in_array((int)$fId, $selectedAdditionalFieldIds, true);
                                                $isActive  = ((int)$fieldItem['is_active'] === 1) && ((int)$fieldItem['container_is_active'] === 1);
                                            ?>
                                            <tr>
                                                <td class="text-center">
                                                    <input class="form-check-input multticket-addfield-checkbox"
                                                           type="checkbox"
                                                           name="displayed_additional_fields[]"
                                                           id="addfield_cfg_<?php echo (int)$fId; ?>"
                                                           value="<?php echo (int)$fId; ?>"
                                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                                           <?php echo !$isActive ? 'disabled' : ''; ?>>
                                                </td>
                                                <td>
                                                    <label class="form-check-label fw-semibold cursor-pointer d-block" for="addfield_cfg_<?php echo (int)$fId; ?>">
                                                        <?php echo htmlspecialchars($fieldItem['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if ((int)$fieldItem['mandatory'] === 1): ?>
                                                            <span class="required-asterisk" title="<?php echo __('Campo de preenchimento obrigatório', 'multticket'); ?>">*</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <div class="text-muted small">
                                                        <code><?php echo htmlspecialchars($fieldItem['name'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-blue-lt">
                                                        <i class="ti ti-box me-1"></i>
                                                        <?php echo htmlspecialchars($fieldItem['container_label'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-lt">
                                                        <?php echo htmlspecialchars($fieldItem['type_label'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ((int)$fieldItem['mandatory'] === 1): ?>
                                                        <span class="badge bg-danger-lt"><?php echo __('Sim', 'multticket'); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><?php echo __('Não', 'multticket'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($isActive): ?>
                                                        <span class="badge bg-success-lt"><?php echo __('Ativo', 'multticket'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-lt"><?php echo __('Inativo', 'multticket'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <button type="submit" name="save_multticket_config" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> <?php echo __('Salvar Configurações', 'multticket'); ?>
                            </button>
                        </div>
                        <?php Html::closeForm(); ?>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnCheckAll = document.getElementById('btn-check-all-addfields');
            const btnUncheckAll = document.getElementById('btn-uncheck-all-addfields');
            const checkboxes = document.querySelectorAll('.multticket-addfield-checkbox:not(:disabled)');

            if (btnCheckAll) {
                btnCheckAll.addEventListener('click', function () {
                    checkboxes.forEach(cb => { cb.checked = true; });
                });
            }
            if (btnUncheckAll) {
                btnUncheckAll.addEventListener('click', function () {
                    checkboxes.forEach(cb => { cb.checked = false; });
                });
            }
        });
        </script>

        <!-- CARD LATERAL: SOBRE O PLUGIN E ATALHOS -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary-lt">
                    <h3 class="card-title text-primary mb-0">
                        <i class="ti ti-info-square-rounded me-2"></i> <?php echo __('Sobre o Plugin', 'multticket'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <strong><?php echo __('Nome:', 'multticket'); ?></strong> Mult Ticket
                        </li>
                        <li class="mb-2">
                            <strong><?php echo __('Autor:', 'multticket'); ?></strong> Wilter Porto
                        </li>
                        <li class="mb-2">
                            <strong><?php echo __('Versão:', 'multticket'); ?></strong> <?php echo PLUGIN_MULTTICKET_VERSION; ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php echo __('Compatibilidade:', 'multticket'); ?></strong> GLPI 10.x & GLPI 11.x
                        </li>
                        <li class="mb-2">
                            <strong><?php echo __('Escopo de Entidades:', 'multticket'); ?></strong> Nível 1 (Raiz) e Nível 2 (Subentidades diretas)
                        </li>
                        <li>
                            <strong><?php echo __('Licença:', 'multticket'); ?></strong> GPLv3+
                        </li>
                    </ul>
                </div>
            </div>

            <!-- CARD DE CONTROLE DE PERFIL -->
            <div class="card shadow-sm border-info mb-3">
                <div class="card-header bg-info-lt">
                    <h3 class="card-title text-info mb-0">
                        <i class="ti ti-users-group me-2"></i> <?php echo __('Permissões por Perfil', 'multticket'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        <?php echo __('O registro de chamados múltiplos é controlado pelas permissões de cada perfil. Configure quais perfis têm permissão de criação na aba <strong>Mult Ticket</strong> em cada perfil.', 'multticket'); ?>
                    </p>
                    <a href="<?php echo $CFG_GLPI['root_doc']; ?>/front/profile.php" class="btn btn-outline-info w-100">
                        <i class="ti ti-settings me-1"></i> <?php echo __('Gerenciar Perfis no GLPI', 'multticket'); ?>
                    </a>
                </div>
            </div>

            <?php if (PluginMultticketProfile::isSuperAdmin()): ?>
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning-lt">
                        <h3 class="card-title text-warning mb-0">
                            <i class="ti ti-shield me-2"></i> <?php echo __('Acesso Superadmin', 'multticket'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            <?php echo __('Acesse o módulo de auditoria com histórico de todas as ações de criação em lote e acessos.', 'multticket'); ?>
                        </p>
                        <a href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/multticket/front/audit.php" class="btn btn-outline-warning w-100">
                            <i class="ti ti-history me-1"></i> <?php echo __('Abrir Painel de Auditoria', 'multticket'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
Html::footer();

