<?

/*

Module to control bulbs and rgbw stripes by using MiLight WiFi gateway (aka Limitless LED, IWY Light)


documentation:

http://www.limitlessled.com/dev/
LimitlessLED v3.0 / v4.0 OpenSource API

v4.0: The Port Number most importantly has changed from 50000 to 8899
v3.0: Wifi Bridge Router Web Config: http://10.10.100.254/home.html Username: admin Password: admin

All UDP Commands are 3 Bytes. First byte is from the list below, plus a fixed 2 byte suffix of 0x00 (decimal: 0) and 0x55 (decimal: 85)
i.e. to turn all RGBW COLOR LimitlessLED Smart lights to ON then send the TCP/IP UDP packet of: 0x42 0x00 0x55

Hexidecimal (byte) Decimal (integer)
RGBW COLOR LED ALL OFF 0x41 65
RGBW COLOR LED ALL ON 0x42 66
DISCO SPEED SLOWER 0x43 67
DISCO SPEED FASTER 0x44 68
GROUP 1 ALL ON 0x45 69 (SYNC/PAIR RGB+W Bulb within 2 seconds of Wall Switch Power being turned ON)
GROUP 1 ALL OFF 0x46 70
GROUP 2 ALL ON 0x47 71 (SYNC/PAIR RGB+W Bulb within 2 seconds of Wall Switch Power being turned ON)
GROUP 2 ALL OFF 0x48 72
GROUP 3 ALL ON 0x49 73 (SYNC/PAIR RGB+W Bulb within 2 seconds of Wall Switch Power being turned ON)
GROUP 3 ALL OFF 0x4A 74
GROUP 4 ALL ON 0x4B 75 (SYNC/PAIR RGB+W Bulb within 2 seconds of Wall Switch Power being turned ON)
GROUP 4 ALL OFF 0x4C 76
DISCO MODE 0x4D 77
SET COLOR TO WHITE (GROUP ALL) 0x42 100ms followed by: 0xC2
SET COLOR TO WHITE (GROUP 1) 0x45 100ms followed by: 0xC5
SET COLOR TO WHITE (GROUP 2) 0x47 100ms followed by: 0xC7
SET COLOR TO WHITE (GROUP 3) 0x49 100ms followed by: 0xC9
SET COLOR TO WHITE (GROUP 4) 0x4B 100ms followed by: 0xCB

LIMITLESSLED RGBW DIRECTLY SETTING THE BRIGHTNESS is by a 3BYTE COMMAND: (First send the Group ON for the group you want to set the brightness for. You send the group ON command 100ms before sending the 4E 1B 55)
Byte1: 0x4E (decimal: 78)
Byte2: 0×02 to 0x1B (decimal range: 2 to 27) full brightness 0x1B (decimal 27)
Byte3: Always 0×55 (decimal: 85)

LIMITLESSLED RGBW COLOR SETTING is by a 3BYTE COMMAND: (First send the Group ON for the group you want to set the colour for. You send the group ON command 100ms before sending the 40)
Byte1: 0×40 (decimal: 64)
Byte2: 0×00 to 0xFF (255 colors) See Color Matrix Chart for the different values below.
Byte3: Always 0×55 (decimal: 85)

Byte2: Color Matrix Chart: (thanks Stephan Schaade, http://knx-user-forum.de http://mknx.github.io/smarthome/)
    0x00 Violet
    0x10 Royal_Blue
    0x20 Baby_Blue
    0x30 Aqua
    0x40 Mint
    0x50 Seafoam_Green
    0x60 Green
    0x70 Lime_Green
    0x80 Yellow
    0x90 Yellow_Orange
    0xA0 Orange
    0xB0 Red
    0xC0 Pink
    0xD0 Fusia
    0xE0 Lilac
    0xF0 Lavendar
*/


class milight extends IPSModule
{
	private $GroupOn;
	private $GroupOff;
	private $GroupWhite;
	
	private $CommandRepeat = 3;

	public function Create()
	{
		//Never delete this line!
		parent::Create();

		$this->RegisterPropertyString("ValueCIP", "255.255.255.255");
		$this->RegisterPropertyInteger("ValueCPort", 8899);
		$this->RegisterPropertyInteger("ValueGroup", 0);
	}

	public function ApplyChanges()
	{
		//Never delete this line!
	parent::ApplyChanges();

		$this->RegisterProfileIntegerEx("milight.State", "", "", "", Array(
			Array(0, 'off', '', -1),
			Array(1, 'white', '', -1),
			Array(2, 'color', '', -1)
		));

		$this->RegisterVariableInteger("STATE", "STATE", "milight.State", 1);
		$this->EnableAction("STATE");
		$this->RegisterVariableInteger("Color", "Color", "~HexColor", 2);
		$this->EnableAction("Color");
		$this->RegisterVariableInteger("Brightness", "Brightness", "~Intensity.255", 3);
		$this->EnableAction("Brightness");
		$this->SetVisibility(0);
		
		$this->TestGateway();
	}

################## PUBLIC
    
