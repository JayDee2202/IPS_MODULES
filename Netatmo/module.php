<?

//require_once(__DIR__ . "/netatmo.php");  // Netatmo Helper Klasse
require_once(__DIR__ . "/netatmo_api/Clients/NAWSApiClient.php");
require_once(__DIR__ . "/netatmo_api/Constants/AppliCommonPublic.php");


    // Klassendefinition

    
    class Netatmo extends IPSModule {
    	
   
    	
    private $client ;
    private $tokens ;     	
    private $refresh_token ;
    private $access_token ;
    private $deviceList;
        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);
  
            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
        	parent::Create();
	$this->RegisterPropertyString("username", "");
	$this->RegisterPropertyString("password", "");
	$this->RegisterPropertyString("client_id", "");
	$this->RegisterPropertyString("client_secret", "");
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();
      IPS_LogMessage(__CLASS__, __FUNCTION__); //                   
       IPS_LogMessage('Config', print_r(json_decode(IPS_GetConfiguration($this->InstanceID)), 1));
   	$this->PrepareConnection();
        }
 
	private function PrepareConnection() 
	{
 	global $client;
    	global $tokens ;     	
    	global $refresh_token ;
    	global $access_token ;
    	
	$config = array();
	$config['client_id'] = $this->ReadPropertyString("client_id");
	$config['client_secret'] = $this->ReadPropertyString("client_secret");
	//application will have access to station and theromstat
	$config['scope'] = "read_station";
	$client = new NAWSApiClient($config);
    		
    	$username = $this->ReadPropertyString("username");
	$pwd = $this->ReadPropertyString("password");
	$client->setVariable("username", $username);
	$client->setVariable("password", $pwd);

	//Authentication with Netatmo server (OAuth2)
try
{
    $tokens = $client->getAccessToken();
}
catch(NAClientException $ex)
{
    $this->handleError("An error happened while trying to retrieve your tokens: " .$ex->getMessage()."\n", TRUE);
	     	 IPS_LogMessage(__CLASS__, "ALL OK !!!!");
		$this->SetStatus(102);// login OK
     		}
	
    
    }
 
	
	public function GetData() {
	
	global $client;
    	global $tokens ;     	
    	global $refresh_token ;
    	global $access_token ;
	global $deviceList;
    	
	$this->PrepareConnection();	
	
//	$deviceList = $client->api("devicelist");	
//	 IPS_LogMessage(__CLASS__, "Devicelist: ". print_r($deviceList ,1));	
//	 echo print_r($deviceList);
		
		
	//Retrieve user's Weather Stations Information
try
{
    //retrieve all stations belonging to the user, and also his favorite ones
    $data = $client->getData(NULL, TRUE);
    $this->printMessageWithBorder("Weather Stations Basic Information");
}
catch(NAClientException $ex)
{
    $this->handleError("An error occured while retrieving data: ". $ex->getMessage()."\n", TRUE);
}
if(empty($data['devices']))
{
    echo 'No devices affiliated to user';
}
else
{
    $users = array();
    $friends = array();
    $fav = array();
    $device = $data['devices'][0];
    $tz = isset($device['place']['timezone']) ? $device['place']['timezone'] : "GMT";
    //devices are already sorted in the following way: first weather stations owned by user, then "friend" WS, and finally favorites stations. Still let's store them in different arrays according to their type
    foreach($data['devices'] as $device)
    {
        //favorites have both "favorite" and "read_only" flag set to true, whereas friends only have read_only
        if(isset($device['favorite']) && $device['favorite'])
            $fav[] = $device;
        else if(isset($device['read_only']) && $device['read_only'])
            $friends[] = $device;
        else $users[] = $device;
    }
    //print first User's device Then friends, then favorite
    $this->printDevices($users, "User's weather stations");
    $this->printDevices($friends, "User's friends weather stations");
    $this->printDevices($fav, "User's favorite weather stations");
    // now get some daily measurements for the last 30 days
     $type = "temperature,Co2,humidity,noise,pressure";
    //first for the main device
    try
    {
        $measure = $client->getMeasure($device['_id'], NULL, "1day" , $type, time() - 24*3600*30, time(), 30,  FALSE, FALSE);
        $this->printMeasure($measure, $type, $tz, $device['_id'] ."'s daily measurements of the last 30 days");
    }
    catch(NAClientException $ex)
    {
        $this->handleError("An error occured while retrieving main device's daily measurements: " . $ex->getMessage() . "\n");
    }
    //Then for its modules
    foreach($device['modules'] as $module)
    {
        //requested data type depends on the module's type
        switch($module['type'])
        {
            case "NAModule3": $type = "sum_rain";
                              break;
            case "NAModule2": $type = "WindStrength,WindAngle,GustStrength,GustAngle,date_max_gust";
                              break;
            case "NAModule1" : $type = "temperature,humidity";
                               break;
            default : $type = "temperature,Co2,humidity,noise,pressure";
        }
        try
        {
            $measure = $client->getMeasure($device['_id'], $module['_id'], "1day" , $type, time()-24*3600*30 , time(), 30,  FALSE, FALSE);
            $this->printMeasure($measure, $type, $tz, $module['_id']. "'s daily measurements of the last 30 days ");
        }
        catch(NAClientException $ex)
        {
            $this->handleError("An error occured while retrieving main device's daily measurements: " . $ex->getMessage() . "\n");
        }
    }
	
		
	}
	}
	
