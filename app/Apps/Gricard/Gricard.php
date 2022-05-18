<?php
namespace App\Apps\Gricard;
use App\Apps\App;
use App\Libs\Jira\Fields;
use App\Libs\Jira\Jira;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Gricard extends App{
	public $timezone='America/New_York';
	
	public $jira_fields = ['key','status','statuscategory','summary','transitions','assignee','self','created','reporter']; 
    public $jira_customfields = ['sprint'=>'Sprint','story_points'=>'Story Points','analyst'=>'External ID'];  	
	public $jira_server = 'EPS';
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
			$this->output="output";
			$this->datafolder = 'data/gricard';
		}
		else
		{
			$this->output="../output";
			$this->datafolder = '../data/gricard';
		}
	}
	function IssueParser($code,$issue,$fieldname)
	{
		switch($fieldname)
		{
			case 'sprint':
				if(isset($issue->fields->$code))
					return $issue->fields->$code;
				return null;
				break;
			case 'story_points':
				if(isset($issue->fields->$code))
					return $issue->fields->$code;
				return 0;
			case 'analyst':
				if(isset($issue->fields->$code))
				{
					return $issue->fields->$code;
				}
				return 0;
			default:
				dd('"'.$fieldname.'" not handled in IssueParser');
		}
	}
	public function Rebuild()
	{
		//$this->db->cards->drop();
		$this->options['email']=0;// no emails when rebuild
	}
	public function IsItPartofSprint($task,$sprint)
	{
		$task->sprintid=0;
		$task->addedlater=0;
		$task->removedfromsprint=0;
		//$transitions=array_reverse($task->transitions);
		foreach($task->transitions as $transition)
		{ 
			//Just read the last sprint transaction type
			if($transition->type == 'sprint')
			{
				$task->last_to_sprintid=$transition->to;
				$task->last_from_sprintid=$transition->from;
				
				$task->before_sprint_duration=1;
				if($transition->created >= $sprint->start)
					$task->before_sprint_duration=0;
				break;
			}
		}
		if($sprint->state == 'closed')
		{
			if(isset($task->last_to_sprintid)&&($task->last_to_sprintid==$sprint->id))
			{
				if($task->statuscategory != 'resolved')
					$task->moved_to_backlog=1;
				return true;
			}
			if(isset($task->last_from_sprintid)&&($task->last_from_sprintid==$sprint->id))
			{
				$task->next_sprintid = $task->last_to_sprintid;
				return true;
			}
			return false;
		}
		if($task->last_to_sprintid==$sprint->id)
		{	
			return true;
		}
		return false;
	}
	public function GetSprintDetails($sprintid)
	{
		$sprint=Jira::GetSprint($sprintid);
		$start= new Carbon($sprint->startDate);
		$this->SetTimeZone($start);
		$sprint->start=$start->getTimestamp();
		$end= new Carbon($sprint->endDate);
		$this->SetTimeZone($end);
		$sprint->end=$end->getTimestamp();
		if($sprintid=='closed')
		{
			$end= new Carbon($sprint->completeDate);
			$this->SetTimeZone($end);
			$sprint->end=$end->getTimestamp();
		}
		return $sprint;
	}
	public function FilterStoryPointTransitions($ticket,$start,$end,$state)
	{
		//$start_clock=$ticket->created;
		$start_sp=0;
		$end_sp = null;
		foreach($ticket->transitions as $transition)
		{
			if($transition->type=='Story Points')
			{
				if(($transition->created >= $start)&&($transition->created <= $end))
				{
					$end_sp=$transition->to_sp;
				}
				else if($transition->created < $start)
					$start_sp=$transition->to_sp;
			}
		}
		if($end_sp == null)
			$end_sp=$start_sp;
		return ['start_sp'=>$start_sp,'end_sp'=>$end_sp];
		
	}
	public function FilteSprintTransitions($ticket,$start,$end,$sprint)
	{
		//dump($ticket->key);
		$output=[];
		foreach($ticket->transitions as $transition)
		{
			if($transition->type=='sprint')
			{
				//dump($transition);
				if(($transition->created >= $start)&&($transition->created <= $end))
				{
					if(($transition->to==$sprint->sequence)||($transition->from==$sprint->sequence))
						$output[]=$transition;
				}
			}
		}
		return $output;
	}
	public function FilterStateTransitions($ticket,$start,$end,$sprint_state)
	{
		//dump('start='.$start);
		//dump('sprint start='.$start.' end='.$end);
		$cur_status=strtolower($ticket->status);
		
		$clocks[$cur_status][]=$ticket->created;
		$last_status=$cur_status;
		
		$i=0;
		foreach($ticket->transitions as $transition)
		{
			if($transition->type=='status')
			{
				$from_status=strtolower($transition->from);
				$to_status=strtolower($transition->to);
				if($i++==0)
				{
					$clocks=[];
					$clocks[$from_status][]=$ticket->created;
				}
				$clocks[$from_status][]=$transition->created;
				$clocks[$to_status][]=$transition->created;
				$last_status=$to_status;
				//dump($clocks);
			}
		}
		$clocks[$last_status][]=$this->CurrentDateTime();
		$output=[];
		foreach($clocks as $status=>&$clock_array)
		{
			
			for($j=0;$j<count($clock_array);$j++)
			{
				$clock_array[$j]=$clock_array[$j] < $start?$start:$clock_array[$j];
				$clock_array[$j]=$clock_array[$j] > $end?$end:$clock_array[$j];
			}
			for($j=0;$j<count($clock_array);$j=$j+2)
			{
				$output[$status][]=$clock_array[$j+1]-$clock_array[$j];
			}
		
		}
		return $output;
	}
	public function FlushQueryInfo($sheet,$query)
	{
		$col='B';$row=3;
		$sheet->setCellValue($col.$row++, $query);
		$sheet->setCellValue($col.$row++, $this->options['backlogquery']);
		$sheet->setCellValue($col.$row++, $this->options['analystquery']);
	}
	public function FlushSprintInfo($sheet,$sprint)
	{
		$col='B';$row=7;
		$sheet->setCellValue($col.$row++, $sprint->name);
		$sheet->setCellValue($col.$row++, $sprint->state);
		$sheet->setCellValue($col.$row++, $this->TimestampToObj($sprint->startDate)->format('Y-m-d h:m'));
		$sheet->setCellValue($col.$row++, $this->TimestampToObj($sprint->endDate)->format('Y-m-d h:m'));

	}	
	public function FlushTeamInfo($sheet,$assignees)
	{
		$col='A';$row=14;
		foreach($assignees as $assignee)
		{
			$bcol=$col;
			$sheet->setCellValue($bcol++.$row, $assignee->name);
			$sheet->setCellValue($bcol++.$row, $assignee->displayName);
			$sheet->setCellValue($bcol++.$row, $assignee->emailAddress);
			$row++;
		}
	}	
	public function SetBackGroundColor($sheet,$cell_loc,$background_color)
	{
		$sheet->getStyle($cell_loc)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($background_color);
		
	}
	public function SetColor($sheet,$cell_loc,$color)
	{
		$sheet->getStyle($cell_loc)->getFont()->getColor()->setRGB($color);
	}
	public function SetBold($sheet,$cell_loc)
	{
		$sheet->getStyle($cell_loc)->getFont()->setBold(true);
	}
	public function FlushBacklog($sheet,$backlog)
	{
		$col='A';$row=3;
		$i=1;
		foreach($backlog as $ticket)
		{
			$parts=explode('rest',$ticket->self);
			$url=$parts[0]."browse/".$ticket->key;
			$hyperlink='=HYPERLINK("'.$url.'","'.$ticket->key.'")';
			
			$sheet->setCellValue($col++.$row, 'Story '.$i++);
			$sheet->setCellValue($col++.$row, $hyperlink);
			$sheet->setCellValue($col++.$row, $ticket->summary);
			$sheet->setCellValue($col++.$row, $ticket->status);
			if(isset($ticket->reporter['displayName']))
				$name=$ticket->reporter['displayName'];
			else
				$name=$ticket->reporter['name'];
			$sheet->setCellValue($col++.$row, $name);
			
			if(isset($ticket->assignee['displayName']))
				$name=$ticket->assignee['displayName'];
			else
				$name=$ticket->assignee['name'];
			
			$sheet->setCellValue($col++.$row, $name);
			if($ticket->analyst==null)
				$sheet->setCellValue($col++.$row, '');
			else
				$sheet->setCellValue($col++.$row, $ticket->analyst);
			$row++;
		}
	}
	public function FlushStoryData($sheet,$sprint,$tickets,$status_totrack)
	{
		$col='G';$row=5;
		$rcol='L';$rrow=4;
		while(1)
		{
			$status=strtolower($sheet->getCell($rcol.$rrow)->getValue());
			if($status=='')
				break;
			else
			{
				$trackable_status[$status]=$rcol++;
			}
		}
		
		foreach($status_totrack as $status)
		{
			if(!array_key_exists($status,$trackable_status))
				Console('template error. Status "'.$status.'" not present  ',"Red");
		}
		
		foreach($tickets as $ticket)
		{
			$parts=explode('rest',$ticket->self);
			$url=$parts[0]."browse/".$ticket->key;
			$bcol=$col;
			$hyperlink='=HYPERLINK("'.$url.'","'.$ticket->key.'")';
			$sheet->setCellValue($bcol++.$row, $hyperlink);
			$sheet->setCellValue($bcol++.$row, $ticket->summary);
			
			$sheet->setCellValue($bcol++.$row, $ticket->sp_transition['start_sp']);
			$sheet->setCellValue($bcol++.$row, $ticket->sp_transition['end_sp']);
			
			if($ticket->removed_from_sprint)
				$sheet->setCellValue($bcol++.$row, 'yes');
			else
				$sheet->setCellValue($bcol++.$row, '');
			
			$sheet->setCellValue($bcol.$row, $ticket->status);
			if($ticket->statuscategory =='inprogress')
				$this->SetBackGroundColor($sheet,$bcol.$row,'00FF7F');	
			else if($ticket->statuscategory =='resolved')
				$this->SetBackGroundColor($sheet,$bcol.$row,'CDCDCD');	
			
			$bcol++;
			
			foreach($ticket->status_transitions as $status=>$transitions)
			{
				$timeinstatus=0;
				foreach($transitions as $dur)
				{
					$timeinstatus += $dur;
				}
				$cur=0;
				
				if(strtolower($ticket->status)==$status)
					$cur=1;
				$status_col=$trackable_status[$status];
				
				//if($ticket->key=='HMIP-1640')
				//	dump($ticket->key."  ".$ticket->status."  ".$status."  ".$cur);
				
				//dump($timeinstatus."--->".$status_col.$row);
				$parts = explode(',',SecondsToString($timeinstatus,24));
				$parts[0]=intval($parts[0]);
				$parts[1]=intval($parts[1]);
				$parts[2]=intval($parts[2]);
				$str='';
				if($parts[0]>0)
					$str.=$parts[0].'d ';
				if($parts[1]>0)
					$str.=$parts[1].'h ';
				if($parts[2]>0)
					$str.=$parts[2].'m';
				if($str =='')
					$str='1 m';
				
				$sheet->setCellValue($status_col.$row,$str );
				if(($cur==1)&&($sprint->state =='ACTIVE'))
					$this->SetBackGroundColor($sheet,$status_col.$row,'00FF7F');
		
				else if(($cur==1)&&($sprint->state !='ACTIVE'))
				{
					$this->SetColor($sheet,$status_col.$row,'006400');
					$this->SetBold($sheet,$status_col.$row);
				}
				//else
				//	$this->SetBackGroundColor($sheet,$status_col.$row,'ffffff');
			}
			$row++;
		}
	}		
	function IdentifySprint($tickets,$sprintstate)
	{
		$sprints=[];
		foreach($tickets as $ticket)
		{
			if($ticket->sprint != null)
			{
				foreach($ticket->sprint as $sprint)
				{
					$parts=explode("state=".$sprintstate.",",$sprint);
					
					if(count($parts)>1)
					{
						
						$parts=explode(",",$parts[1]);
						$sprint=new \stdClass();
						foreach($parts as $part)
						{
							$keyvalue=explode("=",$part);
							$key=$keyvalue[0];
							$sprint->$key=$keyvalue[1];
							if(!isset($sprints[$sprint->name]))
								$sprints[$sprint->name]=$sprint;
							else
								$sprint=$sprints[$sprint->name];
							
						}
						
						$sprint->state=$sprintstate;	
						if($sprint->completeDate !="<null>")
							$sprint->endDate=$sprint->completeDate;
						unset($sprint->completeDate);
						unset($sprint->goal);
					
						$start= new Carbon($sprint->startDate);
						$this->SetTimeZone($start);
						$sprint->startDate=$start->getTimestamp();
						
						$end= new Carbon($sprint->endDate);
						$this->SetTimeZone($end);
						$sprint->endDate=$end->getTimestamp();		
						$sprint->tickets[$ticket->key]=$ticket;
					}
				}
			}
		}
		$sprints = array_values($sprints);
		if(count($sprints)==0)
			dd('No task is in active sprint');
		if(count($sprints)>1)
		{
			Console('Multiple sprints found','Yellow');
			$selected_sprint=null;
			$i=0;
			foreach($sprints as $sprint)
			{
				Console($i++."  ".$sprint->name);
				if($selected_sprint==null)
					$selected_sprint=$sprint;
				
				if($sprint->endDate > $selected_sprint->endDate)
				{
					$selected_sprint=$sprint;
				}
			}
			$sprint=$selected_sprint;
		}
		else
			$sprint=$sprints[0];
		
		foreach($tickets as $ticket)
		{
			$ticket->removed_from_sprint=0;
			$ticket->sprint_transitions=$this->FilteSprintTransitions($ticket,$sprint->startDate,$sprint->endDate,$sprint);
			if(count($ticket->sprint_transitions)>0)
			{
				if(!isset($sprint->tickets[$ticket->key]))
					$ticket->removed_from_sprint=1;
				$sprint->tickets[$ticket->key]=$ticket;
			}
		}
		return $sprint;
	}
	public function ProcessTasksInSprints($sprint)
	{
		$sprint->assignee=[];
		foreach($sprint->tickets as $ticket)
		{
			dump($ticket->key);
			$a = new \StdClass();
			$ticket->sp_transition=$this->FilterStoryPointTransitions($ticket,$sprint->startDate,$sprint->endDate,$sprint->state);
			$status_transitions=$this->FilterStateTransitions($ticket,$sprint->startDate,$sprint->endDate,$sprint->state);
			
			$ticket->status_transitions=$status_transitions;
			$sprint->ticket_states[strtolower($ticket->status)]=strtolower($ticket->status);
			foreach($ticket->assignee as $key=>$value)
			{
				$a->$key=$value;
			}
			$sprint->assignee[$a->name]=$a;
		}
		$sprint->ticket_states=array_values($sprint->ticket_states);
		$sprint->assignee=array_values($sprint->assignee);	
	}
	public function CreateOutput($sprint,$backlog)
	{
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
		$spreadsheet = $reader->load($this->datafolder.'/template.xlsx');
		$sheet =$spreadsheet->getSheet(0); //$spreadsheet->getActiveSheet();
		
		$this->FlushQueryInfo($sheet,$sprint->query);
		$this->FlushSprintInfo($sheet,$sprint);
		$this->FlushTeamInfo($sheet,$sprint->assignee);
		$this->FlushStoryData($sheet,$sprint,$sprint->tickets,$sprint->ticket_states);
		
		$sheet =$spreadsheet->getSheet(1); //$spreadsheet->getActiveSheet();
		$this->FlushBacklog($sheet,$backlog);
		
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xlsx");
		$writer->save($this->output."/".$this->options['name'].'.xlsx');
		dump($this->output."/".$this->options['name'].'.xlsx');
	}
	public function IdentifyCurrentSprint($ticket)
	{
		$transitions=[];
		foreach($ticket->transitions as $transition)
		{
			if($transition->type=='sprint')
			{
				$transitions[]=$transition;
			}
		}
		$last_transition=$transitions[count($transitions)-1];
		return $last_transition->to;
	}
	public function BacklogItems()
	{
		$backlog_items=[];
		$query=$this->options['backlogquery'];
		$tickets=$this->FetchJiraTickets($query);
		foreach($tickets as $ticket)
		{
			if($this->IdentifyCurrentSprint($ticket)=='')
			{
				$backlog_items[]=$ticket;
			}
		}
		return $backlog_items;
	}
	public function AnalystSummary()
	{
		$query=$this->options['analystquery'];
		$tickets=$this->FetchJiraTickets($query);
		foreach($tickets as $ticket)
		{
			dump($ticket->analyst);
		}
	}
	public function Script()
	{
		
		if(($this->options["sprintstate"]=='ACTIVE')||($this->options["sprintstate"]=='CLOSED'))
			$sprintstate=$this->options["sprintstate"];
		else
		{
			Console('Valid sprintstates are ACTIVE and CLOSED','Red');
			return;
		}
		$query=$this->options["query"];
		$tickets=$this->FetchJiraTickets($query);
		$sprint=$this->IdentifySprint($tickets,$sprintstate);
		// to econfirm
		dump($sprint->sequence);
		foreach($sprint->tickets as $ticket)
		{
			//dump(
			//removed_from_sprint
			$sprint_id = $this->IdentifyCurrentSprint($ticket);
			if($ticket->removed_from_sprint==0)
				if($sprint_id != $sprint->sequence)
					dd('Something wrong in sprint identification');
		}
		$sprint->query=$query;
		Console("Sprint selected = ".$sprint->name,"Green");
		$this->ProcessTasksInSprints($sprint);
		$backlogitems=$this->BacklogItems();
		$analystsummary=$this->AnalystSummary();
		$this->CreateOutput($sprint,$backlogitems);

		return;
	}
	public function Others()
	{
		/////////////////////////////////////////////////////////////////
		
		$sprintid=$this->options['sprintid'];
		$sprint=$this->GetSprintDetails($sprintid);
		dump($sprint);
		
		$query='Sprint='.$sprintid." ORDER BY key ASC";
		//$query='key in ("INDLIN-3699")';
		$tasks=$this->FetchJiraTickets($query);
		$sprint_tasks=[];
		dump(count($tasks));
		
		dump(count(Jira::GetSprintTasks($sprintid)));
		$total_story_points=0;
		foreach($tasks as $task)
		{
			if($this->IsItPartofSprint($task,$sprint))
			{
				//$shis->IsItAddedAfterSprintStart($task,$sprint);
				$sprint_tasks[]=$task;
				//$total_story_points += $task->story_points;
				//if($task->addedlater)
				//	dump($task->key."   Added later");
			}
			//if($task->removedfromsprint)
			//	dump($task->key."   Removed ");
			//$name = $task->assignee['displayName'];
			//if(!isset($assigne[$name]))
			//	$assigne[$name]=0;
			//$assigne[$name]++;
			//if($task->key == 'INDLIN-3757')
			//	dd($task->transitions);
			
			
		}
		foreach($sprint_tasks as $task)
		{
			if(isset($task->next_sprintid))
				dump($task->before_sprint_duration."  ".$task->key."  ".$task->status."  ".$task->statuscategory."  ".$task->next_sprintid);
			if(isset($task->moved_to_backlog))
				dump($task->before_sprint_duration."  ".$task->key."  ".$task->status."  ".$task->statuscategory."  to backlog");
			else
				dump($task->before_sprint_duration."  ".$task->key."  ".$task->status."  ".$task->statuscategory);
		}
		dd(count($sprint_tasks));
		//dump($assigne);
		
		//sdd($tasks);
		//dump('Total story points = '.$total_story_points);
		dd('ff');
		$sprints = Jira::GetSprints($this->params,'active');
		if(count($sprints)==0)
			dd('No active sprint found');
		
		$sprint=$sprints[0];
		dd($sprint);
		$tasks=Jira::GetSprintTasks($sprint->id);
		dd($tasks);
		return;
		
	/*	$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
		$spreadsheet = $reader->load('hello world.xlsx');
		
		$sheet =$spreadsheet->getSheet(0); //$spreadsheet->getActiveSheet();
    $last_row = (int) $sheet->getHighestRow();
    $new_row = $last_row+1;
 
    $sheet->setCellValue('A'.$new_row, "14");
    $sheet->setCellValue('B'.$new_row, "Alina");
    $sheet->setCellValue('C'.$new_row, "PG");
    $sheet->setCellValue('D'.$new_row, "$32");
    $sheet->setCellValue('E'.$new_row, "Pending");
 
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xlsx");
    $writer->save('Hostel_records-1.xlsx');
		
		$query='key in ("INDLIN-3000")';
		$tickets =  $this->FetchJiraTickets($query);
		dump($tickets);*/
		
$spreadsheet = new Spreadsheet();
$worksheet = $spreadsheet->getActiveSheet();
$worksheet->fromArray(
    [
        ['', 2010, 2011, 2012],
        ['Q1', 12, 15, 21],
        ['Q2', 56, 73, 86],
        ['Q3', 52, 61, 69],
        ['Q4', 30, 32, 0],
    ]
);

// Set the Labels for each data series we want to plot
//     Datatype
//     Cell reference for data
//     Format Code
//     Number of datapoints in series
//     Data values
//     Data Marker
$dataSeriesLabels = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$1', null, 1), // 2010
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$C$1', null, 1), // 2011
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$D$1', null, 1), // 2012
];
// Set the X-Axis Labels
//     Datatype
//     Cell reference for data
//     Format Code
//     Number of datapoints in series
//     Data values
//     Data Marker
$xAxisTickValues = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$5', null, 4), // Q1 to Q4
];
// Set the Data values for each data series we want to plot
//     Datatype
//     Cell reference for data
//     Format Code
//     Number of datapoints in series
//     Data values
//     Data Marker
$dataSeriesValues = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$5', null, 4),
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$5', null, 4),
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$D$2:$D$5', null, 4),
];
$dataSeriesValues[2]->setLineWidth(60000);

