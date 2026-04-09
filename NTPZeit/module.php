<?php
/**
 * NEUSTART des Moduls: MC_ReloadModule(59139, "KNXSystemZeitgeber");
 * sudo /etc/init.d/symcon start
 * sudo /etc/init.d/symcon stop
 * sudo /etc/init.d/symcon restart
 *
 * ToDo:
 * - 
 * -
*/

declare(strict_types=1);

class NTPZeit extends IPSModuleStrict
{
    private const SECONDS_FROM_1900_TO_1970 = 2208988800;

	/**
	 * Wird beim Erstellen der Instanz aufgerufen.
	 * Registriert Properties, Attribute, Timer und Statusvariablen.
	 * Die NTP-Abfrage erfolgt direkt per UDP-Socket im Modul und nicht über einen UDP-Parent.
	 */
    public function Create(): void
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyString('PrimaryServer', 'pool.ntp.org');
        $this->RegisterPropertyString('SecondaryServer', 'time.google.com');
        $this->RegisterPropertyInteger('NTPPort', 123);
        $this->RegisterPropertyInteger('UpdateInterval', 60); // Minuten
        $this->RegisterPropertyInteger('TimeoutMs', 1500); // Millisekunden
        $this->RegisterPropertyBoolean('UseSystemTimeFallback', true);

        // Attribute
        $this->RegisterAttributeInteger('LastUnixTime', 0);
        $this->RegisterAttributeInteger('LastSyncTimestamp', 0);

        // Timer
        $this->RegisterTimer('UpdateTimer', 0, 'NTPZEIT_QueryTime(' . $this->InstanceID . ');');

