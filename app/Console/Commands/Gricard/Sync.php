<?php
namespace App\Console\Commands\Gricard;

use Illuminate\Console\Command;
use App\Apps\Gricard\Gricard;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync {--rebuild=0} {--force=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
	
    public function __construct()
    {
		parent::__construct();
    }
	
    public function handle()//
    {
		
		$app = new Gricard($this->option());
		$app->Run();
    }
}