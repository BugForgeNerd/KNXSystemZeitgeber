<?php
/**
 * NEUSTART des Moduls: MC_ReloadModule(59139, "KNXSystemZeitgeber");
 * sudo /etc/init.d/symcon start
 * sudo /etc/init.d/symcon stop
 * sudo /etc/init.d/symcon restart
 *
 * ToDo:
 * - Boolean zurückgeben, wenn KSZT_SendKNXTimeAndDate(30864); über Script ausgelöst False wenn exception
 * - KNX Gateway auf Vorhandensein überprüfen
*/



/**
 * KNXSystemZeitgeber
 * 
 * Symcon-Modul zur automatischen Übertragung von Zeit- und Datumswerten an KNX-Gruppenadressen.
 * Unterstützt eine Liste von Sendezeiten, die zyklisch abgearbeitet werden.
 * 
 * Eigenschaften:
 * - SendTimes (string): JSON-codierte Liste der Zeiten (Stunde, Minute, optional Sekunde)
 * - GA_Time (string): Gruppenadresse für die Zeit (DPT 10.001)
 * - GA_Date (string): Gruppenadresse für das Datum (DPT 11.001)
 * - Active (bool): Modul aktiv/inaktiv (Timer & Senden)
 * 
 * Timer:
 * - SendKNXTimeTimer: steuert den zyklischen Versand der Zeit- und Datumswerte
 * 
 * Funktionen:
 * - Create(): Initialisiert die Instanz, registriert Properties und Timer, verbindet KNX-Parent
 * - ApplyChanges(): Wird nach Änderungen aufgerufen, aktualisiert den nächsten Timer
 * - RequestAction($Ident, $Value): Platzhalter für Aktions-Handler
 * - SendKNXTimeAndDate(): Sendet aktuelle Zeit und Datum an KNX und setzt nächsten Timer
 * - EncodeDPT10_Time($hour, $minute, $second): Kodiert Zeit in DPT 10.001 Format
 * - EncodeDPT11_Date($day, $month, $year): Kodiert Datum in DPT 11.001 Format
 * - SetNextTimerExecution(): Berechnet und setzt den nächsten Timerintervall
 * - ReceiveData($JSONString): Nicht benötigt, Platzhalter
 * - GetConfigurationForm(): Liefert das JSON-Konfigurationsformular für die Instanz
 */
declare(strict_types=1);
class KNXSystemZeitgeber extends IPSModule
{
	
	/**
	 * Create
	 * 
	 * Initialisiert das Modul:
	 * - Registriert die Eigenschaften (SendTimes, GA_Time, GA_Date, Active)
	 * - Legt den Timer für das Senden von Zeit/Datum initial an
	 * - Verbindet das Modul mit dem KNX Gateway Interface
	 * 
	 * Parameter: keine
	 * Rückgabewert: void
	 */
    public function Create()
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyString('SendTimes', '[]');
        $this->RegisterPropertyString('GA_Time', '');
        $this->RegisterPropertyString('GA_Date', '');
		$this->RegisterPropertyBoolean('Active', true); // Timer/Sendungen aktiv oder nicht

        // Timer initial registrieren
		$this->RegisterTimer("SendKNXTimeTimer", 0, 'KSZT_SendKNXTimeAndDate(' . $this->InstanceID . ');');

