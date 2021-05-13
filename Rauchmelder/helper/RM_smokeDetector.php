<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rauchmelder/tree/main/Rauchmelder
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait RM_smokeDetector
{
    public function UpdateState(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (empty($smokeDetectors)) {
            return;
        }
        $state = false;
        $sensorStateList = [];
        $timestamp = (string) date('d.m.Y, H:i:s');
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>Name</b></td><td><b>Letzte Statusprüfung</b></td></tr>';
        foreach ($smokeDetectors as $smokeDetector) {
            if (!$smokeDetector->Use) {
                continue;
            }
            $id = $smokeDetector->ID;
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            $unicode = json_decode('"\u2705"'); # white_check_mark
            $actualValue = boolval(GetValue($id));
            $triggerValue = $smokeDetector->TriggerValue;
            switch ($triggerValue) {
                case '0':
                case 'false':
                    $triggerValue = false;
                    break;

                case '1':
                case 'true':
                    $triggerValue = true;
                    break;

                default:
                    $triggerValue = boolval($triggerValue);

            }
            if ($actualValue == $triggerValue) {
                $unicode = json_decode('"\ud83d\udd25"'); # flame
                $state = true;
            }
            $string .= '<tr><td>' . $unicode . '</td><td>' . $smokeDetector->Name . '</td><td>' . $timestamp . '</td></tr>';
            array_push($sensorStateList, [
                'unicode'   => $unicode,
                'name'      => $smokeDetector->Name,
                'timestamp' => $timestamp]);
        }
        $string .= '</table>';
        $this->SetValue('SensorList', $string);
        $this->SetBuffer('SensorStateList', json_encode($sensorStateList));
        $this->SetValue('State', $state);
        if (!$state) {
            $this->SetValue('AlertingSensor', '');
        }
    }

    public function CheckTriggerVariable(int $SenderID, bool $ValueChanged): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'), true);
        if (empty($smokeDetectors)) {
            return;
        }
        $lastState = $this->GetValue('State');
        $this->UpdateState();
        $actualState = $this->GetValue('State');
        $key = array_search($SenderID, array_column($smokeDetectors, 'ID'));
        if (!is_int($key)) {
            return;
        }
        if (!$smokeDetectors[$key]['Use']) {
            return;
        }
        $triggerValue = $smokeDetectors[$key]['TriggerValue'];
        switch ($triggerValue) {
            case '0':
            case 'false':
                $triggerValue = false;
                break;

            case '1':
            case 'true':
                $triggerValue = true;
                break;

            default:
                $triggerValue = boolval($triggerValue);

        }
        $sensorStateList = "Rauchmelder: \n\n";
        $sensors = json_decode($this->GetBuffer('SensorStateList'));
        if (!empty($sensors)) {
            foreach ($sensors as $sensor) {
                $sensorStateList .= $sensor->unicode . ' ' . $sensor->name . "\n";
            }
        }
        $title = 'Rauchmelder';
        $location = $this->ReadPropertyString('LocationDesignation');
        $timestamp = (string) date('d.m.Y, H:i:s');
        $actualValue = boolval(GetValue($SenderID));
        $name = $smokeDetectors[$key]['Name'];
        // Smoke detected
        if ($ValueChanged && ($actualValue == $triggerValue)) {
            $this->SetValue('AlertingSensor', $name);
            if (!$this->ReadPropertyBoolean('UseNotification')) {
                return;
            }
            if (!$this->ReadPropertyBoolean('UseStateSmokeDetected')) {
                return;
            }
            // WebFront Notification
            $unicode = json_decode('"\ud83d\udd25"'); # flame
            $text = $location . "\n" . $unicode . " Rauch erkannt\n" . $name . "\n" . $timestamp;
            $this->SendWebFrontNotification($title, $text, '');
            // WebFront Push Notification
            $text = "\n" . $location . "\n" . $unicode . " Rauch erkannt\n" . $name . "\n" . $timestamp;
            $this->SendWebFrontPushNotification($title, $text, 'alarm');
            // Mailer
            $subject = 'Rauchmelder ' . $location . ' - ' . $unicode . ' Rauch erkannt, ' . $name;
            $text = "Status:\n\n" . $timestamp . ', Rauchmelder ' . $location . ' - ' . $unicode . ' Rauch erkannt, ' . $name . "\n\n";
            $text .= $sensorStateList;
            $this->SendMailNotification($subject, $text);
            // NeXXt Mobile SMS
            $text = $title . "\n" . $location . "\n" . "Rauch erkannt\n" . $name . "\n" . $timestamp;
            $this->SendNeXXtMobileSMS($text);
            // Sipgate SMS
            $text = $title . "\n" . $location . "\n" . $unicode . " Rauch erkannt\n" . $name . "\n" . $timestamp;
            $this->SendSipgateSMS($text);
            // Telegram Message
            $text = $title . "\n" . $location . "\n" . $unicode . " Rauch erkannt\n" . $name . "\n" . $timestamp;
            $this->SendTelegramMessage($text);
        }
        // No smoke detected
        if ($ValueChanged && ($actualValue != $triggerValue)) {
            // All smoke detectors are ok
            if (($actualState != $lastState) && $actualState == false) {
                if (!$this->ReadPropertyBoolean('UseNotification')) {
                    return;
                }
                if (!$this->ReadPropertyBoolean('UseStateOK')) {
                    return;
                }
                // WebFront Notification
                $unicode = json_decode('"\u2705"'); # white_check_mark
                $text = $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendWebFrontNotification($title, $text, '');
                // WebFront Push Notification
                $text = "\n" . $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendWebFrontPushNotification($title, $text, 'alarm');
                // Mailer
                $subject = 'Rauchmelder ' . $location . ' - ' . $unicode . ' OK';
                $text = "Status:\n\n" . $timestamp . ', Rauchmelder ' . $location . ' - ' . $unicode . " OK \n\n";
                $text .= $sensorStateList;
                $this->SendMailNotification($subject, $text);
                // NeXXt Mobile SMS
                $text = $title . "\n" . $location . "\nOK\n" . $timestamp;
                $this->SendNeXXtMobileSMS($text);
                // Sipgate SMS
                $text = $title . "\n" . $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendSipgateSMS($text);
                // Telegram Message
                $text = $title . "\n" . $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendTelegramMessage($text);
            }
        }
    }
}