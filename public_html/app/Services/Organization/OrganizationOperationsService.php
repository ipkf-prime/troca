<?php

namespace App\Services\Organization;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;
use App\Support\PersianDate;

class OrganizationOperationsService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function chart(): array
    {
        try {
            $statement = $this->db->query("\n                SELECT\n                    o.public_reference AS organization_reference,\n                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title,\n                    o.is_active AS organization_active,\n                    ou.public_reference AS unit_reference,\n                    COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS unit_title,\n                    parent.public_reference AS parent_unit_reference,\n                    op.public_reference AS organization_position_reference,\n                    COALESCE(NULLIF(op.title_fa, ''), NULLIF(op.title_override, ''), p.title) AS position_title,\n                    op.is_head,\n                    op.status AS position_status,\n                    a.public_reference AS appointment_reference,\n                    COALESCE(NULLIF(person.display_name_fa, ''), person.full_name) AS occupant_name,\n                    a.is_primary,\n                    a.appointment_kind,\n                    a.valid_from,\n                    a.valid_to\n                FROM organizations o\n                LEFT JOIN org_units ou ON ou.organization_id = o.id AND ou.status = 'active'\n                LEFT JOIN org_units parent ON parent.id = ou.parent_id\n                LEFT JOIN organization_positions op ON op.organization_id = o.id\n                    AND (op.org_unit_id = ou.id OR (op.org_unit_id IS NULL AND ou.id IS NULL))\n                    AND op.status = 'active'\n                LEFT JOIN positions p ON p.id = op.position_id\n                LEFT JOIN organization_appointments a ON a.organization_position_id = op.id\n                    AND a.status = 'active'\n                    AND a.revoked_at IS NULL\n                    AND (a.valid_from IS NULL OR a.valid_from <= CURRENT_DATE)\n                    AND (a.valid_to IS NULL OR a.valid_to >= CURRENT_DATE)\n                LEFT JOIN persons person ON person.id = a.person_id\n                ORDER BY o.title ASC, ou.sort_order ASC, ou.id ASC, op.is_head DESC, op.sort_order ASC, op.id ASC\n            ");
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return ['ok' => true, 'organizations' => $this->buildChart($rows)];
        } catch (Throwable) {
            return ['ok' => false, 'organizations' => []];
        }
    }

    public function appointments(string $query = ''): array
    {
        $query = trim($query);
        try {
            $sql = "\n                SELECT\n                    a.public_reference,\n                    COALESCE(NULLIF(person.display_name_fa, ''), person.full_name) AS person_name,\n                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title,\n                    COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS unit_title,\n                    COALESCE(NULLIF(op.title_fa, ''), NULLIF(op.title_override, ''), p.title) AS position_title,\n                    a.appointment_kind, a.is_primary, a.is_acting, a.status,\n                    a.valid_from, a.valid_to, a.appointment_reference\n                FROM organization_appointments a\n                INNER JOIN persons person ON person.id = a.person_id\n                INNER JOIN organizations o ON o.id = a.organization_id\n                INNER JOIN organization_positions op ON op.id = a.organization_position_id\n                INNER JOIN positions p ON p.id = op.position_id\n                LEFT JOIN org_units ou ON ou.id = op.org_unit_id\n                WHERE a.revoked_at IS NULL\n            ";
            $params=[];
            if ($query !== '') {
                $sql .= " AND (person.full_name LIKE ? OR person.display_name_fa LIKE ? OR o.title LIKE ? OR ou.title LIKE ? OR p.title LIKE ?)";
                $like='%'.$query.'%'; $params=[$like,$like,$like,$like,$like];
            }
            $sql .= " ORDER BY a.status='active' DESC, a.is_primary DESC, a.valid_from DESC, a.id DESC LIMIT 200";
            $st=$this->db->prepare($sql); $st->execute($params);
            $items=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach($items as &$item){
                $item['valid_from_fa']=PersianDate::fromGregorianDate($item['valid_from']??null);
                $item['valid_to_fa']=PersianDate::fromGregorianDate($item['valid_to']??null);
            }
            return ['ok'=>true,'items'=>$items,'q'=>$query];
        } catch (Throwable) {
            return ['ok'=>false,'items'=>[],'q'=>$query];
        }
    }

    public function formOptions(): array
    {
        $positions=$this->db->query("SELECT op.public_reference, op.organization_id, COALESCE(NULLIF(op.title_fa,''),NULLIF(op.title_override,''),p.title) title, COALESCE(NULLIF(o.title_fa,''),o.title) organization_title, COALESCE(NULLIF(ou.title_fa,''),ou.title) unit_title, ou.id AS unit_id FROM organization_positions op INNER JOIN positions p ON p.id=op.position_id INNER JOIN organizations o ON o.id=op.organization_id LEFT JOIN org_units ou ON ou.id=op.org_unit_id WHERE op.status='active' AND o.is_active=1 ORDER BY organization_title, unit_title, title LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $orgRows=$this->db->query("SELECT id,parent_id,COALESCE(NULLIF(title_fa,''),title) title FROM organizations WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $unitRows=$this->db->query("SELECT id,parent_id,organization_id,COALESCE(NULLIF(title_fa,''),title) title FROM org_units WHERE deleted_at IS NULL AND status='active'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $orgMap=[];foreach($orgRows as $r){$orgMap[(int)$r['id']]=$r;}
        $unitMap=[];foreach($unitRows as $r){$unitMap[(int)$r['id']]=$r;}
        foreach($positions as &$position){
            $orgPath=$this->pathLabel((int)$position['organization_id'],$orgMap);
            $unitPath=$position['unit_id']?$this->pathLabel((int)$position['unit_id'],$unitMap):'ستاد سازمان';
            $position['display_path']=$orgPath.' ← '.$unitPath.' ← '.$position['title'];
        }
        return [
            'persons' => $this->db->query("SELECT public_reference, COALESCE(NULLIF(display_name_fa,''), full_name) title FROM persons WHERE status='active' ORDER BY title LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'positions' => $positions,
        ];
    }

    public function createAppointment(array $data): string
    {
        $personRef=trim((string)($data['person_reference']??''));
        $positionRef=trim((string)($data['position_reference']??''));
        $kind=(string)($data['appointment_kind']??'permanent');
        if (!in_array($kind,['permanent','temporary','acting','delegated'],true)) $kind='permanent';
        if ($personRef==='' || $positionRef==='') throw new RuntimeException('انتخاب شخص و پست الزامی است.');
        $person=$this->scalar("SELECT id FROM persons WHERE public_reference=? AND status='active' LIMIT 1",[$personRef]);
        $position=$this->row("SELECT id, organization_id FROM organization_positions WHERE public_reference=? AND status='active' LIMIT 1",[$positionRef]);
        if (!$person || !$position) throw new RuntimeException('شخص یا پست انتخاب‌شده معتبر نیست.');
        $isPrimary=isset($data['is_primary']) ? 1 : 0;
        $validFrom=$this->dateOrNull($data['valid_from']??null); $validTo=$this->dateOrNull($data['valid_to']??null);
        if ($validFrom && $validTo && $validTo < $validFrom) throw new RuntimeException('تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.');
        $this->db->beginTransaction();
        try {
            if ($isPrimary===1) {
                $u=$this->db->prepare("UPDATE organization_appointments SET is_primary=0, updated_at=CURRENT_TIMESTAMP WHERE person_id=? AND status='active' AND revoked_at IS NULL"); $u->execute([(int)$person]);
            }
            $st=$this->db->prepare("INSERT INTO organization_appointments (public_reference,organization_id,person_id,organization_position_id,appointment_type,appointment_kind,is_primary,is_acting,status,valid_from,valid_to,appointment_reference,description,created_at,updated_at) VALUES (UUID(),?,?,?,?,?,?,?,'active',?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $st->execute([(int)$position['organization_id'],(int)$person,(int)$position['id'],$kind,$kind,$isPrimary,$kind==='acting'?1:0,$validFrom,$validTo,$this->clean($data['appointment_reference']??'',150),$this->clean($data['description']??'',2000)]);
            $ref=(string)$this->db->query("SELECT public_reference FROM organization_appointments WHERE id=LAST_INSERT_ID()")->fetchColumn();
            $this->db->commit(); return $ref;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function buildChart(array $rows): array
    {
        $orgs=[];
        foreach($rows as $r){
            $or=(string)$r['organization_reference'];
            $orgs[$or] ??= ['reference'=>$or,'title'=>$r['organization_title'],'active'=>(bool)$r['organization_active'],'units'=>[],'root_positions'=>[]];
            $ur=(string)($r['unit_reference']??'');
            $pos=$r['organization_position_reference'] ? ['reference'=>$r['organization_position_reference'],'title'=>$r['position_title'],'is_head'=>(bool)$r['is_head'],'status'=>$r['position_status'],'occupant'=>$r['occupant_name'] ?: null,'appointment_kind'=>$r['appointment_kind'] ?: null,'is_primary'=>(bool)$r['is_primary']] : null;
            if($ur===''){ if($pos) $orgs[$or]['root_positions'][$pos['reference']]=$pos; continue; }
            $orgs[$or]['units'][$ur] ??= ['reference'=>$ur,'title'=>$r['unit_title'],'parent_reference'=>$r['parent_unit_reference'] ?: null,'positions'=>[],'children'=>[]];
            if($pos) $orgs[$or]['units'][$ur]['positions'][$pos['reference']]=$pos;
        }
        foreach($orgs as &$org){
            foreach(array_keys($org['units']) as $ur){ $p=$org['units'][$ur]['parent_reference']; if($p && isset($org['units'][$p])) $org['units'][$p]['children'][]=$ur; }
            $org['root_units']=array_values(array_filter(array_keys($org['units']),fn($ur)=>empty($org['units'][$ur]['parent_reference']) || !isset($org['units'][$org['units'][$ur]['parent_reference']])));
        }
        return array_values($orgs);
    }
    private function scalar(string $sql,array $p){$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchColumn();}
    private function row(string $sql,array $p):?array{$s=$this->db->prepare($sql);$s->execute($p);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
    private function dateOrNull($v):?string{return PersianDate::toGregorianDate((string)$v);}
    private function pathLabel(int $id,array $map):string{$parts=[];$guard=0;while($id>0&&isset($map[$id])&&$guard++<30){array_unshift($parts,(string)$map[$id]['title']);$id=(int)($map[$id]['parent_id']??0);}return implode(' ← ',$parts);}
    private function clean($v,int $max):?string{$v=trim((string)$v);if($v==='')return null;return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);}
}