	/**
	* This functions will be available automatically after the module is imported with the module control.
	* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
	*
	* e.g. MILIGHT_SetSatte($id,$state);
	*
	*/

	public function SetState(integer $State)
	{
		//$OldState = GetValueInteger($this->GetIDForIdent('STATE'));

		switch ($State) {
		case 0: // aus
			$this->SetBrightness(0);
			break;
		case 1: // weiß
			$Brightness = GetValueInteger($this->GetIDForIdent('Brightness'));
			$this->SetBrightness($Brightness);
			break;
		case 2: // Farbe
			$Color = GetValueInteger($this->GetIDForIdent('Color'));
			$this->SetColor($Color);
			break;
		}
		$this->SetVisibility($State);
	}

	public function SetRGB(integer $Red, integer $Green, integer $Blue)
	{
		if (($Red < 0) or ( $Red > 255) or ( $Green < 0) or ( $Green > 255) or ( $Blue < 0) or ( $Blue > 255))
			IPS_LogMessage("milight", "Color must be between 0 and 255");

		$this->InitGroup($this->ReadPropertyInteger('ValueGroup'));
		$tosend = array();

		//$Value = $this->rgb2milightmatrix($Red, $Green, $Blue);
		$Value = $this->RGB2HSLmilight($Red, $Green, $Blue);
		$Hue = $Value['Color'];
		$Luminance  = $Value['Luminance'];
		if ($Luminance >= 2) {
			$tosend[] = $this->GroupOn; // Send Group n On
			//$tosend[] = ($Hue > 0) ? "\x40".chr($Hue)."\x55" : $this->GroupWhite; // Send Group n White;
			$tosend[] = "\x40".chr($Hue)."\x55";
			$tosend[] = "\x4e".chr($Luminance)."\x55";
		} else {
			$tosend[] = $this->GroupOff; // Send Group n Off
		}
		$this->SendCommand($tosend);
	}

	public function SetColor(integer $Color)
	{
		$r = ($Color & 0x00ff0000) >> 16;
		$g = ($Color & 0x0000ff00) >> 8;
		$b = $Color & 0x000000ff;
		$this->SetRGB($r, $g, $b);
	}

	public function SetBrightness(integer $Level)
	{
		if (($Level < 0) or ( $Level > 255))
			IPS_LogMessage("milight", "Color must be between 0 and 255");

		$this->InitGroup($this->ReadPropertyInteger('ValueGroup'));
		$tosend = array();

		// send brightness (value range 0-255 > 2-27)
		$Luminance = round(($Level / 9.44), 0);
		if ($Luminance > 27)
			$Luminance = 27;
		if ($Luminance >= 2) {
			$tosend[] = $this->GroupOn;    // Send Group n On
			$tosend[] = $this->GroupWhite; // Send Group n White
			$tosend[] = "\x4e".chr($Luminance)."\x55";
		} else {
			$tosend[] = $this->GroupOff; // Send Group n Off
		}
		$this->SendCommand($tosend);
	}


################## ActionHandler

	public function RequestAction($Ident, $Value)
	{
		switch ($Ident) {
			case 'STATE':
				$this->SetValueInteger('STATE', $Value);
				$this->SetState($Value);
				break;
			case 'Color':
				$this->SetValueInteger('Color', $Value);
				$this->SetColor($Value);
				break;
			case 'Brightness':
				$this->SetValueInteger('Brightness', $Value);
				$this->SetBrightness($Value);
				break;
			default:
				throw new Exception('Invalid Ident');
			break;
		}
	}

################## PRIVATE    

	private function SetVisibility(integer $State)
	{
		switch ($State) {
		case 0: // aus
			$this->SetHidden('Color', true);
			$this->SetHidden('Brightness', true);
			break;
		case 1: // weiß
			$this->SetHidden('Color', true);
			$this->SetHidden('Brightness', false);
			break;
		case 2: // Farbe
			$this->SetHidden('Color', false);
			$this->SetHidden('Brightness', true);
			break;
		}
		$this->SetValueInteger('STATE', $State);
	}

	private function SendCommand($Data)
	{
		$result = true;
		try
		{                
			foreach ($Data AS $msg) {
				for ($count=0; $count < $this->CommandRepeat; $count++) {
					if ($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
						if (strcasecmp($this->ReadPropertyString('ValueCIP'), "255.255.255.255") == 0)
							// set special broadcast options
							socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
						$res = socket_sendto($sock, $msg, strlen($msg), 0, $this->ReadPropertyString('ValueCIP'), $this->ReadPropertyInteger('ValueCPort'));
						if ($res == -1)
							$result = false;
						socket_close($sock);
						ips_sleep(100);
					}
				}
			}
		}
		catch (Exception $exc) {
			$result = false;
		}
		if (!$result) {
			$this->SetStatus(201);
			IPS_LogMessage("milight", "socket error");
		}
	}

