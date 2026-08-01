$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Root = Split-Path -Parent $PSScriptRoot
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$Changed = New-Object System.Collections.Generic.List[string]

function Read-RepoFile {
    param([Parameter(Mandatory = $true)][string]$Relative)

    $Path = Join-Path $Root $Relative

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Required file missing: $Relative"
    }

    $Content = [System.IO.File]::ReadAllText($Path)
    $Content = $Content.Replace("`r`n", "`n")
    $Content = $Content.Replace("`r", "`n")

    return $Content
}

function Write-RepoFile {
    param(
        [Parameter(Mandatory = $true)][string]$Relative,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $Path = Join-Path $Root $Relative
    $Directory = Split-Path -Parent $Path

    if (-not (Test-Path -LiteralPath $Directory)) {
        New-Item -ItemType Directory -Path $Directory -Force | Out-Null
    }

    [System.IO.File]::WriteAllText(
        $Path,
        $Content,
        $Utf8NoBom
    )

    if (-not $Changed.Contains($Relative)) {
        $Changed.Add($Relative)
    }
}

function Replace-Once {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string]$Search,
        [Parameter(Mandatory = $true)][string]$Replacement,
        [Parameter(Mandatory = $true)][string]$Label
    )

    if ($Content.Contains($Replacement)) {
        return $Content
    }

    $Index = $Content.IndexOf(
        $Search,
        [System.StringComparison]::Ordinal
    )

    if ($Index -lt 0) {
        throw "Patch anchor missing: $Label"
    }

    return $Content.Substring(0, $Index) +
        $Replacement +
        $Content.Substring($Index + $Search.Length)
}

function Replace-RegexOnce {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string]$Pattern,
        [Parameter(Mandatory = $true)][string]$Replacement,
        [Parameter(Mandatory = $true)][string]$Label
    )

    $Regex = New-Object System.Text.RegularExpressions.Regex(
        $Pattern,
        [System.Text.RegularExpressions.RegexOptions]::Singleline
    )

    if (-not $Regex.IsMatch($Content)) {
        throw "Patch regex missing: $Label"
    }

    return $Regex.Replace(
        $Content,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($Match)
            return $Replacement
        },
        1
    )
}

# ----------------------------------------------------------------------
# Shared admin view helpers must exist before a view starts rendering.
# ----------------------------------------------------------------------
$Helpers = @'
<?php

