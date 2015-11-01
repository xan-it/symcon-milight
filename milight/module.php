<?

class milight extends IPSModule
{
	private $GroupOn;
	private $GroupOff;
	private $GroupWhite;
	
    public function Create()
    {
        //Never delete this line!
        parent::Create();
		
		$this->RegisterPropertyString("ValueCIP", "192.168.1.135");
		$this->RegisterPropertyInteger("ValueCPort", 8899);
		$this->RegisterPropertyInteger("ValueGroup", 1);
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
    }

################## PUBLIC
    
	/**
	* This function will be available automatically after the module is imported with the module control.
	* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
	*
	* milight_RequestInfo($id);
	*
	*/
	public function RequestInfo()
	{
	}

    public function SendSwitch(integer $State)
    {
        $OldState = GetValueInteger($this->GetIDForIdent('STATE'));
		
		DoInit(GetValueInteger($this->GetIDForIdent('ValueGroup')));
		$tosend = array();
		
		switch ($State) {
        case 0: // aus
            $tosend[] = $GroupOff; // Send Group n Off
            $this->SetHidden('Color', true);
            $this->SetHidden('Brightness', true);
          break;
        case 1: // weiß
            $tosend[] = $GroupOn;    // Send Group n On
            $tosend[] = $GroupWhite; // Send Group n White
            $this->SetHidden('Color', true);
            $this->SetHidden('Brightness', false);
          break;
        case 2: // Farbe
            $tosend[] = $GroupOn; // Send Group n On
			//$tosend[] = "\x40".chr($ValueH)."\x55";
            $this->SetHidden('Color', false);
            $this->SetHidden('Brightness', true);
         break;
		}
		SendCommand($tosend);
		$this->SetValueInteger('STATE', $State);
    }

    public function SetRGB(integer $Red, integer $Green, integer $Blue)
    {
		if (($Red < 0) or ( $Red > 255) or ( $Green < 0) or ( $Green > 255) or ( $Blue < 0) or ( $Blue > 255))
            throw new Exception('Invalid Parameterset');
		$Color = ($Red << 16) + ($Green << 8) + $Blue;

		$tosend = array();
		
		$rgb[0] = $Red;
		$rgb[1] = $Green;
		$rgb[2] = $Blue;
		$ValueHSL = rgb2hsl($rgb);
		$Hue = $ValueHSL['Hue'];
		$Saturation = $ValueHSL['Saturation'];
		$Luminance  = $ValueHSL['Luminance'];
		if ($Luminance >= 5) {
			// send color
			$tosend[] = $GroupOn;
			$tosend[] = "\x40".chr($Hue)."\x55";
			// send brightness (value range 0-255 > 2-27)
			$Luminance2 = round(($Luminance / 9.44), 0);
			if ($Luminance2 <  2) $Luminance2 =  2;
			if ($Luminance2 > 27) $Luminance2 = 27;
			$tosend[] = $GroupOn;
			$tosend[] = "\x4e".chr($Luminance2)."\x55";
			if ($this->SendCommand($tosend)) {
				$this->SetValueInteger('Color', $Color);
				$this->SetValueBoolean('STATE', 2);
			}
		} else {
			$tosend[] = $GroupOff;
			if ($this->SendCommand($tosend)) {
				$this->SetValueInteger('Color', $Color);
				$this->SetValueBoolean('STATE', 0);
			}
		}
		$this->SetHidden('Color', false);
		$this->SetHidden('Brightness', true);
	}

    public function SetColor(integer $Color)
    {
		$r = ($Color & 0x00ff0000) >> 16;
		$g = ($Color & 0x0000ff00) >> 8;
		$b = $Color & 0x000000ff;
		SetRGB($r, $g, $b);
    }

    public function SetBrightness(integer $Level)
    {
        if (($Level < 1) or ( $Level > 255))
            throw new Exception('Invalid Brightness-Level');
        $tosend = array();
		$tosend[] = $GroupOn;    // Send Group n On
        $tosend[] = $GroupWhite; // Send Group n White
		// send brightness (value range 0-255 > 2-27)
		$Luminance = round(($Level / 9.44), 0);
		if ($Luminance <  2) $Luminance =  2;
		if ($Luminance > 27) $Luminance = 27;
		$tosend[] = $GroupOn;
		$tosend[] = "\x4e".chr($Luminance)."\x55";
		if ($this->SendCommand($tosend)) {
			$this->SetValueInteger('Color', $Color);
			$this->SetValueBoolean('STATE', 1);
		}
		$this->SetHidden('Color', true);
		$this->SetHidden('Brightness', false);
    }

	
################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
IPS_LogMessage(__CLASS__, __FUNCTION__ . ' Ident:.' . $Ident . ' = ' . $Value); //     
//unset($Value);
        switch ($Ident)
        {
            case 'STATE':
                $this->SendSwitch($Value); //SendInit();
                break;
            case 'Color':
                $this->SetColor($Value);
                break;
            case 'Brightness':
                $this->SetBrightness($Value);
                break;
            default:
                throw new Exception('Invalid Ident');
                break;
        }
    }