// Build the dataseries
$series = new DataSeries(
    DataSeries::TYPE_LINECHART, // plotType
    DataSeries::GROUPING_STACKED, // plotGrouping
    range(0, count($dataSeriesValues) - 1), // plotOrder
    $dataSeriesLabels, // plotLabel
    $xAxisTickValues, // plotCategory
    $dataSeriesValues        // plotValues
);

// Set the series in the plot area
$plotArea = new PlotArea(null, [$series]);
// Set the chart legend
$legend = new ChartLegend(ChartLegend::POSITION_TOPRIGHT, null, false);

$title = new Title('Test Stacked Line Chart');
$yAxisLabel = new Title('Value ($k)');

// Create the chart
$chart = new Chart(
    'chart1', // name
    $title, // title
    $legend, // legend
    $plotArea, // plotArea
    true, // plotVisibleOnly
    DataSeries::EMPTY_AS_GAP, // displayBlanksAs
    null, // xAxisLabel
    $yAxisLabel  // yAxisLabel
);

// Set the position where the chart should appear in the worksheet
$chart->setTopLeftPosition('A7');
$chart->setBottomRightPosition('H20');

// Add the chart to the worksheet
$worksheet->addChart($chart);


$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->setIncludeCharts(true);
$callStartTime = microtime(true);
$filename='Hostel_records-1.xlsx';
$writer->save($filename);



	
	}
}