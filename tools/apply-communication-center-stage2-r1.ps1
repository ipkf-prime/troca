$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$RepoRoot = Split-Path -Parent $PSScriptRoot
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$ChangedFiles = New-Object System.Collections.Generic.List[string]

function Read-RepoFile {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    $Path = Join-Path $RepoRoot $RelativePath

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Required file missing: $RelativePath"
    }

    return [System.IO.File]::ReadAllText($Path)
}

function Write-RepoFile {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $Path = Join-Path $RepoRoot $RelativePath
    [System.IO.File]::WriteAllText($Path, $Content, $Utf8NoBom)

    if (-not $ChangedFiles.Contains($RelativePath)) {
        $ChangedFiles.Add($RelativePath)
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

    $Position = $Content.IndexOf(
        $Search,
        [System.StringComparison]::Ordinal
    )

    if ($Position -lt 0) {
        throw "Patch anchor missing: $Label"
    }

    return $Content.Substring(0, $Position) +
        $Replacement +
        $Content.Substring($Position + $Search.Length)
}

function Patch-RouteClosureUse {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string]$RouteMarker,
        [Parameter(Mandatory = $true)][string]$Variable
    )

    $RouteStart = $Content.IndexOf(
        $RouteMarker,
        [System.StringComparison]::Ordinal
    )

    if ($RouteStart -lt 0) {
        throw "Route marker missing: $RouteMarker"
    }

    $NextRoute = $Content.IndexOf(
        '$router->',
        $RouteStart + $RouteMarker.Length,
        [System.StringComparison]::Ordinal
    )

    if ($NextRoute -lt 0) {
        $NextRoute = $Content.Length
    }

    $RouteBlock = $Content.Substring(
        $RouteStart,
        $NextRoute - $RouteStart
    )

    if (-not $RouteBlock.Contains($Variable + '(')) {
        return $Content
    }

    $Match = [regex]::Match(
        $RouteBlock,
        '(?s)(\)\s+use\s+\()(?<uses>.*?)(\r?\n\)\s+\{)'
    )

    if (-not $Match.Success) {
        throw "Closure use-list missing: $RouteMarker"
    }

    $Uses = $Match.Groups['uses'].Value

    if ($Uses.Contains($Variable)) {
        return $Content
    }

    $Trimmed = $Uses.TrimEnd()

    if (-not $Trimmed.EndsWith(',')) {
        $Trimmed += ','
    }

    $NewUses = $Trimmed + "`n    " + $Variable
    $NewRouteBlock = $RouteBlock.Substring(0, $Match.Groups['uses'].Index) +
        $NewUses +
        $RouteBlock.Substring(
            $Match.Groups['uses'].Index +
            $Match.Groups['uses'].Length
        )

    return $Content.Substring(0, $RouteStart) +
        $NewRouteBlock +
        $Content.Substring($NextRoute)
}

# Migration registry
$Relative = 'public_html/system/Database/Application/ApplicationMigrationRegistry.php'
$Content = Read-RepoFile $Relative
$Search = @'
                    \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables::class,
'@
$Replacement = @'
                    \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables::class,
                    \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables::class,
'@
$Content = Replace-Once $Content $Search $Replacement 'application migration registry'
Write-RepoFile $Relative $Content

# Main migration endpoint
$Relative = 'public_html/public/migrate.php'
$Content = Read-RepoFile $Relative
$Search = @'
        new \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables(),
'@
$Replacement = @'
        new \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables(),
        new \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables(),
'@
$Content = Replace-Once $Content $Search $Replacement 'migrate list'

if (-not $Content.Contains('communication_center_foundation')) {
    $Content = $Content.Replace(
        'notification_core_foundation";',
        'notification_core_foundation, communication_center_foundation";'
    )
}

Write-RepoFile $Relative $Content

# Seeder registry
$Relative = 'public_html/system/Database/Application/ApplicationSeederRegistry.php'
$Content = Read-RepoFile $Relative
$Search = @'
                    \IPKF\Database\Seeds\NotificationCoreSeeder::class,
'@
$Replacement = @'
                    \IPKF\Database\Seeds\NotificationCoreSeeder::class,
                    \IPKF\Database\Seeds\CommunicationCenterSeeder::class,
