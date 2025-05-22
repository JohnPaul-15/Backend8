<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ShowDBConfig extends Command
{
    protected $signature = 'show:dbconfig';
    protected $description = 'Show current database config';

    public function handle()
    {
        $this->info('DB connection: ' . config('database.default'));
        $this->info('DB host: ' . config('database.connections.mysql.host'));
        $this->info('DB database: ' . config('database.connections.mysql.database'));
    }
}