	private function TestGateway()
	{
		$timeout = 1;
		$commandport = 48899;
		$result = true;
		$msg = "Link_Wi-Fi";
		$msgreply = "/.+[.].+[.].+[.].+,[a-zA-Z0-9]{12}/";  // regex check, valid is a response of "<IP address>,<MAC address>," e.g. "192.168.1.111,AABB00112233,"

		if (strcasecmp($this->ReadPropertyString('ValueCIP'), "255.255.255.255") != 0) {
			try {
				$handle = @fsockopen("udp://".$this->ReadPropertyString('ValueCIP'), $commandport, $errno, $errstr, $timeout);
				if (!$handle) {
					$result = false;
				} else {
					socket_set_timeout($handle, $timeout);
					for ($count=0; $count < $this->CommandRepeat; $count++) {
						$write = fwrite($handle, $msg);
						ips_sleep(50);
					}
					$read = fread($handle, 255);
					if (!preg_match($msgreply, $read))
						$result = false;
					fclose($handle);
				}
			} catch (Exception $e) {
				$result = false;
			}
		}
		if (!$result) {
			$this->SetStatus(201);
			IPS_LogMessage("milight", "socket error");
		} else {
			$this->SetStatus(102);
		}
	}

	private function InitGroup($Group)
	{
		switch ($Group) {
			case 1: // Group 1
				$this->GroupOn    = "\x45\x00\x55";
				$this->GroupOff   = "\x46\x00\x55";
				$this->GroupWhite = "\xc5\x00\x55";
				break;
			case 2: // Group 2
				$this->GroupOn    = "\x47\x00\x55";
				$this->GroupOff   = "\x48\x00\x55";
				$this->GroupWhite = "\xc7\x00\x55";
				break;
			case 3: // Group 3
				$this->GroupOn    = "\x49\x00\x55";
				$this->GroupOff   = "\x4a\x00\x55";
				$this->GroupWhite = "\xc9\x00\x55";
				break;
			case 4: // Group 4
				$this->GroupOn    = "\x4b\x00\x55";
				$this->GroupOff   = "\x4c\x00\x55";
				$this->GroupWhite = "\xcb\x00\x55";
				break;
			default: // default to Group 0 (ALL)
				$this->GroupOn    = "\x42\x00\x55";
				$this->GroupOff   = "\x41\x00\x55";
				$this->GroupWhite = "\xc2\x00\x55";
				break;
		}
		return true;
	}

	protected function RGB2HSLmilight($R, $G, $B) {
		if (($R < 0) or ( $R > 255) or ( $G < 0) or ( $G > 255) or ( $B < 0) or ( $B > 255))
			IPS_LogMessage("milight", "Color must be between 0 and 255");
		$R = ($R / 255);
		$G = ($G / 255);
		$B = ($B / 255);
		$cMin = min($R, $G, $B);
		$cMax = max($R, $G, $B);
		$Chroma = $cMax - $cMin;
		$L = ($cMax + $cMin) / 2 * 27;
		//$V = $maxRGB * 27;
		if ($Chroma == 0) {
			$H = 0;
		} else {
			if ($R == $cMin) {
				$H = 1 - (($B - $G) / $Chroma);
			} elseif ($B == $cMin) {
				$H = 3 - (($G - $R) / $Chroma);
			} elseif ($G == $cMin) {
				$H = 5 - (($R - $B) / $Chroma);
			}
			$H = $H / 6 * 255;
		}
		//IPS_LogMessage("rgb2hslmilight", "$R / $G / $B - $H / $L");
		return array('Color' => round($H), 'Luminance' => round($L));
	}


################## helper functions / wrapper

	protected function SetHidden($Ident, $value)
	{
		$id = $this->GetIDForIdent($Ident);
		IPS_SetHidden($id, $value);
	}

	protected function SetValueBoolean($Ident, $value)
	{
		$id = $this->GetIDForIdent($Ident);
		SetValueBoolean($id, $value);
	}

	protected function SetValueInteger($Ident, $value)
	{
		$id = $this->GetIDForIdent($Ident);
		SetValueInteger($id, $value);
	}

	protected function SetValueString($Ident, $value)
	{
		$id = $this->GetIDForIdent($Ident);
		SetValueString($id, $value);
	}

	protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
	{
		if (sizeof($Associations) === 0) {
			$MinValue = 0;
			$MaxValue = 0;
		} else {
			$MinValue = $Associations[0][0];
			$MaxValue = $Associations[sizeof($Associations) - 1][0];
		}
		if (!IPS_VariableProfileExists($Name)) {
			IPS_CreateVariableProfile($Name, 1);
		} else {
			$profile = IPS_GetVariableProfile($Name);
			if ($profile['ProfileType'] != 1)
				throw new Exception("Variable profile type does not match for profile " . $Name);
		}
		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, 0);

		foreach ($Associations as $Association) {
			IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
		}
	}

}

?>
