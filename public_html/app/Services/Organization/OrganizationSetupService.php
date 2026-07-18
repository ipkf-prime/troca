<?php

namespace App\Services\Organization;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class OrganizationSetupService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function workspace(): array
    {
        try {
            return [
                'ok' => true,
                'organizations' => $this->organizations(),
                'units' => $this->units(),
                'position_templates' => $this->positionTemplates(),
                'organization_positions' => $this->organizationPositions(),
                'users' => $this->users(),
                'persons' => $this->persons(),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'organizations' => [], 'units' => [], 'position_templates' => [],
                'organization_positions' => [], 'users' => [], 'persons' => [],
            ];
        }
    }

    public function createOrganization(array $data): string
    {
        $titleFa = $this->required($data['title_fa'] ?? '', 'عنوان فارسی سازمان الزامی است.', 255);
        $titleEn = $this->optional($data['title_en'] ?? '', 255);
        $shortTitle = $this->optional($data['short_title'] ?? '', 150);
        $parentRef = trim((string)($data['parent_reference'] ?? ''));
        $parentId = null; $depth = 0; $path = null;
        if ($parentRef !== '') {
            $parent = $this->row('SELECT id, depth, path FROM organizations WHERE public_reference=? AND deleted_at IS NULL LIMIT 1', [$parentRef]);
            if (!$parent) throw new RuntimeException('سازمان بالادست معتبر نیست.');
            $parentId = (int)$parent['id']; $depth = min(255, (int)$parent['depth'] + 1);
            $path = trim((string)($parent['path'] ?? ''), '/');
        }
        $ref = $this->uuid();
        $st = $this->db->prepare("INSERT INTO organizations (public_reference,parent_id,title,title_fa,title_en,short_title,depth,path,sort_order,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $sort = $this->int($data['sort_order'] ?? 0, 0, 100000);
        $st->execute([$ref,$parentId,$titleFa,$titleFa,$titleEn,$shortTitle,$depth,$path,$sort]);
        $id=(int)$this->db->lastInsertId();
        $finalPath=trim(($path ? $path.'/' : '').$id,'/');
        $this->db->prepare('UPDATE organizations SET path=? WHERE id=?')->execute([$finalPath,$id]);
        return $ref;
    }

    public function createUnit(array $data): string
    {
        $orgRef = trim((string)($data['organization_reference'] ?? ''));
        $orgId = $this->scalar('SELECT id FROM organizations WHERE public_reference=? AND is_active=1 AND deleted_at IS NULL LIMIT 1', [$orgRef]);
        if (!$orgId) throw new RuntimeException('انتخاب سازمان الزامی است.');
        $titleFa=$this->required($data['title_fa']??'','عنوان فارسی واحد الزامی است.',255);
        $titleEn=$this->optional($data['title_en']??'',255);
        $code=$this->optional($data['code']??'',100);
        $parentRef=trim((string)($data['parent_reference']??''));
        $parentId=null;$depth=0;$path=null;
        if($parentRef!==''){
            $parent=$this->row('SELECT id,organization_id,depth,path FROM org_units WHERE public_reference=? AND status=\'active\' AND deleted_at IS NULL LIMIT 1',[$parentRef]);
            if(!$parent || (int)$parent['organization_id']!==(int)$orgId) throw new RuntimeException('واحد بالادست باید متعلق به همان سازمان باشد.');
            $parentId=(int)$parent['id'];$depth=min(255,(int)$parent['depth']+1);$path=trim((string)($parent['path']??''),'/');
        }
        if($code!==null){
            $dupe=$this->scalar('SELECT COUNT(*) FROM org_units WHERE organization_id=? AND code=? AND deleted_at IS NULL',[(int)$orgId,$code]);
            if((int)$dupe>0) throw new RuntimeException('کد واحد در این سازمان تکراری است.');
        }
        $ref=$this->uuid();$sort=$this->int($data['sort_order']??0,0,100000);
        $st=$this->db->prepare("INSERT INTO org_units (public_reference,organization_id,parent_id,code,title,title_fa,title_en,depth,path,sort_order,status,description,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $st->execute([$ref,(int)$orgId,$parentId,$code,$titleFa,$titleFa,$titleEn,$depth,$path,$sort,$this->optional($data['description']??'',2000)]);
        $id=(int)$this->db->lastInsertId();$finalPath=trim(($path?$path.'/':'').$id,'/');
        $this->db->prepare('UPDATE org_units SET path=? WHERE id=?')->execute([$finalPath,$id]);
        return $ref;
    }

    public function createOrganizationPosition(array $data): string
    {
        $orgRef=trim((string)($data['organization_reference']??''));
        $orgId=$this->scalar('SELECT id FROM organizations WHERE public_reference=? AND is_active=1 AND deleted_at IS NULL LIMIT 1',[$orgRef]);
        if(!$orgId) throw new RuntimeException('انتخاب سازمان الزامی است.');
        $unitRef=trim((string)($data['unit_reference']??''));$unitId=null;
        if($unitRef!==''){
            $unit=$this->row('SELECT id,organization_id FROM org_units WHERE public_reference=? AND status=\'active\' AND deleted_at IS NULL LIMIT 1',[$unitRef]);
            if(!$unit || (int)$unit['organization_id']!==(int)$orgId) throw new RuntimeException('واحد انتخاب‌شده متعلق به سازمان نیست.');
            $unitId=(int)$unit['id'];
        }
        $titleFa=$this->required($data['title_fa']??'','عنوان فارسی پست الزامی است.',255);
        $titleEn=$this->optional($data['title_en']??'',255);$code=$this->optional($data['code']??'',100);
        $templateId=$this->scalar('SELECT id FROM positions WHERE title=? AND status=\'active\' LIMIT 1',[$titleFa]);
        if(!$templateId){
            $templateRef=$this->uuid();
            $st=$this->db->prepare("INSERT INTO positions (public_reference,code,title,title_fa,title_en,status,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $st->execute([$templateRef,$code,$titleFa,$titleFa,$titleEn,$this->int($data['sort_order']??0,0,100000)]);
            $templateId=(int)$this->db->lastInsertId();
        }
        $ref=$this->uuid();
        $st=$this->db->prepare("INSERT INTO organization_positions (public_reference,organization_id,org_unit_id,position_id,code,title_override,title_fa,title_en,headcount_limit,is_head,is_acting_allowed,status,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $st->execute([$ref,(int)$orgId,$unitId,(int)$templateId,$code,$titleFa,$titleFa,$titleEn,$this->int($data['headcount_limit']??1,1,10000),isset($data['is_head'])?1:0,$this->int($data['sort_order']??0,0,100000)]);
        return $ref;
    }

    public function linkUserToPerson(array $data): void
    {
        $userId=$this->int($data['user_id']??0,1,PHP_INT_MAX);
        $personRef=trim((string)($data['person_reference']??''));
        $personId=$this->scalar('SELECT id FROM persons WHERE public_reference=? AND status=\'active\' AND deleted_at IS NULL LIMIT 1',[$personRef]);
        if(!$personId) throw new RuntimeException('شخص انتخاب‌شده معتبر نیست.');
        $user=$this->row('SELECT id,person_id FROM users WHERE id=? AND status=\'active\' AND deleted_at IS NULL LIMIT 1',[$userId]);
        if(!$user) throw new RuntimeException('کاربر انتخاب‌شده معتبر نیست.');
        $used=$this->scalar('SELECT id FROM users WHERE person_id=? AND id<>? AND deleted_at IS NULL LIMIT 1',[(int)$personId,$userId]);
        if($used) throw new RuntimeException('این شخص قبلاً به حساب کاربری دیگری متصل شده است.');
        $this->db->prepare('UPDATE users SET person_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$personId,$userId]);
    }

    private function organizations(): array {
        $rows=$this->db->query("SELECT id,public_reference,COALESCE(NULLIF(title_fa,''),title) title,title_en,parent_id,depth,is_active FROM organizations WHERE deleted_at IS NULL ORDER BY depth,sort_order,title LIMIT 500")->fetchAll(PDO::FETCH_ASSOC)?:[];
        $map=[];foreach($rows as $r){$map[(int)$r['id']]=$r;}
        foreach($rows as &$r){$r['display_path']=$this->pathLabel((int)$r['id'],$map);}
        return $rows;
    }
    private function units(): array {
        $rows=$this->db->query("SELECT ou.id,ou.public_reference,ou.organization_id,ou.parent_id,COALESCE(NULLIF(ou.title_fa,''),ou.title) title,ou.title_en,ou.depth,COALESCE(NULLIF(o.title_fa,''),o.title) organization_title FROM org_units ou INNER JOIN organizations o ON o.id=ou.organization_id WHERE ou.deleted_at IS NULL AND ou.status='active' ORDER BY organization_title,ou.depth,ou.sort_order,ou.title LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC)?:[];
        $map=[];foreach($rows as $r){$map[(int)$r['id']]=$r;}
        foreach($rows as &$r){$r['unit_path']=$this->pathLabel((int)$r['id'],$map);$r['display_path']=$r['organization_title'].' ← '.$r['unit_path'];}
        return $rows;
    }
    private function positionTemplates(): array { return $this->db->query("SELECT public_reference,COALESCE(NULLIF(title_fa,''),title) title,title_en,code FROM positions WHERE status='active' ORDER BY sort_order,title LIMIT 500")->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    private function organizationPositions(): array { return $this->db->query("SELECT op.public_reference,COALESCE(NULLIF(op.title_fa,''),NULLIF(op.title_override,''),p.title) title,COALESCE(NULLIF(o.title_fa,''),o.title) organization_title,COALESCE(NULLIF(ou.title_fa,''),ou.title) unit_title,op.is_head,op.headcount_limit FROM organization_positions op INNER JOIN organizations o ON o.id=op.organization_id INNER JOIN positions p ON p.id=op.position_id LEFT JOIN org_units ou ON ou.id=op.org_unit_id WHERE op.status='active' ORDER BY organization_title,unit_title,op.sort_order,title LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    private function users(): array { return $this->db->query("SELECT u.id,u.username,u.email,u.mobile,u.person_id,COALESCE(NULLIF(p.display_name_fa,''),p.full_name) person_name FROM users u LEFT JOIN persons p ON p.id=u.person_id WHERE u.status='active' AND u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    private function persons(): array { return $this->db->query("SELECT public_reference,COALESCE(NULLIF(display_name_fa,''),full_name) title,national_code FROM persons WHERE status='active' AND deleted_at IS NULL ORDER BY title LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    private function required(mixed $v,string $m,int $max):string{$v=trim((string)$v);if($v==='')throw new RuntimeException($m);return $this->cut($v,$max);}
    private function optional(mixed $v,int $max):?string{$v=trim((string)$v);return $v===''?null:$this->cut($v,$max);}
    private function cut(string $v,int $max):string{return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);}
    private function int(mixed $v,int $min,int $max):int{$n=filter_var($v,FILTER_VALIDATE_INT);if($n===false||$n<$min||$n>$max) return $min;return (int)$n;}
    private function uuid():string{return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',random_int(0,65535),random_int(0,65535),random_int(0,65535),random_int(0,4095)|0x4000,random_int(0,16383)|0x8000,random_int(0,65535),random_int(0,65535),random_int(0,65535));}
    private function pathLabel(int $id,array $map):string{$parts=[];$guard=0;while($id>0&&isset($map[$id])&&$guard++<30){array_unshift($parts,(string)$map[$id]['title']);$id=(int)($map[$id]['parent_id']??0);}return implode(' ← ',$parts);}
    private function scalar(string $sql,array $p){$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchColumn();}
    private function row(string $sql,array $p):?array{$s=$this->db->prepare($sql);$s->execute($p);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
}
