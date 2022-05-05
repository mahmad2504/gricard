<?php

namespace App\Libs\Jira;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Worklog;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\TimeTracking;
use App\Libs\Jira\Fields;
use App\Libs\Jira\Ticket;
use JiraRestApi\Field\Field;
use JiraRestApi\Field\FieldService;
use JiraRestApi\Issue\Version;
use JiraRestApi\Issue\Transition;
use JiraRestApi\Project\ProjectService;
use JiraRestApi\Version\VersionService;
use JiraRestApi\JiraException;
use JiraRestApi\User\UserService;
use JiraRestApi\Sprint\SprintService;
use JiraRestApi\Status\StatusService;
use JiraRestApi\Board\BoardService;
use JiraRestApi\Attachment\AttachmentService;
use Carbon\Carbon;
class Jira
{
	public static $issueService;
	public static $sprintService;
	public static $statusService;
	public static $boardService;
	public static $server;
	public static $app = null;
	public static $username ='';
	public function __construct()
	{
		
	}
	public static function Init($app)
	{
		self::$server = $app->jira_server;
		self::$issueService = new IssueService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		self::$sprintService = new SprintService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		self::$statusService = new StatusService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		
		self::$boardService = new BoardService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		
		
		self::$username = env('JIRA_'.self::$server.'_USERNAME');
		self::$app=$app;
	}

	public static function GetBoardIssues($boardid)
	{
		
		$issues = self::$boardService->getBoardIssues($boardid, [
			'maxResults' => 1,
			'jql' => urlencode('status != Closed'),
		]);
		dump($issues);
	}
	public static function AddAttachement($issueKey,$attachements)
	{
		return self::$issueService->addAttachments($issueKey,$attachements);
	}
	public static function GetAttachement($attachement_id)
	{
		$ret = self::$issueService->getAttachments($attachement_id);
	}
	public static function GetAllTicketStates()
	{
		return self::$statusService->getAll();
		
	}
	public static function GetSprint($sprintid)
	{
		try
		{
			return self::$sprintService->getSprint($sprintid);
		}
		catch (JiraException $e) 
		{
			$msg = explode('"',$e->getMessage());
			$i=0;
			foreach($msg as $m)
			{
				if($m == 'errorMessages')
				{
					dd($msg[$i+2]);
				}
				$i++;
			}
		}	
	}
	public static function GetSprintTasks($sprintid)
	{
		return self::$sprintService->getSprintIssues($sprintid,['fields'=>'key']);
	}
	public static function GetFieldService()
	{
		$fieldService = new FieldService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		return $fieldService;
	}
	public function SetFields($project_key,$summary,$desc='',$priority='NA',$type='Task',$version=null)
	{
		$issueField = new IssueField();
		$issueField->setProjectKey($project_key)
                ->setSummary("something's wrong")
                ->setPriorityName($priority)
                ->setIssueType($type)
				 ->setDescription($desc);
	
		if($version != null)
			$issueField->addVersion([$version]);
		return $issueField;
	}
	public function SetCustomeFields($fields)
	{
		foreach($fields as $key=>$value)
		{
			
		}
	}
	public static function AddLabels($issueKey,$labels)
	{
		self::UpdateLabels($issueKey,$labels,[]);
		
	}
	public static function GetUserDetails($user)
	{
		$us = new UserService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
		
		$start = 0;
		
		$paramArray = [
				'username' => $user, // get all users. 
				'startAt' => 0,
				'maxResults' => 1000,
				'includeInactive' => false,
				//'property' => '*',
		];
		$users = $us->findUsers($paramArray);
		foreach($users as $u)
			if($u->name==$user)
				return $u;
		
		
		return null;
	}
	public static function UpdateLabels($issueKey, $addLabels,$removeLabel)
	{
		$ret = self::$issueService->updateLabels($issueKey,
            $addLabels,
            $removeLabel,
        );
	}
	public static function UpdateTaskStatus($key,$status)
	{
		$transition = new Transition();
		$transition->setTransitionName($status);
		$transition->setCommentBody('Performing the transition via REST API.');
		
		self::$issueService->transition($key, $transition);
		
	}
	
