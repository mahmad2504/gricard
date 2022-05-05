<?php
namespace App\Apps;
use App\Email;
use \MongoDB\Client;
use \MongoDB\BSON\UTCDateTime;
use App\Libs\Jira\Jira;
use App\Libs\Jira\Fields;
use Carbon\Carbon;
class App
{
	public $key = 'Unknown';
	public $app = null;
	public $jira_fields = [];
	public $jira_customfields = [];
	public $jirafields_id = 'jirafields';
	public $timezone =  null;
	public $mongo = null;
	public $fields = null;
	public $scriptname = 'app';
	public $admin_email = 'mumtaz_ahmad@mentor.com';
	public function InitOption()
	{
		if(!isset($this->options))
			$this->options = [];
		if(!isset($this->options['rebuild']))
			$this->options['rebuild'] = 0;
		if(!isset($this->options['force']))
			$this->options['force'] = 0;
		if(!isset($this->options['email']))
			$this->options['email'] = 2;
		if(!isset($this->options['email_resend']))
			$this->options['email_resend'] = 0;
		if(!isset($this->options['dbname']))
			$this->options['dbname']=null;
		
	}
	public function InConsole($yes)
	{
		// Child should override this to see if application is running in console or from web
	}
	
	public function __construct($app)
	{		
		$this->InitOption();
		$this->app = $app;
		if(app()->runningInConsole())
		{
			$app->InConsole(true);
		}
		else
			 $app->InConsole(false);
		 
		if(!isset($this->namespace))
			$this->namespace = __NAMESPACE__;
			
		$parts = explode("\\",$app->namespace);
		$key = strtolower($parts[count($parts)-1]);
		$this->key = $key;
		if($this->options['dbname'] == null) 
			$this->dbname=$key;
		else
			$this->dbname=$this->options['dbname'];
		
		if(isset($this->timezone))
			date_default_timezone_set($this->timezone);
		
		//if(!isset($this->mongo_server))
		//	dd("App mongo_server not set");
		
		if(isset($this->mongo_server))
		{
			$mongoclient =new Client($this->mongo_server);
			$this->mongo = $mongoclient;
			$dbname = $this->dbname;
			$this->db = $mongoclient->$dbname;
		}
		if(isset($this->jira_server))
		{
			$servers = explode("|",$this->jira_server);
			if(count($servers)>1)
				$this->Save(['multiplejiraservers'=>1]);
			else
				$this->Save(['multiplejiraservers'=>0]);
		}
		if(isset($this->jira_server))
		{
			$servers = explode("|",$this->jira_server);
			if(count($servers)==1)
			{
				$multiplejiraservers = $this->Read('multiplejiraservers');
				if($multiplejiraservers==1)
					$this->jirafields_id = $this->jira_server."_".$this->jirafields_id;
	
				
				$this->fields = new Fields($this);
				Jira::Init($app);
				if(!$this->fields->Exists()||$this->options['rebuild'])
				{
					Console("Configuring Jira Fields","LightGreen");
					$this->fields = new Fields($this,0);
					$this->fields->Set($this->jira_fields);
					if($this->isAssoc($this->jira_customfields))
						$this->fields->Set($this->jira_customfields);
					$this->fields->Dump();
				}
			}
			else
			{
				foreach($servers as $server)
				{
					$this->jira_server=$server;
					
					$multiplejiraservers = $this->Read('multiplejiraservers');
					if($multiplejiraservers==1)
						$this->jirafields_id = $server."_jirafields";
					
					Jira::Init($app);
					$this->fields = new Fields($this);
					if(!$this->fields->Exists()||$this->options['rebuild'])
					{
						Console("Configuring Jira Fields for ".$server,"LightGreen");
						$this->fields = new Fields($this,0);
						if(isset($this->jira_fields[$server]))
							$this->fields->Set($this->jira_fields[$server]);
						else
							$this->fields->Set($this->jira_fields);
						
						if(isset($this->jira_customfields[$server]))
						{
							if($this->isAssoc($this->jira_customfields[$server]))
								$this->fields->Set($this->jira_customfields[$server]);
						}
						else
						{
							if($this->isAssoc($this->jira_customfields))
								$this->fields->Set($this->jira_customfields);
						}
						$this->fields->Dump();
					}
				}
				$this->Save(['multiplejiraservers'=>1]);
			}
		}
	}
	public function DeleteDirectory($dir) 
	{
		if (!file_exists($dir)) 
		{
			return true;
		}
		if (!is_dir($dir)) 
		{
			return unlink($dir);
		}
		foreach (scandir($dir) as $item) 
		{
			if ($item == '.' || $item == '..') 
			{
				continue;
			}
			if (!$this->DeleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) 
			{
				return false;
			}
		}
		return rmdir($dir);
	}
	public function EmptyDirectory($dir) 
	{
		if (!file_exists($dir)) 
		{
			return true;
		}
		if (!is_dir($dir)) 
		{
			return unlink($dir);
		}
		foreach (scandir($dir) as $item) 
		{
			if ($item == '.' || $item == '..') 
			{
				continue;
			}
			if (!$this->DeleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) 
			{
				return false;
			}
		}
	}
	private function isAssoc(array $arr)
	{
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
	function IssueParser($code,$issue,$fieldname)
	{
		dd("Implement IssueParser function");
	}
	public function JiraFields(&$fields,&$customfields)
	{
		dd("Implement JiraFields function");
	}
	public function Rebuild()
	{
		Console('Rebuild callback function not implemented',"LightRed");
	}
	public function Options()
	{
		$options = [];
		foreach($this->options as $key=>$value)
		{
			if(($key == 'help')||
			   ($key == 'version')||
			   ($key == 'version')||
			   ($key == 'quiet')
			   )
				continue;
			$options["--".$key] = $value;
		}
		return $options	;	
	}
	public function Run($params=null)
	{
		if($this->options['rebuild'])
			$this->Rebuild();
		if($this->app->TimeToRun())
		{
			Console("#########  Running script ".$this->scriptname."  #########","LightGreen");
			$this->Save([$this->scriptname.'_sync_requested'=>1]);
			$this->params=$params;
			$this->Script();
			$this->SaveUpdateTime();
			$this->Save([$this->scriptname.'_sync_requested'=>0]);
			
			$sec = $this->SecondsSinceLastUpdate();
			$error_sync = $this->Read($this->scriptname.'_error_sync');
			if($error_sync==1)
			{
				$subject = "Service Status Alert : ".strtoupper($this->scriptname)." : Up ";
				$this->NotifyAdmin('Service '.$this->scriptname." is restored",$subject);
				$this->Save([$this->scriptname.'_error_sync'=>0]);
			}
			Console("Done","ResetAll");
		}
	}
	public function Script()
	{
		dd("Implement Script function");
	}
	public function IsSyncRequested()
	{
		$val = $this->Read($this->scriptname.'_sync_requested');
		if($val == 0)
			return 0;
		return 1;
	}
	public function RequestSync()
	{
		$this->Save([$this->scriptname.'_sync_requested'=>1]);
	}
	public function ClearSyncRequest()
	{
		$this->Save([$this->scriptname.'_sync_requested'=>0]);
	}
	public function TimeToRun($update_every_xmin=1)
	{
		$sync_requested = $this->Read($this->scriptname.'_sync_requested');
		
		if($this->options['rebuild']||$this->options['force']||$sync_requested)
			return true;
		
		$now = Carbon::now($this->timezone);
		if($now->isWeekend())
			return false;
		
		
		$sec = $this->SecondsSinceLastUpdate();
		
		if($sec == null)
		{
			$this->SaveUpdateTime();
			return true;
		}
		if($sec >=  $update_every_xmin*3*60)
		{
			$timeout=$this->Read($this->scriptname.'_timeout');
			$min_since_last_update = round($sec/60);
			Console("Timeout #".$timeout."  [".$min_since_last_update."] minutes gone since last update. Update time out is ".($update_every_xmin*60)." seconds","LightYellow");
			if($min_since_last_update > 1440)
			{
				$timeout=0;
				$this->SaveUpdateTime();
				$this->Save([$this->scriptname.'_timeout'=>$timeout]);
			}
			if($timeout==null)
				$timeout=0;
			if($timeout==2)
			{
				$subject = "Service Status Alert : ".strtoupper($this->scriptname)." : Down ";
				$this->NotifyAdmin('Service '.$this->scriptname.' has some issue and not updating',$subject);
				Console("Sending Service Error Notification [".round($sec/60)."] minutes gone since last update. Update time out is ".($update_every_xmin*60)." seconds","LightYellow");
				$timeout++;
				$this->Save([$this->scriptname.'_timeout'=>$timeout]);
				$this->Save([$this->scriptname.'_error_sync'=>1]);
				return false;
			}
			else
			{
				$timeout++;
				//dump("timeout #".$timeout);
				$this->Save([$this->scriptname.'_timeout'=>$timeout]);
				return true;
			}
		}
		if($sec >=  $update_every_xmin*60)
		{
			$timeout = 0;
			$this->Save([$this->scriptname.'_timeout'=>$timeout]);
			Console("Updating after [".round($sec/60)."] minutes","LightGreen");
			return true;
		}
		Console("Its not time to update.[".$sec."] seconds gone since last update.Update time out is ".($update_every_xmin*60)." seconds");
		return false;
	}
	public function NotifyAdmin($msg,$subject,$to=null)
	{
		$email = new Email();	
		if($to == null)
			$email->Send(2,$subject,$msg);
		else
			$email->Send(1,$subject,$msg,$to);
	}
	public function SecondsSinceLastUpdate()
	{
		if($this->ReadUpdateTime()==null)
			return null;
		$ldt = new \DateTime($this->ReadUpdateTime());
		$ldt = $ldt->getTimestamp();
		$cdt = $this->CurrentDateTime();
		return $cdt - $ldt;
	}
	public function CurrentDateTime()
	{
		$now =  new \DateTime();
		$now->setTimezone(new \DateTimeZone($this->timezone));
		return $now->getTimestamp();
	}
	public function CurrentDateTimeObj()
	{
		$now =  new Carbon('now');
		$now->setTimezone(new \DateTimeZone($this->timezone));
		return $now;
	}
	public function TimestampToObj($timestamp)
	{
		$dt =  new Carbon('now');
		$dt->setTimeStamp($timestamp);
		$dt->setTimezone(new \DateTimeZone($this->timezone));
		return $dt;
	}
	public function DateStringToObj($datestring)
	{
		$ts = strtotime($datestring);
		return $this->TimestampToObj($ts);
	}
	public function SetTimeZone($datetime)
	{
		$datetime->setTimezone(new \DateTimeZone($this->timezone));
	}
	function Save($obj,$col=null)
	{
		if($col == null)
			$col = 'settings';
		
		$options=['upsert'=>true];
		if(is_array($obj))
		{
			foreach($obj as $key=>$value)
			{
				$query=['id'=>$key];
				$o = new \StdClass();
				$o->id = $key;
				$o->_value=$value;
				if(isset($this->db))
				$this->db->$col->updateOne($query,['$set'=>$o],$options);
				else
					file_put_contents($this->datafolder."/".$key,json_encode($o));
			}
		}
		else
		{
			$query=['id'=>$obj->id];
			if(isset($this->db))
			$this->db->$col->updateOne($query,['$set'=>$obj],$options);
			else
				file_put_contents($this->datafolder."/".$obj->id,json_encode($obj));
		}
	}
	function Read($id,$col=null)
	{
		if($col == null)
			$col = 'settings';
		
		$query=['id'=>$id];
		if(isset($this->db))
			$obj = $this->db->$col->findOne($query);
		else
		{
			if(!file_exists($this->datafolder."/".$id))
				return null;
			$obj = file_get_contents($this->datafolder."/".$id);
			$obj = json_decode($obj);
		}
		if($obj == null)
			return null;
		if(isset($this->db))
			$obj =  $obj->jsonSerialize();
		if(isset($obj->_value))
			return $obj->_value;
		unset($obj->_id);
		return $obj;
	}
	public function MongoRead($collection,$query,$sort=[],$projection=[],$limit=-1)
	{
		$query = $query;
		$options = ['sort' => $sort,
					'projection' => $projection,
					];
		if($limit != -1)
			$options['limit'] = $limit;
		
		$cursor = $this->db->$collection->find($query,$options);
		return $cursor;
	}
	
	public function SaveUpdateTime($id=null)
	{
		if($id==null)
			$id=$this->scriptname.'_lastupdate';
		$obj = new \StdClass();
		$obj->date =  new \DateTime();
		$obj->date = $obj->date->format('Y-m-d H:i:s');
		$obj->id = $id;
		$lastupdate = $this->Save($obj);
	}
	public function ReadUpdateTime($id=null)
	{
		if($id==null)
			$id=$this->scriptname.'_lastupdate';
		
		$ud = $this->Read($id);
		if($ud ==  null)
			return null;
		else
			return $ud->date;
		
	}
	public function Get($query)
	{
		$data = null;
		$query = str_replace(" ","%20",$query);
		$resource=$query;
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_USERPWD => 'mahmad:NDExMTk0Njk2ODAyOts/IfG8+FgNSlBMKSxk21NIYx/U',
		CURLOPT_URL =>$resource,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => array('Content-type: application/json')));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		if($data != null)
		{
			curl_setopt_array($curl, array(
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => $data
				));
		}
		$result = curl_exec($curl);
		
