<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rauchmelder
 */

declare(strict_types=1);

include_once __DIR__ . '/helper/RM_autoload.php';

class Rauchmelder extends IPSModule
{
    //Helper
    use RM_backupRestore;
    use RM_notification;
    use RM_smokeDetector;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterTimer('DailyNotification', 0, 'RM_CheckActualState(' . $this->InstanceID . ', true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetValue('Location', $this->ReadPropertyString('LocationDesignation'));
        if (!$this->ValidateConfiguration()) {
            $this->SetTimerInterval('DailyNotification', 0);
            return;
        }
        $this->SetOptions();
        $this->RegisterMessages();
        $this->SetDailyNotificationTimer();
        $this->CheckActualState(false);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value
                if ($this->CheckMaintenanceMode()) {
                    return;
                }
                $scriptText = 'RM_CheckActualState(' . $this->InstanceID . ', false);';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Smoke detectors
        $vars = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                $id = $var->ID;
                $rowColor = ''; # no color
                if (!$var->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][2]['items'][0]['values'][] = [
                    'Use'                   => $var->Use,
                    'Name'                  => $var->Name,
                    'ID'                    => $var->ID,
                    'Trigger'               => $var->Trigger,
                    'Value'                 => $var->Value,
                    'rowColor'              => $rowColor];
            }
        }
        $webFronts = json_decode($this->ReadPropertyString('WebFrontNotification'));
        if (!empty($webFronts)) {
            foreach ($webFronts as $webFront) {
                $id = $webFront->ID;
                $rowColor = ''; # no color
                if (!$webFront->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][3]['items'][9]['values'][] = [
                    'Use'       => $webFront->Use,
                    'ID'        => $webFront->ID,
                    'Name'      => IPS_GetName($webFront->ID),
                    'rowColor'  => $rowColor];
            }
        }
        $webFronts = json_decode($this->ReadPropertyString('MobileDeviceNotification'));
        if (!empty($webFronts)) {
            foreach ($webFronts as $webFront) {
                $id = $webFront->ID;
                $rowColor = ''; # no color
                if (!$webFront->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][3]['items'][10]['values'][] = [
                    'Use'       => $webFront->Use,
                    'ID'        => $webFront->ID,
                    'Name'      => IPS_GetName($webFront->ID),
                    'rowColor'  => $rowColor];
            }
        }
        $recipients = json_decode($this->ReadPropertyString('MailNotification'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                $id = $recipient->ID;
                $rowColor = ''; # no color
                if (!$recipient->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][3]['items'][11]['values'][] = [
                    'Use'       => $recipient->Use,
                    'ID'        => $recipient->ID,
                    'Name'      => IPS_GetName($recipient->ID),
                    'Recipient' => $recipient->Recipient,
                    'Address'   => $recipient->Address,
                    'rowColor'  => $rowColor];
            }
        }
        //Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht!';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = ''; # no color
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData['actions'][1]['items'][0]['values'][] = [
                'SenderID'              => $senderID,
                'SenderName'            => $senderName,
                'MessageID'             => $messageID,
                'MessageDescription'    => $messageDescription,
                'rowColor'              => $rowColor];
        }
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        //Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableSensorList', true);
        $this->RegisterPropertyBoolean('EnableState', true);
        $this->RegisterPropertyBoolean('EnableAlertingSensor', true);
        //Description
        $this->RegisterPropertyString('LocationDesignation', '');
        //Smoke detectors
        $this->RegisterPropertyString('SmokeDetectors', '[]');
        //Notification
        $this->RegisterPropertyBoolean('UseStateChangedNotification', true);
        $this->RegisterPropertyBoolean('UseStateChangedOK', true);
        $this->RegisterPropertyBoolean('UseStateChangedSmokeDetected', true);
        $this->RegisterPropertyBoolean('UseDailyNotification', true);
        $this->RegisterPropertyBoolean('UseDailyNotificationOK', false);
        $this->RegisterPropertyBoolean('UseDailyNotificationSmokeDetected', true);
        $this->RegisterPropertyString('DailyNotificationTime', '{"hour":7,"minute":0,"second":0}');
        $this->RegisterPropertyString('WebFrontNotification', '[]');
        $this->RegisterPropertyString('MobileDeviceNotification', '[]');
        $this->RegisterPropertyString('MailNotification', '[]');
    }

    private function CreateProfiles(): void
    {
        //State
        $profile = 'RM.' . $this->InstanceID . '.State';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Ok', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Rauch erkannt', 'Warning', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['State'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'RM.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        $id = @$this->GetIDForIdent('Location');
        $this->RegisterVariableString('Location', 'Standort', '', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('Location'), 'IPS');
        }
        //Sensor list
        $id = @$this->GetIDForIdent('SensorList');
        $this->RegisterVariableString('SensorList', 'Rauchmelder', 'HTMLBox', 20);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('SensorList'), 'Flame');
        }
        //State
        $profile = 'RM.' . $this->InstanceID . '.State';
        $this->RegisterVariableBoolean('State', 'Status', $profile, 30);
        //Alerting sensor
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Melder', '', 40);
        $this->SetValue('AlertingSensor', '');
        IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Ok');
    }

    private function SetOptions(): void
    {
        IPS_SetHidden($this->GetIDForIdent('SensorList'), !$this->ReadPropertyBoolean('EnableSensorList'));
        IPS_SetHidden($this->GetIDForIdent('State'), !$this->ReadPropertyBoolean('EnableState'));
        IPS_SetHidden($this->GetIDForIdent('AlertingSensor'), !$this->ReadPropertyBoolean('EnableAlertingSensor'));
    }

    private function SetDailyNotificationTimer(): void
    {
        if (!$this->ReadPropertyBoolean('UseDailyNotification')) {
            $milliseconds = 0;
        } else {
            $now = time();
            $time = json_decode($this->ReadPropertyString('DailyNotificationTime'));
            $hour = $time->hour;
            $minute = $time->minute;
            $second = $time->second;
            $definedTime = $hour . ':' . $minute . ':' . $second;
            if ($now > strtotime($definedTime)) {
                $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
            } else {
                $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
            }
            $milliseconds = ($timestamp - $now) * 1000;
        }
        $this->SetTimerInterval('DailyNotification', $milliseconds);
    }

    private function RegisterMessages(): void
    {
        //Unregister
        $messages = $this->GetMessageList();
        if (!empty($messages)) {
            foreach ($messages as $id => $message) {
                foreach ($message as $messageType) {
                    if ($messageType == VM_UPDATE) {
                        $this->UnregisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
        //Register
        $vars = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                if ($var->Use) {
                    if ($var->ID != 0 && @IPS_ObjectExists($var->ID)) {
                        $this->RegisterMessage($var->ID, VM_UPDATE);
                    }
                }
            }
        }
    }

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        if (empty($this->ReadPropertyString('LocationDesignation'))) {
            $result = false;
            $status = 200;
            $this->SendDebug(__FUNCTION__, 'Abbruch, bitte geben Sie eine Standortbezeichnung an!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, bitte geben Sie eine Standortbezeichnung an!', KL_WARNING);
        }
        //Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $result = false;
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        return $result;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}