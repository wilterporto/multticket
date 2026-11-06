<?php
/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Gerenciador de Entidades até o Nível 2
 * ---------------------------------------------------------------------
 * @author    Wilter Porto
 * @copyright 2026 Wilter Porto
 * @license   GPLv3+
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginMultticketEntity extends CommonDBTM
{

    public static $rightname = 'entity';

    /**
     * Retorna a lista de entidades disponíveis limitadas rigorosamente até o nível 2
     * (Nível 1 = Raiz, Nível 2 = Subentidades diretas)
     * Respeita os limites multitenant e as entidades autorizadas na sessão do usuário.
     *
     * @param bool $onlyAccessible Se true, filtra pelas entidades autorizadas para o usuário ativo
     * @return array Lista de entidades com id, name, completename, level e metadados
     */
    public static function getEntitiesUpToLevel2(bool $onlyAccessible = true): array
    {
        global $DB;

        // Condição restritiva essencial: Apenas entidades com level <= 2
        $where = [
            'level' => ['<=', PluginMultticketEntityLevel::MAX_ALLOWED_LEVEL]
        ];

        // Se o usuário não for superadmin e deve respeitar acessos
        if ($onlyAccessible && !PluginMultticketProfile::isSuperAdmin()) {
            $activeEntities = $_SESSION['glpiactiveentities'] ?? [];
            if (!empty($activeEntities) && is_array($activeEntities)) {
                $where['id'] = array_map('intval', $activeEntities);
            } else {
                // Caso não tenha entidades ativas identificadas, restringe à entidade atual
                $where['id'] = (int) ($_SESSION['glpiactive_entity'] ?? 0);
            }
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'completename', 'entities_id', 'level', 'comment'],
            'FROM' => 'glpi_entities',
            'WHERE' => $where,
            'ORDERBY' => ['level ASC', 'completename ASC']
        ]);

        $entities = [];
        foreach ($iterator as $row) {
            $level = (int) $row['level'];
            $badge = PluginMultticketEntityLevel::getBadgeInfo($level);

            $entities[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => $row['name'] ?: __('(Sem nome)', 'multticket'),
                'completename' => $row['completename'] ?: $row['name'],
                'entities_id' => (int) $row['entities_id'],
                'level' => $level,
                'level_label' => PluginMultticketEntityLevel::getLabel($level),
                'badge_class' => $badge['badge_class'],
                'badge_icon' => $badge['icon'],
                'comment' => $row['comment'] ?? ''
            ];
        }

        return $entities;
    }

    /**
     * Valida se uma determinada entidade é válida para seleção:
     * 1. Deve existir no banco de dados.
     * 2. Deve ter nível <= 2.
     * 3. O usuário autenticado deve possuir permissão de acesso a ela.
     *
     * @param int $entityId ID da entidade a verificar
     * @return boolean
     */
    public static function isValidEntityForSelection(int $entityId): bool
    {
        global $DB;

        $entityId = (int) $entityId;

        // Verifica na base se o level é <= 2
        $iterator = $DB->request([
            'SELECT' => ['id', 'level'],
            'FROM' => 'glpi_entities',
            'WHERE' => [
                'id' => $entityId,
                'level' => ['<=', PluginMultticketEntityLevel::MAX_ALLOWED_LEVEL]
            ],
            'LIMIT' => 1
        ]);

        if (count($iterator) === 0) {
            return false;
        }

        // Se for SuperAdmin, tem permissão irrestrita
        if (PluginMultticketProfile::isSuperAdmin()) {
            return true;
        }

        // Validação de acesso multitenant da sessão
        return Session::haveAccessToEntity($entityId);
    }

    /**
     * Retorna a contagem agrupada de entidades por nível
     *
     * @return array [1 => total_nivel_1, 2 => total_nivel_2, 'total' => total_geral]
     */
    public static function getEntityCountSummary(): array
    {
        $entities = self::getEntitiesUpToLevel2(true);
        $summary = [
            1 => 0,
            2 => 0,
            'total' => count($entities)
        ];

        foreach ($entities as $ent) {
            if ($ent['level'] === 1) {
                $summary[1]++;
            } elseif ($ent['level'] === 2) {
                $summary[2]++;
            }
        }

        return $summary;
    }
}

