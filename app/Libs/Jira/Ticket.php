<?php

namespace App\Libs\Jira;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Libs\Jira\Fields;
use Carbon\Carbon;
class Ticket
{
	protected $app=null;
    function __construct($issue,Fields $fields) 
	{
		$this->app = $fields->GetApp();
		foreach($fields as $field=>$code)
		{
			if($field == 'issuelinks')
			{
				$this->outwardIssue = $this->GetValue('outwardIssue',$issue,'outwardIssue');
				$this->inwardIssue = $this->GetValue('inwardIssue',$issue,'inwardIssue');
			}
			else
				$this->$field = $this->GetValue($code,$issue,$field);
		}
	}
	function DateToState($state)
	{
		$retval = null;
		for($i=0;$i<count($this->transitions);$i++)
		{
			$transition = $this->transitions[$i];
			if(strcasecmp($transition->toString,$state))
			{
				$retval = $transition->created;
			}
		}
		return $retval;
	}
	public function SetTimeZone($dt)
	{
		$this->app->SetTimeZone($dt);
		
	}
	private function GetValue($prop,$issue,$fieldname)
	{
		switch($prop)
		{
			case 'attachment':
				$attch =[];
				if(isset($issue->fields->attachment))
				{
					
					foreach($issue->fields->attachment as $attachement)
					{
						//dd($attachement);
						$created= new Carbon($attachement->created);
						$this->SetTimeZone($created);
						
						$mimetype=$attachement->mimeType;
						$content=$attachement->content;
  
						$attch[]=['id'=>$attachement->id, 'filename'=>$attachement->filename,'created'=>$created,'mimetype'=>$mimetype,'content'=>$content];
					}
				}
				
				return $attch;
				break;
			case 'self':
				return $issue->self;
				break;
			case 'id':
				return $issue->id;
				break;
			case 'labels':
				if(isset($issue->fields->labels))
					return $issue->fields->labels;	
				return [];
			case 'key':
			    return $issue->key;
				break;
			case 'summary':
				return $issue->fields->summary;
				break;
			case 'description':
				if(isset($issue->fields->description))
					return $issue->fields->description;
				return '';
				break;
			case 'timeremainingestimate':
				return $issue->fields->timeTracking->remainingEstimateSeconds;
				break;
			case'timeoriginalestimate':
				return $issue->fields->timeTracking->originalEstimateSeconds;
				break;
			case 'timetracking':
				return $issue->fields->timeTracking;
			case 'timespent':
				return $issue->fields->timeTracking->timeSpentSeconds;
				break;
			case 'updated':
				if(isset($issue->fields->updated))
				{
					$updated= new Carbon($issue->fields->updated);
					$this->SetTimeZone($updated);
					return $updated->getTimestamp();
				}
				else 
				{
					return '';
				}
				break;
			case 'reporter':
				$reporter = [];
				$reporter['name'] = 'none';
				if(isset($issue->fields->reporter))
				{
					$reporter['name'] = $issue->fields->reporter->name;
					$reporter['displayName'] = $issue->fields->reporter->displayName;
					$reporter['emailAddress'] = $issue->fields->reporter->emailAddress;
				}
				return $reporter;
				break;
			case 'assignee':
				$assignee = [];
				$assignee['name'] = 'unassigned';
				$assignee['displayName'] = 'unassigned';
				$assignee['emailAddress'] = 'unassigned';
				if(isset($issue->fields->assignee))
				{
					$assignee['name'] = $issue->fields->assignee->name;
					$assignee['displayName'] = $issue->fields->assignee->displayName;
					$assignee['emailAddress'] = $issue->fields->assignee->emailAddress;
				}
				return $assignee;
				break;
			case 'affectVersions':
				$cstr = [];
				if(isset($issue->fields->affectVersions))
				{
					//dd($issue->fields->affectVersions);
					foreach($issue->fields->affectVersions as $affectVersion)
					{
						$cstr[] = $affectVersion->name;
					}
				}
				return $cstr;
				break;
			case 'fixVersions':
				$cstr = [];
				if(isset($issue->fields->fixVersions))
				{
					foreach($issue->fields->fixVersions as $version)
					{
						$cstr[] = $version->name;
					}
				}
				return $cstr;
				break;
			case 'versions':
				$cstr = [];
				if(isset($issue->fields->versions))
				{
					foreach($issue->fields->versions as $version)
					{
						$cstr[] = $version->name;
					}
				}
				return $cstr;
				break;
			case 'components':
				$cstr = [];
				if(isset($issue->fields->components))
				{
					
					foreach($issue->fields->components as $component)
					{
						$cstr[] = $component->name;
					}
				}
				return $cstr;
				break;
			case 'project':
				return $issue->fields->project->key;
				break;
			case 'created':
				if(isset($issue->fields->created))
				{
					$created= new Carbon($issue->fields->created);
					$this->SetTimeZone($created);
					return $created->getTimestamp();
				}
			break;
			case 'resolutiondate':
				if($issue->fields->status->statuscategory->id != 3) // if not resolved
					return null;
					
				if(isset($issue->fields->resolutiondate))
				{
					$resolutiondate= new Carbon($issue->fields->resolutiondate);
					$this->SetTimeZone($resolutiondate);
					return $resolutiondate->getTimestamp();
				}
				else 
					return '';
				break;
			case 'subtasks':
				$subtasks = [];
				if(isset($issue->fields->subtasks))
				{
					foreach($issue->fields->subtasks as $subtask)
					{
						$subtasks[] = $subtask->key;
					}
				}
				return $subtasks;
				break;
			case 'duedate':
				if(isset($issue->fields->duedate))
				{
					$duedate= new Carbon($issue->fields->duedate);
					$this->SetTimeZone($duedate);
					return $duedate->getTimestamp();
				}
				else 
				{
					return '';
				}
				break;
			case 'resolution':
			    if(isset($issue->fields->resolution->name))
				{
					return  $issue->fields->resolution->name;
				}
				else 
					return '';
				break;
			case 'status':
				return  $issue->fields->status->name;
				break;
			case 'subtask':
				if(!isset($issue->fields->issuetype))
					dd("ERROR::Enable issuetype fields for subtask");
				return  $issue->fields->issuetype->subtask;
				break;
			case 'issuetype':
				return  $issue->fields->issuetype->name;
				break;
			case 'statuscategory':
				if(!isset($issue->fields->status))
					dd("ERROR::Enable status fields for statuscategory");
				if($issue->fields->status->statuscategory->id == 2)
					return 'open';
				else if($issue->fields->status->statuscategory->id == 3)
					return 'resolved';
				else if($issue->fields->status->statuscategory->id == 4)
					return 'inprogress';
				else
					dd($issue->key." has unknown category");
			
				break;
			case 'priority':
			    $priority['name'] = 'unknown';
				$priority['id'] = '-1';
				if(isset($issue->fields->priority))
				{
					$priority['name'] = $issue->fields->priority->name;
					$priority['id'] = $issue->fields->priority->id;
				}
				return $priority;
				break;
			case 'transitions':
				$transitions = [];
				if(!isset($issue->changelog->histories))
					return $transitions;
				foreach($issue->changelog->histories as $history)
				{
					foreach($history->items as $item)
					{
						if($item->field == "status")
						{
							$obj =  new \StdClass();
							$created= new Carbon($history->created);
							$this->SetTimeZone($created);
							$obj->created = $created->getTimestamp();
							$obj->from = $item->fromString;
							$obj->to = $item->toString;
							$transitions[] = $obj;
						}
					}
				}
				return $transitions;
				break;
			case 'inwardIssue':
				$issuelinks = [];
				if(!isset($issue->fields->issuelinks))
					return [];
				
				foreach($issue->fields->issuelinks as $issuelink)
				{
					if(isset($issuelink->inwardIssue))
					{
						$issuelinks[$issuelink->type->inward][]=$issuelink->inwardIssue->key;
					}
				}
				return $issuelinks;
				break;
			case 'inwardIssue':
				$issuelinks = [];
				if(!isset($issue->fields->issuelinks))
					return [];
				
				foreach($issue->fields->issuelinks as $issuelink)
				{
					if(isset($issuelink->inwardIssue))
					{
						$issuelinks[$issuelink->type->inward][]=$issuelink->inwardIssue->key;
					}
				}
				return $issuelinks;
				break;
			case 'outwardIssue':
				$issuelinks = [];
				if(!isset($issue->fields->issuelinks))
					return $issuelinks;
				foreach($issue->fields->issuelinks as $issuelink)
				{
					if(isset($issuelink->outwardIssue))
					{
						$issuelinks[$issuelink->type->outward][]=$issuelink->outwardIssue->key;
					}
				}
				return $issuelinks;
				break;
			case 'story_points':
				if(isset($issue->fields->customFields[$code]))
				{
					return $issue->fields->customFields[$code];
				}
				return 0;
				break;
			case 'sprint':
				if(isset($issue->fields->customFields[$code]))
				{
					return $issue->fields->customFields[$code];
				}
				return '';
				break;
			case 'epic_link':
				if(isset($issue->fields->customFields[$code]))
					return $issue->fields->customFields[$code];
				else
					return '';
			default:
				return $this->app->IssueParser($prop,$issue,$fieldname); 
				break;
	
		}
	}
}

