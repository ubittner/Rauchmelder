<?php

declare(strict_types=1);

trait RM_notification
{
    public function Notify(bool $DailyNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->CheckNotification($DailyNotification)) {
            return false;
        }
        $result1 = $this->SendWebFrontNotification($DailyNotification);
        $result2 = $this->SendMobileDeviceNotification($DailyNotification);
        $result3 = $this->SendMailNotification($DailyNotification);
        if (!$result1 || !$result2 || !$result3) {
            return false;
        }
        return true;
    }

    #################### Private

    private function SendWebFrontNotification(bool $DailyNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->CheckNotification($DailyNotification)) {
            return false;
        }
        $notification = false;
        $actualState = $this->GetValue('State');
        if (!$DailyNotification) {
            if (!$actualState && $this->ReadPropertyBoolean('UseStateChangedOK')) {
                $notification = true;
            }
            if ($actualState && $this->ReadPropertyBoolean('UseStateChangedSmokeDetected')) {
                $notification = true;
            }
        }
        if ($DailyNotification) {
            if (!$actualState && $this->ReadPropertyBoolean('UseDailyNotificationOK')) {
                $notification = true;
            }
            if ($actualState && $this->ReadPropertyBoolean('UseDailyNotificationSmokeDetected')) {
                $notification = true;
            }
        }
        $success = false;
        if ($notification) {
            $timestamp = (string) date('d.m.Y, H:i:s');
            $webFronts = json_decode($this->ReadPropertyString('WebFrontNotification'));
            if (empty($webFronts)) {
                return false;
            }
            $error = false;
            foreach ($webFronts as $webFront) {
                if ($webFront->Use) {
                    $id = $webFront->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        if (!$actualState) {
                            $unicode = json_decode('"\u2705"'); # white_check_mark
                            $message = $timestamp . "\n" . $unicode . ' OK';
                        } else {
                            $unicode = json_decode('"\ud83d\udd25"'); # flame
                            $message = $timestamp . "\n" . $unicode . " Rauch erkannt\n" . $this->GetValue('AlertingSensor');
                        }
                        $result = @WFC_SendNotification($id, 'Rauchmelder', $message, '', $webFront->DisplayDuration);
                        if (!$result) {
                            $error = true;
                        }
                    }
                }
            }
            if (!$error) {
                $success = true;
            }
        }
        return $success;
    }

    private function SendMobileDeviceNotification(bool $DailyNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->CheckNotification($DailyNotification)) {
            return false;
        }
        $notification = false;
        $actualState = $this->GetValue('State');
        if (!$DailyNotification) {
            if (!$actualState && $this->ReadPropertyBoolean('UseStateChangedOK')) {
                $notification = true;
            }
            if ($actualState && $this->ReadPropertyBoolean('UseStateChangedSmokeDetected')) {
                $notification = true;
            }
        }
        if ($DailyNotification) {
            if (!$actualState && $this->ReadPropertyBoolean('UseDailyNotificationOK')) {
                $notification = true;
            }
            if ($actualState && $this->ReadPropertyBoolean('UseDailyNotificationSmokeDetected')) {
                $notification = true;
            }
        }
        $success = false;
        if ($notification) {
            $timestamp = (string) date('d.m.Y, H:i:s');
            $webFronts = json_decode($this->ReadPropertyString('MobileDeviceNotification'));
            if (empty($webFronts)) {
                return false;
            }
            $error = false;
            foreach ($webFronts as $webFront) {
                if ($webFront->Use) {
                    $id = $webFront->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $location = $this->ReadPropertyString('LocationDesignation');
                        if (!$actualState) {
                            $unicode = json_decode('"\u2705"'); # white_check_mark
                            $message = $timestamp . "\n" . $unicode . " OK\n" . $location;
                            $sound = '';
                        } else {
                            $unicode = json_decode('"\ud83d\udd25"'); # flame
                            $message = $timestamp . "\n" . $unicode . " Rauch erkannt\n" . $location . "\n" . $this->GetValue('AlertingSensor');
                            $sound = 'alarm';
                        }
                        $result = @WFC_PushNotification($id, 'Rauchmelder', "\n" . $message, $sound, 0);
                        if (!$result) {
                            $error = true;
                        }
                    }
                }
            }
            if (!$error) {
                $success = true;
            }
        }
        return $success;
    }

    private function SendMailNotification(bool $DailyNotification): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->CheckNotification($DailyNotification)) {
            return false;
        }
        $recipients = json_decode($this->ReadPropertyString('MailNotification'));
        if (empty($recipients)) {
            return false;
        }
        $notification = false;
        $actualState = $this->GetValue('State');
        if (!$DailyNotification) {
            if (!$actualState && $this->ReadPropertyBoolean('UseStateChangedOK')) {
                $notification = true;
            }
            if ($actualState && $this->ReadPropertyBoolean('UseStateChangedSmokeDetected')) {
                $notification = true;
            }
        }
        if ($DailyNotification) {
            if (!$actualState && $this->ReadPropertyBoolean('UseDailyNotificationOK')) {
                $notification = true;
            }
            if ($actualState && $this->ReadPropertyBoolean('UseDailyNotificationSmokeDetected')) {
                $notification = true;
            }
        }
        $success = false;
        if ($notification) {
            $timestamp = (string) date('d.m.Y, H:i:s');
            $error = false;
            foreach ($recipients as $recipient) {
                if ($recipient->Use) {
                    $id = $recipient->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $address = $recipient->Address;
                        if (!empty($address) && strlen($address) > 3) {
                            $sensorStateList = "Rauchmelder: \n\n";
                            $sensors = json_decode($this->GetBuffer('SensorStateList'));
                            if (!empty($sensors)) {
                                foreach ($sensors as $sensor) {
                                    $sensorStateList .= $sensor->unicode . ' ' . $sensor->name . "\n";
                                }
                            }
                            $location = $this->ReadPropertyString('LocationDesignation');
                            if (!$actualState) {
                                $unicode = json_decode('"\u2705"'); # white_check_mark
                                $subject = 'Rauchmelder ' . $location . ' - ' . $unicode . ' OK';
                                $text = "Status:\n\n" . $timestamp . ', Rauchmelder ' . $location . ' - ' . $unicode . " OK \n\n";
                            } else {
                                $unicode = json_decode('"\ud83d\udd25"'); # flame
                                $subject = 'Rauchmelder ' . $location . ' - ' . $unicode . ' Rauch erkannt, ' . $this->GetValue('AlertingSensor');
                                $text = "Status:\n\n" . $timestamp . ', Rauchmelder ' . $location . ' - ' . $unicode . ' Rauch erkannt, ' . $this->GetValue('AlertingSensor') . "\n\n";
                            }
                            $text .= $sensorStateList;
                            $result = @SMTP_SendMailEx($id, $address, $subject, $text);
                            if (!$result) {
                                $error = true;
                            }
                        }
                    }
                }
            }
            if (!$error) {
                $success = true;
            }
        }
        return $success;
    }

    #################### Private

    private function CheckNotification(bool $DailyNotification): bool
    {
        if (!$DailyNotification && !$this->ReadPropertyBoolean('UseStateChangedNotification')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Benachrichtigung bei Statusänderung ist deaktiviert!', 0);
            return false;
        }
        if ($DailyNotification && !$this->ReadPropertyBoolean('UseDailyNotification')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die tägliche Benachrichtigung ist deaktiviert!', 0);
            return false;
        }
        return true;
    }
}