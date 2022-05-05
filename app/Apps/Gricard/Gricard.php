<?php
namespace App\Apps\Gricard;
use App\Apps\App;
use App\Libs\Jira\Fields;
use App\Libs\Jira\Jira;
use Carbon\Carbon;


class Gricard extends App{
	public $timezone='Asia/Karachi';
	
	public $jira_fields = ['key','status','statuscategory','summary']; 
    //public $jira_customfields = ['sprint'=>'Sprint'];  	
	public $jira_server = 'ATLASSIAN';
	public $scriptname = 'gricard';
	public $options = 0;
	public function __construct($options=null)
    {
		$this->namespace = __NAMESPACE__;
		$this->options = $options;
		parent::__construct($this);

    }
	public function TimeToRun($update_every_xmin=10)
	{
		return parent::TimeToRun($update_every_xmin);
	}
	public function InConsole($yes)
	{
		if($yes)
		{
			$this->base="/";
			$this->datafolder = 'data/gricard';
		}
		else
		{
			$this->base="/../";
			$this->datafolder = '../data/gricard';
		}
	}
	function IssueParser($code,$issue,$fieldname)
	{
		switch($fieldname)
		{
			default:
				dd('"'.$fieldname.'" not handled in IssueParser');
		}
	}
	public function Rebuild()
	{
		//$this->db->cards->drop();
		$this->options['email']=0;// no emails when rebuild
	}
	public function Script()
	{
		dump("Running script");
		$query='key in ("INDLIN-3000")';
		$tickets =  $this->FetchJiraTickets($query);
		dump($tickets);
	}
}