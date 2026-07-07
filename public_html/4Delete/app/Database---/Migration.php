<?php

namespace IPKF\Database;

abstract class Migration
{
    abstract public function up(Schema $schema);

    abstract public function down(Schema $schema);
}