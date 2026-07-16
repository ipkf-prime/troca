<?php

namespace IPKF\Database\Migrations;

use PDO;

class CreateStandaloneAutomationCorrespondenceFoundationTables extends CreateAutomationCorrespondenceFoundationTables
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db, false);
    }
}
