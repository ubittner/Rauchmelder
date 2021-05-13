<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rauchmelder/tree/main/Rauchmelder
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Rauchmelder extends IPSModule
{
    //Helper
    use RM_backupRestore;
    use RM_notification;
    use RM_smokeDetector;

    // Constants
    private const WEBFRONT_MODULE_GUID = '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}';
    private const MAILER_MODULE_GUID = '{E43C3C36-8402-6B6D-2699-D870FBC216EF}';
    private const NEXXTMOBILE_SMS_MODULE_GUID = '{7E6DBE40-4438-ABB7-7EE0-93BC4F1AF0CE}';
    private const SIPGATE_SMS_MODULE_GUID = '{965ABB3F-B4EE-7F9F-1E5E-ED386219EF7C}';
    private const TELEGRAM_BOT_MODULE_GUID = '{32464EBD-4CCC-6174-4031-5AA374F7CD8D}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        // Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableLocationDesignation', true);
        $this->RegisterPropertyBoolean('EnableSensorList', true);
        $this->RegisterPropertyBoolean('EnableState', true);
        $this->RegisterPropertyBoolean('EnableAlertingSensor', true);
        // Description
        $this->RegisterPropertyString('LocationDesignation', '');
        // Smoke detectors
        $this->RegisterPropertyString('SmokeDetectors', '[]');
        // Notification
        $this->RegisterPropertyBoolean('UseNotification', true);
        $this->RegisterPropertyBoolean('UseStateSmokeDetected', true);
        $this->RegisterPropertyBoolean('UseStateOK', true);
        $this->RegisterPropertyBoolean('UseDailyNotification', false);
        $this->RegisterPropertyString('DailyNotificationTime', '{"hour":7,"minute":0,"second":0}');
        // WebFront Notification
        $this->RegisterPropertyInteger('WebFrontNotification', 0);
        $this->RegisterPropertyInteger('DisplayDuration', 0);
        // WebFront Push Notification
        $this->RegisterPropertyInteger('WebFrontPushNotification', 0);
        // Mailer
        $this->RegisterPropertyInteger('Mailer', 0);
        // NeXXt Mobile SMS
        $this->RegisterPropertyInteger('NeXXtMobile', 0);
        // Sipgate SMS
        $this->RegisterPropertyInteger('Sipgate', 0);
        // Telegram Instant Messaging
        $this->RegisterPropertyInteger('Telegram', 0);

        // Variables
        // Location
        $id = @$this->GetIDForIdent('Location');
        $this->RegisterVariableString('Location', 'Standort', '', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('Location'), 'IPS');
        }
        // Sensor list
        $id = @$this->GetIDForIdent('SensorList');
        $this->RegisterVariableString('SensorList', 'Rauchmelder', 'HTMLBox', 20);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('SensorList'), 'Flame');
        }
        // State
        $profile = 'RM.' . $this->InstanceID . '.State';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Ok', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Rauch erkannt', 'Warning', 0xFF0000);
        $this->RegisterVariableBoolean('State', 'Status', $profile, 30);
        // Alerting sensor
        $id = @$this->GetIDForIdent('AlertingSensor');
        $this->RegisterVariableString('AlertingSensor', 'Auslösender Melder', '', 40);
        $this->SetValue('AlertingSensor', '');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlertingSensor'), 'Warning');
        }

        // Timer
        $this->RegisterTimer('DailyNotification', 0, 'RM_SendDailyNotification(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Options
        IPS_SetHidden($this->GetIDForIdent('Location'), !$this->ReadPropertyBoolean('EnableLocationDesignation'));
        IPS_SetHidden($this->GetIDForIdent('SensorList'), !$this->ReadPropertyBoolean('EnableSensorList'));
        IPS_SetHidden($this->GetIDForIdent('State'), !$this->ReadPropertyBoolean('EnableState'));
        IPS_SetHidden($this->GetIDForIdent('AlertingSensor'), !$this->ReadPropertyBoolean('EnableAlertingSensor'));
        $this->SetValue('Location', $this->ReadPropertyString('LocationDesignation'));

        // Validation
        if (!$this->ValidateConfiguration()) {
            $this->SetTimerInterval('DailyNotification', 0);
            return;
        }

        $this->RegisterMessages();
        $this->UpdateState();
        $this->SetDailyNotificationTimer();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete Profiles
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

                // Check trigger variable
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                $scriptText = 'RM_CheckTriggerVariable(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Smoke detectors
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($smokeDetectors)) {
            foreach ($smokeDetectors as $smokeDetector) {
                $id = $smokeDetector->ID;
                $rowColor = ''; # no color
                if (!$smokeDetector->Use) {
                    $rowColor = '#DFDFDF'; # grey
                }
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $status = '';
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $rowColor = '#C0FFC0'; # light green
                    $status = 'OK';
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
                        $rowColor = '#FFC0C0'; # red
                        $status = 'Rauch erkannt';
                    }
                }
                $formData['elements'][2]['items'][0]['values'][] = [
                    'Use'          => $smokeDetector->Use,
                    'ActualStatus' => $status,
                    'Name'         => $smokeDetector->Name,
                    'ID'           => $smokeDetector->ID,
                    'TriggerValue' => $smokeDetector->TriggerValue,
                    'rowColor'     => $rowColor];
            }
        }
        // WebFront Notification
        $id = $this->ReadPropertyInteger('WebFrontNotification');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][7] = [
            'type'  => 'RowLayout',
            'items' => [$formData['elements'][3]['items'][7]['items'][0] = [
                'type'     => 'SelectModule',
                'name'     => 'WebFrontNotification',
                'caption'  => 'WebFront (Notification)',
                'moduleID' => self::WEBFRONT_MODULE_GUID,
            ],
                $formData['elements'][3]['items'][7]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][7]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
                $formData['elements'][3]['items'][7]['items'][3] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][7]['items'][4] = [
                    'type'     => 'NumberSpinner',
                    'caption'  => 'Anzeigedauer',
                    'name'     => 'DisplayDuration',
                    'add'      => 0,
                    'suffix'   => 'Sekunden',
                    'minimum'  => 0,
                    'visible'  => $visibility,
                    'objectID' => $id
                ]
            ]
        ];
        // WebFront Push Notification
        $id = $this->ReadPropertyInteger('WebFrontPushNotification');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][8] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][8]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'WebFrontPushNotification',
                    'caption'  => 'WebFront (Push Notification)',
                    'moduleID' => self::WEBFRONT_MODULE_GUID,
                ],
                $formData['elements'][3]['items'][8]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][8]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ]
            ]
        ];
        // Mailer
        $id = $this->ReadPropertyInteger('Mailer');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][9] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][9]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'Mailer',
                    'caption'  => 'Mailer (E-Mail)',
                    'moduleID' => '{E43C3C36-8402-6B6D-2699-D870FBC216EF}',
                ],
                $formData['elements'][3]['items'][9]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][9]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
            ]
        ];
        // NeXXt Mobile SMS
        $id = $this->ReadPropertyInteger('NeXXtMobile');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][10] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][10]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'NeXXtMobile',
                    'caption'  => 'NeXXt Mobile (SMS)',
                    'moduleID' => self::NEXXTMOBILE_SMS_MODULE_GUID,
                ],
                $formData['elements'][3]['items'][10]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][10]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
            ]
        ];
        // Sipgate SMS
        $id = $this->ReadPropertyInteger('Sipgate');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][11] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][11]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'Sipgate',
                    'caption'  => 'Sipgate (SMS)',
                    'moduleID' => self::SIPGATE_SMS_MODULE_GUID,
                ],
                $formData['elements'][3]['items'][11]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][11]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
            ]
        ];
        // Telegram Instant Messaging
        $id = $this->ReadPropertyInteger('Telegram');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][12] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][12]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'Telegram',
                    'caption'  => 'Telegram (Instant Messaging)',
                    'moduleID' => self::TELEGRAM_BOT_MODULE_GUID,
                ],
                $formData['elements'][3]['items'][12]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][12]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'Konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
            ]
        ];
        // Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht!';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '#C0FFC0'; # light green
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
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription,
                'rowColor'           => $rowColor];
        }
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function EnableTriggerVariableConfigurationButton(int $ObjectID): void
    {
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'caption', 'Variable ' . $ObjectID . ' Bearbeiten');
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'visible', true);
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'enabled', true);
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'objectID', $ObjectID);
    }

    public function ShowVariableDetails(int $VariableID): void
    {
        if ($VariableID == 0 || !@IPS_ObjectExists($VariableID)) {
            return;
        }
        if ($VariableID != 0) {
            // Variable
            echo 'ID: ' . $VariableID . "\n";
            echo 'Name: ' . IPS_GetName($VariableID) . "\n";
            $variable = IPS_GetVariable($VariableID);
            if (!empty($variable)) {
                $variableType = $variable['VariableType'];
                switch ($variableType) {
                    case 0:
                        $variableTypeName = 'Boolean';
                        break;

                    case 1:
                        $variableTypeName = 'Integer';
                        break;

                    case 2:
                        $variableTypeName = 'Float';
                        break;

                    case 3:
                        $variableTypeName = 'String';
                        break;

                    default:
                        $variableTypeName = 'Unbekannt';
                }
                echo 'Variablentyp: ' . $variableTypeName . "\n";
            }
            // Profile
            $profile = @IPS_GetVariableProfile($variable['VariableProfile']);
            if (empty($profile)) {
                $profile = @IPS_GetVariableProfile($variable['VariableCustomProfile']);
            }
            if (!empty($profile)) {
                $profileType = $variable['VariableType'];
                switch ($profileType) {
                    case 0:
                        $profileTypeName = 'Boolean';
                        break;

                    case 1:
                        $profileTypeName = 'Integer';
                        break;

                    case 2:
                        $profileTypeName = 'Float';
                        break;

                    case 3:
                        $profileTypeName = 'String';
                        break;

                    default:
                        $profileTypeName = 'Unbekannt';
                }
                echo 'Profilname: ' . $profile['ProfileName'] . "\n";
                echo 'Profiltyp: ' . $profileTypeName . "\n\n";
            }
            if (!empty($variable)) {
                echo "\nVariable:\n";
                print_r($variable);
            }
            if (!empty($profile)) {
                echo "\nVariablenprofil:\n";
                print_r($profile);
            }
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterMessages(): void
    {
        // Unregister
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
        // Register
        $smokeDetectors = json_decode($this->ReadPropertyString('SmokeDetectors'));
        if (!empty($smokeDetectors)) {
            foreach ($smokeDetectors as $smokeDetector) {
                if ($smokeDetector->Use) {
                    if ($smokeDetector->ID != 0 && @IPS_ObjectExists($smokeDetector->ID)) {
                        $this->RegisterMessage($smokeDetector->ID, VM_UPDATE);
                    }
                }
            }
        }
    }

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Maintenance mode
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