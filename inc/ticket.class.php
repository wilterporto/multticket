<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Lógica Central de Criação de Chamados Múltiplos por Entidade
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginMultticketTicket extends CommonDBTM {

    public static $rightname = 'ticket';

    /**
     * Executa o processo de criação de múltiplos chamados independentes,
     * criando exatamente 1 chamado para cada entidade selecionada (até o nível 2).
     *
     * @param array $formData Dados preenchidos no formulário
     * @param array $selectedEntities IDs das entidades selecionadas
     * @return array [success => bool, message => string, created => array, errors => array, batch_id => string]
     */
    public static function createMultipleTickets(array $formData, array $selectedEntities): array {
        global $DB;

        // 1. Validação de Direitos de Criação de Chamado configurados no Perfil
        if (!PluginMultticketProfile::canCreate()) {
            return [
                'success'  => false,
                'message'  => __('Seu perfil de usuário não possui permissão para registrar chamados em múltiplas entidades.', 'multticket'),
                'created'  => [],
                'errors'   => [],
                'batch_id' => ''
            ];
        }

        // 2. Sanitização e Validação Estrita dos Campos Obrigatórios
        $title   = trim(strip_tags($formData['name'] ?? ''));
        $content = trim($formData['content'] ?? '');

        if (empty($title)) {
            return [
                'success'  => false,
                'message'  => __('O campo Título do Chamado é obrigatório!', 'multticket'),
                'created'  => [],
                'errors'   => [],
                'batch_id' => ''
            ];
        }

        if (empty($content)) {
            return [
                'success'  => false,
                'message'  => __('O campo Descrição / Conteúdo do Chamado é obrigatório!', 'multticket'),
                'created'  => [],
                'errors'   => [],
                'batch_id' => ''
            ];
        }

        if (empty($selectedEntities) || !is_array($selectedEntities)) {
            return [
                'success'  => false,
                'message'  => __('Nenhuma entidade selecionada! É obrigatório selecionar ao menos uma entidade.', 'multticket'),
                'created'  => [],
                'errors'   => [],
                'batch_id' => ''
            ];
        }

        // 3. Validação das Entidades: Apenas entidades existentes com level <= 2 e autorizadas
        $validEntities = [];
        $availableEntities = PluginMultticketEntity::getEntitiesUpToLevel2(true);

        foreach ($selectedEntities as $entId) {
            $entId = (int)$entId;
            if (isset($availableEntities[$entId]) && PluginMultticketEntity::isValidEntityForSelection($entId)) {
                $validEntities[$entId] = $availableEntities[$entId];
            }
        }

        if (empty($validEntities)) {
            return [
                'success'  => false,
                'message'  => __('Nenhuma das entidades selecionadas é válida ou possui nível até 2 permitido.', 'multticket'),
                'created'  => [],
                'errors'   => [],
                'batch_id' => ''
            ];
        }

        // 4. Validação e preparação dos Campos Adicionais configurados para exibição
        if (class_exists('Plugin') && method_exists('Plugin', 'isPluginActive') && \Plugin::isPluginActive('fields')) {
            if (!class_exists('PluginFieldsContainer') && method_exists('Plugin', 'load')) {
                \Plugin::load('fields');
            }
        }

        $currentEntityId = (int)($_SESSION['glpiactive_entity'] ?? 0);
        $enabledAdditionalFields = PluginMultticketConfig::getEnabledAdditionalFieldsForTicketForm($currentEntityId);
        $additionalFieldsByContainer = [];

        foreach ($enabledAdditionalFields as $fId => $fieldMeta) {
            $fType      = (string)$fieldMeta['type'];
            $fName      = (string)$fieldMeta['name'];
            $fLabel     = (string)$fieldMeta['label'];
            $fMandatory = (int)($fieldMeta['mandatory'] ?? 0) === 1;
            $fReadonly  = (int)($fieldMeta['is_readonly'] ?? 0) === 1;
            $cId        = (int)$fieldMeta['plugin_fields_containers_id'];

            if (!isset($additionalFieldsByContainer[$cId])) {
                $additionalFieldsByContainer[$cId] = [
                    'plugin_fields_containers_id' => $cId,
                ];
            }

            if ($fType === 'glpi_item') {
                $itemtypeKey = 'itemtype_' . $fName;
                $itemsIdKey  = 'items_id_' . $fName;
                $valType     = trim((string)($formData[$itemtypeKey] ?? ''));
                $valId       = (int)($formData[$itemsIdKey] ?? 0);

                if ($fMandatory && !$fReadonly && (empty($valType) || $valId <= 0)) {
                    return [
                        'success'  => false,
                        'message'  => sprintf(__('O campo adicional "%s" é obrigatório!', 'multticket'), $fLabel),
                        'created'  => [],
                        'errors'   => [],
                        'batch_id' => ''
                    ];
                }

                if (!empty($valType) && $valId > 0) {
                    $additionalFieldsByContainer[$cId][$itemtypeKey] = $valType;
                    $additionalFieldsByContainer[$cId][$itemsIdKey]  = $valId;
                }
                continue;
            }

            $inputKey = ($fType === 'dropdown') ? ('plugin_fields_' . $fName . 'dropdowns_id') : $fName;
            $rawValue = $formData[$inputKey] ?? null;

            if ($fType === 'datetime' && is_string($rawValue) && str_contains($rawValue, 'T')) {
                $rawValue = str_replace('T', ' ', $rawValue);
                if (strlen($rawValue) === 16) {
                    $rawValue .= ':00';
                }
            }

            if ($fMandatory && !$fReadonly && $fType !== 'yesno') {
                $isEmpty = ($rawValue === null || $rawValue === '' || $rawValue === []
                    || (($fType === 'dropdown' || str_starts_with($fType, 'dropdown-')) && (int)$rawValue === 0)
                    || (in_array($fType, ['date', 'datetime'], true) && $rawValue === 'NULL'));

                if ($isEmpty) {
                    return [
                        'success'  => false,
                        'message'  => sprintf(__('O campo adicional "%s" é obrigatório!', 'multticket'), $fLabel),
                        'created'  => [],
                        'errors'   => [],
                        'batch_id' => ''
                    ];
                }
            }

            if ($rawValue !== null) {
                $additionalFieldsByContainer[$cId][$inputKey] = $rawValue;
            } elseif (!empty($fieldMeta['multiple']) && isset($formData['_' . $inputKey . '_defined'])) {
                $additionalFieldsByContainer[$cId][$inputKey] = [];
                $additionalFieldsByContainer[$cId]['_' . $inputKey . '_defined'] = 1;
            }
        }

        // 5. Parâmetros do Chamado com Enumeradores e Defaults
        $type              = isset($formData['type']) ? (int)$formData['type'] : PluginMultticketTicketType::DEMAND;
        $urgency           = isset($formData['urgency']) ? (int)$formData['urgency'] : PluginMultticketUrgency::MEDIUM;
        $impact            = isset($formData['impact']) ? (int)$formData['impact'] : PluginMultticketImpact::MEDIUM;
        $priority          = isset($formData['priority']) ? (int)$formData['priority'] : CommonITILObject::computePriority($urgency, $impact);
        $itilcategoriesId  = !empty($formData['itilcategories_id']) ? (int)$formData['itilcategories_id'] : 0;
        $requesttypesId    = !empty($formData['requesttypes_id']) ? (int)$formData['requesttypes_id'] : 0;
        $requesterId       = !empty($formData['_users_id_requester']) ? (int)$formData['_users_id_requester'] : Session::getLoginUserID();
        $assignUserId      = !empty($formData['_users_id_assign']) ? (int)$formData['_users_id_assign'] : 0;
        $timeToResolve     = !empty($formData['time_to_resolve']) ? trim($formData['time_to_resolve']) : null;
        $linkTickets       = !empty($formData['link_tickets']);

        // Geração do identificador exclusivo do lote para rastreabilidade
        $batchId = uniqid('multtkt_', true);

        $createdTickets = [];
        $failedEntities = [];

        // 6. Criação sequencial de um chamado para cada entidade selecionada
        foreach ($validEntities as $entityId => $entityData) {
            $ticket = new Ticket();

            $input = [
                'name'                => $title,
                'content'             => $content,
                'entities_id'         => $entityId,
                'type'                => $type,
                'urgency'             => $urgency,
                'impact'              => $impact,
                'priority'            => $priority,
                'status'              => CommonITILObject::INCOMING, // Status inicial: Novo
                '_users_id_requester' => $requesterId,
            ];

            if ($itilcategoriesId > 0) {
                $input['itilcategories_id'] = $itilcategoriesId;
            }

            if ($requesttypesId > 0) {
                $input['requesttypes_id'] = $requesttypesId;
            }

            if ($assignUserId > 0) {
                $input['_users_id_assign'] = $assignUserId;
            }

            if (!empty($timeToResolve)) {
                $input['time_to_resolve'] = $timeToResolve;
            }

            // Inclui os campos adicionais também no input do chamado
            foreach ($additionalFieldsByContainer as $cId => $containerPayload) {
                foreach ($containerPayload as $k => $v) {
                    if ($k !== 'plugin_fields_containers_id') {
                        $input[$k] = $v;
                    }
                }
            }

            // Executa a adição do chamado no GLPI
            $ticketId = $ticket->add($input);

            if ($ticketId && $ticketId > 0) {
                // Persiste os valores de todos os containers de campos adicionais configurados
                if (!empty($additionalFieldsByContainer) && class_exists('PluginFieldsContainer')) {
                    foreach ($additionalFieldsByContainer as $cId => $containerPayload) {
                        if (count($containerPayload) <= 1) {
                            continue;
                        }
                        try {
                            $fieldsContainer = new PluginFieldsContainer();
                            $payloadToSave = $containerPayload;
                            $payloadToSave['items_id']    = (int)$ticketId;
                            $payloadToSave['itemtype']    = 'Ticket';
                            $payloadToSave['entities_id'] = (int)$entityId;
                            $fieldsContainer->updateFieldsValues($payloadToSave, 'Ticket', false);
                        } catch (\Throwable $e) {
                            error_log("Erro ao salvar campos adicionais no chamado #{$ticketId}: " . $e->getMessage());
                        }
                    }
                }

                $createdTickets[] = [
                    'ticket_id'    => (int)$ticketId,
                    'entity_id'    => $entityId,
                    'entity_name'  => $entityData['completename'],
                    'entity_level' => $entityData['level']
                ];

                // Se solicitada a ligação entre os chamados do lote
                if ($linkTickets && count($createdTickets) > 1 && class_exists('Ticket_Ticket')) {
                    $firstTicketId = $createdTickets[0]['ticket_id'];
                    $ticketLink = new Ticket_Ticket();
                    $ticketLink->add([
                        'tickets_id_1' => $firstTicketId,
                        'tickets_id_2' => $ticketId,
                        'link'         => Ticket_Ticket::LINK_TO
                    ]);
                }
            } else {
                $failedEntities[] = [
                    'entity_id'   => $entityId,
                    'entity_name' => $entityData['completename'],
                    'error'       => __('Falha ao persistir chamado na entidade via API GLPI.', 'multticket')
                ];
            }
        }

        // 6. Registro do Lote na Tabela de Auditoria Multitenant
        $ticketIdsList = array_column($createdTickets, 'ticket_id');
        $entityIdsList = array_column($createdTickets, 'entity_id');

        PluginMultticketAudit::logAction([
            'action'            => PluginMultticketAuditAction::BATCH_CREATE,
            'batch_id'          => $batchId,
            'tickets_count'     => count($createdTickets),
            'tickets_created'   => $ticketIdsList,
            'entities_selected' => $entityIdsList,
            'details'           => sprintf(
                'Lote criado com %d chamado(s) nas entidades com nível <= 2. Título: "%s".',
                count($createdTickets),
                $title
            ),
            'entities_id'       => (int)($_SESSION['glpiactive_entity'] ?? 0)
        ]);

        $isSuccess = count($createdTickets) > 0;
        $message = sprintf(
            __('Processamento concluído: %1$d chamado(s) criado(s) com sucesso em %2$d entidade(s).', 'multticket'),
            count($createdTickets),
            count($validEntities)
        );

        if (!empty($failedEntities)) {
            $message .= ' ' . sprintf(__('%d entidade(s) apresentaram inconsistência.', 'multticket'), count($failedEntities));
        }

        return [
            'success'  => $isSuccess,
            'message'  => $message,
            'created'  => $createdTickets,
            'errors'   => $failedEntities,
            'batch_id' => $batchId
        ];
    }
}