if (!function_exists('admin_fa')) {
    function admin_fa(string $entities): string
    {
        return html_entity_decode(
            $entities,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

if (!function_exists('admin_nav_is_active')) {
    function admin_nav_is_active(
        array $item,
        string $currentPath
    ): bool {
        $paths = $item['active_paths']
            ?? [$item['url'] ?? '#'];

        foreach ($paths as $path) {
            if ($currentPath === (string) $path) {
                return true;
            }

            if (
                str_ends_with((string) $path, '/*')
                && str_starts_with(
                    $currentPath,
                    rtrim((string) $path, '/*') . '/'
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
'@

Write-RepoFile `
    'public_html/resources/views/admin/partials/view-helpers.php' `
    ($Helpers + "`n")

$Relative = 'public_html/routes/web.php'
$Content = Read-RepoFile $Relative

$RenderAnchor = @'
    extract($data, EXTR_SKIP);
    ob_start();
    require $path;
'@

$RenderReplacement = @'
    $helpers = BASE_PATH
        . '/resources/views/admin/partials/view-helpers.php';

    if (is_readable($helpers)) {
        require_once $helpers;
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $path;
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $RenderAnchor `
    -Replacement $RenderReplacement `
    -Label 'admin render shared helpers'

Write-RepoFile $Relative $Content

# ----------------------------------------------------------------------
# Dynamic accordion sidebar. Every item with children starts collapsed.
# ----------------------------------------------------------------------
$Relative = 'public_html/resources/views/admin/layout.php'
$Content = Read-RepoFile $Relative

$OldStyle = @'
        .admin-nav__children {
            display: grid;
            gap: .2rem;
            margin: .2rem 1.4rem .55rem .25rem;
        }
        .admin-nav__children a {
            font-size: .78rem;
            min-height: 2.2rem;
            padding-block: .35rem;
        }
'@

$NewStyle = @'
        .admin-nav__group {
            display: grid;
            gap: .2rem;
        }
        .admin-nav__group-toggle {
            align-items: center;
            appearance: none;
            background: transparent;
            border: 0;
            border-radius: inherit;
            color: inherit;
            cursor: pointer;
            display: grid;
            font: inherit;
            grid-template-columns: auto minmax(0, 1fr) auto auto;
            min-height: inherit;
            padding: inherit;
            text-align: right;
            width: 100%;
        }
        .admin-nav__group-toggle:hover,
        .admin-nav__group-toggle.is-active {
            background: var(--admin-primary-soft);
            color: var(--admin-primary);
        }
        .admin-nav__group-chevron {
            font-size: .72rem;
            transition: transform .18s ease;
        }
        .admin-nav__group-toggle[aria-expanded="true"]
            .admin-nav__group-chevron {
            transform: rotate(180deg);
        }
        .admin-nav__children {
            display: grid;
            gap: .2rem;
            margin: .2rem 1.4rem .55rem .25rem;
        }
        .admin-nav__children[hidden] {
            display: none;
        }
        .admin-nav__children a {
            font-size: .78rem;
            min-height: 2.2rem;
            padding-block: .35rem;
        }
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $OldStyle `
    -Replacement $NewStyle `
    -Label 'accordion sidebar styles'

$OldNavigation = @'
                <?php foreach ($systemNav as $item): ?>
                    <?php $href = (string) ($item['url'] ?? '#'); ?>
                    <a class="<?= admin_nav_is_active($item, $currentPath) ? 'is-active' : '' ?>" href="<?= admin_h($href) ?>">
                        <span class="admin-nav__icon">
                            <?= \App\Support\AdminIcon::html((string) ($item['icon'] ?? 'dashboard')) ?>
                        </span>
                        <span><?= admin_h($item['title'] ?? '') ?></span>
                        <?php if (($item['badge'] ?? '') !== ''): ?>
                            <small class="admin-nav__badge"><?= admin_h($item['badge']) ?></small>
                        <?php endif; ?>
                    </a>
                    <?php if (($item['children'] ?? []) !== []): ?>
                        <div class="admin-nav__children">
                            <?php foreach ($item['children'] as $child): ?>
                                <a
                                    class="<?= admin_nav_is_active($child, $currentPath) ? 'is-active' : '' ?>"
                                    href="<?= admin_h((string) ($child['url'] ?? '#')) ?>"
                                >
                                    <span><?= admin_h($child['title'] ?? '') ?></span>
                                    <?php if (($child['badge'] ?? '') !== ''): ?>
                                        <small class="admin-nav__badge"><?= admin_h($child['badge']) ?></small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
'@

$NewNavigation = @'
                <?php foreach ($systemNav as $item): ?>
                    <?php
                    $href = (string) ($item['url'] ?? '#');
                    $children = is_array($item['children'] ?? null)
                        ? $item['children']
                        : [];
                    $groupActive = false;

                    foreach ($children as $child) {
                        if (admin_nav_is_active($child, $currentPath)) {
                            $groupActive = true;
                            break;
                        }
                    }
                    ?>
                    <?php if ($children !== []): ?>
                        <div
                            class="admin-nav__group"
                            data-admin-nav-group
                        >
                            <button
                                class="admin-nav__group-toggle<?= $groupActive
                                    ? ' is-active'
                                    : '' ?>"
                                type="button"
                                aria-expanded="false"
                                data-admin-nav-group-toggle
                            >
                                <span class="admin-nav__icon">
                                    <?= \App\Support\AdminIcon::html(
                                        (string) (
                                            $item['icon']
                                            ?? 'dashboard'
                                        )
                                    ) ?>
                                </span>
                                <span>
                                    <?= admin_h(
                                        $item['title'] ?? ''
                                    ) ?>
                                </span>
                                <?php if (
                                    ($item['badge'] ?? '') !== ''
                                ): ?>
                                    <small class="admin-nav__badge">
                                        <?= admin_h(
                                            $item['badge']
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                                <span
                                    class="admin-nav__group-chevron"
                                    aria-hidden="true"
                                >
                                    ▾
                                </span>
                            </button>

                            <div
                                class="admin-nav__children"
                                data-admin-nav-children
                                hidden
                            >
                                <?php foreach (
                                    $children as $child
                                ): ?>
                                    <a
                                        class="<?= admin_nav_is_active(
                                            $child,
                                            $currentPath
                                        ) ? 'is-active' : '' ?>"
                                        href="<?= admin_h(
                                            (string) (
                                                $child['url'] ?? '#'
                                            )
                                        ) ?>"
                                    >
                                        <span>
                                            <?= admin_h(
                                                $child['title']
                                                ?? ''
                                            ) ?>
                                        </span>
                                        <?php if (
                                            ($child['badge'] ?? '')
                                                !== ''
                                        ): ?>
                                            <small
                                                class="admin-nav__badge"
                                            >
                                                <?= admin_h(
                                                    $child['badge']
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <a
                            class="<?= admin_nav_is_active(
                                $item,
                                $currentPath
                            ) ? 'is-active' : '' ?>"
                            href="<?= admin_h($href) ?>"
                        >
                            <span class="admin-nav__icon">
                                <?= \App\Support\AdminIcon::html(
                                    (string) (
                                        $item['icon']
                                        ?? 'dashboard'
                                    )
                                ) ?>
                            </span>
                            <span>
                                <?= admin_h(
                                    $item['title'] ?? ''
                                ) ?>
                            </span>
                            <?php if (
                                ($item['badge'] ?? '') !== ''
                            ): ?>
                                <small class="admin-nav__badge">
                                    <?= admin_h(
                                        $item['badge']
                                    ) ?>
                                </small>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $OldNavigation `
    -Replacement $NewNavigation `
    -Label 'accordion sidebar markup'

$BodyClose = @'
</body>
</html>
'@

$AccordionScript = @'
    <script>
    (() => {
        const groups = [
            ...document.querySelectorAll(
                '[data-admin-nav-group]'
            ),
        ];

        groups.forEach(group => {
            const toggle = group.querySelector(
                '[data-admin-nav-group-toggle]'
            );
            const children = group.querySelector(
                '[data-admin-nav-children]'
            );

            if (!toggle || !children) {
                return;
            }

            toggle.addEventListener('click', () => {
                const opening =
                    toggle.getAttribute('aria-expanded')
                        !== 'true';

                groups.forEach(otherGroup => {
                    const otherToggle =
                        otherGroup.querySelector(
                            '[data-admin-nav-group-toggle]'
                        );
                    const otherChildren =
                        otherGroup.querySelector(
                            '[data-admin-nav-children]'
                        );

                    if (!otherToggle || !otherChildren) {
                        return;
                    }

                    otherToggle.setAttribute(
                        'aria-expanded',
                        'false'
                    );
                    otherChildren.hidden = true;
                });

                toggle.setAttribute(
                    'aria-expanded',
                    opening ? 'true' : 'false'
                );
                children.hidden = !opening;
            });
        });
    })();
    </script>
</body>
</html>
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $BodyClose `
    -Replacement $AccordionScript `
    -Label 'accordion sidebar script'

Write-RepoFile $Relative $Content

# ----------------------------------------------------------------------
# Address records are kept separately by address_type_id.
# ----------------------------------------------------------------------
$Relative = 'public_html/app/Services/AdminUserManagementService.php'
$Content = Read-RepoFile $Relative

$AddressDefaultAnchor = @'
            'address_line' => '',
            'role_ids' => [],
'@

$AddressDefaultReplacement = @'
            'address_line' => '',
            'address_records' => [],
            'role_ids' => [],
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $AddressDefaultAnchor `
    -Replacement $AddressDefaultReplacement `
    -Label 'address records form state'

Write-RepoFile $Relative $Content

$Relative = 'public_html/app/Repositories/AdminUserManagementRepository.php'
$Content = Read-RepoFile $Relative

$OldFindMerge = @'
        $user['role_ids'] = $this->globalRoleIdsForUser($userId);
        $user = array_merge(
            $user,
            $this->contactFormData((int) ($user['person_id'] ?? 0)),
            $this->addressFormData((int) ($user['person_id'] ?? 0))
        );

        return $user;
'@

$NewFindMerge = @'
        $personId = (int) ($user['person_id'] ?? 0);
        $addressRecords = $this->addressRecordsForPerson(
            $personId
        );

        $user['role_ids'] = $this->globalRoleIdsForUser(
            $userId
        );
        $user = array_merge(
            $user,
            $this->contactFormData($personId),
            $this->addressFormDataFromRecords(
                $addressRecords
            )
        );
        $user['address_records'] = $addressRecords;

        return $user;
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $OldFindMerge `
    -Replacement $NewFindMerge `
    -Label 'load all address types'

$NewAddressMethods = @'
    private function emptyAddressFormData(): array
    {
        return [
            'address_type_id' => 0,
            'province_location_id' => 0,
            'county_location_id' => 0,
            'city_location_id' => 0,
            'geographic_location_id' => 0,
            'district' => '',
            'address_line' => '',
            'postal_code' => '',
        ];
    }

    private function addressFormDataFromRecords(
        array $records
    ): array {
        $first = $records[0] ?? null;

        return is_array($first)
            ? array_merge(
                $this->emptyAddressFormData(),
                $first
            )
            : $this->emptyAddressFormData();
    }

    private function addressRecordsForPerson(
        int $personId
    ): array {
        if (
            $personId < 1
            || !Database::tableExists(
                'person_addresses'
            )
        ) {
            return [];
        }

        $select = [
            'id',
            'address_type_id',
            'is_primary',
            'district',
            'address_line',
            'postal_code',
        ];

        foreach ([
            'province_id',
            'city_id',
            'geographic_location_id',
        ] as $column) {
            $select[] = Database::columnExists(
                'person_addresses',
                $column
            )
                ? $column
                : "NULL AS {$column}";
        }

        $statement = $this->connection()->prepare(
            'SELECT '
            . implode(', ', $select)
            . "
              FROM person_addresses
              WHERE person_id = ?
                AND status = 'active'
              ORDER BY is_primary DESC, id ASC"
        );
        $statement->execute([$personId]);

        $records = [];

        foreach (
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
            as $address
        ) {
            $record = array_merge(
                $this->emptyAddressFormData(),
                [
                    'id' => (int) (
                        $address['id'] ?? 0
                    ),
                    'address_type_id' => (int) (
                        $address['address_type_id'] ?? 0
                    ),
                    'is_primary' => !empty(
                        $address['is_primary']
                    ),
                    'geographic_location_id' => (int) (
                        $address[
                            'geographic_location_id'
                        ] ?? 0
                    ),
                    'district' => (string) (
                        $address['district'] ?? ''
                    ),
                    'address_line' => (string) (
                        $address['address_line'] ?? ''
                    ),
                    'postal_code' => (string) (
                        $address['postal_code'] ?? ''
                    ),
                ]
            );

            $geographicLocationId = (int) (
                $address[
                    'geographic_location_id'
                ] ?? 0
            );

            if ($geographicLocationId > 0) {
                $selection =
                    $this->geographicSelection(
                        $geographicLocationId
                    );

                if ($selection !== null) {
                    $record = array_merge(
                        $record,
                        $selection
                    );
                }
            } else {
                $record['province_location_id'] =
                    (int) (
                        $address['province_id'] ?? 0
                    );
                $record['city_location_id'] =
                    (int) (
                        $address['city_id'] ?? 0
                    );
            }

            $records[] = $record;
        }

        return $records;
    }

'@

$Content = Replace-RegexOnce `
    -Content $Content `
    -Pattern '    private function addressFormData\(int \$personId\): array\s*\{.*?(?=    private function syncPrimaryContacts)' `
    -Replacement $NewAddressMethods `
    -Label 'address record loader'

$NewAddressSelection = @'
        $hasAddress = $geographicLocationId > 0
            || $district !== ''
            || $addressLine !== ''
            || $postalCode !== '';

        if ($addressTypeId < 1 && $hasAddress) {
            $addressTypes = $this->idOptions(
                'address_types'
            );
            $addressTypeId = (int) (
                $addressTypes[0]['id'] ?? 0
            );
        }

        if ($addressTypeId < 1) {
            return;
        }

        $existing = $this->connection()->prepare("
            SELECT id
            FROM person_addresses
            WHERE person_id = ?
              AND address_type_id = ?
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ");
        $existing->execute([
            $personId,
            $addressTypeId,
        ]);
        $addressId = $existing->fetchColumn();

        if (!$hasAddress) {
            if ($addressId !== false) {
                $this->connection()->prepare("
                    UPDATE person_addresses
                    SET is_primary = 0,
                        status = 'inactive',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([(int) $addressId]);
            }

            return;
        }

        $fields = [
'@

$Content = Replace-RegexOnce `
    -Content $Content `
    -Pattern '        \$hasAddress = \$geographicLocationId > 0.*?        \$fields = \[' `
    -Replacement $NewAddressSelection `
    -Label 'save address by type'

$PrimaryAnchor = @'
        if ($addressId !== false) {
'@

$PrimaryReplacement = @'
        $this->connection()->prepare("
            UPDATE person_addresses
            SET is_primary = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE person_id = ?
              AND is_primary = 1
        ")->execute([$personId]);

        if ($addressId !== false) {
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $PrimaryAnchor `
    -Replacement $PrimaryReplacement `
    -Label 'selected address becomes primary'

Write-RepoFile $Relative $Content

# ----------------------------------------------------------------------
# Address type selector loads its own record or clears the form.
# ----------------------------------------------------------------------
$Relative = 'public_html/resources/views/admin/admin-user-form.php'
$Content = Read-RepoFile $Relative

$AddressTypesAnchor = @'
$addressTypes = $page['address_types'] ?? [];
$statusOptions = $page['status_options'] ?? [];
'@

$AddressTypesReplacement = @'
$addressTypes = $page['address_types'] ?? [];
$addressRecords = is_array(
    $form['address_records'] ?? null
)
    ? array_values($form['address_records'])
    : [];
$statusOptions = $page['status_options'] ?? [];
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $AddressTypesAnchor `
    -Replacement $AddressTypesReplacement `
    -Label 'address records in user form'

$ScriptAnchor = @'
<script>
(() => {
'@

$AddressJson = @'
<script
    type="application/json"
    data-address-records
><?= json_encode(
    $addressRecords,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<script>
(() => {
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $ScriptAnchor `
    -Replacement $AddressJson `
    -Label 'embedded address records'

$AddressControlsAnchor = @'
    const province = root.querySelector('[data-province]');
    const county = root.querySelector('[data-county]');
    const city = root.querySelector('[data-city]');
'@

$AddressControlsReplacement = @'
    const province = root.querySelector('[data-province]');
    const county = root.querySelector('[data-county]');
    const city = root.querySelector('[data-city]');
    const addressType = root.querySelector(
        '[name="address_type_id"]'
    );
    const district = root.querySelector(
        '[name="district"]'
    );
    const postalCode = root.querySelector(
        '[name="postal_code"]'
    );
    const addressLine = root.querySelector(
        '[name="address_line"]'
    );
    const addressRecordsNode = document.querySelector(
        '[data-address-records]'
    );
    let addressRecords = [];

    try {
        addressRecords = JSON.parse(
            addressRecordsNode?.textContent || '[]'
        );
    } catch (error) {
        addressRecords = [];
    }
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $AddressControlsAnchor `
    -Replacement $AddressControlsReplacement `
    -Label 'address type controls'

$CascadeMissingAnchor = @'
        if (!province || !county || !city) {
            return;
        }
'@

$CascadeMissingReplacement = @'
        if (!province || !county || !city) {
            return null;
        }
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $CascadeMissingAnchor `
    -Replacement $CascadeMissingReplacement `
    -Label 'location cascade nullable API'

$CascadeReturnAnchor = @'
        refreshCounties(true);
        refreshCities(true);
    };

    buildLocationCascade(province, county, city);
'@

$CascadeReturnReplacement = @'
        refreshCounties(true);
        refreshCities(true);

        return {
            setValues(values = {}) {
                province.value = String(
                    values.province_location_id
                    ?? 0
                );
                refreshCounties(false);

                county.value = String(
                    values.county_location_id
                    ?? 0
                );
                refreshCities(false);

                city.value = String(
                    values.city_location_id
                    ?? 0
                );
            },
        };
    };

    const locationCascade = buildLocationCascade(
        province,
        county,
        city
    );

    const loadSelectedAddressType = () => {
        const typeId = Number(
            addressType?.value || 0
        );
        const record = addressRecords.find(
            item => Number(
                item.address_type_id || 0
            ) === typeId
        ) || null;

        locationCascade?.setValues(
            record || {
                province_location_id: 0,
                county_location_id: 0,
                city_location_id: 0,
            }
        );

        if (district) {
            district.value = record?.district || '';
        }

        if (postalCode) {
            postalCode.value =
                record?.postal_code || '';
        }

        if (addressLine) {
            addressLine.value =
                record?.address_line || '';
        }
    };

    addressType?.addEventListener(
        'change',
        loadSelectedAddressType
    );
'@

$Content = Replace-Once `
    -Content $Content `
    -Search $CascadeReturnAnchor `
    -Replacement $CascadeReturnReplacement `
    -Label 'load or clear address by type'

Write-RepoFile $Relative $Content

# ----------------------------------------------------------------------
# Regression test file.
# ----------------------------------------------------------------------
$Test = @'
<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$web = $read('public_html/routes/web.php');
$layout = $read(
    'public_html/resources/views/admin/layout.php'
);
$form = $read(
    'public_html/resources/views/admin/admin-user-form.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'AdminUserManagementService.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'AdminUserManagementRepository.php'
);

$expect(
    str_contains($web, 'partials/view-helpers.php'),
    'Admin helpers are not loaded before view rendering.'
);

$expect(
    str_contains($layout, 'data-admin-nav-group-toggle')
    && str_contains($layout, 'data-admin-nav-children')
    && str_contains($layout, 'hidden'),
    'Sidebar child navigation is not collapsible.'
);

$expect(
    str_contains($service, "'address_records' => []")
    && str_contains($repository, 'addressRecordsForPerson')
    && str_contains(
        $repository,
        'AND address_type_id = ?'
    ),
    'Address records are not separated by address type.'
);

$expect(
    str_contains($form, 'data-address-records')
    && str_contains(
        $form,
        'loadSelectedAddressType'
    )
    && str_contains(
        $form,
        "addressLine.value ="
    ),
    'Address type selector does not load or clear form state.'
);

echo "Communication UI and address type hotfix checks passed.\n";
'@

Write-RepoFile `
    'tests/CommunicationUiAddressHotfixTest.php' `
    ($Test + "`n")

Write-Host 'Communication UI and address type R2 patch applied.'
Write-Host ('changed_files=' + $Changed.Count)

foreach ($File in $Changed) {
    Write-Host $File
}