'@
$Content = Replace-Once $Content $Search $Replacement 'application seeder registry'
Write-RepoFile $Relative $Content

# Main seed endpoint
$Relative = 'public_html/public/seed.php'
$Content = Read-RepoFile $Relative
$Search = @'
        new \IPKF\Database\Seeds\NotificationCoreSeeder(),
'@
$Replacement = @'
        new \IPKF\Database\Seeds\NotificationCoreSeeder(),
        new \IPKF\Database\Seeds\CommunicationCenterSeeder(),
'@
$Content = Replace-Once $Content $Search $Replacement 'seed list'

if (-not $Content.Contains('communication_center_metadata')) {
    $Content = $Content.Replace(
        'notification_core_metadata";',
        'notification_core_metadata, communication_center_metadata";'
    )
}

Write-RepoFile $Relative $Content

# Route loader
$Relative = 'public_html/system/Routing/RouteLoader.php'
$Content = Read-RepoFile $Relative
$Search = @'
            BASE_PATH . '/routes/notifications.php',
'@
$Replacement = @'
            BASE_PATH . '/routes/notifications.php',
            BASE_PATH . '/routes/communication-center.php',
'@
$Content = Replace-Once $Content $Search $Replacement 'route loader'
Write-RepoFile $Relative $Content

# Login unread-message notification
$Relative = 'public_html/app/Services/AuthService.php'
$Content = Read-RepoFile $Relative
$RecordBlock = @'
        (new LoginHistoryService())->record(
            $userId,
            $activeAssignment,
            $method,
            $mfaVerified
        );
'@
$RecordReplacement = $RecordBlock + @'


        (new InternalMessageLoginNotifierService())
            ->notify($userId);
'@
$Content = Replace-Once $Content $RecordBlock $RecordReplacement 'auth login notifier'

if (-not $Content.Contains("Session::forget('messages_unread_on_login');")) {
    $Content = $Content.Replace(
        "        Session::forget('module_sso_return_path');`n",
        "        Session::forget('module_sso_return_path');`n        Session::forget('messages_unread_on_login');`n"
    )
}

Write-RepoFile $Relative $Content

# Dynamic sidebar and topbar navigation
$Relative = 'public_html/resources/views/admin/layout.php'
$Content = Read-RepoFile $Relative
$Search = @'
$systemNav = $context['navigation']['system'] ?? [];
'@
$Replacement = @'
$navigationShell = $isModuleShell ? $moduleShellKey : 'core';
$dynamicNavigation = new \App\Services\DynamicAdminNavigationService();
$systemNav = $themeUserId !== null
    ? $dynamicNavigation->navigation((int) $themeUserId, $navigationShell)
    : [];
$topbarNav = $themeUserId !== null
    ? $dynamicNavigation->topbar((int) $themeUserId, $navigationShell)
    : [];
'@
$Content = Replace-Once $Content $Search $Replacement 'dynamic sidebar'

$ChildAnchor = @'
                    </a>
                <?php endforeach; ?>
            </nav>
'@
$ChildReplacement = @'
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
            </nav>
'@
$Content = Replace-Once $Content $ChildAnchor $ChildReplacement 'nested sidebar navigation'

$TopbarAnchor = @'
                <div class="admin-topbar__actions">
'@
$TopbarReplacement = @'
                <div class="admin-topbar__actions">
                    <?php foreach ($topbarNav as $topbarItem): ?>
                        <a
                            class="admin-role admin-topbar-notification"
                            href="<?= admin_h((string) ($topbarItem['url'] ?? '#')) ?>"
                        >
                            <?= \App\Support\AdminIcon::html((string) ($topbarItem['icon'] ?? 'envelope')) ?>
                            <span><?= admin_h($topbarItem['title'] ?? '') ?></span>
                            <?php if (($topbarItem['badge'] ?? '') !== ''): ?>
                                <b><?= admin_h($topbarItem['badge']) ?></b>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
'@
$Content = Replace-Once $Content $TopbarAnchor $TopbarReplacement 'dynamic topbar notification'