################## PRIVATE    

    private function SendCommand($Data)
    {
        if (!$this->lock('InitRun'))
            return;
        else
            $this->unlock('InitRun');
        if ($this->lock('SendCommand'))
        {
            try
            {                
				$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
				foreach ($$Data AS $msg) {
					$len = strlen($msg);
					socket_sendto($sock, $msg, $len, 0, '192.168.1.135', 8899);
					//socket_sendto($sock, $msg, $len, 0, $this->ReadPropertyString("ValueCIP"), $this->ReadPropertyInteger("ValueCPort"));
					ips_sleep(100);
				}
				socket_close($sock);
                $this->unlock('SendCommand');
			}
            catch (Exception $exc)
            {
                $this->unlock('SendCommand');
                throw new $exc;
            }
        }
        else
        {
            throw new Exception('SendCommand is blocked.');
        }
    }

    private function DoInit($Group)
    {
		switch ($Group) {
			case 1: // Group 1
				$GroupOn    = "\x45\x00\x55";
				$GroupOff   = "\x46\x00\x55";
				$GroupWhite = "\xc5\x00\x55";
				break;
			case 2: // Group 2
				$GroupOn    = "\x47\x00\x55";
				$GroupOff   = "\x48\x00\x55";
				$GroupWhite = "\xc7\x00\x55";
				break;
			case 3: // Group 3
				$GroupOn    = "\x49\x00\x55";
				$GroupOff   = "\x4a\x00\x55";
				$GroupWhite = "\xc9\x00\x55";
				break;
			case 4: // Group 4
				$GroupOn    = "\x4b\x00\x55";
				$GroupOff   = "\x4c\x00\x55";
				$GroupWhite = "\xcb\x00\x55";
				break;
			default: // default to Group 1
				$GroupOn    = "\x45\x00\x55";
				$GroupOff   = "\x46\x00\x55";
				$GroupWhite = "\xc5\x00\x55";
				break;
		}
		return true;
    }

################## DATAPOINTS

    public function ReceiveData($JSONString)
    {
        IPS_LogMessage('RecData', utf8_decode($JSONString));
        IPS_LogMessage(__CLASS__, __FUNCTION__); // 
		//FIXME Bei Status inaktiv abbrechen
		$data = json_decode($JSONString);
		if ($data->DataID <> '{8E870253-4594-43B5-A91D-B79376E30A20}')
			return false;
        $this->SetReplyEvent(TRUE);
        return true;
    }

################## SEMAPHOREN Helper  - private  

    private function lock($ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("milight_" . (string) $this->InstanceID . (string) $ident, 1))
            {
//                IPS_LogMessage((string)$this->InstanceID,"Lock:LMS_" . (string) $this->InstanceID . (string) $ident);
                return true;
            }
            else
            {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function unlock($ident)
    {
        IPS_SemaphoreLeave("milight_" . (string) $this->InstanceID . (string) $ident);
    }

################## DUMMYS / WOARKAROUNDS - protected

    private function SetValueBoolean($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        SetValueBoolean($id, $value);
    }

    private function SetValueInteger($Ident, $value)
    {
        $id = $this->GetIDForIdent($Ident);
        SetValueInteger($id, $value);
    }

    private function SetValueString($Ident, $value)
    {
      $id = $this->GetIDForIdent($Ident);
      SetValueString($id, $value);
    }

/*
    protected function HasActiveParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }
*/

    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (sizeof($Associations) === 0)
        {
            $MinValue = 0;
            $MaxValue = 0;
        }
        else
        {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations) - 1][0];
        }
		if (!IPS_VariableProfileExists($Name))
        {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1)
                throw new Exception("Variable profile type does not match for profile " . $Name);
        }
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association)
        {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

	// Funktion übernommen von www.php.net und an eigene Bedürfnisse angepasst
	// Convert RGB colors array into HSL array
	// @param array $ RGB colors set, each color component with range 0 to 255
	// @return array HSL set, each color component with range 0 to 1
    function rgb2hsl($rgb){
        $clrR = ($rgb[0]);
        $clrG = ($rgb[1]);
        $clrB = ($rgb[2]);

        $clrMin = min($clrR, $clrG, $clrB);
        $clrMax = max($clrR, $clrG, $clrB);
        $deltaMax = $clrMax - $clrMin;

        $L = ($clrMax + $clrMin) / 510;

        if (0 == $deltaMax){
            $H = 0;
            $S = 0;
        }
        else{
            if (0.5 > $L){
                $S = $deltaMax / ($clrMax + $clrMin);
            }
            else{
                $S = $deltaMax / (510 - $clrMax - $clrMin);
            }

            if ($clrMax == $clrR) {
                $H = ($clrG - $clrB) / (6.0 * $deltaMax);
            }
            else if ($clrMax == $clrG) {
                $H = 1/3 + ($clrB - $clrR) / (6.0 * $deltaMax);
            }
            else {
                $H = 2 / 3 + ($clrR - $clrG) / (6.0 * $deltaMax);
            }

            if (0 > $H) $H += 1;
            if (1 < $H) $H -= 1;
        }

         $H = round(($H * 255), 0);
         $S = round(($S * 255), 0);
         $L = round(($L * 255), 0);

      $HSLColor = array( 'Hue' => $H, 'Saturation' => $S, 'Luminance' => $L );

      return $HSLColor;
	}
	
}

?>