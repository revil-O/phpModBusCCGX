<?php
/********************************************************************************************                                                      *
* Author:   Oliver Schmidt 
*
* File:     /getCCGXModBusData.php                                                                                                               *                                                                                                        *
*---------------------------------------------------------------------------------------------------------------------------------*
* Fetches Victron Energy CCGX data via ModBus,display and transmit them to                                      *
* OpenHAB / fhem                                                                                                                                                                     *
*---------------------------------------------------------------------------------------------------------------------------------*
* Dieses Werk bzw. Inhalt steht unter einer Creative Commons Namensnennung -                                                            *
* Weitergabe unter gleichen Bedingungen 3.0 Deutschland Lizenz: CC-BY-SA                                                                    *
********************************************************************************************/
require_once 'Phpmodbus/ModbusMaster.php';         // ModBus Class
require_once 'solar_config.inc.php';                          // Config and credentials for solar scripts

//-------------------------------------------------------------------------------------------------------------------------------------
// OpenHAB item names. Index 0..33 corresponds to Modbus addresses 3..36:
$OHABitem[0]  =  "VEMod_ACIn1_V";            // Input voltage phase 1
$OHABitem[1]  =  "VEMod_ACIn2_V";           // Input voltage phase 2
$OHABitem[2]  =  "VEMod_ACIn3_V";           // Input voltage phase 3
$OHABitem[3]  =  "VEMod_ACIn1_C";           // Input current phase 1
$OHABitem[4]  =  "VEMod_ACIn2_C";           // Input current phase 2
$OHABitem[5]  =  "VEMod_ACIn3_C";           // Input current phase 3
$OHABitem[6]  =  "VEMod_ACIn1_F";           // Input frequency 1
$OHABitem[7]  =  "VEMod_ACIn1_F";           // Input frequency 2
$OHABitem[8]  =  "VEMod_ACIn1_F";           // Input frequency 3
$OHABitem[9]  =  "VEMod_ACIn1_P";           // Input power 1
$OHABitem[10] =  "VEMod_ACIn2_P";           // Input power 2
$OHABitem[11] =  "VEMod_ACIn3_P";           // Input power 3
$OHABitem[12] =  "VEMod_ACOut1_V";          // Output voltage phase 1
$OHABitem[13] =  "VEMod_ACOut2_V";          // Output voltage phase 2
$OHABitem[14] =  "VEMod_ACOut3_V";          // Output voltage phase 1
$OHABitem[15] =  "VEMod_ACOut1_C";          // Output current phase 1
$OHABitem[16] =  "VEMod_ACOut2_C";          // Output current phase 2
$OHABitem[17] =  "VEMod_ACOut3_C";          // Output current phase 3
$OHABitem[18] =  "VEWeb_ACOut_F";           // Output frequency
$OHABitem[19] =  "VEMod_InputLimit";        // Active input current limit
$OHABitem[20] =  "VEMod_ACOut1_P";          // Output power 1
$OHABitem[21] =  "VEMod_ACOut2_P";          // Output power 2
$OHABitem[22] =  "VEMod_ACOut3_P";          // Output power 3
$OHABitem[23] =  "VEMod_Bat_V";             // Battery voltage
$OHABitem[24] =  "VEMod_Bat_C";             // Battery current
$OHABitem[25] =  "VEMod_PhaseCount";        // Phase count
$OHABitem[26] =  "VEMod_ActInp";            // Active input
$OHABitem[27] =  "VEMod_Bat_SOC";           // VE.Bus state of charge
$OHABitem[28] =  "VEMod_State";             // VE.Bus state
$OHABitem[29] =  "VEMod_Error";             // VE.Bus Error code
$OHABitem[30] =  "VEMod_Switch";            // Switch Position
$OHABitem[31] =  "VEMod_OTemp";             // Temperature
$OHABitem[32] =  "VEMod_LBat";              // Low battery
$OHABitem[33] =  "VEMod_OLoad";             // Overload


// Text messages for Victron states:
// Texts for Victron codes:
$SPos[1] = "Nur Laden";
$SPos[2] = "Nur Inverter";
$SPos[3] = "Ein";
$SPos[4] = "Aus";

$Alarm[0] = "OK";
$Alarm[1] = "Warnung";
$Alarm[2] = "Alarm";

$VEState[0]   = "Aus";
$VEState[1]   = "Low Power Modus";
$VEState[2]   = "Fehler";
$VEState[3]   = "Bulk";
$VEState[4]   = "Absorption";
$VEState[5]   = "Float";
$VEState[6]   = "Storage";
$VEState[7]   = "Equalize";
$VEState[8]   = "Passthru";
$VEState[9]   = "Inverting";
$VEState[10]  = "Power Assist";
$VEState[11]  = "Power Supply Modus";
$VEState[252] = "Bulk Protection";
$VEError[0]  = "No Error";
$VEError[1]  = "VE.Bus Error: Device is switched off because one of the other phases in the system has switched off";
$VEError[2]  = "VE.Bus Error: New and old types MK2 are mixed in the system";
$VEError[3]  = "VE.Bus Error: No error Not all, or more than, the expected devices were found in the system";
$VEError[4]  = "VE.Bus Error: No other device whatsoever detected";
$VEError[5]  = "VE.Bus Error: Overvoltage on AC-out";
$VEError[6]  = "VE.Bus Error: Error in DDC Program";
$VEError[10] = "VE.Bus Error: System time synchronisation problem occurred";
$VEError[14] = "VE.Bus Error: Device cannot transmit data";
$VEError[16] = "VE.Bus Error: Dongle missing";
$VEError[17] = "VE.Bus Error: One of the devices assumed master status because the original master failed";
$VEError[18] = "VE.Bus Error: Overvoltage has occurred";
$VEError[22] = "VE.Bus Error: This device cannot function as slave";
$VEError[24] = "VE.Bus Error: Switch-over system protection initiated";
$VEError[25] = "VE.Bus Error: Firmware incompatibility. The firmware of one of the connected device is not sufficiently up to date to operate in conjunction with this device";
$VEError[26] = "VE.Bus Error: Internal error";