if (-not $Content.Contains('communication-navigation-style')) {
    $StylePosition = $Content.IndexOf(
        '<style id="admin-theme-vars">',
        [System.StringComparison]::Ordinal
    )

    if ($StylePosition -lt 0) {
        throw 'Patch anchor missing: navigation styles'
    }

    $StyleEnd = $Content.IndexOf(
        '</style>',
        $StylePosition,
        [System.StringComparison]::Ordinal
    )

    if ($StyleEnd -lt 0) {
        throw 'Patch failed: navigation styles'
    }

    $StyleEnd += '</style>'.Length
    $NavigationStyle = @'

    <style id="communication-navigation-style">
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
        .admin-topbar-notification {
            align-items: center;
            display: inline-flex;
            gap: .35rem;
            text-decoration: none;
        }
        .admin-topbar-notification .admin-icon {
            height: 1rem;
            width: 1rem;
        }
    </style>
'@

    $Content = $Content.Substring(0, $StyleEnd) +
        $NavigationStyle +
        $Content.Substring($StyleEnd)
}

Write-RepoFile $Relative $Content

# Dynamic notification channel catalog
$Relative = 'public_html/app/Repositories/NotificationRepository.php'
$Content = Read-RepoFile $Relative

if (-not $Content.Contains('public function activeChannelCodes')) {
    $Anchor = '    private function resolveUserId('

    if (-not $Content.Contains($Anchor)) {
        throw 'Patch anchor missing: notification repository channels'
    }

    $ChannelMethod = @'
    public function activeChannelCodes(): array
    {
        $statement = $this->connection()->query("
            SELECT code
            FROM notification_channels
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");

        return array_values(array_map(
            'strval',
            $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []
        ));
    }

'@

    $Content = $Content.Replace(
        $Anchor,
        $ChannelMethod + $Anchor
    )
}

Write-RepoFile $Relative $Content

# Dynamic channel validation in publisher and worker
foreach ($Relative in @(
    'public_html/app/Services/NotificationPublisherService.php',
    'public_html/app/Services/NotificationOutboxProcessorService.php'
)) {
    $Content = Read-RepoFile $Relative

    if (-not $Content.Contains('activeChannelCodes')) {
        $Search = "        `$allowed = ['in_app', 'email', 'sms', 'bale'];`n"

        if (-not $Content.Contains($Search)) {
            throw "Patch anchor missing: $Relative channel list"
        }

        $Content = $Content.Replace(
            $Search,
            "        `$allowed = `$this->notifications`n            ->activeChannelCodes();`n"
        )
    }

    Write-RepoFile $Relative $Content
}

# Remove duplicate notification seed registrations
$Relative = 'public_html/system/Database/Application/ApplicationSeederRegistry.php'
$Content = Read-RepoFile $Relative
$Lines = [regex]::Split($Content, '\r?\n')
$SeenSeeders = @{}
$CleanLines = New-Object System.Collections.Generic.List[string]

foreach ($Line in $Lines) {
    $SeederClass = $null
    $Match = [regex]::Match(
        $Line,
        '\\IPKF\\Database\\Seeds\\(NotificationCoreSeeder|CommunicationCenterSeeder)::class,'
    )

    if ($Match.Success) {
        $SeederClass = $Match.Groups[1].Value
    }

    if ($null -ne $SeederClass -and $SeenSeeders.ContainsKey($SeederClass)) {
        continue
    }

    if ($null -ne $SeederClass) {
        $SeenSeeders[$SeederClass] = $true
    }

    $CleanLines.Add($Line)
}

$Content = [string]::Join("`n", $CleanLines)

if (-not $Content.EndsWith("`n")) {
    $Content += "`n"
}

Write-RepoFile $Relative $Content

# Fix admin user route closure helper capture
$Relative = 'public_html/routes/admin-users-manage.php'
$Content = Read-RepoFile $Relative

foreach ($RouteMarker in @(
    '$router->post(''/admin/users'', function (',
    '$router->post(''/admin/users/{id}'', function ('
)) {
    $Content = Patch-RouteClosureUse `
        $Content `
        $RouteMarker `
        '$adminUserVerificationRedirect'
}

Write-RepoFile $Relative $Content

# Version
Write-RepoFile `
    'public_html/VERSION' `
    "0.6.0-communication-center-stage2-r1-dev`n"

Write-Host 'Communication Center Stage 2 R1 patch applied.'
Write-Host ("changed_files=" + $ChangedFiles.Count)

foreach ($File in $ChangedFiles) {
    Write-Host $File
}