        // KNX Parent verbinden (GUID des KNX Gateway Interface)
        $this->ConnectParent("{1C902193-B044-43B8-9433-419F09C641B8}");
    }


	/**
	 * ApplyChanges
	 * 
	 * Wird aufgerufen, wenn die Modulkonfiguration geändert wurde.
	 * - Ruft die übergeordnete ApplyChanges-Methode auf
	 * - Setzt den Timer für die nächste geplante Sendezeit neu
	 * 
	 * Parameter: keine
	 * Rückgabewert: void
	 */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetNextTimerExecution();
    }


	/**
	 * RequestAction
	 * 
	 * Wird aufgerufen, wenn eine Aktion auf eine Variable des Moduls ausgeführt wird.
	 * Aktuell nicht implementiert.
	 * 
	 * Parameter:
	 *  - string $Ident : Der Ident der Variable, auf die die Aktion ausgeführt wird
	 *  - mixed  $Value : Der Wert, der gesetzt werden soll
	 * 
	 * Rückgabewert: void
	 */
    public function RequestAction($Ident, $Value)
    {

    }


	/**
	 * SendKNXTimeAndDate
	 * 
	 * Sendet die aktuelle Uhrzeit und das aktuelle Datum an die in den Eigenschaften
	 * definierten KNX-Gruppenadressen. Die Funktion prüft, ob das Modul aktiv ist
	 * (Eigenschaft 'Active') und sendet nur dann. Anschließend wird der Timer für
	 * die nächste Sendezeit neu gesetzt.
	 * 
	 * Parameter: keine
	 * 
	 * Rückgabewert: void
	 */
    public function SendKNXTimeAndDate()
    {
		if (!$this->ReadPropertyBoolean('Active')) {
			$this->SendDebug("KNXsystime", "Senden übersprungen (Active = false)", 0);
			return;
		}
	
        $gaTime = $this->ReadPropertyString('GA_Time');
        $gaDate = $this->ReadPropertyString('GA_Date');
		
        // --- Zeit senden ---
        if (!empty($gaTime)) {
            $parts = explode('/', $gaTime);
            if (count($parts) === 3) {
				$date = new DateTimeImmutable();
                $hours = (int)$date->format('H');
                $minutes = (int)$date->format('i');
                $seconds = (int)$date->format('s');

				// chr(0x80) ist der Write-Befehl
				$knx_time_payload = chr(0x80) . $this->EncodeDPT10_Time($hours, $minutes, $seconds);
				//IPS_LogMessage("KNXsystime", "HexWert Time: " . bin2hex($knx_time_payload));
				$this->SendDebug("KNXsystime", "HexWert Time: " . bin2hex($knx_time_payload), 0);
				
				$json = json_encode(
					Array(
						"DataID" => "{42DFD4E4-5831-4A27-91B9-6FF1B2960260}", 
						"GroupAddress1" => (int)$parts[0],
						"GroupAddress2" => (int)$parts[1],
						"GroupAddress3" => (int)$parts[2],
						"Data" => utf8_encode($knx_time_payload)
					)
				);	
				$result = $this->SendDataToParent($json);
				IPS_LogMessage("KNXsystime", "Zeit auf den Bus gesetzt: " . $date->format('H:i:s'));
            }
        }

        // --- Datum senden ---
        if (!empty($gaDate)) {
            $parts = explode('/', $gaDate);
            if (count($parts) === 3) {
				$date = new DateTimeImmutable(); // aktuelles Datum/Uhrzeit
                $day   = (int)$date->format('d'); // 11
                $month = (int)$date->format('m'); // 12
                $year  = (int)$date->format('Y'); // 2025 (4-stellig für die Funktion)

				// chr(0x80) ist der Write-Befehl
				$knx_date_payload = chr(0x80) . $this->EncodeDPT11_Date($day, $month, $year);
				//IPS_LogMessage("KNXsystime", "HexWert Date: " . bin2hex($knx_date_payload));
				$this->SendDebug("KNXsystime", "HexWert  Dat: " . bin2hex($knx_date_payload), 0);
				
				 $json = json_encode(
					Array(
						"DataID" => "{42DFD4E4-5831-4A27-91B9-6FF1B2960260}", 
						"GroupAddress1" => (int)$parts[0],
						"GroupAddress2" => (int)$parts[1],
						"GroupAddress3" => (int)$parts[2],
						"Data" => utf8_encode($knx_date_payload)
					)
				);	
				$result = $this->SendDataToParent($json);
				IPS_LogMessage("KNXsystime", "Datum auf den Bus gesetzt: " . $date->format('d.m.Y'));
            }
        }

        // Nächsten Timer setzen
        $this->SetNextTimerExecution();
    }


	/**
	 * EncodeDPT11_Date
	 * 
	 * Kodiert ein Datum (Tag, Monat, Jahr) in das KNX DPT 11.001 Format (3 Bytes).
	 * Die Kodierung reduziert das Jahr auf 2-stellig und stellt sicher, dass Tag und
	 * Monat innerhalb der gültigen KNX-Bereiche liegen.
	 * 
	 * Parameter:
	 *  - int $day    : Tag (1-31)
	 *  - int $month  : Monat (1-12)
	 *  - int $year   : Jahr (4-stellig, z.B. 2025)
	 * 
	 * Rückgabewert:
	 *  - string : 3-Byte Binärstring passend für KNX DPT 11.001
	 */
    protected function EncodeDPT11_Date(int $day, int $month, int $year) : string
    {
        // Sicherstellen, dass die Werte innerhalb der KNX-Bereiche liegen
        $day   = max(1, min(31, $day));
        $month = max(1, min(12, $month));
        
        // Jahr auf 2-stellig konvertieren (YY), basierend auf der 90er Logik der Decode-Funktion
        if ($year >= 2000) {
            $year = $year - 2000; // z.B. 25
        } elseif ($year >= 1900) {
            $year = $year - 1900;
        } else {
            // Bei sehr alten Jahren einfach die letzten beiden Ziffern nehmen
            $year = $year % 100;
        }

        // Byte 0: RRRDDDDD
        // Wir nehmen die 5 Bits des Tages und lassen die oberen 3 Bits (RRR) auf 0.
        $byte0 = $day & 0x1F; 

        // Byte 1: RRRRMMMM
        // Wir nehmen die 4 Bits des Monats und lassen die oberen 4 Bits (RRRR) auf 0.
        $byte1 = $month & 0x0F;

        // Byte 2: RYYYYYYY
        // Wir nehmen die 7 Bits des Jahres (YY) und lassen das oberste Bit (R) auf 0.
        $byte2 = $year & 0x7F;

        // Packen in einen 3-Byte Binärstring
        return pack('C3', $byte0, $byte1, $byte2);
    }


	/**
	 * EncodeDPT10_Time
	 * 
	 * Kodiert eine Uhrzeit (Stunde, Minute, Sekunde) in das KNX DPT 10.001 Format (3 Bytes).
	 * Dabei werden die Werte auf die zulässigen KNX-Bereiche begrenzt und in die entsprechenden
	 * Bitfelder des DPT 10.001 Standards gesetzt.
	 * 
	 * Parameter:
	 *  - int $hour    : Stunde (0-23)
	 *  - int $minute  : Minute (0-59)
	 *  - int $second  : Sekunde (0-59)
	 * 
	 * Rückgabewert:
	 *  - string : 3-Byte Binärstring passend für KNX DPT 10.001
	 */
	protected function EncodeDPT10_Time(int $hour, int $minute, int $second) : string
	{
		// Werte innerhalb der KNX-Bereiche einschränken
		$hour   = max(0, min(23, $hour));
		$minute = max(0, min(59, $minute));
		$second = max(0, min(59, $second));

		// Die KNX-Spezifikation verwendet spezifische Bitmasken und Reservierungs-Bits.

		// Byte 0: RRRHHHHH (3 Bits Reserved/Error, 5 Bits Hour)
		// Die Stundenwerte (0-23) passen in die unteren 5 Bits (0x1F).
		// Standardmäßig sind die oberen 3 Bits 0 (kein Fehler).
		$byte0 = $hour & 0x1F;

		// Byte 1: EEMMMMMM (2 Bits Error/Reserved, 6 Bits Minute)
		// Die Minutenwerte (0-59) passen in die unteren 6 Bits (0x3F).
		// Standardmäßig sind die oberen 2 Bits 0.
		$byte1 = $minute & 0x3F;

		// Byte 2: EESSSSSS (2 Bits Error/Reserved, 6 Bits Second)
		// Die Sekundenwerte (0-59) passen in die unteren 6 Bits (0x3F).
		// Standardmäßig sind die oberen 2 Bits 0.
		$byte2 = $second & 0x3F;

		// pack('C3', ...) erstellt einen Binärstring aus 3 vorzeichenlosen Zeichen (Bytes).
		// Führende 0x80 für einen Write-Befehl ist eine EIB/KNXnet/IP-Spezifität,
		// gehört aber nicht zur reinen DPT-Kodierung selbst, sondern zum Übertragungsprotokollrahmen.
		// Die DPT-Kodierung sind nur die 3 Bytes.
		return pack('C3', $byte0, $byte1, $byte2);
	}


	/**
	 * SetNextTimerExecution
	 * 
	 * Berechnet die nächste Ausführungszeit basierend auf den in der Liste gespeicherten Sendezeiten
	 * und setzt den internen Symcon-Timer entsprechend. Berücksichtigt, ob das Senden/Timer aktiv ist.
	 * 
	 * Schritte:
	 * 1. Prüft, ob die Eigenschaft 'Active' aktiviert ist; sonst Timer deaktivieren.
	 * 2. Liest die Sendezeiten aus der Property 'SendTimes'.
	 * 3. Filtert nur gültige Zeiten (Stunde + Minute) heraus.
	 * 4. Sortiert die Zeiten und sucht die nächste kommende Zeit.
	 * 5. Setzt den Timer auf das Intervall bis zur nächsten Zeit (in Millisekunden).
	 * 
	 * Parameter: keine
	 * Rückgabewert: keiner
	 */
	private function SetNextTimerExecution()
	{

		if (!$this->ReadPropertyBoolean('Active')) {
			$this->SetTimerInterval('SendKNXTimeTimer', 0);
			$this->SendDebug("KNXsystime", "Timer deaktiviert (Active = false)", 0);
			return;
		}
	
		$times = json_decode($this->ReadPropertyString('SendTimes'), true);
		//$this->SendDebug("KNXsystime", "Inhalt: " . print_r($times, true), 0);

		$now = time();
		$today = date('Y-m-d');

		// Keine Zeiten vorhanden -> Timer aus
		if (!is_array($times) || empty($times)) {
			$this->SetTimerInterval('SendKNXTimeTimer', 0);
			$this->SendDebug("KNXsystime", "Keine gültigen Zeiten. Timer deaktiviert.", 0);
			return;
		}

		// Nur gültige Zeiten behalten (hour + minute)
		$sendTimes = [];
		foreach ($times as $t) {
			if (isset($t['Time'])) {
				$timeObj = json_decode($t['Time'], true);
				if (is_array($timeObj) && isset($timeObj['hour'], $timeObj['minute'])) {
					$hh = str_pad((string)$timeObj['hour'], 2, '0', STR_PAD_LEFT);
					$mm = str_pad((string)$timeObj['minute'], 2, '0', STR_PAD_LEFT);
					$sendTimes[] = "$hh:$mm";
				}
			}
		}

		if (empty($sendTimes)) {
			$this->SetTimerInterval('SendKNXTimeTimer', 0);
			$this->SendDebug("KNXsystime", "Keine gültigen Zeiten. Timer deaktiviert.", 0);
			return;
		}

		sort($sendTimes);

		// Nächste Zeit suchen
		$next = false;
		foreach ($sendTimes as $t) {
			list($h, $m) = explode(':', $t);
			$ts = strtotime("$today $h:$m:00");
			if ($ts > $now) {
				$next = $ts;
				break;
			}
		}

		// Wenn keine Zeit mehr heute -> erste Zeit morgen
		if ($next === false) {
			list($h, $m) = explode(':', $sendTimes[0]);
			$next = strtotime(date('Y-m-d', strtotime('+1 day')) . " $h:$m:00");
		}

		// Timer setzen
		$intervalMs = ($next - $now) * 1000;
		$intervalMs = max(0, min($intervalMs, 2147483647)); // max 32bit
		$this->SetTimerInterval('SendKNXTimeTimer', $intervalMs);

		$this->SendDebug("KNXsystime", "Nächster Sendezeitpunkt: " . date('d.m.Y H:i:s', $next) . " (in $intervalMs ms)", 0);
	}


	/**
	 * ReceiveData
	 * 
	 * Diese Funktion wird vom Symcon-Framework aufgerufen, wenn Daten vom Parent-Modul ankommen.
	 * In diesem Modul wird sie nicht benötigt, da keine eingehenden Daten verarbeitet werden.
	 * 
	 * Parameter:
	 * @param string $JSONString - JSON-kodierte Daten vom Parent
	 * 
	 * Rückgabewert:
	 * string - immer leer, da keine Verarbeitung erfolgt
	 */
    public function ReceiveData($JSONString)
    {
        // Nicht benötigt
        return '';
    }


	/**
	 * GetConfigurationForm
	 * 
	 * Liefert das JSON-Konfigurationsformular für die Instanz im Symcon-Frontend.
	 * Enthält alle Eingabefelder, Listen und Aktionen, die der Benutzer in der Web-Oberfläche sieht.
	 * Übersetzungen werden über die lokale JSON-Datei unterstützt.
	 * 
	 * Rückgabewert:
	 * string - JSON-kodiertes Formular
	 */
	public function GetConfigurationForm()
	{
		$elements = [
			[
				"type"    => "CheckBox",
				"name"    => "Active",
				"caption" => $this->Translate("Active")
			],
			[
				"type"    => "Label",
				"caption" => $this->Translate("KNX_CONNECTOR_NOTICE") // neuen Eintrag in local.json anlegen
			],
			[
				"type"    => "ValidationTextBox",
				"name"    => "GA_Time",
				"caption" => $this->Translate("GA_Time"),
				"validate"=> "^\\d{1,2}/\\d{1,2}/\\d{1,2}$",
				"width"   => "300px"
			],
			[
				"type"    => "ValidationTextBox",
				"name"    => "GA_Date",
				"caption" => $this->Translate("GA_Date"),
				"validate"=> "^\\d{1,2}/\\d{1,2}/\\d{1,2}$",
				"width"   => "300px"
			],
			[
				"type"    => "Label",
				"caption" => $this->Translate("SEND_TIMES_NOTICE") // neuen Eintrag in local.json anlegen
			],
			[
				"type"    => "List",
				"name"    => "SendTimes",
				"caption" => $this->Translate("SendTimes"),
				"rowCount"=> 5,
				"add"     => ["Time" => ""],
				"delete"  => true,
				"columns" => [
					[
						"caption" => $this->Translate("TIME_COLUMN"),
						"name"    => "Time",
						"width"   => "200px",
						"add"     => "",
						"edit"    => ["type"=>"SelectTime"]
					]
				]
			]
		];

		$actions = [
			[
				"type"    => "Button",
				"caption" => $this->Translate("SendNow"),
				"onClick" => "KSZT_SendKNXTimeAndDate(\$id);"
			],
				[
					"type"    => "Label",
					"width"   => "50%",
					"caption" => $this->Translate("LICENSE_NOTICE")
				],
				[
					"type"    => "Label",
					"width"   => "50%",
					"bold"    => true,
					"caption" => $this->Translate("DONATION_HEADER")
				],
				[
					"type"    => "Label",
					"width"   => "50%",
					"caption" => $this->Translate("DONATION_TEXT")
				],
				[
					"type"  => "RowLayout",
					"items" => [
						[
							"type"    => "Image",
							"onClick" => "echo '" . $this->Translate("PAYPAL_LINK") . "';",	
							"image"   => 						""
						],
						[
							"type"    => "Label",
							"caption" => " "
						],
						[
							"type"    => "Label",
							"width"   => "70%",
							"caption" => $this->Translate("DONATION_INFO")
						],
						[
							"type"    => "Label",
							"caption" => " "
						]
					]
				],
				[
					"type"    => "Label",
					"width"   => "50%",
					"caption" => $this->Translate("PAYPAL_LINK")
				]
			];

		return json_encode([
			"elements" => $elements,
			"actions"  => $actions
		]);
	}

}

?>
