<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\AdminNavigationRbacService;
use App\Services\AdminThemeService;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;


/**
 * TICKETING_PORTAL_ADMIN_MANAGEMENT_READ_V1
 *
 * D2A1 is deliberately read-only.
 *
 * Permission:
 *   ticketing.project.manage
 *
 * Writes are introduced only after the read/admin shell
 * is proven on DEV.
 */
final class PortalManagementService
{
    private PDO $ticketing;


    public function __construct(
        ?PDO $ticketing = null
    ) {
        $this->ticketing =
            $ticketing
            ??
            (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );


        $this->ticketing
            ->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );


        $this->ticketing
            ->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );
    }


    public function adminPage(
        int $userId,
        string $portalReference = '',
        int $editItemId = 0
    ): array {

        $this->authorize(
            $userId
        );


        $portals =
            $this->portals();


        if ($portals === []) {
            throw new RuntimeException(
                'portal_catalog_empty'
            );
        }


        $selected =
            $this->selectPortal(
                $portals,
                $portalReference
            );


        $portalId =
            (int) $selected[
                'portal_id'
            ];


        $projectId =
            (int) $selected[
                'project_id'
            ];


        $context = [
            'project_id' =>
                $projectId,

            'realm_id' =>
                (int) $selected[
                    'realm_id'
                ],

            'portal_id' =>
                $portalId,

            'project_reference' =>
                (string) $selected[
                    'project_reference'
                ],

            'realm_reference' =>
                (string) $selected[
                    'realm_reference'
                ],

            'portal_reference' =>
                (string) $selected[
                    'portal_reference'
                ],

            'project_title' =>
                (string) $selected[
                    'project_title'
                ],

            'realm_title' =>
                (string) $selected[
                    'realm_title'
                ],

            'portal_title' =>
                (string) $selected[
                    'portal_title'
                ],
        ];


        $effectivePage =
            (
                new TicketingPortalLandingService()
            )->page(
                $context
            );


        /*
         * TICKETING_PORTAL_ADMIN_FORM_READ_V1
         */
        $editingItem =
            $editItemId > 0
                ? $this->landingItem(
                    $portalId,
                    $editItemId
                )
                : null;


        $themePresets =
            (
                new AdminThemeService()
            )->presets();


        return [
            'portals' =>
                $portals,

            'selected' =>
                $selected,

            'hosts' =>
                $this->hosts(
                    $portalId
                ),

            'project_brand_overrides' =>
                $this->settings(
                    'ticketing_support_project_brand_settings',
                    'project_id',
                    $projectId
                ),

            'portal_brand_overrides' =>
                $this->settings(
                    'ticketing_support_portal_brand_settings',
                    'portal_id',
                    $portalId
                ),

            'landing_setting_overrides' =>
                $this->settings(
                    'ticketing_support_portal_landing_settings',
                    'portal_id',
                    $portalId
                ),

            'landing_item_overrides' =>
                $this->landingItems(
                    $portalId
                ),

            'effective_page' =>
                $effectivePage,

            'effective_theme' =>
                is_array(
                    $effectivePage[
                        'theme'
                    ]
                    ?? null
                )
                    ? $effectivePage[
                        'theme'
                    ]
                    : [],
            'theme_presets' =>
                is_array(
                    $themePresets
                )
                    ? $themePresets
                    : [],

            'editing_item' =>
                $editingItem,
        ];
    }


    private function portals(): array
    {
        return
            $this->ticketing
                ->query("
                    SELECT
                        p.id
                            AS portal_id,

                        p.public_reference
                            AS portal_reference,

                        p.project_id,
                        p.realm_id,

                        p.code
                            AS portal_code,

                        p.title
                            AS portal_title,

                        p.status
                            AS portal_status,

                        r.public_reference
                            AS realm_reference,

                        r.code
                            AS realm_code,

                        r.title
                            AS realm_title,

                        sp.public_reference
                            AS project_reference,

                        sp.code
                            AS project_code,

                        sp.title
                            AS project_title

                    FROM
                        ticketing_support_portals p

                    INNER JOIN
                        ticketing_support_realms r

                      ON r.id =
                            p.realm_id

                     AND r.project_id =
                            p.project_id

                    INNER JOIN
                        ticketing_support_projects sp

                      ON sp.id =
                            p.project_id

                    WHERE
                        p.archived_at IS NULL

                      AND
                        r.archived_at IS NULL

                      AND
                        sp.archived_at IS NULL

                    ORDER BY
                        sp.sort_order,
                        sp.id,
                        r.sort_order,
                        r.id,
                        p.id
                ")
                ->fetchAll()
            ?: [];
    }


    private function selectPortal(
        array $portals,
        string $portalReference
    ): array {

        $portalReference =
            trim(
                $portalReference
            );


        if ($portalReference === '') {
            return $portals[0];
        }


        foreach (
            $portals
            as $portal
        ) {

            if (
                (string) (
                    $portal[
                        'portal_reference'
                    ]
                    ?? ''
                )
                === $portalReference
            ) {
                return $portal;
            }
        }


        throw new RuntimeException(
            'portal_not_found'
        );
    }


    private function hosts(
        int $portalId
    ): array {

        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        id,
                        public_reference,
                        portal_id,
                        hostname,
                        requires_https,
                        canonical_host_slot,
                        status,
                        created_at,
                        updated_at

                    FROM
                        ticketing_support_portal_hosts

                    WHERE
                        portal_id = ?

                    ORDER BY
                        canonical_host_slot IS NULL,
                        canonical_host_slot,
                        id
                ");


        $statement->execute([
            $portalId,
        ]);


        return
            $statement->fetchAll()
            ?: [];
    }


    private function settings(
        string $table,
        string $scopeColumn,
        int $scopeId
    ): array {

        $allowed = [
            'ticketing_support_project_brand_settings'
                => 'project_id',

            'ticketing_support_portal_brand_settings'
                => 'portal_id',

            'ticketing_support_portal_landing_settings'
                => 'portal_id',
        ];


        if (
            ($allowed[$table] ?? null)
            !== $scopeColumn
        ) {
            throw new RuntimeException(
                'portal_admin_setting_scope_invalid'
            );
        }


        $statement =
            $this->ticketing
                ->prepare("
                    SELECT
                        id,
                        setting_key,
                        setting_value,
                        value_type,
                        is_active,
                        created_at,
                        updated_at

                    FROM
                        `{$table}`

                    WHERE
                        `{$scopeColumn}` = ?

                    ORDER BY
                        setting_key,
                        id
                ");


        $statement->execute([
            $scopeId,
        ]);


        return
            $statement->fetchAll()
            ?: [];
    }


    private function landingItems(
        int $portalId
    ): array {

        $statement =
            $this->ticketing
                ->prepare("
                    SELECT *

                    FROM
                        ticketing_support_portal_landing_items

                    WHERE
                        portal_id = ?

                    ORDER BY
                        item_type,
                        sort_order,
                        id
                ");


        $statement->execute([
            $portalId,
        ]);


        return
            $statement->fetchAll()
            ?: [];
    }


    private function landingItem(
        int $portalId,
        int $id
    ): ?array {

        $statement =
            $this->ticketing
                ->prepare("
                    SELECT *

                    FROM
                        ticketing_support_portal_landing_items

                    WHERE
                        portal_id = ?
                      AND id = ?

                    LIMIT 1
                ");


        $statement->execute([
            $portalId,
            $id,
        ]);


        $row =
            $statement->fetch();


        return
            is_array($row)
                ? $row
                : null;
    }


    private function authorize(
        int $userId
    ): void {

        if (
            $userId < 1
            ||
            !(
                new AdminNavigationRbacService()
            )->can(
                $userId,
                'ticketing.project.manage'
            )
        ) {
            throw new RuntimeException(
                'portal_management_forbidden'
            );
        }
    }
}