	public static function UpdateTask($key,$summary=null,$desc=null,$priority=null,$type=null,$version=null,$customefields=null,$label=null)
	{
		$issueField = new IssueField(true);
		if($label != null)
			$issueField->addLabel($label);
		if($summary != null)
			$issueField->setSummary($summary);
		if($desc != null)
			$issueField->setDescription($desc);
		if($priority != null)
			$issueField->setPriorityName($priority);
		if($type != null)
			$issueField->setIssueType($type);
		if($version != null)
			$issueField->addVersion([$version]);
		if($label != null)
			$issueField->addLabel($label);
		
		if($customefields != null)
		{
			foreach($customefields as $field=>$value)
			{
				$f = self::$app->fields->$field;
				$issueField->addCustomField($f,$value);
			}
		}
		//$editParams = [
		//	'notifyUsers' => true,
		//	];
		$ret = self::$issueService->update($key, $issueField);//,$editParams);	
	}
	
	public static function CreateSubTask($project_key,$parentkey,$summary,$desc='',$priority='NA',$version=null,$customefields=null)
	{
		$type='Sub-task';
		try{
		$issueField = new IssueField();
		$issueField->setProjectKey($project_key)
                ->setSummary($summary)
                ->setPriorityName($priority)
                ->setIssueType($type)
				->setParentKeyOrId($parentkey)
				 ->setDescription($desc);
	
		if($version != null)
			$issueField->addVersion([$version]);
		
		if($customefields != null)
		{
			foreach($customefields as $field=>$value)
			{
				$f = self::$app->fields->$field;
				$issueField->addCustomField($f,$value);
			}
		}
		$ret = self::$issueService->create($issueField);
		}
		catch (JiraRestApi\JiraException $e) 
		{
			print("Error Occured! " . $e->getMessage());
		}
		return $ret;
		
	}
	public static function CreateTask($project_key,$summary,$desc='',$priority='NA',$type='Task',$version=null,$customefields=null)
	{
		try{
		$issueField = new IssueField();
		$issueField->setProjectKey($project_key)
                ->setSummary($summary)
                ->setPriorityName($priority)
                ->setIssueType($type)
				 ->setDescription($desc);
	
		if($version != null)
			$issueField->addVersion([$version]);
		
		
		if($customefields != null)
		{
			foreach($customefields as $field=>$value)
			{
				$f = self::$app->fields->$field;
				$issueField->addCustomField($f,$value);
			}
		}
		$ret = self::$issueService->create($issueField);
		}
		catch (JiraRestApi\JiraException $e) 
		{
			print("Error Occured! " . $e->getMessage());
		}
		return $ret;
		
	}
	public static function GetVersions($project_key)
	{
		try {
			$proj = new ProjectService(new ArrayConfiguration([
			 'jiraHost' => env('JIRA_'.self::$server.'_URL'),
              'jiraUser' => env('JIRA_'.self::$server.'_USERNAME'),
             'jiraPassword' => env('JIRA_'.self::$server.'_PASSWORD'),
		]));
			$vers = $proj->getVersions($project_key);
			return $vers;
		} 
		catch (JiraRestApi\JiraException $e) 
		{
			print("Error Occured! " . $e->getMessage());
		}
	}
	public static function ValidateQuery($query)
	{
		$data = null;
		$query = str_replace(" ","%20",$query);
		$resource=env('JIRA_'.self::$server.'_URL').'/rest/api/latest/search?jql='.$query.'&maxResults=0';
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_USERPWD => env('JIRA_'.self::$server.'_USERNAME').':'.env('JIRA_'.self::$server.'_PASSWORD'),
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
			return 1;
		return 0;
	}
	public static function GetStructureObjects($structid)
	{
		$data = '{"forests":[{"spec":{"type":"clipboard"},"version":{"signature":898732744,"version":0}},{"spec":{"structureId":'.$structid.',"title":true},"version":{"signature":0,"version":0}}],"items":{"version":{"signature":-157412296,"version":43401}}}';
		$resource=env('JIRA_'.self::$server.'_URL').'/rest/structure/2.0/poll';
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_USERPWD => env('JIRA_'.self::$server.'_USERNAME').':'.env('JIRA_'.self::$server.'_PASSWORD'),
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
		
		$ch_error = curl_error($curl);
		$code = curl_getinfo ($curl, CURLINFO_HTTP_CODE);
		$objects = array();
		if($code == 200)
		{
			$data = json_decode($result,true);
			if(isset($data['forestUpdates']))
			{
				if(isset($data['forestUpdates'][1]['formula']))
				{
					$formula = $data['forestUpdates'][1]['formula'];
					$formula_array = explode(",",$formula);
					
					foreach($formula_array as $formula)
					{
						$detail = explode(":",$formula);
						$obj = new \StdClass();
						$obj->rwoid = $detail[0];
						$obj->level = $detail[1];
						$obj->taskid = $detail[2];
						if(strpos($detail[2], "/")>0)
						{}
						else
						{
							$objects[$obj->taskid] = $obj;
						}
					}
				}
			}
		}
		if(count($objects)==0)
			return null;
		return $objects;
	}
	public static function GetStructureQuery($objects)
	{
		$query = 'id in (' ;
		$del = "";
		foreach($objects as $object)
		{
			$query = $query.$del.$object->taskid;
			$del = ",";
		}
		$query = $query.")";
		return $query;
	}
	public static function StructureTree($objects,$tickets)
	{
		$output = [];
		$taskatlevel[0] = new \StdClass();
		//this->extid = $this->pextid.".".$this->pos;
		
		foreach($tickets as $key=>$ticket)
		{
			$objects[$ticket->id]->ticket=$ticket;
			$ticket->children = [];
		}
		foreach($objects as $object)
		{
			$level = $object->level;
			$parent = $taskatlevel[$level-1];
		
			//$object->ticket->extid = $parent.".".count($taskatlevel[$level]);
			$parent->children[] = $object->ticket;
			$taskatlevel[$level] = $object->ticket;
			if($level == 1)
				$output[] = $object->ticket;
		}
		return $output;
		//return $taskatlevel[0];
	}
	public static function Comments($issueKey)
	{
		$comments = self::$issueService->getComments($issueKey);
		return $comments;
	}
	public static function WorkLogs($issueKey)
	{
		$worklogs = self::$issueService->getWorklog($issueKey)->getWorklogs();
		$wlgs = [];
		foreach($worklogs as $worklog)
		{
			$obj =  new \StdClass();
			$started = new Carbon($worklog->started);
			self::$app->SetTimeZone($started);
			$obj->id = $worklog->id;
			$obj->started = $started->getTimeStamp();
			$obj->seconds = $worklog->timeSpentSeconds;
			//$obj->issueId = $worklog->issueId;
			$obj->author = $worklog->author['displayName'];
			//unset($obj->author['avatarUrls']);
			$wlgs[] = $obj;
		}
		return $wlgs;
	}
	public static function FetchChildren($tickets)
	{
		$query = '';
		$count = 0;
		$del = '';
		foreach($tickets as $ticket)
		{
			if(isset($ticket->linkedtasks))
				continue;
			$missing = 0;
			$children = [];
			if(isset($ticket->outwardIssue["implemented by"]))
			{
				foreach($ticket->outwardIssue["implemented by"] as $key)
				{
					if(!isset($tickets[$key]))
					{
						$query .= $del.$key;
						$del = ",";
						$missing = 1;
						$count++;
					}
					else
						$children[] = $tickets[$key];
				}
			}
			foreach($ticket->subtasks as $key)
			{
				if(!isset($tickets[$key]))
				{
					$query .= $del.$key;
					$del = ",";
					$missing = 1;
					$count++;
				}
				else
					$children[] = $tickets[$key];
			}
			if($missing == 0)
			{
				$ticket->children= $children;
				$ticket->linkedtasks = 1;
			}
		}
		if($count > 0)
		{
			$query = "key in (".$query.")";
			$ntickets = self::FetchTickets($query);
			$t = array_merge($tickets,$ntickets);
			return self::FetchChildren(array_merge($tickets,$ntickets));
		}
		else
			return $tickets;
    }
	public static function FetchEpics($tickets)
	{
		foreach($tickets as $ticket)
		{
			if(isset($ticket->issuesinepic))
				continue;
			if($ticket->issuetypecategory == 'EPIC')
			{
				$query =  "'Epic Link'=".$ticket->key;
				$children = self::FetchTickets($query);
				$ticket->children = [];
				$ticket->issuesinepic=1;
				foreach($children as $child)
				{
					echo $child->key."\n";
					if(isset($tickets[$child->key]))
						$ticket->children[] = $tickets[$child->key];
					else
					{
						$ticket->children[] = $child;
						$tickets[$child->key] = $child;
					}
				}
				return self::FetchEpics($tickets);
			}
		}
		return $tickets;
	}
	public static function SetQueries($tasks)
	{
		$link_implemented_by = 'implemented by';
		$link_parentof = 'is parent of';
		$link_testedby= 'is tested by';
		foreach($tasks as $task)
		{
			if($task->issuetypecategory == 'EPIC')
				$task->query = "'Epic Link'=".$task->key;
			else if(($task->issuetypecategory == 'REQUIREMENT')||($task->issuetypecategory == 'WORKPACKAGE'))
			{
				$del = '';
				$task->query = 'issue in linkedIssues("'.$task->key.'","'.$link_implemented_by.'")';
				$del = ' || ';
				$task->query .= $del.'issue in linkedIssues("'.$task->key.'","'.$link_parentof.'")';
				$del = ' || ';
				$task->query .= $del.'issue in linkedIssues("'.$task->key.'","'.$link_testedby.'")' ;
			}
		}
		return $tasks;
	}

