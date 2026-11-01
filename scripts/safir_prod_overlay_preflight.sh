#!/usr/bin/env bash
# SAFIR PROD PREP: isolated additive overlay, NO DEPLOY, no credentials, no DB.
set -Eeuo pipefail
umask 077
BASE=/srv/troca/portal-test
CURRENT="$BASE/current"
STAGING_BASE=/srv/troca/_staging/releases
BRANCH=feature/bale-safir-v090-integration-dev
fail(){ printf 'FAIL=%s\n' "$*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail ROOT_REQUIRED
for exe in git php python3 sha256sum; do command -v "$exe" >/dev/null || fail "MISSING_TOOL:$exe"; done
[ -L "$CURRENT" ] || fail CURRENT_NOT_SYMLINK
ACTIVE="$(readlink -e "$CURRENT")" || fail CURRENT_NOT_ACCESSIBLE
case "$ACTIVE" in "$BASE"/releases/*) : ;; *) fail CURRENT_OUTSIDE_RELEASES ;; esac
[ -d "$STAGING_BASE" ] || fail STAGING_MISSING
(
 cd "$ACTIVE"
 sha256sum -c - <<'SUMS'
ded5d77f129fb9493e2c4a69e935246ac7c97f143a1b92c0cee32cfe853e4135  public/index.php
f9824aaab334b4492f53d4ee6cc1cf0b14052182fa5792e70b8f8e98f2f31cdf  routes/web.php
76423289b6b47c87727c492cb0279712c3d46c3c8409cbb1bcce04b2e7d9ffee  routes/communication-center.php
bfc28fa0242a668b588122249db58ac01474736f2a7f9d8720f3b1a49841fd0a  system/Routing/RouteLoader.php
255289bb6e1619751a19c5eccc6bb0cab17d033d215698daefe7dc8918814855  app/Services/CommunicationSettingsService.php
c64c1672b4d4ef2f58903fbe7cc28b14ff75a2761bb803ff6d052c458a3660d0  app/Services/AdminNavigationRbacService.php
876966a04dac826324f39a0e5a87833c971ee086964eac7cc00e4460ec98444f  resources/views/admin/communication-settings.php
SUMS
) || fail PRODUCTION_SHA_MISMATCH
echo PRODUCTION_BASELINE_HASH_GATE=PASS
for relative in app/Services/BaleSafirCampaignService.php app/Services/BaleSafirTemplateCatalogService.php routes/bale-safir-campaigns.php resources/ui-content/bale-safir-templates.json resources/views/admin/partials/bale-safir-campaign.php; do
 [ ! -e "$ACTIVE/$relative" ] || fail "SAFIR_ALREADY_INSTALLED:$relative"
done
WORKDIR="$(mktemp -d "$STAGING_BASE/safir-source-preflight-XXXXXXXX")"
chmod 700 "$WORKDIR"
SOURCE="$WORKDIR/source"
PATCHED="$WORKDIR/overlay"
mkdir -p "$PATCHED/public_html" "$PATCHED/tests"
GIT_TERMINAL_PROMPT=0 git -c credential.helper= clone --quiet --depth 1 --single-branch --branch "$BRANCH" https://github.com/ipkf-prime/troca.git "$SOURCE" || fail GITHUB_SOURCE_CLONE_FAILED
SOURCE_COMMIT="$(git -C "$SOURCE" rev-parse HEAD)"
printf 'GITHUB_SOURCE_COMMIT=%s\n' "$SOURCE_COMMIT"
(
 cd "$SOURCE"
 for spec in \
  ed09578761a4c83e28d8da7807843d0fd16308f5:public_html/app/Services/BaleSafirTemplateCatalogService.php \
  6949d68f4c98be3b2fc51d1472cb2d7b6fb455a1:public_html/app/Services/BaleSafirCampaignService.php \
  b1e4235c13a70d1e03f0afc4da8ed9cf5d530c49:public_html/resources/ui-content/bale-safir-templates.json \
  7f6cac60063b7e13cb38d6a361a3368a3fdd4385:public_html/routes/bale-safir-campaigns.php \
  0ead7d17db61752f8d53ed7664db7be9873b86d4:public_html/resources/views/admin/partials/bale-safir-campaign.php \
  8799d7d97f20464d3f5526f40a06e99ef230edb6:tests/bale-safir-campaign-smoke.php
 do
   IFS=: read -r expected relative <<< "$spec"
   actual="$(git hash-object "$relative")"
   [ "$actual" = "$expected" ] || fail "UNREVIEWED_SOURCE:$relative"
 done
) || fail SOURCE_BLOB_CHECK_FAILED
echo REVIEWED_SOURCE_BLOB_GATE=PASS
for relative in \
 public_html/app/Services/BaleSafirTemplateCatalogService.php \
 public_html/app/Services/BaleSafirCampaignService.php \
 public_html/resources/ui-content/bale-safir-templates.json \
 public_html/routes/bale-safir-campaigns.php \
 public_html/resources/views/admin/partials/bale-safir-campaign.php \
 tests/bale-safir-campaign-smoke.php; do
 mkdir -p "$(dirname "$PATCHED/$relative")"
 install -m 0640 "$SOURCE/$relative" "$PATCHED/$relative"
done
for relative in \
 system/Routing/RouteLoader.php \
 app/Services/CommunicationSettingsService.php \
 app/Services/AdminNavigationRbacService.php \
 resources/views/admin/communication-settings.php; do
 mkdir -p "$(dirname "$PATCHED/public_html/$relative")"
 install -m 0640 "$ACTIVE/$relative" "$PATCHED/public_html/$relative"
done
# The offline smoke needs two framework support classes. Copy them ONLY
# into the isolated preflight tree; do not modify the running release.
for relative in \
 system/Support/Env.php \
 system/Support/Session.php \
 system/Http/Response.php; do
 [ -f "$ACTIVE/$relative" ] || fail "SMOKE_DEPENDENCY_MISSING:$relative"
 mkdir -p "$(dirname "$PATCHED/public_html/$relative")"
 install -m 0640 "$ACTIVE/$relative" "$PATCHED/public_html/$relative"
done
echo OFFLINE_FRAMEWORK_DEPENDENCIES_STAGED=PASS
IPKF_SAFIR_PATCH_ROOT="$PATCHED/public_html" python3 - <<'PY'
import os
from pathlib import Path
root=Path(os.environ['IPKF_SAFIR_PATCH_ROOT'])
def patch(relative,old,new):
 p=root/relative
 s=p.read_text(encoding='utf-8')
 n=s.count(old)
 if n!=1: raise SystemExit('FAIL=PATCH_ANCHOR_COUNT:{}:{}'.format(relative,n))
 p.write_text(s.replace(old,new,1),encoding='utf-8')
a="            BASE_PATH . '/routes/communication-center.php',"
patch('system/Routing/RouteLoader.php',a,a+"\n            BASE_PATH . '/routes/bale-safir-campaigns.php',")
a="            '/admin/communications/templates' => 'admin.settings.manage',"
patch('app/Services/AdminNavigationRbacService.php',a,"            '/admin/communications/settings/safir' => 'notifications.send.manage',\n            '/admin/communications/settings/safir/export' => 'notifications.send.manage',\n"+a)
a="""        'send' => [
            'title' => 'ارسال اعلان',
            'permission' => 'notifications.send.view',
        ],"""
patch('app/Services/CommunicationSettingsService.php',a,a+"""
        'safir' => [
            'title' => 'مدیریت قالب‌ها و ارسال سفیر بله',
            'permission' => 'notifications.send.manage',
        ],""")
a="""            'notification_send_center' =>
                $notificationSendCenter,"""
patch('app/Services/CommunicationSettingsService.php',a,"""            'safir_campaign' => ($section === 'safir' && isset($sections['safir']))
                ? (new BaleSafirCampaignService())->page($userId)
                : [],
"""+a)
a="        <?php if ($section === 'internal'): ?>"
patch('resources/views/admin/communication-settings.php',a,"        <?php if ($section === 'safir'): ?>\n            <?php require BASE_PATH . '/resources/views/admin/partials/bale-safir-campaign.php'; ?>\n        <?php elseif ($section === 'internal'): ?>")
print('PRODUCTION_ADDITIVE_PATCH=PASS')
PY
for relative in \
 public_html/system/Routing/RouteLoader.php \
 public_html/app/Services/CommunicationSettingsService.php \
 public_html/app/Services/AdminNavigationRbacService.php \
 public_html/resources/views/admin/communication-settings.php \
 public_html/system/Support/Env.php \
 public_html/system/Support/Session.php \
 public_html/system/Http/Response.php \
 public_html/app/Services/BaleSafirCampaignService.php \
 public_html/app/Services/BaleSafirTemplateCatalogService.php \
 public_html/routes/bale-safir-campaigns.php \
 public_html/resources/views/admin/partials/bale-safir-campaign.php \
 tests/bale-safir-campaign-smoke.php; do
 php -l "$PATCHED/$relative" || fail "PHP_LINT_FAILED:$relative"
done
php "$PATCHED/tests/bale-safir-campaign-smoke.php" || fail OFFLINE_SMOKE_FAILED
[ "$(readlink -e "$CURRENT")" = "$ACTIVE" ] || fail ACTIVE_RELEASE_SWITCHED
printf 'ACTIVE_RELEASE=%s\nSOURCE_COMMIT=%s\nRUN_MIGRATIONS=NO\nRUN_SYSTEM_SEEDS=NO\nSEND_ENABLED=NO\n' "$ACTIVE" "$SOURCE_COMMIT" > "$WORKDIR/provenance.txt"
(cd "$PATCHED" && find . -type f -print0 | sort -z | xargs -0 sha256sum) > "$WORKDIR/overlay-sha256sums.txt"
printf '\nSAFIR_PROD_OVERLAY_PREFLIGHT=PASS\nACTIVE_RELEASE_UNTOUCHED=YES\nDATABASE_MUTATION=NO\nPRODUCTION_DEPLOY=NO\nLIVE_MESSAGE_SEND=NO\n'
printf 'OVERLAY_PATH=%s\nSOURCE_COMMIT=%s\n' "$PATCHED" "$SOURCE_COMMIT"
echo NEXT=VERIFY_OFFICIAL_RELEASE_PACKAGE_CONTRACT
