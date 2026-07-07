<?php

namespace IPKF\Core;

class Logger
{
    protected string $file;

    public function __construct()
    {
        $this->file = BASE_PATH.'/storage/logs/app.log';
    }

    public function write(string $level,string $message): void
    {
        $line =
            '['.date('Y-m-d H:i:s').'] '.
            '['.$level.'] '.
            $message.
            PHP_EOL;

        file_put_contents(
            $this->file,
            $line,
            FILE_APPEND
        );
    }

    public function info(string $message): void
    {
        $this->write('INFO',$message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR',$message);
    }

    public function warning(string $message): void
    {
        $this->write('WARNING',$message);
    }
}