	public static function BuildTree($jql)
	{
		$tickets = self::FetchTickets($jql);
		dump(count($tickets));
		$tickets = self::FetchEpics($tickets);
		dump(count($tickets));
		$tickets = self::FetchChildren($tickets);
		dump(count($tickets));
		$tickets = self::FetchEpics($tickets);
		dump(count($tickets));
		return $tickets;
	}
	public static function UpdateTimeTrack($key,$timeoriginalestimate,$timeremainingestimate,$timespent)
	{
		//dump($key." ".$timeoriginalestimate." ".$timeremainingestimate." ".$timespent);
		try 
		{
			//$timeTracking->setOriginalEstimate('1w 1d 6h');
			$current = self::$issueService->getTimeTracking($key);
			if(
			 ($current->getOriginalEstimateSeconds() != $timeoriginalestimate)||
			 ($current->getRemainingEstimateSeconds() != $timeremainingestimate))
			{
				$timeTracking = new TimeTracking;
				$timeTracking->setOriginalEstimate(($timeoriginalestimate/60)."m");
				$timeTracking->setRemainingEstimate(($timeremainingestimate/60)."m");
				$ret = self::$issueService->timeTracking($key, $timeTracking);
				dump($key." Updating timetrack");
			}
			$wlgs = self:: WorkLogs($key);
			$wlg = null;
			for($i=0;$i<count($wlgs);$i++)
			{
				$wlg = $wlgs[$i];
				if(isset($wlg->comment))
					if($wlg->comment == "@auto")
						break;
				if($wlg->author == self::$username)
					break;
			}
			if($i==count($wlgs))
				$wlg = null;
			
			if($timespent > 0)
			{
				$workLog = new Worklog();
				$workLog->setComment('@auto')
				->setStarted("2016-05-28 12:35:54")
				->setTimeSpentSeconds($timespent);
				if($wlg == null) 
				{
					dump($key."  updating worklog");
					$ret = self::$issueService->addWorklog($key, $workLog);
				}
				else
				{
					$seconds = $wlg->seconds;
					//this  is test
					if($timespent != $seconds)
					{
						dump($key."  updating worklog");
						$ret = self::$issueService->editWorklog($key, $workLog, $wlg->id);
					}
				}
			}
		} catch (JiraRestApi\JiraException $e) 
		{
			dd($e->getMessage());
		}
	}
	public  static function  UpdateCustomField($key,$prop,$value)
	{
		//Jira::UpdateCustomField('SIEJIR-5811',$fields->violation_firstcontact,['value' => 'True']);
		
		$issueField = new IssueField(true);
		$issueService = self::$issueService;
		
		$issueField->addCustomField($prop,$value);
		//$editParams = [
		//	'notifyUsers' => true,
		//	];
		$ret = $issueService->update($key, $issueField);	
	}	
	
	public static function FetchTickets($jql,Fields $Jirafields=null)
	{
		if($Jirafields == null)
			$Jirafields=self::$app->fields;
			
		if(isset($Jirafields->transitions))
			$expand = ['changelog'];
		else
			$expand = [];//['changelog'];
		$fields = [];
		
		foreach($Jirafields as $field=>$code)
		{
			$fields[ ]= $code;			
		}
		
		$issues = [];
		$start = 0;
		$max = 100;
		//dump($fields);
		while(1)
		{
			$data = self::$issueService->search($jql,$start, $max,$fields,$expand);
			if(count($data->issues) < $max)
			{
				foreach($data->issues as $issue)
				{
					$ticket = new Ticket($issue,$Jirafields);
					$issues[$ticket->key] = $ticket ;
				}
				//echo count($issues)." Found"."\n";
				return $issues;
			}
			foreach($data->issues as $issue)
			{
				$ticket = new Ticket($issue,$Jirafields);

				$issues[$ticket->key] = $ticket ;	
			}
			$start = $start + count($data->issues);
		}
	}
}