        // Variablen
        if ($this->RegisterVariableString('NTPDate', $this->Translate('NTPDate'))) {
            $this->SetValue('NTPDate', '');
        }
        if ($this->RegisterVariableString('NTPTime', $this->Translate('NTPTime'))) {
            $this->SetValue('NTPTime', '');
        }
        if ($this->RegisterVariableString('LastSync', $this->Translate('LastSync'))) {
            $this->SetValue('LastSync', '');
        }
        if ($this->RegisterVariableString('TimeSource', $this->Translate('TimeSource'))) {
            $this->SetValue('TimeSource', '');
        }
    }

	/**
	 * Wird nach Änderungen der Konfiguration oder beim Start aufgerufen.
	 * Setzt den Status abhängig vom Aktiv-Status des Moduls und initialisiert
	 * den Intervall-Timer für die periodische NTP-Abfrage.
	 */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }

        $this->UpdateUpdateTimer();
    }

	/**
	 * Verarbeitet Aktionen aus Variablen mit Aktion.
	 *
	 * @param string $Ident Ident der Variable
	 * @param mixed $Value Übergebener Wert
	 */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        throw new Exception('Invalid Ident');
    }

	/**
	 * Diese Funktion wird nicht verwendet, da die NTP-Abfrage direkt per
	 * Socket im Modul erfolgt und nicht über einen Parent-Datenfluss.
	 *
	 * @param string $JSONString Empfangene Daten im JSON-Format
	 * @return string Immer leer
	 */
    public function ReceiveData(string $JSONString): string
    {
        return '';
    }

	/**
	 * Liefert das Konfigurationsformular der Instanz.
	 * Enthält Einstellungen für NTP-Server, Timer und Fallback.
	 *
	 * @return string JSON-Formularbeschreibung
	 */
    public function GetConfigurationForm(): string
    {
        $elements = [
            [
                'type'    => 'CheckBox',
                'name'    => 'Active',
                'caption' => $this->Translate('Active')
            ],
            [
                'type'    => 'Label',
                'caption' => $this->Translate('AccuracyNotice')
            ],
            [
                'type'    => 'ValidationTextBox',
                'name'    => 'PrimaryServer',
                'caption' => $this->Translate('PrimaryServer'),
                'width'   => '350px'
            ],
            [
                'type'    => 'ValidationTextBox',
                'name'    => 'SecondaryServer',
                'caption' => $this->Translate('SecondaryServer'),
                'width'   => '350px'
            ],
            [
                'type'    => 'NumberSpinner',
                'name'    => 'NTPPort',
                'caption' => $this->Translate('NTPPort')
            ],
            [
                'type'    => 'NumberSpinner',
                'name'    => 'UpdateInterval',
                'caption' => $this->Translate('UpdateInterval')
            ],
            [
                'type'    => 'NumberSpinner',
                'name'    => 'TimeoutMs',
                'caption' => $this->Translate('TimeoutMs')
            ],
            [
                'type'    => 'CheckBox',
                'name'    => 'UseSystemTimeFallback',
                'caption' => $this->Translate('UseSystemTimeFallback')
            ],
            [
                'type'    => 'Label',
                'caption' => $this->Translate('UDP_NOTICE')
            ]
        ];

        $actions = [
            [
                'type'    => 'Button',
                'caption' => $this->Translate('NTPABFRAGE'),
                'onClick' => 'NTPZEIT_QueryTime($id);'
            ],
            [
                'type'    => 'Label',
                'width'   => '50%',
                'caption' => $this->Translate('LICENSE_NOTICE')
            ],
            [
                'type'    => 'Label',
                'width'   => '50%',
                'bold'    => true,
                'caption' => $this->Translate('DONATION_HEADER')
            ],
            [
                'type'    => 'Label',
                'width'   => '50%',
                'caption' => $this->Translate('DONATION_TEXT')
            ],
            [
                'type'  => 'RowLayout',
                'items' => [
                    [
                        'type'    => 'Image',
                        'onClick' => 'echo \'' . $this->Translate('PAYPAL_LINK') . '\';',
                        'image'   => 'data:image/png;base64,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'
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => ' '
                    ],
                    [
                        'type'    => 'Label',
                        'width'   => '70%',
                        'caption' => $this->Translate('DONATION_INFO')
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => ' '
                    ]
                ]
            ],
            [
                'type'    => 'Label',
                'width'   => '50%',
                'caption' => $this->Translate('PAYPAL_LINK')
            ]
        ];

        return json_encode([
            'elements' => $elements,
            'actions'  => $actions
        ]);
    }

	/**
	 * Startet eine direkte NTP-Zeitabfrage.
	 * Versucht zuerst den primären Server, danach den sekundären Server.
	 * Falls beide Server nicht erreichbar sind oder keine gültige Zeit liefern,
	 * wird optional auf die Systemzeit zurückgefallen.
	 *
	 * Bei Erfolg werden die Statusvariablen unterhalb des Moduls aktualisiert.
	 *
	 * @return bool true wenn eine Zeit ermittelt wurde, sonst false
	 */
	public function QueryTime(): bool
	{
		if (!$this->ReadPropertyBoolean('Active')) {
			$this->SendDebug('NTPZeit', 'Abfrage übersprungen (Active = false)', 0);
			return false;
		}

		$result = $this->ResolveBestAvailableTime();
		if ($result['unixTime'] <= 0) {
			$this->SetStatus(IS_INACTIVE);
			return false;
		}

		$this->WriteAttributeInteger('LastUnixTime', $result['unixTime']);
		$this->WriteAttributeInteger('LastSyncTimestamp', time());
		$this->UpdateTimeVariables($result['unixTime'], $result['source']);
		$this->SetStatus(IS_ACTIVE);

		//$this->LogMessage(sprintf($this->Translate('NTP_SUCCESS_LOG'), $result['server'], date('Y-m-d H:i:s', $result['unixTime'])),KL_NOTIFY);
		$this->SendDebug('NTPZeit', 'NTP-Zeit erfolgreich aktualisiert: ' . date('Y-m-d H:i:s', $result['unixTime']), 0);

		return true;
	}

	/**
	 * Gibt den zuletzt synchronisierten Unix-Zeitstempel zurück.
	 * Optionaler Fallback auf Systemzeit wenn aktiviert.
	 *
	 * @return int Unix-Timestamp
	 */
	public function GetUnixTime(): int
	{
		$timestamp = $this->ReadAttributeInteger('LastUnixTime');
		$lastSyncTimestamp = $this->ReadAttributeInteger('LastSyncTimestamp');

		if ($timestamp > 0 && $lastSyncTimestamp > 0) {
			return $timestamp + (time() - $lastSyncTimestamp);
		}

		if ($this->ReadPropertyBoolean('UseSystemTimeFallback')) {
			return time();
		}

		return 0;
	}

	/**
	 * Gibt die formatierte Uhrzeit zurück.
	 *
	 * @return string Uhrzeit im Format HH:MM:SS
	 */
    public function GetFormattedTime(): string
    {
        $timestamp = $this->GetUnixTime();
        if ($timestamp <= 0) {
            return '';
        }

        return date('H:i:s', $timestamp);
    }

	/**
	 * Gibt das formatierte Datum zurück.
	 *
	 * @return string Datum im Format TT.MM.JJJJ
	 */
    public function GetFormattedDate(): string
    {
        $timestamp = $this->GetUnixTime();
        if ($timestamp <= 0) {
            return '';
        }

        return date('d.m.Y', $timestamp);
    }

	/**
	 * Prüft ob eine gültige Zeit verfügbar ist.
	 *
	 * @return bool true wenn gültige Zeit vorhanden
	 */
    public function HasValidTime(): bool
    {
        return $this->GetUnixTime() > 0;
    }

	/**
	 * Führt eine direkte Live-Abfrage der Zeit durch.
	 * Es wird zuerst der primäre NTP-Server abgefragt, bei Fehler der sekundäre
	 * und anschließend optional auf die Systemzeit zurückgefallen.
	 *
	 * Diese Funktion aktualisiert keine Variablen im Objektbaum, sondern liefert
	 * ausschließlich den aktuellen Unix-Timestamp zurück.
	 *
	 * @return int Unix-Timestamp
	 */
	public function GetLiveUnixTime(): int
	{
		$result = $this->ResolveBestAvailableTime();
		return $result['unixTime'];
	}

	/**
	 * Aktualisiert den Intervall-Timer für periodische Abfragen.
	 */
    private function UpdateUpdateTimer(): void
    {
        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        $minutes = $this->ReadPropertyInteger('UpdateInterval');
        if ($minutes <= 0) {
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        $intervalMs = $minutes * 60 * 1000;
        $intervalMs = max(0, min($intervalMs, 2147483647));
        $this->SetTimerInterval('UpdateTimer', $intervalMs);
    }

	/**
	 * Ermittelt die bestmögliche aktuelle Zeit.
	 * Es wird zuerst der primäre NTP-Server abgefragt, danach der sekundäre Server.
	 * Wenn beide Server keine gültige Zeit liefern, wird optional die Systemzeit verwendet.
	 *
	 * @return array{unixTime:int,source:string,server:string}
	 */
	private function ResolveBestAvailableTime(): array
	{
		$primaryServer = trim($this->ReadPropertyString('PrimaryServer'));
		$secondaryServer = trim($this->ReadPropertyString('SecondaryServer'));
		$port = $this->ReadPropertyInteger('NTPPort');
		$timeoutMs = $this->ReadPropertyInteger('TimeoutMs');

		if ($port <= 0 || $port > 65535) {
			$port = 123;
		}

		if ($timeoutMs <= 0) {
			$timeoutMs = 1500;
		}

		$unixTime = $this->QueryNTPServerDirect($primaryServer, $port, $timeoutMs);
		if ($unixTime > 0) {
			$this->SendDebug(
				'NTPZeit',
				'Live-/Direktabfrage OK (Primary): ' . date('Y-m-d H:i:s', $unixTime) . ' [' . $unixTime . ']',
				0
			);
			return [
				'unixTime' => $unixTime,
				'source'   => $this->Translate('PrimaryServerLabel'),
				'server'   => $primaryServer
			];
		}

		$unixTime = $this->QueryNTPServerDirect($secondaryServer, $port, $timeoutMs);
		if ($unixTime > 0) {
			$this->SendDebug(
				'NTPZeit',
				'Live-/Direktabfrage OK (Secondary): ' . date('Y-m-d H:i:s', $unixTime) . ' [' . $unixTime . ']',
				0
			);
			return [
				'unixTime' => $unixTime,
				'source'   => $this->Translate('SecondaryServerLabel'),
				'server'   => $secondaryServer
			];
		}

		if ($this->ReadPropertyBoolean('UseSystemTimeFallback')) {
			$unixTime = $this->UseSystemTimeFallback();
			$this->LogMessage($this->Translate('NTP_ALL_FAILED_FALLBACK_LOG'), KL_ERROR);
			return [
				'unixTime' => $unixTime,
				'source'   => $this->Translate('SystemFallbackSource'),
				'server'   => $this->Translate('SystemFallbackSource')
			];
		}

		$this->LogMessage($this->Translate('NTP_ALL_FAILED_NO_FALLBACK_LOG'), KL_ERROR);
		$this->SendDebug('NTPZeit', 'Direktabfrage fehlgeschlagen, kein Fallback aktiv', 0);

		return [
			'unixTime' => 0,
			'source'   => '',
			'server'   => ''
		];
	}

	/**
	 * Verwendet die lokale Systemzeit als Fallback.
	 *
	 * @return int Unix-Timestamp der Systemzeit
	 */
    private function UseSystemTimeFallback(): int
    {
        $unixTime = time();

        $this->SendDebug('NTPZeit', 'Fallback auf Systemzeit: ' . date('Y-m-d H:i:s', $unixTime), 0);
        return $unixTime;
    }

	/**
	 * Aktualisiert die Statusvariablen mit Datum und Uhrzeit.
	 *
	 * @param int $unixTime Unix-Timestamp
	 * @param string $source Quelle der Zeit
	 */
    private function UpdateTimeVariables(int $unixTime, string $source): void
    {
        $this->SetValue('NTPDate', date('d.m.Y', $unixTime));
        $this->SetValue('NTPTime', date('H:i:s', $unixTime));
        $this->SetValue('LastSync', date('d.m.Y H:i:s'));
        $this->SetValue('TimeSource', $source);
    }

	/**
	 * Löst Hostnamen in eine IP-Adresse auf.
	 *
	 * @param string $server Hostname oder IP
	 * @return string|null IP-Adresse oder null bei Fehler
	 */
    private function ResolveServerAddress(string $server): ?string
    {
        if (filter_var($server, FILTER_VALIDATE_IP) !== false) {
            return $server;
        }

        $resolved = gethostbyname($server);
        if ($resolved === $server && filter_var($resolved, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $resolved;
    }

	/**
	 * Fragt einen NTP-Server direkt per UDP ab.
	 *
	 * @param string $server Hostname oder IP
	 * @param int $port Port des NTP-Servers
	 * @param int $timeoutMs Timeout in Millisekunden
	 * @return int Unix-Timestamp oder 0 bei Fehler
	 */
	private function QueryNTPServerDirect(string $server, int $port, int $timeoutMs): int
	{
		if ($server === '') {
			return 0;
		}

		$resolvedIp = $this->ResolveServerAddress($server);
		if ($resolvedIp === null) {
			$this->SendDebug('NTPZeit', 'Direktabfrage: DNS-Auflösung fehlgeschlagen: ' . $server, 0);
			return 0;
		}

		$timeoutSeconds = $timeoutMs / 1000;
		$errno = 0;
		$errstr = '';

		$socket = @fsockopen('udp://' . $resolvedIp, $port, $errno, $errstr, $timeoutSeconds);
		if ($socket === false) {
			$this->SendDebug('NTPZeit', 'Direktabfrage: Socket konnte nicht geöffnet werden: ' . $server . ' / ' . $errstr, 0);
			return 0;
		}

		stream_set_timeout(
			$socket,
			(int) floor($timeoutSeconds),
			(int) (($timeoutSeconds - floor($timeoutSeconds)) * 1000000)
		);

		$packet = chr(0x1B) . str_repeat(chr(0x00), 47);

		$written = @fwrite($socket, $packet);
		if ($written === false || $written !== 48) {
			fclose($socket);
			$this->SendDebug('NTPZeit', 'Direktabfrage: Paket konnte nicht vollständig gesendet werden: ' . $server, 0);
			return 0;
		}

		$response = @fread($socket, 48);
		$meta = stream_get_meta_data($socket);
		fclose($socket);

		if (!is_string($response) || strlen($response) < 48) {
			$this->SendDebug('NTPZeit', 'Direktabfrage: Antwort zu kurz oder leer von: ' . $server, 0);
			return 0;
		}

		if (!empty($meta['timed_out'])) {
			$this->SendDebug('NTPZeit', 'Direktabfrage: Timeout von: ' . $server, 0);
			return 0;
		}

		$unpacked = unpack('N12', substr($response, 0, 48));
		if (!is_array($unpacked) || !isset($unpacked[11])) {
			$this->SendDebug('NTPZeit', 'Direktabfrage: Antwort konnte nicht ausgewertet werden: ' . $server, 0);
			return 0;
		}

		$ntpSeconds = (int) $unpacked[11];
		$unixTime = $ntpSeconds - self::SECONDS_FROM_1900_TO_1970;

		if ($unixTime <= 0) {
			$this->SendDebug('NTPZeit', 'Direktabfrage: Ungültiger Zeitstempel von: ' . $server, 0);
			return 0;
		}

		return $unixTime;
	}
}
