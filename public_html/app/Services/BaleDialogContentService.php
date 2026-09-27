<?php
declare(strict_types=1);
namespace App\Services;

/* B7-A129: Immutable per-bot dialog copy, edited in IPKF. UI copy grants no permissions. */
final class BaleDialogContentService
{
    public static function forProvider(array $provider): array
    {
        if (!defined('BASE_PATH')) throw new \RuntimeException('DIALOG_BASE_UNAVAILABLE');
        $providerConfig=json_decode((string)($provider['configuration_json']??''),true,16,JSON_THROW_ON_ERROR);
        $username=is_array($providerConfig) ? ltrim(trim((string)($providerConfig['bot_username']??'')),'@') : '';
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/D',$username)!==1) {
            throw new \RuntimeException('DIALOG_PROVIDER_USERNAME_UNAVAILABLE');
        }
        $file=dirname(BASE_PATH).'/.ipkf-bale-menu-admin/config.json';
        if (!is_file($file) || is_link($file) || filesize($file)>8192) throw new \RuntimeException('DIALOG_CONFIG_UNAVAILABLE');
        $config=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($config) || ($config['schema_version']??null)!==1 || !is_array($config['bots']??null) || count($config['bots'])>20) {
            throw new \RuntimeException('DIALOG_CONFIG_INVALID');
        }
        $matched=null;
        foreach ($config['bots'] as $key=>$entry) {
            if (!is_string($key) || !is_array($entry) ||
                preg_match('/^[a-z][a-z0-9_-]{1,31}$/D',$key)!==1) continue;
            // Reuse the restricted existing editor's runtime lookup, not a guessed bot path.
            $page=(new BaleMenuManagementService())->page($key,'');
            if (strcasecmp((string)($page['username']??''),$username)!==0) continue;
            if ($matched!==null) throw new \RuntimeException('DIALOG_PROVIDER_AMBIGUOUS');
            $matched=$page['service_content']??null;
        }
        if (!is_array($matched) || count($matched)<1 || count($matched)>150) {
            throw new \RuntimeException('DIALOG_CONTENT_MISSING');
        }
        self::validate($matched);
        return $matched;
    }

    public static function validate(array $values): void
    {
        if (count($values)<1 || count($values)>150) throw new \RuntimeException('DIALOG_COUNT_INVALID');
        foreach ($values as $key=>$value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9._-]{1,79}$/D',$key)!==1 ||
                !is_string($value) || trim($value)==='' || strlen($value)>1500 ||
                preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value)) {
                throw new \RuntimeException('DIALOG_CONTENT_INVALID');
            }
        }
    }

    public static function text(array $dialog, string $key, array $params=[]): string
    {
        $value=$dialog[$key]??null;
        if (!is_string($value) || trim($value)==='') throw new \RuntimeException('DIALOG_CONTENT_KEY_MISSING');
        // Substitutions are data only. Variables cannot change a ticket's ownership or routing.
        $replacements=[];
        foreach ($params as $name=>$part) {
            if (!is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,30}$/D',$name)!==1 ||
                !is_scalar($part) || strlen((string)$part)>400) throw new \RuntimeException('DIALOG_PARAMETER_INVALID');
            $replacements['{'.$name.'}']=(string)$part;
        }
        preg_match_all('/\{[a-z][a-z0-9_]{0,30}\}/',$value,$placeholders);
        foreach ($placeholders[0] as $placeholder) {
            if (!array_key_exists($placeholder,$replacements)) throw new \RuntimeException('DIALOG_PARAMETER_MISSING');
        }
        return strtr($value,$replacements);
    }
}
