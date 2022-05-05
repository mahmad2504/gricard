<?php
use Carbon\Carbon;
use Aws\S3\S3Client;

function Authenticate($url,$username,$password)
{
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERPWD, $username.":".$password);
	curl_setopt($ch, CURLOPT_URL, $url);
	//curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	//curl_setopt ( $ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST );
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}
function Post($url,$data)
{
	$ch=curl_init($url);
	//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch,CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['cmd'=>'cvedata','data'=>$data]));
	//curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	//curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	//curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER,
    array(
        'Content-Type:application/json',
        //'Content-Length: '. strlen($json_string)
    ));
	$result = curl_exec($ch);
	$item = json_decode($result);
	curl_close($ch);
	return $result;
}

function getStartAndEndDate($year, $week) 
{
  $dto = new DateTime();
  $dto->setISODate($year, $week);
  $ret['week_start'] = $dto->format('Y-m-d');
  $dto->modify('+6 days');
  $ret['week_end'] = $dto->format('Y-m-d');
  return $ret;
}
function S3DeleteFolder($folder)
{
	$s3Client = new S3Client([
		//'profile' => 'default',
		'region' => 'us-west-2',
		'version'     => 'latest',
		'credentials' => [
			'key'    => env('AWS_KEY'),
			'secret' => env('AWS_SECRET'),
		]
	]);
	$s3Client->deleteMatchingObjects(env('AWS_URL'), $folder);
}
function S3DeleteFile($file)// dest  is 'cveportal/data/filename',
{
	$s3Client = new S3Client([
		//'profile' => 'default',
		'region' => 'us-west-2',
		'version'     => 'latest',
		'credentials' => [
			'key'    => env('AWS_KEY'),
			'secret' => env('AWS_SECRET'),
		]
	]);
	$result = $s3Client->deleteObject([
	'Bucket' => env('AWS_URL'),
	'Key'    => $file,
	]);
}
function S3UploadData($data,$dest)// dest  is 'cveportal/data/filename',
{
	$s3Client = new S3Client([
		//'profile' => 'default',
		'region' => 'us-west-2',
		'version'     => 'latest',
		'credentials' => [
			'key'    => env('AWS_KEY'),
			'secret' => env('AWS_SECRET'),
		]
	]);
	$result = $s3Client->putObject([
	'Bucket' => env('AWS_URL'),
	'Key'    => $dest,
	'Body'   => $data,
	'ACL'    => 'public-read'
	]);
}
function Console($msg,$color="Default")
{
	$colorcodes=[
		"ResetAll" => ["\033[0m","color:white;"],
		"Bold"       => ["\033[1m","color:black;font-weight:Bold"],
		"Dim"        => ["\033[2m"],
		"Underlined" => ["\033[4m"],
		"Blink"      => ["\033[5m"],
		"Reverse"    => ["\033[7m"],
		"Hidden"     => ["\033[8m"],

		"ResetBold"       => ["\033[21m"],
		"ResetDim"        => ["\033[22m"],
		"ResetUnderlined" => ["\033[24m"],
		"ResetBlink"      => ["\033[25m"],
		"ResetReverse"    => ["\033[27m"],
		"ResetHidden"     => ["\033[28m"],

		"Default"      => ["\033[39m","color:black;"],
		"Black"        => ["\033[30m"],
		"Red"          => ["\033[31m"],
		"Green"        => ["\033[32m"],
		"Yellow"       => ["\033[33m","color:yellow;"],
		"Blue"         => ["\033[34m"],
		"Magenta"      => ["\033[35m"],
		"Cyan"         => ["\033[36m","color:cyan;"],
		"LightGray"    => ["\033[37m"],
		"DarkGray"     => ["\033[90m"],
		"LightRed"     => ["\033[91m","color:red;"],
		"LightGreen"   => ["\033[92m","color:green;"],
		"LightYellow"  => ["\033[93m","color:yellow;"],
		"LightBlue"    => ["\033[94m"],
		"LightMagenta" => ["\033[95m"],
		"LightCyan"    => ["\033[96m"],
		"White"        => ["\033[97m"],

		"BackgroundDefault"      => ["\033[49m"],
		"BackgroundBlack"        => ["\033[40m"],
		"BackgroundRed"          => ["\033[41m"],
		"BackgroundGreen"        => ["\033[42m"],
		"BackgroundYellow"       => ["\033[43m"],
		"BackgroundBlue"         => ["\033[44m"],
		"BackgroundMagenta"      => ["\033[45m"],
		"BackgroundCyan"         => ["\033[46m"],
		"BackgroundLightGray"    => ["\033[47m"],
		"BackgroundDarkGray"     => ["\033[100m"],
		"BackgroundLightRed"     => ["\033[101m"],
		"BackgroundLightGreen"   => ["\033[102m"],
		"BackgroundLightYellow"  => ["\033[103m"],
		"BackgroundLightBlue"    => ["\033[104m"],
		"BackgroundLightMagenta" => ["\033[105m"],
		"BackgroundLightCyan"    => ["\033[106m"],
		"BackgroundWhite"        => ["\033[107m"]
	];
	
	if(App::runningInConsole())
	{
		echo $colorcodes[$color][0].$msg."\n";
	}
	else
	{
		$css = $colorcodes[$color][1];
    	$msg = str_replace('"', "'", $msg);	
		echo "<span style='".$css."'>".$msg."</span></br>";
		//echo "data: {\n";
		//echo "data: \"msg\": \"$msg\", \n";
		//echo "data: }\n";
		//echo PHP_EOL;
		ob_flush();
		flush();
	}
}