/**
 * Prints a list of devices
 *
 */
function printDevices($devices, $title = NULL)
{
    if(!is_null($devices) && is_array($devices) && !empty($devices))
    {
        if(!is_null($title))
            printMessageWithBorder($title);
        foreach($devices as $device)
        {
            printWSBasicInfo($device);
        }
    }
}



// UTILS.php

function handleError($message, $exit = FALSE)
{
    echo $message;
    if($exit)
        exit(-1);
}
function printTimeInTz($time, $timezone, $format)
{
    try{
        $tz = new DateTimeZone($timezone);
    }
    catch(Exception $ex)
    {
        $tz = new DateTimeZone("GMT");
    }
    $date = new DateTime();
    $date->setTimezone($tz);
    $date->setTimestamp($time);
    echo $date->format($format);
}
function printBorder($message)
{
    $size = strlen($message);
    for($i = 0; $i < $size; $i++)
        echo("-");
    echo("\n");
}
function printMessageWithBorder($message)
{
    $message = "- " . $message . " -";
    $this->printBorder($message);
    echo $message . "\n";
    $this->printBorder($message);
}
function printMeasure($measurements, $type, $tz, $title = NULL, $monthly = FALSE)
{
    if(!empty($measurements))
    {
        if(!empty($title))
            $this->printMessageWithBorder($title);
        if($monthly)
            $dateFormat = 'F: ';
        else $dateFormat = 'j F: ';
        //array of requested info type, needed to map result values to what they mean
        $keys = explode(",", $type);
        foreach($measurements as $timestamp => $values)
        {
            $this->printTimeinTz($timestamp, $tz, $dateFormat);
             echo"\n";
            foreach($values as $key => $val)
            {
                echo $keys[$key] . ": ";
                if($keys[$key] === "time_utc" || preg_match("/^date_.*/", $keys[$key]))
                    echo $this->printTimeInTz($val, $tz, "j F H:i");
                else{
                    echo $val;
                    $this->printUnit($keys[$key]);
                }
                if(count($values)-1 === $key || $monthly)
                    echo "\n";
                else echo ", ";
            }
        }
    }
}
/**
 * function printing a weather station or modules basic information such as id, name, dashboard data, modules (if main device), type(if module)
 *
 */
