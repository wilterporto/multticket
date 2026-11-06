<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Gerenciamento de Direitos e Permissões por Perfil de Usuário
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginMultticketProfile extends CommonDBTM {

    public static $rightname = 'profile';

    public static function getTypeName($nb = 0) {
        return __('Mult Ticket', 'multticket');
    }

    /**
     * Define a aba "Mult Ticket" dentro da tela de edição de cada Perfil do GLPI
     *
     * @param CommonGLPI $item
     * @param integer $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() === 'Profile') {
            return self::getTypeName();
        }
        return '';
    }

    /**
     * Exibe o conteúdo da aba no perfil selecionado
     *
     * @param CommonGLPI $item
     * @param integer $tabnum
     * @param integer $withtemplate
     * @return boolean
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() === 'Profile') {
            $profile = new self();
            $profile->showForm((int)$item->getID());
        }
        return true;
    }

    /**
     * Renderiza o formulário de concessão de direitos para o perfil
     *
     * @param integer $profiles_id
     * @param boolean $openform
     * @param boolean $closeform
     * @return void
     */
    public function showForm($ID, array $options = []) {
        global $DB;

        $profiles_id = (int)$ID;
        $openform    = $options['openform'] ?? true;
        $closeform   = $options['closeform'] ?? true;

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);

        // Garante que o registro de direito exista para o perfil
        $check = $DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => (int)$profiles_id,
                'name'        => 'plugin_multticket'
            ],
            'LIMIT' => 1
        ]);

        if (count($check) === 0) {
            $DB->insert('glpi_profilerights', [
                'profiles_id' => (int)$profiles_id,
                'name'        => 'plugin_multticket',
                'rights'      => 0
            ]);
            if (class_exists('ProfileRight')) {
                ProfileRight::cleanAllPossibleRights();
            }
        }

        echo "<div class='card shadow-sm my-2'>";
        echo "<div class='card-header bg-primary-lt d-flex align-items-center justify-content-between'>";
        echo "<h3 class='card-title text-primary mb-0'><i class='ti ti-shield-lock me-2'></i>" . __('Permissões do Plugin Mult Ticket', 'multticket') . "</h3>";
        echo "<span class='badge bg-blue-lt'>" . __('Autor: Wilter Porto', 'multticket') . "</span>";
        echo "</div>";

        echo "<div class='card-body'>";
        echo "<div class='alert alert-info py-2 px-3 small mb-3'>";
        echo "<i class='ti ti-info-circle me-1'></i>";
        echo __("Defina se este perfil possui permissão para registrar chamados simultâneos em múltiplas entidades (até o nível 2). Somente perfis com o direito <strong>Criar</strong> habilitado poderão acessar e executar o registro.", 'multticket');
        echo "</div>";

        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        if ($canedit && $openform) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $rights = [
            [
                'rights' => [
                    CREATE => __('Criar chamados em múltiplas entidades', 'multticket'),
                    READ   => __('Visualizar interface do plugin', 'multticket')
                ],
                'label'  => __('Mult Ticket - Chamados em Múltiplas Entidades', 'multticket'),
                'field'  => 'plugin_multticket'
            ]
        ];

        $matrix_options = [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2'
        ];

        $profile->displayRightsChoiceMatrix($rights, $matrix_options);

        if ($canedit && $closeform) {
            echo "<div class='d-flex justify-content-center mt-3'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo "<button type='submit' name='update' class='btn btn-primary px-4'>";
            echo "<i class='ti ti-device-floppy me-1'></i> " . _sx('button', 'Save');
            echo "</button>";
            echo "</div>";
            Html::closeForm();
        }

        echo "</div>"; // card-body
        echo "</div>"; // card
        return true;
    }

    /**
     * Verifica se o usuário autenticado é SuperAdmin ou possui permissões administrativas globais (config UPDATE e profile UPDATE)
     * Compatível com GLPI 10 e GLPI 11 (onde Session::isSuperAdmin() não existe)
     *
     * @return boolean
     */
    public static function isSuperAdmin(): bool {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $activeProfileId   = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
        $activeProfileName = (string)($_SESSION['glpiactiveprofile']['name'] ?? '');

        if ($activeProfileId === 4 || stripos($activeProfileName, 'super-admin') !== false) {
            return true;
        }

        if (Session::haveRight('config', UPDATE) && Session::haveRight('profile', UPDATE)) {
            return true;
        }

        return false;
    }

    /**
     * Valida se o usuário atualmente autenticado possui permissão de CRIAÇÃO de chamados múltiplos
     *
     * @return boolean
     */
    public static function canCreate(): bool {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $activeProfileId = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($activeProfileId <= 0) {
            return false;
        }

        // 1. Verificação primária via sessão nativa do GLPI
        if (Session::haveRight('plugin_multticket', CREATE)) {
            return true;
        }

        // 2. Fallback resiliente: consulta o banco para o perfil ativo caso a sessão ainda não tenha sido sincronizada
        return self::checkDbRight($activeProfileId, CREATE);
    }

    /**
     * Valida se o perfil possui direito de visualização da interface
     *
     * @return boolean
     */
    public static function canView(): bool {
        if (!Session::getLoginUserID()) {
            return false;
        }

        $activeProfileId = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($activeProfileId <= 0) {
            return false;
        }

        if (Session::haveRight('plugin_multticket', READ) || Session::haveRight('plugin_multticket', CREATE)) {
            return true;
        }

        return self::checkDbRight($activeProfileId, READ) || self::checkDbRight($activeProfileId, CREATE);
    }

    /**
     * Consulta diretamente na tabela glpi_profilerights o direito para o perfil indicado
     *
     * @param integer $profileId ID do perfil
     * @param integer $rightBit Bit do direito (CREATE ou READ)
     * @return boolean
     */
    public static function checkDbRight(int $profileId, int $rightBit): bool {
        global $DB;

        if ($profileId <= 0) {
            return false;
        }

        $iterator = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $profileId,
                'name'        => 'plugin_multticket'
            ],
            'LIMIT'  => 1
        ]);

        if (count($iterator) > 0) {
            $row = $iterator->current();
            $rights = (int)($row['rights'] ?? 0);

            // Atualiza o valor na sessão para otimizar chamadas subsequentes
            if (isset($_SESSION['glpiactiveprofile']) && is_array($_SESSION['glpiactiveprofile'])) {
                $_SESSION['glpiactiveprofile']['plugin_multticket'] = $rights;
            }

            return ($rights & $rightBit) === $rightBit;
        }

        return false;
    }

    /**
     * Instalação e propagação das permissões para todos os perfis durante a instalação do plugin
     *
     * @return void
     */
    public static function install(): void {
        global $DB;

        $profiles = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_profiles'
        ]);

        foreach ($profiles as $prof) {
            $profId = (int)$prof['id'];

            $check = $DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => [
                    'profiles_id' => $profId,
                    'name'        => 'plugin_multticket'
                ],
                'LIMIT' => 1
            ]);

            if (count($check) === 0) {
                // Perfil Super-Admin (ID 4) ou perfis administrativos recebem permissão completa inicial
                // Demais perfis recebem 0 (bloqueado), devendo ser configurados pelo administrador
                $isSuperAdminProfile = ($profId === 4) || (stripos($prof['name'], 'admin') !== false);
                $initialRights = $isSuperAdminProfile ? (CREATE | READ) : 0;

                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profId,
                    'name'        => 'plugin_multticket',
                    'rights'      => $initialRights
                ]);
            }
        }

        if (class_exists('ProfileRight')) {
            ProfileRight::cleanAllPossibleRights();
        }
    }

    /**
     * Limpeza das permissões do plugin ao desinstalar
     *
     * @return void
     */
    public static function uninstall(): void {
        global $DB;

        $DB->delete('glpi_profilerights', [
            'name' => 'plugin_multticket'
        ]);

        if (class_exists('ProfileRight')) {
            ProfileRight::cleanAllPossibleRights();
        }
    }
}

