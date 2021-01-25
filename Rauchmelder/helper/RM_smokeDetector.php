<?php

declare(strict_types=1);

trait RM_smokeDetector
{
    /**
     * Checks the actual state off all enabled smoke detectors.
     *
     * @param bool $DailyNotification
     * false    = immediate notification
     * true     = daily notification
     *
     * @return bool
     * false    = ok
     * true     = smoke detected
     */
    public function CheckActualState(bool $DailyNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $DailyNotification: ' . json_encode($DailyNotification), 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $sensorStateList = [];
        $timestamp = (string) date('d.m.Y, H:i:s');
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>Name</b></td><td><b>Letzte Statusprüfung</b></td></tr>';
        $vars = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (empty($vars)) {
            return false;
        }
        $actualState = $this->GetValue('State');
        $state = false;
        foreach ($vars as $var) {
            if (!$var->Use) {
                continue;
            }
            $id = $var->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            $unicode = json_decode('"\u2705"'); # white_check_mark
            $smokeDetected = false;
            $type = IPS_GetVariable($id)['VariableType'];
            $value = $var->Value;
            switch ($var->Trigger) {
                case 0: #on limit drop (integer, float)
                    switch ($type) {
                        case 1: #integer
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei Grenzunterschreitung (integer)', 0);
                            if (GetValueInteger($id) < intval($value)) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                        case 2: #float
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei Grenzunterschreitung (float)', 0);
                            if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                    }
                    break;

                case 1: #on limit exceed (integer, float)
                    switch ($type) {
                        case 1: #integer
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei Grenzunterschreitung (integer)', 0);
                            if (GetValueInteger($id) > intval($value)) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                        case 2: #float
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei Grenzunterschreitung (float)', 0);
                            if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                    }
                    break;

                case 2: #on specific value (bool, integer, float, string)
                    switch ($type) {
                        case 0: #bool
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei bestimmten Wert (bool)', 0);
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if (GetValueBoolean($id) == boolval($value)) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                        case 1: #integer
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei bestimmten Wert (integer)', 0);
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($id) == intval($value)) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                        case 2: #float
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei bestimmten Wert (float)', 0);
                            if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                        case 3: #string
                            $this->SendDebug(__FUNCTION__, 'ID: ' . $id . ', bei bestimmten Wert (string)', 0);
                            if (GetValueString($id) == (string) $value) {
                                $smokeDetected = true;
                                $state = true;
                            }
                            break;

                    }
                    break;
            }
            if ($smokeDetected) {
                $unicode = json_decode('"\ud83d\udd25"'); # flame
                $this->SetValue('State', 1);
                $this->SetValue('AlertingSensor', $var->Name);
                IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Warning');
            }
            $string .= '<tr><td>' . $unicode . '</td><td>' . $var->Name . '</td><td>' . $timestamp . '</td></tr>';
            array_push($sensorStateList, [
                'unicode'   => $unicode,
                'name'      => $var->Name,
                'timestamp' => $timestamp]);
        }
        $string .= '</table>';
        $this->SetValue('SensorList', $string);
        $this->SetBuffer('SensorStateList', json_encode($sensorStateList));
        if (!$state) {
            $this->SetValue('State', false);
            $this->SetValue('AlertingSensor', '');
            IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Ok');
        }
        if (!$DailyNotification && ($state != $actualState)) {
            $this->SendDebug(__FUNCTION__, 'Statusänderung, aktueller Status: ' . json_encode($state), 0);
            $this->Notify(false);
        }
        if ($DailyNotification) {
            $this->SetDailyNotificationTimer();
            $this->Notify(true);
        }
        return $state;
    }
}