<?php
declare(strict_types=1);
namespace App\Services;

/* UI-only editor for the already deployed, private, immutable bot catalog.
 * The bot token is never accessed. Existing screen and button identities,
 * targets and default route remain immutable in this editor.
 */
final class BaleMenuManagementService
{
    private function config(): array
    {
        if (!defined('BASE_PATH')) throw new \RuntimeException('MENU_BASE_UNAVAILABLE');
        $path = dirname(BASE_PATH) . '/.ipkf-bale-menu-admin/config.json';
        if (!is_file($path) || is_link($path) || !is_readable($path) || filesize($path) > 8192) {
            throw new \RuntimeException('MENU_PRIVATE_CONFIG_UNAVAILABLE');
        }
        $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema_version'] ?? null) !== 1 || !is_array($data['bots'] ?? null)) {
            throw new \RuntimeException('MENU_PRIVATE_CONFIG_INVALID');
        }
        return $data['bots'];
    }

    private function bot(string $key): array
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $key) !== 1) {
            throw new \RuntimeException('MENU_BOT_KEY_INVALID');
        }
        $bots = $this->config();
        $config = $bots[$key] ?? null;
        $path = is_array($config) ? ($config['app'] ?? null) : null;
        if (!is_string($path) || !str_starts_with($path, dirname(BASE_PATH) . '/') ||
            !preg_match('~^/[A-Za-z0-9/._-]+$~D', $path) || str_contains($path, '/..') ||
            !is_dir($path) || is_link($path)) {
            throw new \RuntimeException('MENU_BOT_NOT_CONFIGURED');
        }
        foreach (['BaleRuntimeConfig.php', 'BaleCatalogRegistry.php', 'BaleInlineMenu.php', 'BaleMenuPresentation.php'] as $name) {
            if (!is_file($path . '/' . $name) || is_link($path . '/' . $name)) {
                throw new \RuntimeException('MENU_BOT_CONTRACT_MISSING');
            }
        }
        require_once $path . '/BaleRuntimeConfig.php';
        require_once $path . '/BaleCatalogRegistry.php';
        require_once $path . '/BaleMenuPresentation.php';
        $runtime = \npBaleRuntimeLoad($path);
        if (($runtime['environment'] ?? '') !== 'dev') throw new \RuntimeException('MENU_PRODUCTION_REFUSED');
        return [$runtime, $bots];
    }

    public function page(string $botKey, string $screenKey): array
    {
        [$runtime, $bots] = $this->bot($botKey);
        $active = \npBaleRegistryReadActive($runtime['paths']['catalog_dir']);
        $catalog = $active['catalog'];
        if ($screenKey === '' || !isset($catalog['screens'][$screenKey])) {
            $screenKey = $catalog['default_screen'];
        }
        return [
            'bots' => array_keys($bots),
            'bot' => $botKey,
            'username' => $runtime['bot_username'],
            'active_hash' => $active['sha256'],
            'revision' => $active['revision'],
            'screen_key' => $screenKey,
            'screens' => $catalog['screens'],
            'service_content' => $catalog['service_content'] ?? [],
            'default_screen' => $catalog['default_screen'],
        ];
    }

    private static function saveAtomic(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) || is_link($dir) || is_link($path)) throw new \RuntimeException('MENU_UNSAFE_POINTER');
        $temp = $dir . '/.a123-' . bin2hex(random_bytes(12));
        $fh = fopen($temp, 'x');
        if ($fh === false) throw new \RuntimeException('MENU_TEMP_CREATE_FAILED');
        $ok = false;
        try {
            chmod($temp, 0600);
            $length = strlen($content);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($fh, substr($content, $offset));
                if (!is_int($written) || $written < 1) throw new \RuntimeException('MENU_WRITE_FAILED');
                $offset += $written;
            }
            if (!fflush($fh) || !fsync($fh)) throw new \RuntimeException('MENU_FSYNC_FAILED');
            $ok = true;
        } finally {
            fclose($fh);
            if (!$ok) @unlink($temp);
        }
        if (!rename($temp, $path)) {
            @unlink($temp);
            throw new \RuntimeException('MENU_RENAME_FAILED');
        }
    }

    public function publish(string $botKey, array $input, int $actorId): string
    {
        if ($actorId < 1) throw new \RuntimeException('MENU_ACTOR_INVALID');
        [$runtime] = $this->bot($botKey);
        $catalogDir = $runtime['paths']['catalog_dir'];
        if (!is_dir($catalogDir) || is_link($catalogDir)) throw new \RuntimeException('MENU_CATALOG_UNSAFE');
        $lockPath = $catalogDir . '/.a121-publisher.lock';
        if (is_link($lockPath)) throw new \RuntimeException('MENU_LOCK_UNSAFE');
        $lock = fopen($lockPath, 'c');
        if ($lock === false) throw new \RuntimeException('MENU_LOCK_FAILED');
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new \RuntimeException('MENU_PUBLISH_BUSY');
            $pointerPath = $catalogDir . '/active.json';
            if (!is_file($pointerPath) || is_link($pointerPath) || filesize($pointerPath) > 4096) {
                throw new \RuntimeException('MENU_POINTER_UNSAFE');
            }
            $pointerBefore = file_get_contents($pointerPath);
            if (!is_string($pointerBefore)) throw new \RuntimeException('MENU_POINTER_UNREADABLE');
            $active = \npBaleRegistryReadActive($catalogDir);
            $expected = $input['active_hash'] ?? null;
            if (!is_string($expected) || !hash_equals($active['sha256'], $expected)) {
                throw new \RuntimeException('MENU_STALE_FORM_REFRESH');
            }
            $screenKey = $input['screen_key'] ?? null;
            if (!is_string($screenKey) || !isset($active['catalog']['screens'][$screenKey])) {
                throw new \RuntimeException('MENU_SCREEN_INVALID');
            }
            $mode = $input['mode'] ?? null;
            if (!in_array($mode, ['inline', 'reply'], true)) throw new \RuntimeException('MENU_MODE_INVALID');
            $columns = $input['columns'] ?? null;
            if (!in_array((string) $columns, ['1', '2', '3'], true)) throw new \RuntimeException('MENU_COLUMNS_INVALID');
            $text = $input['screen_text'] ?? null;
            if (!is_string($text) || trim($text) === '' || strlen($text) > 8192) {
                throw new \RuntimeException('MENU_TEXT_INVALID');
            }
            $labels = $input['labels'] ?? null;
            $orders = $input['orders'] ?? null;
            if (!is_array($labels) || !is_array($orders)) throw new \RuntimeException('MENU_BUTTON_INPUT_INVALID');
            $catalog = $active['catalog'];
            // Dynamic dialog copy and inline labels belong to the SAME immutable release.
            $previousContent=$catalog['service_content']??null;
            $postedContent=$input['service_content']??null;
            if (!is_array($previousContent) || !is_array($postedContent) ||
                count($previousContent)<1 || count($previousContent)>150 ||
                count($previousContent)!==count($postedContent) ||
                array_diff(array_keys($previousContent),array_keys($postedContent))!==[] ||
                array_diff(array_keys($postedContent),array_keys($previousContent))!==[]) {
                throw new \RuntimeException('MENU_SERVICE_CONTENT_IDENTITY_CHANGED');
            }
            $cleanContent=[];
            foreach ($previousContent as $contentKey=>$previousText) {
                $newText=$postedContent[$contentKey]??null;
                if (!is_string($newText) || trim($newText)==='' || strlen($newText)>1500 ||
                    preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$newText)) {
                    throw new \RuntimeException('MENU_SERVICE_CONTENT_INVALID');
                }
                // A text editor can change prose, never the placeholder contract.
                preg_match_all('/\{[a-z][a-z0-9_]{0,30}\}/',(string)$previousText,$oldTags);
                preg_match_all('/\{[a-z][a-z0-9_]{0,30}\}/',$newText,$newTags);
                sort($oldTags[0]); sort($newTags[0]);
                if ($oldTags[0]!==$newTags[0]) throw new \RuntimeException('MENU_CONTENT_PARAMETERS_IMMUTABLE');
                $cleanContent[$contentKey]=$newText;
            }
            $catalog['service_content']=$cleanContent;
            $screen = $catalog['screens'][$screenKey];
            $existing = [];
            foreach ($screen['button_rows'] as $row) foreach ($row as $button) {
                if (isset($existing[$button['key']])) throw new \RuntimeException('MENU_DUPLICATE_KEY');
                $existing[$button['key']] = $button;
            }
            if (count($existing) !== count($labels) || count($existing) !== count($orders) ||
                array_diff(array_keys($existing), array_keys($labels)) !== [] ||
                array_diff(array_keys($existing), array_keys($orders)) !== []) {
                throw new \RuntimeException('MENU_BUTTON_IDENTITY_CHANGED');
            }
            $ordered = [];
            $ordinal = 0;
            foreach ($existing as $key => $button) {
                $label = $labels[$key];
                $order = $orders[$key];
                if (!is_string($label) || trim($label) === '' || strlen($label) > 256 ||
                    !is_scalar($order) || preg_match('/^(?:[1-9]|[1-9][0-9]{1,2})$/D', (string)$order) !== 1) {
                    throw new \RuntimeException('MENU_BUTTON_FIELD_INVALID');
                }
                $button['label'] = trim($label);
                $ordered[] = ['order' => (int)$order, 'ordinal' => $ordinal++, 'button' => $button];
            }
            usort($ordered, static fn(array $a, array $b): int =>
                ($a['order'] <=> $b['order']) ?: ($a['ordinal'] <=> $b['ordinal']));
            $buttons = array_map(static fn(array $entry): array => $entry['button'], $ordered);
            $rows = array_chunk($buttons, (int) $columns);
            if (count($rows) > 12) throw new \RuntimeException('MENU_TOO_MANY_ROWS');
            $screen['text'] = trim($text);
            $screen['button_rows'] = $rows;
            $screen['menu_mode'] = $mode;
            $catalog['screens'][$screenKey] = $screen;
            if ($catalog['screens'] === $active['catalog']['screens'] &&
                $catalog['service_content'] === $active['catalog']['service_content']) return 'unchanged';
            $catalog['revision'] = 'a123-' . substr(hash('sha256', $active['sha256'] . '|' .
                json_encode([$catalog['screens'],$catalog['service_content']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 24);
            \npBaleCatalogValidate($catalog);
            foreach ($catalog['screens'] as $key => $item) {
                \npBaleCatalogRender($catalog, $key);
                \npBaleConfiguredMarkup($catalog, $key);
            }
            // Prevent an old reply-keyboard label from being reassigned to a different target.
            $oldLabels = [];
            foreach ($active['catalog']['screens'] as $item) foreach ($item['button_rows'] as $row)
                foreach ($row as $button) $oldLabels[trim($button['label'])] = $button['target'];
            foreach ($catalog['screens'] as $item) foreach ($item['button_rows'] as $row)
                foreach ($row as $button) {
                    $label = trim($button['label']);
                    if (isset($oldLabels[$label]) && $oldLabels[$label] !== $button['target']) {
                        throw new \RuntimeException('MENU_HISTORICAL_LABEL_CONFLICT');
                    }
                }
            // Retained old reply keyboards must never route a reused label
            // to a different destination after this publication.
            $releaseFiles = glob($catalogDir . '/releases/*.json');
            if ($releaseFiles === false || count($releaseFiles) > 100) {
                throw new \RuntimeException('MENU_HISTORICAL_RELEASE_SCAN_FAILED');
            }
            $allHistorical = $oldLabels;
            foreach ($releaseFiles as $releaseFile) {
                $releaseHash = basename($releaseFile, '.json');
                $previous = \npBaleRegistryReadRelease($catalogDir . '/releases', $releaseHash);
                foreach ($previous['screens'] as $previousScreen) {
                    foreach ($previousScreen['button_rows'] as $previousRow) {
                        foreach ($previousRow as $previousButton) {
                            $priorLabel = trim($previousButton['label']);
                            $priorTarget = $previousButton['target'];
                            if (isset($allHistorical[$priorLabel]) && $allHistorical[$priorLabel] !== $priorTarget) {
                                throw new \RuntimeException('MENU_HISTORICAL_LABEL_CONFLICT');
                            }
                            $allHistorical[$priorLabel] = $priorTarget;
                        }
                    }
                }
            }
            foreach ($catalog['screens'] as $currentScreen) foreach ($currentScreen['button_rows'] as $currentRow)
                foreach ($currentRow as $currentButton) {
                    $currentLabel = trim($currentButton['label']);
                    if (isset($allHistorical[$currentLabel]) && $allHistorical[$currentLabel] !== $currentButton['target']) {
                        throw new \RuntimeException('MENU_HISTORICAL_LABEL_CONFLICT');
                    }
                }
            $newHash = \npBaleRegistryPublish($catalogDir . '/releases', $catalog);
            $newPointer = json_encode(['version'=>1, 'sha256'=>$newHash, 'revision'=>$catalog['revision']],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
            if (file_get_contents($pointerPath) !== $pointerBefore) {
                throw new \RuntimeException('MENU_CONCURRENT_POINTER_CHANGE');
            }
            // Durable audit before pointer mutation; log contains no token or message contents.
            $audit = dirname(BASE_PATH) . '/.ipkf-bale-menu-admin/audit.ndjson';
            if (is_link($audit)) throw new \RuntimeException('MENU_AUDIT_PATH_UNSAFE');
            $line = json_encode(['event'=>'publish_attempt','utc'=>gmdate('c'),'actor_id'=>$actorId,
                'bot_key'=>$botKey, 'screen_key'=>$screenKey, 'from'=>$active['sha256'], 'to'=>$newHash],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
            $auditFile = fopen($audit, 'a');
            if ($auditFile === false) throw new \RuntimeException('MENU_AUDIT_UNAVAILABLE');
            try {
                if (!flock($auditFile, LOCK_EX) || fwrite($auditFile, $line) !== strlen($line) ||
                    !fflush($auditFile) || !fsync($auditFile)) throw new \RuntimeException('MENU_AUDIT_WRITE_FAILED');
                chmod($audit, 0600);
            } finally {
                flock($auditFile, LOCK_UN);
                fclose($auditFile);
            }
            self::saveAtomic($pointerPath, $newPointer);
            try {
                $verified = \npBaleRegistryReadActive($catalogDir);
                if ($verified['sha256'] !== $newHash || $verified['catalog'] !== $catalog) {
                    throw new \RuntimeException('MENU_PUBLICATION_VERIFY_FAILED');
                }
            } catch (\Throwable $error) {
                if (file_get_contents($pointerPath) === $newPointer) {
                    self::saveAtomic($pointerPath, $pointerBefore);
                }
                throw $error;
            }
            return 'published';
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /* B7_A130_MULTI_BOT_REGISTER — pre-provisioned, isolated DEV instances only. */
    public function registerProvisionedBot(string $key, string $site, int $actorId): string
    {
        if ($actorId < 1 || preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $key) !== 1 ||
            preg_match('/^[a-z][a-z0-9-]{2,47}-dev\.[a-z0-9][a-z0-9.-]{2,99}$/D', $site) !== 1 || str_contains($site,'..')) {
            throw new \RuntimeException('BOT_REGISTRATION_INVALID');
        }
        if (!defined('BASE_PATH') || !str_ends_with(BASE_PATH, '/dev.troca.ir')) {
            throw new \RuntimeException('BOT_CORE_DEV_REQUIRED');
        }
        $home = dirname(BASE_PATH);
        $private = $home . '/.ipkf-bale-menu-admin';
        $registry = $private . '/config.json';
        $lockPath = $private . '/.registration.lock';
        if (!is_dir($private) || is_link($private) || !is_file($registry) || is_link($registry) || is_link($lockPath)) {
            throw new \RuntimeException('BOT_REGISTRY_UNAVAILABLE');
        }
        $lock = fopen($lockPath, 'c');
        if ($lock === false) throw new \RuntimeException('BOT_REGISTRATION_BUSY');
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new \RuntimeException('BOT_REGISTRATION_BUSY');
            $before = file_get_contents($registry);
            if (!is_string($before) || strlen($before)>8192) throw new \RuntimeException('BOT_REGISTRY_UNAVAILABLE');
            $registryData = json_decode($before, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($registryData) || ($registryData['schema_version'] ?? null)!==1 || !is_array($registryData['bots'] ?? null)) {
                throw new \RuntimeException('BOT_REGISTRY_UNAVAILABLE');
            }
            $bots = $registryData['bots'];
            if (isset($bots[$key])) throw new \RuntimeException('BOT_KEY_EXISTS');
            if (count($bots)>=24) throw new \RuntimeException('BOT_REGISTRY_LIMIT');
            $candidate = $home . '/' . $site . '/app';
            // One parent prefix, no user-controllable absolute paths or traversal.
            if (!is_dir($candidate) || is_link($candidate) || realpath($candidate)!==$candidate) {
                throw new \RuntimeException('BOT_NOT_PROVISIONED');
            }
            foreach (['BaleRuntimeConfig.php','BaleCatalogRegistry.php','BaleInlineMenu.php',
                'BaleMenuPresentation.php','BalePollingWorker.php','BaleServiceQueryWorker.php'] as $required) {
                if (!is_file($candidate.'/'.$required) || is_link($candidate.'/'.$required)) {
                    throw new \RuntimeException('BOT_NOT_PROVISIONED');
                }
            }
            // Runtime loader is the existing audited loader. Do not read any token contents.
            $firstKey = array_key_first($bots);
            if (!is_string($firstKey)) throw new \RuntimeException('BOT_REGISTRY_UNAVAILABLE');
            [$existingRuntime] = $this->bot($firstKey);
            $runtime = \npBaleRuntimeLoad($candidate);
            if (($runtime['environment'] ?? '')!=='dev' || ($runtime['app'] ?? '')!==$candidate) {
                throw new \RuntimeException('BOT_NOT_DEV');
            }
            $username = strtolower((string)($runtime['bot_username'] ?? ''));
            foreach ($bots as $existingKey => $existingConfig) {
                if (!is_array($existingConfig) || !is_string($existingConfig['app'] ?? null)) {
                    throw new \RuntimeException('BOT_REGISTRY_UNAVAILABLE');
                }
                if ($existingConfig['app']===$candidate) throw new \RuntimeException('BOT_ALREADY_REGISTERED');
                $other = \npBaleRuntimeLoad($existingConfig['app']);
                if ($username===strtolower((string)$other['bot_username']) ||
                    ($runtime['paths']['catalog_dir'] ?? null)===($other['paths']['catalog_dir'] ?? null) ||
                    ($runtime['paths']['token_file'] ?? null)===($other['paths']['token_file'] ?? null)) {
                    throw new \RuntimeException('BOT_IDENTITY_CONFLICT');
                }
            }
            // A bot without its OWN valid catalog cannot be exposed in the editor.
            $catalog = \npBaleRegistryReadActive($runtime['paths']['catalog_dir']);
            if (!is_array($catalog['catalog']['screens'] ?? null) || ($catalog['catalog']['screens'] ?? [])===[]) {
                throw new \RuntimeException('BOT_CATALOG_NOT_READY');
            }
            $registryData['bots'][$key] = ['app'=>$candidate];
            $after = json_encode($registryData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (strlen($after)>8192 || file_get_contents($registry)!==$before) {
                throw new \RuntimeException('BOT_REGISTRY_CHANGED');
            }
            // Durable audit is recorded before registration publication.
            $auditPath = $private.'/audit.ndjson';
            if (is_link($auditPath)) throw new \RuntimeException('BOT_AUDIT_UNAVAILABLE');
            $auditLine = json_encode(['event'=>'bot_registration','utc'=>gmdate('c'),
                'actor_id'=>$actorId,'bot_key'=>$key,'username'=>$username],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $audit = fopen($auditPath,'a');
            if ($audit===false) throw new \RuntimeException('BOT_AUDIT_UNAVAILABLE');
            try {
                if (!flock($audit,LOCK_EX) || fwrite($audit,$auditLine)!==strlen($auditLine) ||
                    !fflush($audit) || !fsync($audit)) throw new \RuntimeException('BOT_AUDIT_UNAVAILABLE');
                chmod($auditPath,0600);
            } finally {flock($audit,LOCK_UN);fclose($audit);}
            self::saveAtomic($registry,$after);
            if (file_get_contents($registry)!==$after) {
                throw new \RuntimeException('BOT_REGISTRY_VERIFY_FAILED');
            }
            chmod($registry,0600);
            return 'bot_registered';
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }
}
