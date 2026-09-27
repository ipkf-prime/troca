<?php

declare(strict_types=1);
namespace IPKF\Database\Migrations;
use RuntimeException;
final class SeedTicketingPhase1MembershipUiContent extends Migration
{
    public function up(): void
    {
        $items = [
            'ticketing.membership.affiliation.page_title' => 'وابستگی سازمانی پروژه',
            'ticketing.membership.affiliation.heading' => 'انتخاب شرکت / سازمان',
            'ticketing.membership.affiliation.project_prefix' => 'وابستگی سازمانی برای پروژه',
            'ticketing.membership.affiliation.identity_required' => 'ابتدا اطلاعات هویتی حساب کاربری خود را تکمیل کنید.',
            'ticketing.membership.affiliation.identity_action' => 'تکمیل اطلاعات هویتی',
            'ticketing.membership.affiliation.pending' => 'درخواست وابستگی سازمانی شما در انتظار بررسی است.',
            'ticketing.membership.affiliation.no_organization' => 'سازمان فعالی برای این پروژه در دسترس نیست.',
            'ticketing.membership.affiliation.organization_label' => 'شرکت / سازمان',
            'ticketing.membership.affiliation.organization_placeholder' => 'انتخاب کنید',
            'ticketing.membership.affiliation.position_label' => 'سمت سازمانی',
            'ticketing.membership.affiliation.position_none' => 'بدون انتخاب سمت',
            'ticketing.membership.affiliation.primary_label' => 'وابستگی اصلی من',
            'ticketing.membership.affiliation.submit' => 'ثبت درخواست',
            'ticketing.membership.common.back' => 'بازگشت',
            'ticketing.membership.request.pending' => 'درخواست عضویت شما ثبت شد و در انتظار تأیید مدیر پروژه است.',
            'ticketing.membership.request.pending_button' => 'در انتظار تأیید',
            'ticketing.membership.affiliation.select_action' => 'انتخاب شرکت / سازمان',
            'ticketing.membership.requests.page_title' => 'درخواست‌های عضویت',
            'ticketing.membership.requests.empty' => 'درخواست در انتظار بررسی وجود ندارد.',
            'ticketing.membership.requests.members_action' => 'اعضا و دسترسی‌ها',
            'ticketing.membership.requests.user_column' => 'کاربر',
            'ticketing.membership.requests.organization_column' => 'شرکت / سازمان',
            'ticketing.membership.requests.role_column' => 'نقش سازمانی',
            'ticketing.membership.requests.time_column' => 'زمان درخواست',
            'ticketing.membership.requests.actions_column' => 'عملیات',
            'ticketing.membership.requests.approve_action' => 'تأیید',
            'ticketing.membership.requests.reject_action' => 'رد',
            'ticketing.membership.requests.forbidden' => 'مدیریت درخواست‌های عضویت این پروژه برای شما مجاز نیست.',
            'ticketing.membership.requests.project_not_found' => 'پروژه پشتیبانی موردنظر پیدا نشد.',
        ];
        foreach ($items as $key => $body) {
            $definitionId = $this->definition($key);
            $this->override($definitionId, $key, $body);
        }
    }
    public function down(): void { /* managed content: non-destructive */ }
    private function definition(string $key): int
    {
        $reference = 'UICD-' . strtoupper(substr(hash('sha256', 'definition|' . $key), 0, 24));
        $q = $this->db->prepare("INSERT IGNORE INTO ui_content_definitions (public_reference,content_key,content_type,default_locale,description,is_active,created_by_user_reference,updated_by_user_reference,created_at,updated_at) VALUES (?,?,'guide','fa',?,1,'system:t1','system:t1',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $q->execute([$reference,$key,'Ticketing Phase 1 managed membership UI content']);
        $q = $this->db->prepare("SELECT id FROM ui_content_definitions WHERE content_key=? LIMIT 1");
        $q->execute([$key]); $id=(int)$q->fetchColumn();
        if ($id<1) throw new RuntimeException('ticketing_membership_ui_content_definition_unavailable');
        return $id;
    }
    private function override(int $definitionId,string $key,string $body): void
    {
        $scopeKey='scope:ticketing:surface:membership';
        $reference='UICO-'.strtoupper(substr(hash('sha256','override|'.$definitionId.'|'.$scopeKey.'|fa'),0,24));
        $scope=json_encode([['type'=>'surface','reference'=>'membership']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $meta=json_encode(['seed'=>'ticketing-phase1-t1','content_key'=>$key],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $q=$this->db->prepare("INSERT IGNORE INTO ui_content_overrides (public_reference,definition_id,scope_type,scope_key,module_key,scope_reference,scope_path_json,locale,body,visibility_mode,metadata_json,is_active,created_by_user_reference,updated_by_user_reference,created_at,updated_at) VALUES (?,?,'surface',?,'ticketing','membership',?,'fa',?,'show',?,1,'system:t1','system:t1',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $q->execute([$reference,$definitionId,$scopeKey,$scope,$body,$meta]);
    }
}
