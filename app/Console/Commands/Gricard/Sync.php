<?php
namespace App\Console\Commands\Gricard;

use Illuminate\Console\Command;
use App\Apps\Gricard\Gricard;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync {--rebuild=0} {--force=1} {--name=report} {--query=} {--sprintstate=ACTIVE} {--backlogquery=}  {--input=}';

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
	public function ParseInput($filename)
	{
		$valid_columns=5;
		Console('Reading '.$filename,'Green');
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
		$spreadsheet = $reader->load($filename);
		$rows=$spreadsheet->getSheet(0)->toArray();
		$i=0;
		$projects=[];
		foreach($rows as $row)
		{
			if($i++==0)
				continue;
			
			dump(count($row));
			if(count($row)<$valid_columns)
			{
				Console("Row ".$i." has wrong column count",'Red');
				Console("");
				exit();
			}
			for($i=$valid_columns;$i<count($row);$i++)
				unset($row[$i]);
			$options=$this->options();
			$options['name']=$row[0]=trim($row[0]);
			$options['query']=$row[1]=trim($row[1]);
			$options['sprintstate']=$row[2]=trim($row[2]);
			$options['backlogquery']=$row[2]=trim($row[3]);
			$options['analystquery']=$row[2]=trim($row[4]);
			
			$projects[$options['name']]=$options;
			if(in_array('',$row))
			{
				Console("Row ".$i." has some columns empty",'Red');
				Console("");
				exit();
			}
			
		}
		return $projects;
	}
    public function handle()
    {	
		$input_filename=$this->option()['input'];
		if($input_filename !='')
		{
			if(!file_exists($input_filename))
			{
				Console($input_filename." file not found",'Red');
				Console("");
				exit();
			}
			$projects=$this->ParseInput($input_filename);
			foreach($projects as $options)
			{
				$app = new Gricard($options);
				$app->Run();
			}
			//dd($options);
			return;
		}
		else
		{
			$options=$this->option();
			if($options['query']=='')
			{
				Console('query parameter is missing');
				Console("");
				exit();
			}
			if($options['sprintstate']=='')
			{
				Console('sprintstate parameter is missing, allowed valued are ACTIVE/CLOSED');
				Console("");
				exit();
			}
			$app = new Gricard($this->option());
			$app->Run();
		}
    }
}