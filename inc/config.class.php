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

    /** @var bool Evita verificações repetidas de schema na mesma requisição */
    private static bool $schemaChecked = false;

    public static function getTypeName($nb = 0) {
        return __('Configurações do Mult Ticket', 'multticket');
    }

    /**
     * Garante que a coluna displayed_additional_fields exista na tabela de configuração
     * (migração automática sem exigir reinstalação do plugin)
     *
     * @return void
     */
    public static function ensureSchemaUpToDate(): void {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        global $DB;

        if ($DB->tableExists('glpi_plugin_multticket_configs')
            && !$DB->fieldExists('glpi_plugin_multticket_configs', 'displayed_additional_fields', false)) {
            $DB->doQuery("ALTER TABLE `glpi_plugin_multticket_configs` ADD COLUMN `displayed_additional_fields` LONGTEXT DEFAULT NULL AFTER `link_tickets`;");
            if (method_exists($DB, 'clearSchemaCache')) {
                $DB->clearSchemaCache();
            }
        }
    }

    /**
     * Retorna todos os campos adicionais (Plugin Fields) criados para o tipo Ticket no GLPI
     *
     * @return array Lista de campos adicionais disponíveis agrupados ou indexados por ID
     */
    public static function getAvailableAdditionalFields(): array {
        global $DB;

        if (class_exists('Plugin') && method_exists('Plugin', 'isPluginActive') && !\Plugin::isPluginActive('fields')) {
            return [];
        }

        if (!class_exists('PluginFieldsDropdown') && class_exists('Plugin') && method_exists('Plugin', 'load')) {
            \Plugin::load('fields');
        }

        if (!$DB->tableExists('glpi_plugin_fields_containers') || !$DB->tableExists('glpi_plugin_fields_fields')) {
            return [];
        }

        // Busca containers vinculados ao itemtype Ticket
        $containersIterator = $DB->request([
            'FROM'    => 'glpi_plugin_fields_containers',
            'ORDERBY' => ['is_active DESC', 'label ASC', 'name ASC']
        ]);

        $ticketContainers = [];
        foreach ($containersIterator as $cRow) {
            $itemtypesRaw = (string)($cRow['itemtypes'] ?? '');
            $decoded = json_decode($itemtypesRaw, true);
            $isTicketContainer = false;

            if (is_array($decoded)) {
                foreach ($decoded as $it) {
                    if (strcasecmp((string)$it, 'Ticket') === 0) {
                        $isTicketContainer = true;
                        break;
                    }
                }
            } elseif (stripos($itemtypesRaw, 'Ticket') !== false) {
                $isTicketContainer = true;
            }

            if ($isTicketContainer) {
                $cLabel = $cRow['label'] ?: $cRow['name'];
                if (class_exists('PluginFieldsLabelTranslation')) {
                    $cItem = [
                        'itemtype' => 'PluginFieldsContainer',
                        'id'       => (int)$cRow['id'],
                        'label'    => $cLabel
                    ];
                    $translated = PluginFieldsLabelTranslation::getLabelFor($cItem);
                    if (!empty($translated)) {
                        $cLabel = $translated;
                    }
                }

                $ticketContainers[(int)$cRow['id']] = [
                    'id'           => (int)$cRow['id'],
                    'name'         => $cRow['name'],
                    'label'        => $cLabel,
                    'type'         => $cRow['type'],
                    'is_active'    => (int)$cRow['is_active'],
                    'entities_id'  => (int)$cRow['entities_id'],
                    'is_recursive' => (int)$cRow['is_recursive']
                ];
            }
        }

        if (empty($ticketContainers)) {
            return [];
        }

        $fieldsIterator = $DB->request([
            'FROM'    => 'glpi_plugin_fields_fields',
            'WHERE'   => [
                'plugin_fields_containers_id' => array_keys($ticketContainers),
                'NOT'                         => ['type' => 'header']
            ],
            'ORDERBY' => ['plugin_fields_containers_id ASC', 'ranking ASC', 'id ASC']
        ]);

        $typeLabels = [];
        if (class_exists('PluginFieldsField') && method_exists('PluginFieldsField', 'getTypes')) {
            try {
                $typeLabels = PluginFieldsField::getTypes(true);
            } catch (\Throwable $e) {
                $typeLabels = [];
            }
        }

        $availableFields = [];
        foreach ($fieldsIterator as $fRow) {
            $cId = (int)$fRow['plugin_fields_containers_id'];
            if (!isset($ticketContainers[$cId])) {
                continue;
            }

            $containerInfo = $ticketContainers[$cId];
            $fLabel = $fRow['label'] ?: $fRow['name'];
            if (class_exists('PluginFieldsLabelTranslation')) {
                $fItem = [
                    'itemtype' => 'PluginFieldsField',
                    'id'       => (int)$fRow['id'],
                    'label'    => $fLabel
                ];
                $translated = PluginFieldsLabelTranslation::getLabelFor($fItem);
                if (!empty($translated)) {
                    $fLabel = $translated;
                }
            }

            $fType = (string)$fRow['type'];
            $fTypeLabel = $typeLabels[$fType] ?? $fType;

            $availableFields[(int)$fRow['id']] = [
                'id'                          => (int)$fRow['id'],
                'name'                        => (string)$fRow['name'],
                'label'                       => (string)$fLabel,
                'type'                        => $fType,
                'type_label'                  => (string)$fTypeLabel,
                'plugin_fields_containers_id' => $cId,
                'container_name'              => (string)$containerInfo['name'],
                'container_label'             => (string)$containerInfo['label'],
                'container_type'              => (string)$containerInfo['type'],
                'container_is_active'         => (int)$containerInfo['is_active'],
                'ranking'                     => (int)($fRow['ranking'] ?? 0),
                'default_value'               => $fRow['default_value'] ?? '',
                'is_active'                   => (int)($fRow['is_active'] ?? 1),
                'is_readonly'                 => (int)($fRow['is_readonly'] ?? 0),
                'mandatory'                   => (int)($fRow['mandatory'] ?? 0),
                'multiple'                    => (int)($fRow['multiple'] ?? 0),
                'allowed_values'              => $fRow['allowed_values'] ?? ''
            ];
        }

        return $availableFields;
    }

    /**
     * Retorna a lista de IDs dos campos adicionais habilitados na configuração para exibição no formulário
     *
     * @param int $entityId
     * @return array<int>
     */
    public static function getDisplayedAdditionalFieldIds(int $entityId = 0): array {
        $config = self::getConfigForEntity($entityId);
        if (empty($config['displayed_additional_fields'])) {
            return [];
        }

        if (is_array($config['displayed_additional_fields'])) {
            return array_values(array_unique(array_map('intval', $config['displayed_additional_fields'])));
        }

        $decoded = json_decode((string)$config['displayed_additional_fields'], true);
        if (is_array($decoded)) {
            return array_values(array_unique(array_map('intval', $decoded)));
        }

        return [];
    }

    /**
     * Retorna os metadados completos apenas dos campos adicionais configurados para exibição no registro do chamado
     *
     * @param int $entityId
     * @return array
     */
    public static function getEnabledAdditionalFieldsForTicketForm(int $entityId = 0): array {
        $selectedIds = self::getDisplayedAdditionalFieldIds($entityId);
        if (empty($selectedIds)) {
            return [];
        }

        $allFields = self::getAvailableAdditionalFields();
        $enabled   = [];

        foreach ($selectedIds as $fId) {
            if (isset($allFields[$fId]) && (int)$allFields[$fId]['is_active'] === 1 && (int)$allFields[$fId]['container_is_active'] === 1) {
                $enabled[$fId] = $allFields[$fId];
            }
        }

        return $enabled;
    }

    /**
     * Retorna a configuração aplicável para uma entidade (com herança recursiva)
     *
     * @param int $entityId ID da entidade
     * @return array Configurações normalizadas
     */
    public static function getConfigForEntity(int $entityId = 0): array {
        global $DB;

        self::ensureSchemaUpToDate();

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
            'id'                          => 0,
            'entities_id'                 => $entityId,
            'is_recursive'                => 1,
            'default_type'                => PluginMultticketTicketType::DEMAND,
            'default_urgency'             => PluginMultticketUrgency::MEDIUM,
            'default_impact'              => PluginMultticketImpact::MEDIUM,
            'link_tickets'                => 0,
            'displayed_additional_fields' => '[]',
            'is_deleted'                  => 0
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

        self::ensureSchemaUpToDate();

        $entityId = (int)$entityId;

        $selectedAdditionalFields = [];
        if (isset($data['displayed_additional_fields']) && is_array($data['displayed_additional_fields'])) {
            foreach ($data['displayed_additional_fields'] as $fId) {
                $fId = (int)$fId;
                if ($fId > 0) {
                    $selectedAdditionalFields[] = $fId;
                }
            }
            $selectedAdditionalFields = array_values(array_unique($selectedAdditionalFields));
        }

        $payload = [
            'default_type'                => isset($data['default_type']) ? (int)$data['default_type'] : PluginMultticketTicketType::DEMAND,
            'default_urgency'             => isset($data['default_urgency']) ? (int)$data['default_urgency'] : PluginMultticketUrgency::MEDIUM,
            'default_impact'              => isset($data['default_impact']) ? (int)$data['default_impact'] : PluginMultticketImpact::MEDIUM,
            'link_tickets'                => !empty($data['link_tickets']) ? 1 : 0,
            'displayed_additional_fields' => json_encode($selectedAdditionalFields),
            'date_mod'                    => date('Y-m-d H:i:s')
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
                'details'     => sprintf('Configurações atualizadas para a entidade ID %d (%d campo(s) adicional(is) habilitado(s)).', $entityId, count($selectedAdditionalFields)),
                'entities_id' => $entityId
            ]);
        }

        return (bool)$success;
    }

    /**
     * Renderiza o controle HTML de um campo adicional configurado para exibição no formulário do Mult Ticket
     *
     * @param array $field Metadados do campo adicional
     * @param int $entityId ID da entidade ativa
     * @param array $postValues Valores submetidos anteriormente (para preservar em caso de erro)
     * @return void
     */
    public static function renderAdditionalFieldInput(array $field, int $entityId = 0, array $postValues = []): void {
        if (!class_exists('PluginFieldsDropdown') && class_exists('Plugin') && method_exists('Plugin', 'load')) {
            \Plugin::load('fields');
        }

        $type       = (string)$field['type'];
        $name       = (string)$field['name'];
        $label      = (string)$field['label'];
        $mandatory  = (int)($field['mandatory'] ?? 0) === 1;
        $isReadonly = (int)($field['is_readonly'] ?? 0) === 1;
        $multiple   = (int)($field['multiple'] ?? 0) === 1;
        $defaultVal = $field['default_value'] ?? '';

        $inputKey = ($type === 'dropdown') ? ('plugin_fields_' . $name . 'dropdowns_id') : $name;
        $value    = $postValues[$inputKey] ?? null;

        if ($value === null) {
            if (in_array($type, ['dropdown', 'yesno'], true) && $defaultVal === '') {
                $value = 0;
            } elseif ($defaultVal !== '') {
                $value = $defaultVal;
                if (in_array($type, ['date', 'datetime'], true) && $value === 'now') {
                    $value = date($type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
                }
            } else {
                $value = '';
            }
        }

        if ($multiple && !is_array($value)) {
            $decoded = json_decode((string)$value, true);
            $value   = is_array($decoded) ? $decoded : [];
        }

        $colClass = in_array($type, ['textarea', 'richtext', 'glpi_item'], true) ? 'col-12' : 'col-sm-6';
        $reqAttr  = ($mandatory && !$isReadonly) ? 'required' : '';
        $roAttr   = $isReadonly ? 'readonly' : '';

        echo "<div class='{$colClass}' data-additional-field-id='" . (int)$field['id'] . "'>";
        echo "<label class='form-label' for='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "'>";
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if ($mandatory) {
            echo " <span class='required-asterisk' style='color: red;'>*</span>";
        }
        echo "</label>";

        if ($type === 'text') {
            echo "<input type='text' class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
        } elseif ($type === 'number') {
            echo "<input type='number' step='any' class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
        } elseif ($type === 'url') {
            echo "<input type='url' class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' placeholder='https://...' value='" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
        } elseif ($type === 'textarea' || $type === 'richtext') {
            echo "<textarea class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' rows='3' {$reqAttr} {$roAttr}>" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "</textarea>";
        } elseif ($type === 'yesno') {
            Dropdown::showYesNo($inputKey, (int)$value, -1, [
                'width'    => '100%',
                'disabled' => $isReadonly
            ]);
        } elseif ($type === 'date') {
            $dateVal = !empty($value) && $value !== 'NULL' ? substr((string)$value, 0, 10) : '';
            echo "<input type='date' class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars($dateVal, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
        } elseif ($type === 'datetime') {
            $dtVal = '';
            if (!empty($value) && $value !== 'NULL') {
                $ts = strtotime((string)$value);
                if ($ts !== false) {
                    $dtVal = date('Y-m-d\TH:i', $ts);
                }
            }
            echo "<input type='datetime-local' class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars($dtVal, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
        } elseif ($type === 'dropdown') {
            $dropdownClass = class_exists('PluginFieldsDropdown')
                ? PluginFieldsDropdown::getClassname($name)
                : ('PluginFields' . ucfirst($name) . 'Dropdown');

            if (!class_exists($dropdownClass)) {
                $possiblePaths = [];
                if (defined('PLUGINFIELDS_CLASS_PATH')) {
                    $possiblePaths[] = PLUGINFIELDS_CLASS_PATH;
                }
                if (defined('GLPI_PLUGIN_DOC_DIR')) {
                    $possiblePaths[] = GLPI_PLUGIN_DOC_DIR . '/fields/inc';
                }
                if (defined('GLPI_VAR_DIR')) {
                    $possiblePaths[] = GLPI_VAR_DIR . '/_plugins/fields/inc';
                }
                $possiblePaths[] = '/var/lib/glpi/_plugins/fields/inc';
                $possiblePaths[] = 'C:/var/lib/glpi/_plugins/fields/inc';

                $fileName = strtolower($name) . 'dropdown.class.php';
                foreach ($possiblePaths as $dir) {
                    $fullPath = rtrim($dir, '/\\') . '/' . $fileName;
                    if (file_exists($fullPath)) {
                        include_once $fullPath;
                        if (class_exists($dropdownClass)) {
                            break;
                        }
                    }
                }
            }

            if ($multiple) {
                echo "<input type='hidden' name='_" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "_defined' value='1'>";
            }

            if (class_exists($dropdownClass) && is_subclass_of($dropdownClass, 'CommonDBTM')) {
                $dropdownClass::dropdown([
                    'name'        => $inputKey,
                    'value'       => $value,
                    'entity'      => $entityId,
                    'entity_sons' => true,
                    'multiple'    => $multiple,
                    'readonly'    => $isReadonly,
                    'display'     => true,
                    'width'       => '100%'
                ]);
            } else {
                echo "<input type='text' class='form-control' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars(is_array($value) ? implode(',', $value) : (string)$value, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
            }
        } elseif (preg_match('/^dropdown-(?<class>.+)$/i', $type, $matches)) {
            $dropdownClass = $matches['class'];
            if (class_exists($dropdownClass) && is_subclass_of($dropdownClass, 'CommonDBTM')) {
                $condition = [];
                $obj = new $dropdownClass();
                if ($obj->maybeDeleted()) {
                    $condition['is_deleted'] = 0;
                }
                if ($obj->maybeActive()) {
                    $condition['is_active'] = 1;
                }

                $ddParams = [
                    'name'        => $inputKey,
                    'value'       => $value,
                    'entity'      => ($dropdownClass === 'User') ? -1 : $entityId,
                    'entity_sons' => true,
                    'multiple'    => $multiple,
                    'readonly'    => $isReadonly,
                    'condition'   => $condition,
                    'display'     => true,
                    'width'       => '100%'
                ];
                if ($dropdownClass === 'User') {
                    $ddParams['right'] = 'all';
                }
                $dropdownClass::dropdown($ddParams);
            } else {
                echo "<input type='text' class='form-control' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars(is_array($value) ? implode(',', $value) : (string)$value, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
            }
        } elseif ($type === 'glpi_item') {
            $allowedRaw = !empty($field['allowed_values']) ? json_decode((string)$field['allowed_values'], true) : [];
            $allowedTypes = [];
            if (is_array($allowedRaw)) {
                foreach ($allowedRaw as $aType) {
                    if (is_string($aType) && class_exists($aType) && is_subclass_of($aType, 'CommonDBTM')) {
                        $allowedTypes[$aType] = $aType::getTypeName(1);
                    }
                }
            }
            $selType = $postValues['itemtype_' . $name] ?? '';
            $selId   = $postValues['items_id_' . $name] ?? '';
            echo "<div class='row g-2'>";
            echo "<div class='col-sm-6'>";
            echo "<select class='form-select' name='itemtype_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' {$reqAttr}>";
            echo "<option value=''>-----</option>";
            foreach ($allowedTypes as $aClass => $aLabel) {
                $sel = ($selType === $aClass) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($aClass, ENT_QUOTES, 'UTF-8') . "' {$sel}>" . htmlspecialchars($aLabel, ENT_QUOTES, 'UTF-8') . "</option>";
            }
            echo "</select>";
            echo "</div>";
            echo "<div class='col-sm-6'>";
            echo "<input type='number' min='0' class='form-control' name='items_id_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' placeholder='" . __('ID do Item', 'multticket') . "' value='" . htmlspecialchars((string)$selId, ENT_QUOTES, 'UTF-8') . "' {$reqAttr}>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<input type='text' class='form-control' id='addfield_" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' name='" . htmlspecialchars($inputKey, ENT_QUOTES, 'UTF-8') . "' value='" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "' {$reqAttr} {$roAttr}>";
        }

        echo "</div>";
    }
}

