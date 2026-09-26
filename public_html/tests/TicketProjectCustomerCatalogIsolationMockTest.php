<?php

declare(strict_types=1);

namespace IPKF\Database\Connections { final class ConnectionResolver {} }
namespace {
require dirname(__DIR__) . '/app/Services/Ticketing/TicketProjectCustomerCatalogPolicy.php';
require dirname(__DIR__) . '/app/Services/Ticketing/TicketProjectOrganizationAffiliationService.php';

use App\Services\Ticketing\TicketProjectOrganizationAffiliationService;

final class FakeDb extends PDO
{
    public array $writes = [];
    public array $projectOwners = [10 => 'cust-a', 20 => 'cust-b'];
    public array $customers = ['cust-a' => true, 'cust-b' => true];
    public array $catalogs = [
        'ref-a1' => ['public_reference'=>'ref-a1','code'=>'a1','title'=>'A 1','owner'=>'cust-a'],
        'ref-a2' => ['public_reference'=>'ref-a2','code'=>'a2','title'=>'A 2','owner'=>'cust-a'],
        'ref-b1' => ['public_reference'=>'ref-b1','code'=>'b1','title'=>'B 1','owner'=>'cust-b'],
    ];
    public array $grants = ['ref-a2' => ['cust-b']];
    public array $legacy = [30 => ['ref-a1']];
    public function __construct(public string $kind) {}
    public function prepare(string $query, array $options=[]): PDOStatement|false
    {
        return new FakeStmt($this, $query);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw new RuntimeException('Unexpected unscoped query');
    }
}
final class FakeStmt extends PDOStatement
{
    private array $rows = [];
    public function __construct(private FakeDb $db, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $sql = $this->sql;
        if (str_contains($sql, 'SELECT ownership.customer_reference')) {
            $owner = $this->db->projectOwners[(int)$params[0]] ?? null;
            $this->rows = $owner === null ? [] : [['customer_reference'=>$owner]];
        } elseif (str_contains($sql, 'SELECT 1 FROM platform_customers')) {
            $this->rows = !empty($this->db->customers[$params[0]]) ? [[1]] : [];
        } elseif (str_contains($sql, 'FROM organization_catalogs catalog')) {
            $customer = $params[0];
            $requiredRefs = array_slice($params, 2);
            $this->rows = [];
            foreach ($this->db->catalogs as $ref => $row) {
                if ($row['owner'] !== $customer && !in_array($customer,$this->db->grants[$ref] ?? [],true)) continue;
                if ($requiredRefs !== [] && !in_array($ref,$requiredRefs,true)) continue;
                $result = $row; unset($result['owner']); $this->rows[] = $result;
            }
        } elseif (str_contains($sql, 'SELECT 1 FROM ticketing_project_catalog_bindings')) {
            $this->rows = !empty($this->db->legacy[(int)$params[0]]) ? [[1]] : [];
        } elseif (str_contains($sql, 'SELECT') && str_contains($sql, 'core_catalog_reference') && str_contains($sql, 'ticketing_project_catalog_bindings')) {
            $this->rows = array_map(static fn($r)=>['core_catalog_reference'=>$r],$this->db->legacy[(int)$params[0]] ?? []);
        } elseif (preg_match('/^\s*(UPDATE|INSERT|DELETE)/i',$sql)) {
            $this->db->writes[] = $sql;
        } else {
            throw new RuntimeException('Unexpected SQL: '.$sql);
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return $row === null ? false : array_values($row)[$column];
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(static fn(array $row): mixed => array_values($row)[0], $this->rows);
        }
        return $this->rows;
    }
}
function ok(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException('FAIL: '.$name);
    echo 'PASS: ', $name, "\n";
}
$core = new FakeDb('core');
$ticketing = new FakeDb('ticketing');
$sut = new TicketProjectOrganizationAffiliationService(null, $ticketing, $core);
$refs = static fn(array $rows): array => array_column($rows, 'public_reference');
ok($refs($sut->catalogOptions(10)) === ['ref-a1','ref-a2'], 'customer A sees only own catalogs');
ok($refs($sut->catalogOptions(20)) === ['ref-a2','ref-b1'], 'customer B sees explicit shared grant');
ok($sut->catalogOptions(30) === [], 'unknown owner has zero available catalogs');
ok($sut->selectionErrors(['references'=>['ref-a1','ref-a2'],'primary_reference'=>'ref-a1'],10)===[], 'multi-catalog same customer');
ok($sut->selectionErrors(['references'=>['ref-a2'],'primary_reference'=>'ref-a2'],20)===[], 'explicit grant accepted');
ok($sut->selectionErrors(['references'=>['ref-b1'],'primary_reference'=>'ref-b1'],10)!==[], 'cross-customer tampered POST rejected');
ok($sut->selectionErrors(['references'=>[],'primary_reference'=>''],30)!==[], 'unowned project cannot clear existing bindings');
try { $sut->replaceProjectBindings(10,['references'=>['ref-b1'],'primary_reference'=>'ref-b1'],'user:test'); throw new RuntimeException('write allowed'); } catch (RuntimeException $e) { ok($e->getMessage()==='project_organization_catalog_invalid','unauthorized write fails before mutations'); }
ok($ticketing->writes === [], 'zero writes in negative cases');
$context = $sut->projectAffiliationOptions(30,2);
ok($context['required']===true && $context['items']===[] && $context['catalog_references']===[], 'legacy unauthorized binding is fail-closed');
try { $sut->resolveForProject(30,2); throw new RuntimeException('unexpected unscoped access'); } catch (RuntimeException $e) { ok($e->getMessage()==='requester_affiliation_required','legacy unowned project cannot bypass affiliation'); }
$ticketing->legacy[10] = ['ref-b1'];
ok($sut->selectionErrors(['references'=>[],'primary_reference'=>''],10)!==[], 'hidden legacy catalog cannot be wiped by empty selection');
$blocked = $sut->projectAffiliationOptions(10,2);
ok($blocked['required']===true && $blocked['items']===[], 'mixed or unauthorized legacy catalog blocks affiliation');
ok($ticketing->writes === [], 'legacy review gate has no mutations');
$ticketing->legacy[20] = [];
$sut->replaceProjectBindings(20,['references'=>['ref-a2','ref-b1'],'primary_reference'=>'ref-b1'],'user:test');
ok(count($ticketing->writes)===3,'authorized multi-catalog save reaches normal update/upsert path');
$core->grants = [];
ok($refs($sut->catalogOptions(20)) === ['ref-b1'],'revoked shared grant stops future catalog visibility');
echo "A67_MOCK_TESTS=PASS\n";

}