		$code = curl_getinfo ($curl, CURLINFO_HTTP_CODE);
		if($code == 200)
			return json_decode($result);
		return 0;
	}
	function FetchJiraTickets($jql=null)
	{
		if($jql==null)
			return Jira::FetchTickets($this->query);
		else
			return Jira::FetchTickets($jql);
	}
	function FetchSprintData($sprintid)
	{
		return Jira::GetSprint($sprintid);
	}
	function FetchSprintTasks($sprintid)
	{
		$tasks = Jira::GetSprintTasks($sprintid);
		$ret = [];
		foreach($tasks as $task)
		{
			$ret[$task->key] = $task->key;
		}
		return array_values($ret);
	}
	function JiraSearch($jql=null)
	{
		if($jql==null)
			return Jira::FetchTickets($this->query);
		else
			return Jira::FetchTickets($jql);
	}
	function FetchJiraTicketStates()
	{
		$statuses = Jira::GetAllTicketStates();
		foreach($statuses as $status)
		{
			$ret[$status->name]=['id'=>$status->id,'category'=>$status->statusCategory->name];
		}
		return $ret;
	}
	function FetchUser($user)
	{
		return Jira::GetUserDetails($user);
	}
	function FetchComments($key)
	{
		return Jira::Comments($key);
	}
	function SelectJiraServer($server)
	{
		$this->jira_server = $server;
		Jira::Init($this->app);
	}
	function GetBusinessMinutes($ini_stamp,$end_stamp,$start_hour,$end_hour)
	{
		$ini = new \DateTime();
		$ini->setTimeStamp($ini_stamp);
		$ini->setTimezone(new \DateTimeZone($this->timezone));
		
		$end = new \DateTime();
		$end->setTimeStamp($end_stamp);
		$end->setTimezone(new \DateTimeZone($this->timezone));
		
		return round(GetBusinessSeconds($ini,$end,$start_hour,$end_hour)/60);
	}
	function IsArrayAssociative($array)
	{
		if(array_keys($array) !== range(0, count($array) - 1))
			return true;
		else
			return false;
	
	}
	function CustomfielOtherdName($field)
	{
		$id=explode("customfield_",$field)[1];
		return "cf[".$id."]";
	}
}