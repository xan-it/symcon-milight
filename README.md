# milight
IPS-Modul zur Steuerung des milight-RGBW-Gateways (auch bekannt als Limitless LED oder IWY-Light)

## Inhalt

1. [Funktionsumfang](#1-funktionsumfang)

2. [Voraussetzungen](#2-voraussetzungen)

3. [Software-Installation](#3-software-installation)

4. [Einrichten der Instanzen in IPS](#4-einrichten-der-instanzen-in-ips)

5. [PHP-Befehlsreferenz](#5-php-befehlsreferenz)

6. [Changelog](#6-changelog) 

## 1. Funktionsumfang

   Direkte native Unterstützung des MiLight RGBW-Gateways

   *   Setzen der Farbe
   *   Setzen der Helligkeit

## 2. Voraussetzungen

   * IPS ab Version 4.x
   * konfiguriertes MiLight RGBW-Gateway

## 3. Software-Installation

   Über Kern Instanzen > Modules folgende URL hinzufügen:
   `git://github.com/xan-it/symcon-milight.git`

## 4. Einrichten der Instanzen in IPS

   Über "Instanz hinzufügen" ist der 'milight RGBW-Gateway' unter dem Hersteller 'milight' aufgeführt.  
   Die Einstellungen der IP-Adresse, des UDP-Ports und der MiLight-Gruppe (Kanal) sind in der angelegten Instanz zu konfigurieren.
   Es besteht auch die Möglichkeit, anstelle der IP des Gateways die Broadcast-Adresse 255.255.255.255 zu verwenden (Standardeinstellung).
   In diesem Fall werden alle Gateways angesprochen.

## 5. PHP-Befehlsreferenz

   `void MILIGHT_SetState(integer $InstanzID, integer $State);`  
        Setzt den Zustand der Lampen.
		State = 0: aus
		State = 1: Weiß-Modus
		State = 2: Farbmodus
   
   `void MILIGHT_SetRGB(integer $InstanzID, integer $Red, integer $Green, integer $Blue);`  
        Setzt die Werte für die 3 Farbkanäle.
        Erlaubte Werte für die Farben sind 0 bis 255.  

   `void MILIGHT_SetColor(integer $InstanzID, integer $Color);`  
        Setzt die Werte für die 3 Farbkanäle anhand eines kombinierten Farbwertes (z.B. durch Verwendung des Variablentyps ~HexColor)
        Erlaubte Werte zwischen 0x000000 und 0xFFFFFF.  

   `void MILIGHT_SetBrightness(integer $InstanzID, integer $Level);`  
        Setzt die Helligkeit für den Weiß-Modus.
        Erlaubte Werte für die Helligkeit sind 0 bis 255.  


## 6. Changelog

   0.1. : erste Beta-Version für IPS 4.x
