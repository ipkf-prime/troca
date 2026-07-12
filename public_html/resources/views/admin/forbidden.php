<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

ob_start();
?>
<section class="admin-section">
    <div class="admin-empty-state">
        <h2>&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x063A;&#x06CC;&#x0631;&#x0645;&#x062C;&#x0627;&#x0632;</h2>
        <p>&#x0634;&#x0645;&#x0627; &#x0645;&#x062C;&#x0648;&#x0632; &#x0644;&#x0627;&#x0632;&#x0645; &#x0628;&#x0631;&#x0627;&#x06CC; &#x0645;&#x0634;&#x0627;&#x0647;&#x062F;&#x0647; &#x0627;&#x06CC;&#x0646; &#x0628;&#x062E;&#x0634; &#x0631;&#x0627; &#x0646;&#x062F;&#x0627;&#x0631;&#x06CC;&#x062F;.</p>
        <a class="admin-button admin-button--primary" href="/admin/dashboard">&#x0628;&#x0627;&#x0632;&#x06AF;&#x0634;&#x062A; &#x0628;&#x0647; &#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F;</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

