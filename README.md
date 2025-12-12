# KNXSystemZeitgeber

Modul zur automatischen Übertragung von Zeit- und Datumswerten an KNX-Gruppenadressen.  
Es unterstützt eine Liste von Sendezeiten, die zyklisch abgearbeitet werden, sowie das Aktivieren/Deaktivieren des Sendens über einen Schalter in der Konfiguration.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Automatisches Senden der aktuellen Zeit (DPT 10.001) an eine KNX-Gruppenadresse.
* Automatisches Senden des aktuellen Datums (DPT 11.001) an eine KNX-Gruppenadresse.
* Verwaltung mehrerer Sendezeiten, die täglich abgearbeitet werden.
* Möglichkeit, das Senden und den Timer über einen Schalter (`Active`) zu aktivieren oder deaktivieren.
* Debug-Funktionalität zur Anzeige der gesendeten Daten im Symcon Debug-Fenster.
* Kompatibel mit KNX-Gateway Interfaces über das KNX Splitter-Modul.

### 2. Voraussetzungen

- IP-Symcon ab Version 7.1
- KNX-Konfigurator oder kompatibles KNX Splitter-Interface.

### 3. Software-Installation

* Über den Module Store das `KNXSystemZeitgeber`-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen:  
  https://github.com/BugForgeNerd/KNXSystemZeitgeber`

### 4. Einrichten der Instanzen in IP-Symcon

Unter 'Instanz hinzufügen' kann das `KNXSystemZeitgeber`-Modul mithilfe des Schnellfilters gefunden werden.  
Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

| Name      | Beschreibung                                                     |
| --------- | ---------------------------------------------------------------- |
| Active    | Aktiviert/deaktiviert das Senden & Timer                         |
| GA_Time   | KNX-Gruppenadresse für die Zeit (DPT 10.001, Format z.B. 8/0/1)  |
| GA_Date   | KNX-Gruppenadresse für das Datum (DPT 11.001, Format z.B. 8/0/0) |
| SendTimes | Liste der Sendezeiten (HH:mm:ss)                                 |

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

| Name  | Typ | Beschreibung                                          |
| ----- | --- | ----------------------------------------------------- |
| Keine |     | Alle Einstellungen erfolgen über Properties und Timer |

#### Profile

| Name  | Typ |
| ----- | --- |
| Keine |     |

### 6. Visualisierung

* Im WebFront können die Sendezeiten über die Instanzkonfiguration angepasst werden.
* Debug-Ausgaben werden im Symcon-Debug-Fenster angezeigt, einschließlich der HEX-Daten für Zeit und Datum.

### 7. PHP-Befehlsreferenz

#### `KSZT_SendKNXTimeAndDate(int $InstanceID)`

Sendet die aktuelle Zeit und das Datum an die konfigurierten KNX-Gruppenadressen und setzt den nächsten Timer.

**Beispiel:**

Sendet das Datum und die Uhrzeit die aktuell auf dem System liegt auf den KNX Bus. Die Zahl 12345 ist dabei durch die ID dieses Moduls zu ersetzen.

```php
KSZT_SendKNXTimeAndDate(12345);
```

Gibt zurück, ob das Modul aktuell aktiv ist (Senden & Timer). Die Zahl 12345 ist dabei durch die ID dieses Moduls zu ersetzen.

```php
$active = GetActive(12345);
```

Aktiviert oder deaktiviert das Senden und den Timer. Die Zahl 12345 ist dabei durch die ID dieses Moduls zu ersetzen.

```php
SetActive(12345, true);
```

## Screenshots:

<div>
<img width="800" alt="Screenshot" src="imgs/Screenshot_ModulKNXSystemZeitgeber1.png">
</div>
