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

include ("../../../inc/includes.php");

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
    Session::checkCSRF($_POST);

    $saved = PluginMultticketConfig::saveConfig($currentEntityId, $_POST);
    if ($saved) {
        Session::addMessageAfterRedirect(__('Configurações do Mult Ticket salvas com sucesso!', 'multticket'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Erro ao salvar configurações.', 'multticket'), false, ERROR);
    }
    Html::redirect($_SERVER['PHP_SELF']);
}

$config = PluginMultticketConfig::getConfigForEntity($currentEntityId);

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
                                <?php if (Session::isSuperAdmin()): ?>
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
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
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
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="link_tickets" name="link_tickets" value="1" <?php echo !empty($config['link_tickets']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="link_tickets">
                                <i class="ti ti-link me-1"></i> <?php echo __('Habilitar vinculação mútua entre chamados por padrão', 'multticket'); ?>
                            </label>
                            <div class="form-text text-muted">
                                <?php echo __('Quando ativado, os chamados gerados em um mesmo lote serão interligados automaticamente.', 'multticket'); ?>
                            </div>
                        </div>

                        <?php Html::closeForm(); ?>
                        <div class="mt-4">
                            <button type="submit" name="save_multticket_config" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> <?php echo __('Salvar Configurações', 'multticket'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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

            <?php if (Session::isSuperAdmin()): ?>
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