function CDateTimeOld($stamp,$timezone=null)
{
	$dt = Carbon::createFromTimestamp($stamp);
	if($timezone != null)
		$dt->setTimezone(new \DateTimeZone($timezone));
	return $dt;
}
function CDateTime($datestring='now',$timezone=null)
{
	if($datestring=='now')
	{
		$now =  Carbon::now();
		if($timezone != null)
			$now->setTimezone(new \DateTimeZone($timezone));
		return $now;
	}
	if(is_numeric($datestring))
		$ts = $datestring;
	else
		$ts = strtotime($datestring);
	
	$dt = Carbon::createFromTimestamp($ts);
	if($timezone != null)
		$dt->setTimezone(new \DateTimeZone($timezone));
	return $dt;
}

function CTimestamp($datestring='now',$timezone=null)
{
	return CDateTime($datestring,$timezone)->getTimestamp();
}
function SecondsToString($ss,$hours_day) 
{
	$s = $ss%60;
	$m = floor(($ss%3600)/60);
	$h = floor(($ss)/3600);
	
	$d = floor($h/$hours_day);
	$h = $h%$hours_day;
	//return "$d days, $h hours, $m minutes, $s seconds";
	return "$d day,$h hour,$m min";
}
/**
 * Check if the given DateTime object is a business day.
 *
 * @param DateTime $date
 * @return bool
 */
function isBusinessDay(\DateTime $date,$holidays=null)
{
	if ($date->format('N') > 5) {
		return false;
	}

	//Hard coded public Holidays
	if($holidays == null)
		$holidays = [
			"New Years Day"         => new \DateTime(date('Y') . '-01-01'),
			"Memorial Day"          => new \DateTime(date('Y') . '-05-25'),
			"Independence Day"      => new \DateTime(date('Y') . '-07-03'),
			"Labor Day"             => new \DateTime(date('Y') . '-09-07'),
			"Thanksgiving Day"      => new \DateTime(date('Y') . '-11-26'),
			"Thanksgiving Day2"     => new \DateTime(date('Y') . '-11-27'),
			"Floating Holiday1"     => new \DateTime(date('Y') . '-12-24'),
			"Christmas Day"         => new \DateTime(date('Y') . '-12-25'),
			"Floating Holiday2"     => new \DateTime(date('Y') . '-12-31'),
		];
	foreach ($holidays as $holiday) {
		if ($holiday->format('Y-m-d') === $date->format('Y-m-d')) {
			return false;
		}
	}

	//December company holidays
	//if (new \DateTime(date('Y') . '-12-15') <= $date && $date <= new \DateTime((date('Y') + 1) . '-01-08')) {
	//	return false;
	//}

	// Other checks can go here

	return true;
}
/**
 * Get the available business time between two dates (in seconds).
 *
 * @param $start
 * @param $end
 * @return mixed
 */
function GetBusinessSeconds($start, $end,$starthour=8,$endhour=20,$holidays=null)
{
	$start = $start instanceof \DateTime ? $start : new \DateTime($start);
	$end = $end instanceof \DateTime ? $end : new \DateTime($end);
	$dates = [];

	$date = clone $start;

	while ($date <= $end) {

		$datesEnd = (clone $date)->setTime(23, 59, 59);

		if (isBusinessDay($date,$holidays)) {
			$dates[] = (object)[
				'start' => clone $date,
				'end'   => clone ($end < $datesEnd ? $end : $datesEnd),
				'starthour' => $starthour,
				'endhour' => $endhour,
			];
		}

		$date->modify('+1 day')->setTime(0, 0, 0);
	}

	return array_reduce($dates, function ($carry, $item) {

		$businessStart = (clone $item->start)->setTime($item->starthour, 000, 0);
		$businessEnd = (clone $item->start)->setTime($item->endhour, 00, 0);

		$start = $item->start < $businessStart ? $businessStart : $item->start;
		$end = $item->end > $businessEnd ? $businessEnd : $item->end;

		//Diff in seconds
		return $carry += max(0, $end->getTimestamp() - $start->getTimestamp());
	}, 0);
}
