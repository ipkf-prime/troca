#!/usr/bin/env bash
# SAFIR standalone source/identity verification. No provider/network/database changes.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
cd "$ROOT"
printf 'SAFIR_VALIDATION_MODE=NO_SEND_NO_DATABASE\n'
for item in \
  public_html/app/Services/BaleSafirFinalMessageIdentity.php \
  public_html/app/Services/BaleSafirMessageIdempotencyRepository.php \
  public_html/app/Services/BaleSafirGuardedDispatch.php \
  public_html/app/Services/BaleSafirDurableCampaignWorker.php \
  public_html/app/Services/BaleSafirDurableProviderTransport.php \
  public_html/app/Services/BaleSafirCampaignPreSendAudit.php \
  public_html/app/Services/BaleSafirCampaignService.php \
  tools/safir/SAFIR_FinalMessageIdentity_StandaloneTests.php
do
  test -f "$item"
  php -l "$item" >/dev/null
  printf 'SYNTAX_PASS=%s\n' "$item"
done
php -r 'if (!extension_loaded("mbstring")) { fwrite(STDERR,"MISSING_MBSTRING\n"); exit(2); }'
php tools/safir/SAFIR_FinalMessageIdentity_StandaloneTests.php
printf 'SAFIR_SOURCE_TESTS=PASS\n'
printf 'WORKER_LIVE_DEPLOYED=NO\n'
printf 'PROVIDER_SEND=NO\n'