// ---------- Get actual time ----------
$TSfull = strftime("%d.%m.%Y / %H:%M:%S", time());				// Actual date and time for website
$TSfull = urlencode($TSfull);
//------------------------------------------------------------------------------------------------
// Decode program parameter:
$debug=0;
$CLparameter = '';
if(isset($_SERVER['argv'][1])) 	$CLparameter = $_SERVER['argv'][1];	// Check for commandline parameter in Linux
if($CLparameter=="-debug") $debug=1;																	// Print ECOMulti values in debug mode (command line mode)
if(isset($_GET['debug']) && $_GET['debug']==1) $debug = 1;			// Print ECOMulti values in debug mode (web mode)

//------------------------------------------------------------------------------------------------
$modbus = new ModbusMaster($UnitIP, $UnitProtocol);

try {
    // FC 3
    $recData = $modbus->readMultipleRegisters($UnitID, $UnitStartAddress, $UnitLength);
}
catch (Exception $e) {
    // Print error information if any
    echo $modbus;
    echo $e;
    exit;
}

// Chunk the data array to set of 2 bytes
$values = array_chunk($recData, 2);

$ArrIdx = 0;
foreach($values as $bytes) {
   	switch ($ArrIdx):
	    case 0:					// Address = 3, Input voltage phase 1
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 1:					// Address = 4, Input voltage phase 2
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 2:					// Address = 5, Input voltage phase 3
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 3:					// Address = 6, Input current phase 1
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 4:					// Address = 7, Input current phase 2
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 5:					// Address = 8, Input current phase 3
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 6:					// Address = 9, Input frequency 1
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 100;
	        break;
	    case 7:					// Address = 10, Input frequency 2
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 100;
	        break;
	    case 8:					// Address = 11, Input frequency 3
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 100;
	        break;
	    case 9:					// Address = 12, Input power 1
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) * 10;
	        break;
	    case 10:				// Address = 13, Input power 2
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) * 10;
	        break;
	    case 11:				// Address = 14, Input power 3
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) * 10;
	        break;
	    case 12:				// Address = 15, Output voltage phase 1
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 13:				// Address = 16, Output voltage phase 2
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 14:				// Address = 17, Output voltage phase 3
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 15:				// Address = 18, Output current phase 1
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 16:				// Address = 19, Output current phase 2
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 17:				// Address = 20, Output current phase 3
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 18:				// Address = 21, Output frequency
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 100;
	        break;
	    case 19:				// Address = 22, Active input current limit
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 20:				// Address = 23, Output power 1
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) * 10;
	        break;
	    case 21:				// Address = 24, Output power 2
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) * 10;
	        break;
	    case 22:				// Address = 25, Output power 3
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) * 10;
	        break;
	    case 23:				// Address = 26, Battery voltage
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 100;
	        break;
	    case 24:				// Address = 27, Battery current
	        $OutVal[$ArrIdx] = PhpType::bytes2signedInt($bytes) / 10;
	        break;
	    case 25:				// Address = 28, Phase count
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 26:				// Address = 29, Active input
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 27:				// Address = 30, VE.Bus state of charge
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes) / 10;
	        break;
	    case 28:				// Address = 31, VE.Bus state
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 29:				// Address = 32, VE.Bus Error code
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 30:				// Address = 33, Switch Position
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 31:				// Address = 34, Temperature
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 32:				// Address = 35, Low battery
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
	    case 33:				// Address = 36, Overload
	        $OutVal[$ArrIdx] = PhpType::bytes2unsignedInt($bytes);
	        break;
		endswitch;
		if($debug==1) echo "Index=" . $ArrIdx . ", " . $OHABitem[$ArrIdx] . ", " . $OutVal[$ArrIdx] . "\n";
		$ArrIdx += 1;
	}

//------------------------------------------------------------------------------------------------
// Send data to OpenHAB REST interface:
// $TSfull
postOpenHab("VEMod_TS", $TSfull);
for($i=0; $i<=33; $i++) {
	postOpenHab($OHABitem[$i], $OutVal[$i]);
}

// ToDo:
// Calculate energy for PV inverter:
//$PVEnergy = $FieldVal[32] * $PVsampleTime / 3600;															// Energy in Wh
//$URLPar   = $URLPar . "&" . $SensorPre . ":70=" . rawurlencode($PVEnergy);		// PV Inverter Energy

//====================================================================================================================
function postOpenHab($Item, $Value) {
	// Post the SMA values to OpenHab items
	global $openHabSrvIP , $debug;
	$url = "http://" . $openHabSrvIP  . ":8080/CMD?". $Item . "=" . $Value;
	if($debug==1) echo "Post to OpenHAB>>> " . $url . "\n";
	$ch1 = curl_init();
	curl_setopt($ch1, CURLOPT_URL, $url);									// website for CSV export
	curl_setopt($ch1, CURLOPT_HEADER, 0);									// Don't show header
	curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, true);						// Use redirection from server
	curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);						// Return transfer for getting website in a string
	$csvstr = curl_exec($ch1);																	// Execute cURL
	curl_close($ch1);																						// Release cURL ressources
}
//====================================================================================================================
?>
