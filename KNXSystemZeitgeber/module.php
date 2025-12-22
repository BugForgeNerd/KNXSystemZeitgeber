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
						"GroupAddress1" => 8,
						"GroupAddress2" => 0,
						"GroupAddress3" => 1,
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
						"GroupAddress1" => 8,
						"GroupAddress2" => 0,
						"GroupAddress3" => 0,
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
							"image"   => 						"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADsAAAA6CAYAAAAOeSEWAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAoiSURBVGhD7ZprkBTVFcd/5/bsC1heAvIKLAgsuwqYGI3ECPjAfAhRtOKjKjGlljwUTfIhSlZNrVtBVqIJsomFIBUrqZRVPkvFqphofMVoJEYSXrsrbxU1PFR2kd3Zmb4nH3qmp+fua4adjR/0V9U1955zunv+fe89t/t2wxcIcQ19p9ZQNeBiPEa7nh5RPiPhH8Kwh5Pje3ilLumG9JXCi62qvw3Puys4tDqn0EiZlC8dE/m12gI8D/4GdtT8GcTd8YQovNjqu1/EeOcFlajY7spp0nqci2P1eWLmB2y55WDEcUIY19BnRKZ3bsE0rqB0vbt4QGQ+SfvCSZWryl1XvhRW7Iz6YZAeq27rud25K4Fua0tgMjLjiGGl48ybwopN+JWISJc6IDI20+W0uGg5unPkghmuHzJr9dCIM28KK9bEqoLWiP757nC7sHuF0kJTdpHSo/H2c5ygvCisWHS6U8+uQqQVo60cjXO7eyTeyPiIM28KK1YkJTb1512tmlQ6WpVEC8HWCh2tmd+Olsh2TEkeV2yHhmNc6dPcW1ixms7EqdaR6DhUaDsM8cNC+xHCLR75zdoOC20Hhc8OCMfeVdqPKMNL26Kny5fCiZ1bWwpM7jrRpGx+e8SWB2qFjlbh0gs2sH7vNa47Vwon9mDZKRiJBZVI66axSQXtLWt1z+ByZXB5KcgGb/2u8113LhROrBBk4lSlU/KxiXRkhrCb58DUU9Il42vsrmxnbhROrGrkzsnNTN2I1S7iumPGaZmycBYP7j456s6FwoklnYnDenbrdiU2V8aPV8aPjVoEjU2LGnKhcGKFqkyDRrtzqnyiYsXARV0MUT9Z6pp6ozBiL7/cA6nsNNVEsR2Zcj5j9bw5ypguHo3FO+KaeqMwYrfNHI/IwIwh0qIAahW1GUN3Y9W9COfMVmaflW0L8Im17XSNvVEYsaZoujvTZBFt1Z5IX4SBA5XLLlHmnetGBCjNXF/V6pp7ozBisVVZVbflbC53eQIjRyoXnq8sWwxVlW5ABtE/uaZc6Kk9cqeq/gE8b0l2Bo6QOKpMHg2lJdlZuqgIBg6E4cNg7BgoH+Tu2RVKMjmLG6dscx29URix1b98GSNzXXOAwpxZltMrC3Mu5RmWVCx0zblQiG4soKlu3EWrKjBpXGGEwtESqz9xjbnSd7HTVw5HGBWo6kJTzIPBkUR94rRj7ZXxGybtcx250nexalLza3SOjTB0oHaaUvJFZR+auJClk//iuvKh72JN9M6Jzq07bHB2PR9UdqLcSssnM1gy9XXXnS99vORA9ap7MOanQaWLrnxWlXJ25Ca+Mx2oPg7SimoHokcwZg/t/iZumrwTKcwCOZ3/2QlQvepZjPmOaw6787e/oVROdJ0R9A4WT+rzMmku9L0bQxeLbJHGGN5LN07o311Tf9E3sWfUDkCoCCppgenOklpS7UmsKpQmd7jm/qJvYluLpyDidcrAacGDyhTPc3wRRI5w3dTDrrm/6JtYz0zKNjiie2pVAJXmQiag3ujhsufAxPkfEfc9fN2M1U3BZjPbzKnK2BE9LGzL8zx73zOutb/oezbuifX7HgAWu+YQ5VaWVNzrmvuLvnXj3nEytYNPk2vqT/pPrKqA9PBQio+Yza6xP+nfbrx2zyKMN8M1A6D2ZZZOetI1f8mXfEmPZI/ZyhVfh9g3s2zB6tkHeO0vsaPu42xfAVm3Zy5iZmIjS64YH5H38I6/lPdq4rq90xGZj7WCxp7ghgkHssVW370RYxZ0uXCmtIL/fbbXbMx2FIh1+99C9GuuOcVhlIUsqcj9mXbd3l8gcjsACb7FsorXo1OPQPpNnIK1cXzNLPiKlKPyALW1hZ+ual+KOZ8otEPWW/YRwG8j9RwwwfEUKDZNZM2zZ9SWgZkICqpxBhcNY9TxgST81IM5IDKWJ0uHhPVC8ZWJ4xAGBBX5iKGbyhl6bBCqkbsrqaJW87jQ4cU7yKIJH5M1ZivrZ1DkbQHAahM7lgcrhtNWnklxbBMIqI1TOrScycMsW3YvxOMCVMqA7SW27fG4lKWWOP3NJUYOxK1ZAIDYN2i87c3wXDNqx5Mo+17g023cfKVHzKQWvuVFFk+8EIB1+y9G9CkAlA9ZUjGONe+UUFp8BcI5qMbA/rvE2OfifvECDCD8jSGtW/m0vAW0GHiVxRXzAFJvygGP6Zmxqo2AMLfW46AsCmMs/xzEB0OObf/0aYq8VCIL9onLgFsxMhIAX+rjfuJJYrHVCGDlH8BsSL0E2172R2JmLgrY5FUYGROeQ2kChIf2lpDQa0M7vFa6Yc/Edms2AsE6jwjgEbfeYYyOAMCaH3FkSAueXxwcT5vTB4h2i6owKYmZR/XdTRwacAjjLQpaVRW1vz7WNuBhjARCrTZh7VqsvhEKBVDbxFWJt0F3BQ/xnMnMe0YBsPWMmzFmLghY/2Eab3sUidxWil7Bun1NdMhB4JLgeNiY2vvbffNUKBTZDKwFtiAEQgFsohmxkfFvwsWBjNjwsx5AGIbxpmFkKCJgbQLr34GRQ4jMD/6ovjxlwvuns2P5jZz61rlY+1a4v+83UVdnUR5JHdsjkVxQXH1XNZ4E603Wf48YNwEafKIQMgJhKkLwLkRoQ1maVEYjzApC9HEOTDiTxRXLKNKzQfaHexfFtmclO5HwYSMj1ka+ibD2aZL+Gnz7K6x/M76dRmPNSuCCIETBT96/67nfxAF47DEf2E3gsmh5cALfPhK+5BJZ2CGxhxApC2L8a9la80nwwBARq/owyhpU7kXtDfhMYWnFBjyTeSOtch91YgG4dlI7qu+m7Ed573cfZV089RrTxWDMXv6ox/a9wWt7VR9frqZ5eedJXMl8GWq8MCuXzVoxri0hFwUx+iG7ftwCQHPNNqpXbUfkVMR8N0yH6jfQePtfAVjffBJSGgwB5SiLK67uZvUiMwtYDb9hLF67r7oDzgZAdKfeWafy4HWVwazCMT5Y/346NmjZ/zRPQCRI/cqHNC8/lg7IQnRbMKwFjNxLVX091fUr2hJFb2JkWCoqvJJBF9B0Vw4s1m5jZHtNJqQ0822E8E43QkElmCkAPLOB9XvrWL9/VYfRVxGKghga5U4VlOqgrjupqwt6QCjWi75M1qbgT3ZBWfujWLsDFESG4sV+hvFuB4LMB+lsmiGhmWUX1TiqP+SVuszXX1ldrqeH+diDCAeCso4G+TnoLYjxwxCrTYzcNwrRVC/IjFdCsSr/xWoDNtmA2tXRgCz+VXcctXPwtR7VF7B2I8nkMkhchPUbsH4DXuL3kT2EGMuCooL6dTTWZD+wi+4GGoAGPP6Q5YuyZNxhtGM2yhqQF1GeArkGXy8L9tcGPPsExrfh8bDroofIvjcuMN5p9Zf66j2BIPj+a5z29rxUMvtc6D+x01eMwRRtwZgRqG0pMfar8a01e9yw/yd53Gvmi+nAk+sRXQh2zuct9AvH/wAcerqGMemSoQAAAABJRU5ErkJggg=="
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
