<?php
declare(strict_types=1);
$bmText = static fn (string $key): string =>
    \App\Services\UiContent\UiContentInlineGuide::bodyText($key, 'core', 'bale-menu-admin');
$bmHtml = static fn (string $key): string =>
    \App\Services\UiContent\UiContentInlineGuide::bodyHtml($key, 'core', 'bale-menu-admin');
$title = $bmText('core.bale-menu.ui.page_title');
$bmTabs = [
    'bots' => $bmText('core.bale-menu.ui.tab.bots'),
    'menus' => $bmText('core.bale-menu.ui.tab.menus'),
    'transport' => $bmText('core.bale-menu.ui.tab.transport'),
    'messages' => $bmText('core.bale-menu.ui.tab.messages'),
];

$escapeMenu = static fn(mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$page = is_array($page ?? null) ? $page : [];
$status = (string)($status ?? '');
$screenKey = (string)($page['screen_key'] ?? '');
$screen = $page['screens'][$screenKey] ?? [];
$rows = $screen['button_rows'] ?? [];
$buttons = [];
foreach ($rows as $row) foreach ($row as $button) $buttons[] = $button;
$columns = count($rows) > 0 ? max(array_map('count', $rows)) : 2;
$mode = ($screen['menu_mode'] ?? 'inline');
$csrf = (new \IPKF\Security\Csrf())->token();

/* B7_A130_MULTI_BOT_ADMIN: scoped, server-side tabs. */
$bmBase='/admin/communications/settings/bale-menus';
$bmTab=trim((string)($_GET['tab']??'menus'));
if (!in_array($bmTab,['bots','menus','transport','messages'],true)) $bmTab='menus';
$bmQuery=static function(string $tab, array $more=[]) use($bmBase,$page):string {
    return $bmBase.'?'.http_build_query(array_merge([
        'tab'=>$tab,'bot'=>(string)($page['bot']??''),
        'screen'=>(string)($page['screen_key']??'')],$more),'','&',PHP_QUERY_RFC3986);
};
$bmDigits=static fn(mixed $value):string=>\App\Support\AdminFormat::digits($value);
$bmStatusKeys=[
    'published'=>'core.bale-menu.notice.published',
    'unchanged'=>'core.bale-menu.notice.unchanged',
    'invalid_csrf'=>'core.bale-menu.error.invalid_csrf',
    'MENU_STALE_FORM_REFRESH'=>'core.bale-menu.error.stale_form',
    'MENU_HISTORICAL_LABEL_CONFLICT'=>'core.bale-menu.error.label_conflict',
    'MENU_BUTTON_FIELD_INVALID'=>'core.bale-menu.error.button_invalid',
    'MENU_BUTTON_IDENTITY_CHANGED'=>'core.bale-menu.error.identity_changed',
    'MENU_MODE_INVALID'=>'core.bale-menu.error.mode_invalid',
    'MENU_COLUMNS_INVALID'=>'core.bale-menu.error.columns_invalid',
    'MENU_TOO_MANY_ROWS'=>'core.bale-menu.error.rows_invalid',
    'MENU_TEXT_INVALID'=>'core.bale-menu.error.text_invalid',
    'MENU_PUBLISH_BUSY'=>'core.bale-menu.error.publish_busy',
    'bot_registered'=>'core.bale-menu.notice.bot_registered',
    'BOT_REGISTRATION_INVALID'=>'core.bale-menu.error.registration_invalid',
    'BOT_KEY_EXISTS'=>'core.bale-menu.error.key_exists',
    'BOT_REGISTRY_LIMIT'=>'core.bale-menu.error.registry_limit',
    'BOT_NOT_PROVISIONED'=>'core.bale-menu.error.not_provisioned',
    'BOT_ALREADY_REGISTERED'=>'core.bale-menu.error.already_registered',
    'BOT_IDENTITY_CONFLICT'=>'core.bale-menu.error.identity_conflict',
    'BOT_CATALOG_NOT_READY'=>'core.bale-menu.error.catalog_not_ready',
    'BOT_NOT_DEV'=>'core.bale-menu.error.not_dev',
    'BOT_REGISTRATION_BUSY'=>'core.bale-menu.error.registration_busy',
    'BOT_REGISTRY_CHANGED'=>'core.bale-menu.error.registry_changed',
    'MENU_SERVICE_CONTENT_INVALID'=>'core.bale-menu.error.text_invalid',
    'MENU_SERVICE_CONTENT_IDENTITY_CHANGED'=>'core.bale-menu.error.identity_changed',
    'MENU_CONTENT_PARAMETERS_IMMUTABLE'=>'core.bale-menu.error.text_invalid',
    'save_failed'=>'core.bale-menu.error.save_failed',
];
$bmNoticeText='';
if ($status!=='') {
    $contentKey=$bmStatusKeys[$status]??$bmStatusKeys['save_failed'];
    $bmNoticeText=str_contains($contentKey,'.notice.')
       ?\App\Services\UiContent\UiContentInlineGuide::noticeBodyText($contentKey,'core','bale-menu-admin')
       :\App\Services\UiContent\UiContentInlineGuide::errorBodyText($contentKey,'core','bale-menu-admin');
    if (trim($bmNoticeText)==='') {
    $bmNoticeText=\App\Services\UiContent\UiContentInlineGuide::errorBodyText(
        'core.bale-menu.error.save_failed','core','bale-menu-admin'
    );
}
}

ob_start();
?>
<style>
.bm-wrap{direction:rtl;max-width:1080px;margin:auto;padding:16px;line-height:1.9;color:var(--admin-text,#243144)}
.bm-card{background:var(--admin-surface,#fff);border:1px solid var(--admin-border,#dce5e3);border-radius:14px;padding:18px;margin:12px 0}
.bm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:12px}
.bm-wrap input,.bm-wrap textarea,.bm-wrap select{width:100%;border:1px solid var(--admin-border,#cbd5e1);border-radius:8px;background:var(--admin-surface,#fff);color:inherit;padding:8px;font:inherit;box-sizing:border-box}
.bm-wrap label{display:block;font-weight:600;margin:8px 0 4px}
.bm-btn{border:0;border-radius:9px;background:var(--admin-primary,#218568);color:#fff;padding:9px 23px;cursor:pointer;font:inherit}
.bm-hint{font-size:.9em;opacity:.78}.bm-note{border-right:4px solid var(--admin-primary,#218568);padding:10px;margin:12px 0;background:var(--admin-surface-muted,#eef5f3)}
.bm-list{display:flex;flex-wrap:wrap;gap:8px}.bm-list a{border:1px solid var(--admin-border,#dce5e3);border-radius:8px;padding:5px 12px;text-decoration:none;color:inherit}.bm-list .current{background:var(--admin-surface-muted,#eef5f3);font-weight:700}
.bm-preview{display:grid;gap:6px;margin-top:12px}.bm-preview-row{display:flex;gap:6px}.bm-preview-item{border:1px solid #20a88f;border-radius:8px;text-align:center;padding:6px;flex:1}

/* B7_A130_MULTI_BOT_ADMIN : clean responsive layout, admin-font inheritance */
.bm-wrap{max-width:940px;margin-inline:auto;padding:clamp(12px,2.4vw,24px);direction:rtl;text-align:right;font-family:inherit;line-height:1.85}
.bm-wrap h2{font-size:clamp(1.2rem,2vw,1.5rem);margin:0 0 8px}
.bm-wrap h3{font-size:1rem;margin:0 0 10px}
.bm-tabs{display:flex;align-items:center;gap:6px;overflow-x:auto;scrollbar-width:thin;padding:4px 2px 12px;margin:10px 0;white-space:nowrap}
.bm-tabs a{border:1px solid var(--admin-border,#dce5e3);border-radius:10px;color:inherit;text-decoration:none;padding:7px 12px;font-weight:500;flex-shrink:0}
.bm-tabs a[aria-current="page"]{background:var(--admin-surface-muted,#edf7f2);border-color:var(--admin-primary,#218568);font-weight:700}
.bm-panel[hidden]{display:none!important}
.bm-identity{padding:14px;margin-top:0}
.bm-identity .bm-list{margin-top:8px}
.bm-bots{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:8px}
.bm-bot-tile{display:flex;flex-direction:column;gap:6px;padding:12px;border:1px solid var(--admin-border,#dce5e3);border-radius:12px;text-decoration:none;color:inherit;overflow-wrap:anywhere}
.bm-bot-tile:hover,.bm-link-btn:hover{border-color:var(--admin-primary,#218568)}
.bm-bot-name{font-weight:600;text-align:left;unicode-bidi:isolate}
.bm-site-input{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.bm-site-input input{flex:1 1 130px;width:auto;min-width:0}
.bm-site-input span{font-size:.83rem;opacity:.74}
.bm-callout{background:var(--admin-surface-muted,#eef5f3);border-inline-start:3px solid var(--admin-primary,#218568);border-radius:8px;padding:12px;margin:12px 0}
.bm-link-btn{display:inline-block;text-decoration:none;color:inherit;border:1px solid var(--admin-border,#dce5e3);padding:8px 12px;border-radius:9px}
.bm-panel input,.bm-panel textarea,.bm-panel select,.bm-panel button{font-family:inherit;max-width:100%}
.bm-panel .bm-card .bm-card{padding:12px;margin-block:8px}
.bm-panel .bm-preview-item{overflow-wrap:anywhere}
.bm-note{overflow-wrap:anywhere}
@media(max-width:640px){.bm-wrap{padding:10px}.bm-card{padding:12px;margin:8px 0}.bm-grid{grid-template-columns:minmax(0,1fr)}.bm-tabs{margin:6px 0;padding-bottom:8px}.bm-tabs a{padding:6px 9px}.bm-wrap input,.bm-wrap select,.bm-wrap textarea{font-size:16px}.bm-preview-row{flex-wrap:wrap}.bm-preview-item{min-width:0}.bm-btn{width:100%}}

.bm-workspace-bar{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  flex-wrap:wrap;margin:14px 0 12px;padding:10px 12px;
  border:1px solid var(--border,#dbe7df);border-radius:12px;
  background:rgba(255,255,255,.72)
}
.bm-workspace-switcher{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0}
.bm-workspace-switcher label{margin:0;font-weight:700;white-space:nowrap}
.bm-workspace-switcher select{min-width:220px;max-width:360px;margin:0}
.bm-workspace-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.bm-workspace-separator{opacity:.55}
.bm-workspace-add{margin-inline-start:4px}
.bm-menu-screen-picker{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  margin:0 0 12px;padding:10px 12px;
  border:1px solid var(--border,#dbe7df);border-radius:10px;
  background:rgba(255,255,255,.55)
}
.bm-menu-screen-label{font-weight:700}
.bm-screen-links{display:flex;gap:7px;flex-wrap:wrap}
.bm-screen-link{
  display:inline-flex;align-items:center;min-height:34px;padding:5px 10px;
  border:1px solid var(--border,#dbe7df);border-radius:8px;text-decoration:none
}
.bm-screen-link.is-active{font-weight:700;border-color:#2c9a61}
.bm-legacy-context[hidden],.bm-transport-selected-legacy[hidden]{display:none!important}
@media (max-width:720px){
  .bm-workspace-bar{align-items:stretch}
  .bm-workspace-switcher,.bm-workspace-meta{width:100%}
  .bm-workspace-switcher select{min-width:0;width:100%;max-width:none}
}</style>
<div class="bm-wrap" lang="fa" dir="rtl">
  <h2><?= $bmHtml('core.bale-menu.ui.page_title') ?></h2>
  <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.overview','core','bale-menu-admin') ?></p>
  <?php if ($status !== ''): ?><div class="bm-note" role="status"><?= $escapeMenu($bmNoticeText) ?></div><?php endif; ?>

  <nav class="bm-tabs" aria-label="<?= $bmHtml('core.bale-menu.ui.tabs_aria') ?>">
    <?php foreach ($bmTabs as $tabKey=>$tabLabel): ?>
    <?php if ($tabKey==='messages') continue; ?>
      <a href="<?= $escapeMenu($bmQuery($tabKey)) ?>" <?= $bmTab===$tabKey?'aria-current="page" class="is-current"':'' ?>><?= $escapeMenu($tabLabel) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php
  $bmWorkspaceBots = is_array($page['bots'] ?? null) ? $page['bots'] : [];
  $bmWorkspaceCurrent = trim((string)($page['bot'] ?? ''));
  $bmWorkspaceUsername = ltrim((string)($page['username'] ?? ''),'@');

  $bmWorkspaceBotKeys = [];
  foreach ($bmWorkspaceBots as $botEntry) {
      if (!is_string($botEntry)) continue;
      $candidate=trim($botEntry);
      if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D',$candidate)!==1) continue;
      $bmWorkspaceBotKeys[$candidate]=$candidate;
  }

  if ($bmWorkspaceCurrent!=='' &&
      preg_match('/^[a-z][a-z0-9_-]{1,31}$/D',$bmWorkspaceCurrent)===1) {
      $bmWorkspaceBotKeys[$bmWorkspaceCurrent]=$bmWorkspaceCurrent;
  }

  $bmWorkspaceBotKeys=array_values($bmWorkspaceBotKeys);
  sort($bmWorkspaceBotKeys,SORT_STRING);

  if ($bmWorkspaceCurrent==='' && $bmWorkspaceBotKeys!==[]) {
      $bmWorkspaceCurrent=(string)$bmWorkspaceBotKeys[0];
  }
  ?>

  <div class="bm-workspace-bar">
    <form class="bm-workspace-switcher" method="get"
          action="/admin/communications/settings/bale-menus">
      <input type="hidden" name="tab" value="<?= $escapeMenu($bmTab) ?>">
      <label for="bm-workspace-bot"><?= $bmHtml('core.bale-menu.ui.selected_bot_prefix') ?></label>
      <select id="bm-workspace-bot" name="bot" onchange="this.form.submit()">
        <?php foreach ($bmWorkspaceBotKeys as $candidateKey): ?>
          <option value="<?= $escapeMenu($candidateKey) ?>"
              <?= hash_equals($candidateKey,$bmWorkspaceCurrent)?'selected':'' ?>>
            <?= $escapeMenu($candidateKey) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="bm-workspace-meta">
      <?php if ($bmWorkspaceUsername!==''): ?>
        <strong dir="ltr">@<?= $escapeMenu($bmWorkspaceUsername) ?></strong>
      <?php endif; ?>
      <a class="bm-link-btn bm-workspace-add"
         href="<?= $escapeMenu($bmQuery('bots',['screen'=>''])) ?>">
        <?= $bmHtml('core.bale-menu.ui.add_bot_heading') ?>
      </a>
    </div>
  </div>
<div class="bm-legacy-context" hidden aria-hidden="true">
  <div class="bm-card bm-identity">
    <b><?= $bmHtml('core.bale-menu.ui.bot_prefix') ?> @<?= $escapeMenu($page['username'] ?? '') ?></b> — <span class="bm-hint"><?= $bmHtml('core.bale-menu.ui.revision_prefix') ?> <?= $escapeMenu($page['revision'] ?? '') ?></span>
    <div class="bm-list">
      <?php foreach (($page['bots'] ?? []) as $botKey): ?>
        <a <?= $botKey === $page['bot'] ? 'class="current"' : '' ?> href="/admin/communications/settings/bale-menus?bot=<?= rawurlencode($botKey) ?>"><?= $escapeMenu($botKey) ?></a>
      <?php endforeach; ?>
    </div>
    <label><?= $bmHtml('core.bale-menu.ui.menu_select') ?></label>
    <div class="bm-list">
      <?php foreach (($page['screens'] ?? []) as $key => $item): ?>
        <a <?= $key === $screenKey ? 'class="current"' : '' ?> href="/admin/communications/settings/bale-menus?bot=<?= rawurlencode((string)$page['bot']) ?>&amp;screen=<?= rawurlencode((string)$key) ?>"><?= $escapeMenu($key) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  </div>

<section class="bm-panel" id="bm-menus" <?= $bmTab==='menus'?'':'hidden' ?> aria-label="<?= $bmHtml('core.bale-menu.ui.menu_edit_aria') ?>">
    <div class="bm-menu-screen-picker">
      <span class="bm-menu-screen-label"><?= $bmHtml('core.bale-menu.ui.menu_select') ?></span>
      <div class="bm-screen-links">
        <?php foreach (array_keys($page['screens'] ?? []) as $workspaceScreenKey): ?>
          <a class="bm-screen-link<?= $workspaceScreenKey===$screenKey?' is-active':'' ?>"
             href="<?= $escapeMenu($bmQuery('menus',['screen'=>(string)$workspaceScreenKey])) ?>">
            <?= $escapeMenu((string)$workspaceScreenKey) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
<form class="bm-card" method="post" action="/admin/communications/settings/bale-menus/publish" accept-charset="UTF-8">
    <input type="hidden" name="_token" value="<?= $escapeMenu($csrf) ?>">
    <input type="hidden" name="bot" value="<?= $escapeMenu($page['bot'] ?? '') ?>">
    <input type="hidden" name="screen_key" value="<?= $escapeMenu($screenKey) ?>">
    <input type="hidden" name="active_hash" value="<?= $escapeMenu($page['active_hash'] ?? '') ?>">
    <div class="bm-grid">
      <div><label for="bm-cols"><?= $bmHtml('core.bale-menu.ui.columns_label') ?></label><select id="bm-cols" name="columns">
        <?php foreach ([1,2,3] as $n): ?><option value="<?= $n ?>" <?= $columns === $n ? 'selected' : '' ?>><?= $escapeMenu($bmDigits($n)) ?></option><?php endforeach; ?>
      </select></div>
      <div><label for="bm-mode"><?= $bmHtml('core.bale-menu.ui.display_mode_label') ?></label><select id="bm-mode" name="mode"><option value="inline" <?= $mode === 'inline' ? 'selected' : '' ?>><?= $bmHtml('core.bale-menu.ui.display_mode.inline') ?></option><option value="reply" <?= $mode === 'reply' ? 'selected' : '' ?>><?= $bmHtml('core.bale-menu.ui.display_mode.reply') ?></option></select></div>
    </div>
    <label for="bm-text"><?= $bmHtml('core.bale-menu.ui.menu_text_label') ?></label><textarea name="screen_text" id="bm-text" rows="4" maxlength="8192" required><?= $escapeMenu($screen['text'] ?? '') ?></textarea>
    <h3><?= $bmHtml('core.bale-menu.ui.buttons_heading') ?></h3>
    <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.button_order','core','bale-menu-admin') ?></p>
    <?php foreach ($buttons as $i => $button): ?>
      <div class="bm-card"><b><?= $escapeMenu($button['key']) ?></b> <span class="bm-hint">← <?= $escapeMenu($button['target']) ?></span>
      <div class="bm-grid"><div><label><?= $bmHtml('core.bale-menu.ui.button_title_label') ?></label><input required maxlength="256" name="labels[<?= $escapeMenu($button['key']) ?>]" value="<?= $escapeMenu($button['label']) ?>"></div>
      <div><label><?= $bmHtml('core.bale-menu.ui.order_label') ?></label><input required type="number" min="1" max="999" name="orders[<?= $escapeMenu($button['key']) ?>]" value="<?= $i + 1 ?>" dir="ltr"></div></div></div>
    <?php endforeach; ?>
    <!-- B7_A129_R2_PER_BOT_SERVICE_CONTENT_EDITOR / preserve A130 tabs -->
    <details class="bm-card" id="bm-dialog-content">
      <summary style="cursor:pointer;font-weight:700"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.managed_content','core','bale-menu-admin') ?></summary>
      <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.editor_scope','core','bale-menu-admin') ?></p>
      <?php foreach (($page['service_content'] ?? []) as $contentKey => $contentValue): ?>
        <label for="bm-copy-<?= $escapeMenu($contentKey) ?>"><?= $escapeMenu($contentKey) ?></label>
        <textarea id="bm-copy-<?= $escapeMenu($contentKey) ?>"
          name="service_content[<?= $escapeMenu($contentKey) ?>]"
          rows="3" maxlength="1500" required><?= $escapeMenu($contentValue) ?></textarea>
      <?php endforeach; ?>
    </details>
    <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.preview','core','bale-menu-admin') ?></p>
    <div class="bm-preview" aria-label="<?= $bmHtml('core.bale-menu.ui.preview_aria') ?>">
    <?php foreach ($rows as $row): ?><div class="bm-preview-row"><?php foreach ($row as $button): ?><span class="bm-preview-item"><?= $escapeMenu($button['label']) ?></span><?php endforeach; ?></div><?php endforeach; ?>
    </div>
    <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.publish_scope','core','bale-menu-admin') ?></p>
    <button class="bm-btn" type="submit"><?= $bmHtml('core.bale-menu.ui.publish_button') ?></button>
  </form>

  </section>
  <section class="bm-panel" id="bm-bots" <?= $bmTab==='bots'?'':'hidden' ?> aria-label="<?= $bmHtml('core.bale-menu.ui.bots_aria') ?>">
    <div class="bm-card">
      <h3><?= $bmHtml('core.bale-menu.ui.registered_bots_heading') ?></h3>
      <div class="bm-bots">
      <?php foreach (($page['bots']??[]) as $botKey): ?>
        <a class="bm-bot-tile" href="<?= $escapeMenu($bmQuery('menus',['bot'=>$botKey,'screen'=>''])) ?>">
            <span class="bm-bot-name" dir="ltr"><?= $escapeMenu($botKey) ?></span>
            <span><?= $bmHtml('core.bale-menu.ui.manage_menu') ?> <span aria-hidden="true">←</span></span>
        </a>
      <?php endforeach; ?>
      </div>
    </div>
    <form class="bm-card" action="/admin/communications/settings/bale-menus/register" method="post" accept-charset="UTF-8">
      <h3><?= $bmHtml('core.bale-menu.ui.add_bot_heading') ?></h3>
      <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.register','core','bale-menu-admin') ?></p>
      <input type="hidden" name="_token" value="<?= $escapeMenu($csrf) ?>">
      <div class="bm-grid">
        <div><label for="bm-new-key"><?= $bmHtml('core.bale-menu.ui.bot_key_label') ?></label><input id="bm-new-key" dir="ltr" name="bot_key" required pattern="[a-z][a-z0-9_-]{1,31}" maxlength="32" placeholder="<?= $bmHtml('core.bale-menu.ui.bot_key_placeholder') ?>"></div>
        <div><label for="bm-new-site"><?= $bmHtml('core.bale-menu.ui.site_slug_label') ?></label><div class="bm-site-input"><input id="bm-new-site" dir="ltr" name="site_slug" required pattern="[a-z][a-z0-9-]{2,47}-dev\.[a-z0-9][a-z0-9.-]{2,99}" maxlength="160" placeholder="<?= $bmHtml('core.bale-menu.ui.site_slug_placeholder') ?>"></div></div>
      </div>
      <button class="bm-btn" type="submit"><?= $bmHtml('core.bale-menu.ui.register_bot_button') ?></button>
    </form>
  </section>
  <section class="bm-panel" id="bm-transport" <?= $bmTab==='transport'?'':'hidden' ?> aria-label="<?= $bmHtml('core.bale-menu.ui.transport_aria') ?>">
    <div class="bm-card"><h3><?= $bmHtml('core.bale-menu.ui.transport_heading') ?></h3>
      <p class="bm-transport-selected-legacy" hidden><?= $bmHtml('core.bale-menu.ui.selected_bot_prefix') ?> <b dir="ltr">@<?= $escapeMenu($page['username']??'') ?></b></p>
      <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.transport_privacy','core','bale-menu-admin') ?></p>
      <div class="bm-callout"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.webhook_pending','core','bale-menu-admin') ?></div>
      <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.registration_scope','core','bale-menu-admin') ?></p>
    </div>
  </section>
  <section class="bm-panel" id="bm-messages" <?= $bmTab==='messages'?'':'hidden' ?> aria-label="<?= $bmHtml('core.bale-menu.ui.messages_aria') ?>">
    <div class="bm-card"><h3><?= $bmHtml('core.bale-menu.ui.messages_heading') ?></h3>
      <p class="bm-hint"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.managed_content','core','bale-menu-admin') ?></p>
      <a class="bm-link-btn" href="<?= $escapeMenu($bmQuery('menus')) ?>#bm-dialog-content"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.managed_content','core','bale-menu-admin') ?></a>
      <a class="bm-link-btn" href="/admin/system/help-texts?module=core&amp;q=core.bale-menu."><?= $bmHtml('core.bale-menu.ui.open_content_management') ?></a>
      <div class="bm-callout"><?= \App\Services\UiContent\UiContentInlineGuide::bodyHtml('core.bale-menu.guide.editor_scope','core','bale-menu-admin') ?></div>
    </div>
  </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