function printWSBasicInfo($device)
{
    if(isset($device['station_name']))
        echo ("- ".$device['station_name']. " -\n");
    else if($device['module_name'])
        echo ("- ".$device['module_name']. " -\n");
    echo ("id: " . $device['_id']. "\n");
    if(isset($device['type']))
    {
        echo ("type: ");
        switch($device['type'])
        {
            // Outdoor Module
            case "NAModule1": echo ("Outdoor\n");
                              break;
            //Wind Sensor
            case "NAModule2": echo("Wind Sensor\n");
                              break;
            //Rain Gauge
            case "NAModule3": echo("Rain Gauge\n");
                              break;
            //Indoor Module
            case "NAModule4": echo("Indoor\n");
                              break;
            case "NAMain" : echo ("Main device \n");
                            break;
        }
    }
    if(isset($device['place']['timezone']))
        $tz = $device['place']['timezone'];
    else $tz = 'GMT';
    if(isset($device['dashboard_data']))
    {
        echo ("Last data: \n");
        foreach($device['dashboard_data'] as $key => $val)
        {
            if($key === 'time_utc' || preg_match("/^date_.*/", $key))
            {
                echo $key .": ";
                $this->printTimeInTz($val, $tz, 'j F H:i');
                echo ("\n");
            }
            else if(is_array($val))
            {
                //do nothing : don't print historic
            }
            else {
                echo ($key .": " . $val);
                $this->printUnit($key);
                echo "\n";
            }
        }
        if(isset($device['modules']))
        {
            echo (" \n\nModules: \n");
            foreach($device['modules'] as $module)
                $this->printWSBasicInfo($module);
        }
    }
    echo"       ----------------------   \n";
}
function printUnit($key)
{
    $typeUnit = array('temp' => '°C', 'hum' => '%', 'noise' => 'db', 'strength' => 'km/h', 'angle' => '°', 'rain' => 'mm', 'pressure' => 'mbar', 'co2' => 'ppm');
    foreach($typeUnit as $type => $unit)
    {
        if(preg_match("/.*$type.*/i", $key))
        {
            echo " ".$unit;
            return;
        }
    }
}
/** THERM Utils function **/
/*
* @brief print a thermostat basic information in CLI
*/
function printThermBasicInfo($dev)
{
    //Device
    echo (" -".$dev['station_name']."- \n");
    echo (" id: ".$dev['_id']." \n");
    echo ("Modules : \n");
    // Device's modules info
    foreach($dev['modules'] as $module)
    {
        echo ("    - ".$module['module_name']." -\n");
        //module last measurements
        echo ("    Last Measure date : ");
        $this->printTimeInTz($module['measured']['time'], $dev['place']['timezone'], 'j F H:i');
        echo("\n");
        echo ("    Last Temperature measured: ". $module['measured']['temperature']);
        $this->printUnit("temperature");
        echo("\n");
        echo ("    Last Temperature setpoint: ". $module['measured']['setpoint_temp']);
        $this->printUnit('setpoint_temp');
        echo("\n");
        echo ("    Program List: \n");
        //program list
        foreach($module['therm_program_list'] as $program)
        {
            if(isset($program['name']))
                echo ("        -".$program['name']."- \n");
            else echo("        -Standard- \n");
            echo ("        id: ".$program['program_id']." \n");
            if(isset($program['selected']) && $program['selected'] === TRUE)
            {
                echo "         This is the current program \n";
            }
        }
    }
}
/**
* @brief returns the current program of a therm module
*/
function getCurrentProgram($module)
{
    foreach($module['therm_program_list'] as $program)
    {
        if(isset($program['selected']) && $program['selected'] === TRUE)
            return $program['program_id'];
    }
    //not found
    return NULL;
}
/**
* @brief returns the current setpoint of a therm module along with its setpoint temperature and endtime if defined
*/
function getCurrentMode($module)
{
    $initialMode = $module["setpoint"]["setpoint_mode"];
    $initialTemp = isset($module["setpoint"]["setpoint_temp"]) ? $module["setpoint"]["setpoint_temp"]: NULL;
    $initialEndtime = isset($module['setpoint']['setpoint_endtime']) ? $module['setpoint']['setpoint_endtime'] : NULL;
    return array($initialMode, $initialTemp, $initialEndtime);
}
function printHomeInformation(NAHome $home)
{
    !is_null($home->getName()) ? $this->printMessageWithBorder($home->getName()) : $this->printMessageWithBorder($home->getId());
    echo ("id: ". $home->getId() ."\n");
    $tz = $home->getTimezone();
    $persons = $home->getPersons();
	
    if(!empty($persons))
    {
        $this->printMessageWithBorder("Persons");
        //print person list
        foreach($persons as $person)
        {
            $this->printPersonInformation($person, $tz);
        }
    }
    if((!empty($home->getEvents())))
    {
        $this->printMessageWithBorder('Timeline of Events');
        //print event list
        foreach($home->getEvents() as $event)
        {
            $this->printEventInformation($event, $tz);
        }
    }
    if(!empty($home->getCameras()))
    {
        $this->printMessageWithBorder("Cameras");
        foreach($home->getCameras() as $camera)
        {
            $this->printCameraInformation($camera);
        }
    }
}
function printPersonInformation(NAPerson $person, $tz)
{
    $person->isKnown() ? $this->printMessageWithBorder($person->getPseudo()) : $this->printMessageWithBorder("Inconnu");
    echo("id: ". $person->getId(). "\n");
    if($person->isAway())
        echo("is away from home \n" );
    else echo("is home \n");
    echo ("Last seen on: ");
    printTimeInTz($person->getLastSeen(), $tz, "j F H:i");
    echo ("\n");
}
function printEventInformation(NAEvent $event, $tz)
{
  printTimeInTz($event->getTime(), $tz, "j F H:i");
  $message = removeHTMLTags($event->getMessage());
  echo(": ".$message. "\n");
}
function printCameraInformation(NACamera $camera)
{
    !is_null($camera->getName()) ? printMessageWithBorder($camera->getName()) : printMessageWithBorder($camera->getId());
    echo("id: ". $camera->getId() ."\n");
    echo("Monitoring status: ". $camera->getVar(NACameraInfo::CI_STATUS) ."\n");
    echo("SD card status: " .$camera->getVar(NACameraInfo::CI_SD_STATUS) . "\n");
    echo ("Power status: ". $camera->getVar(NACameraInfo::CI_ALIM_STATUS) ."\n");
    if($camera->getGlobalStatus())
        $globalStatus = "OK";
    else $globalStatus = "NOK";
    echo ("Global Status: ". $globalStatus ."\n");
}
function removeHTMLTags($string)
{
   return preg_replace("/<.*?>/", "", $string);
}


    }
?>
