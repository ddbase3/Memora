<?php declare(strict_types=1);

namespace Memora\Api;

/**
 * Schnittstelle für den Zugriff auf Benutzerprofile (base3system_sysprofile).
 *
 * Der Service kapselt den Zugriff auf Benutzerprofile über DataHawk
 * und bietet Methoden zum Laden, Aktivieren und Auflisten von Profilen.
 */
interface IMemoraProfileService {

	/**
	 * Gibt das aktuell aktive Profil des angegebenen oder eingeloggten Benutzers zurück.
	 *
	 * Wenn kein Benutzer angegeben ist, wird der aktuelle Benutzer aus dem Usermanager
	 * verwendet. Gibt null zurück, wenn kein aktives oder Standardprofil existiert.
	 *
	 * @param int|null $userId Benutzer-ID oder null für aktuellen Benutzer
	 * @return array|null Assoziatives Array des Profil-Datensatzes oder null
	 */
	public function getActiveProfile(?int $userId = null): ?array;


	/**
	 * Gibt alle Profile des angegebenen oder eingeloggten Benutzers zurück.
	 *
	 * @param int|null $userId Benutzer-ID oder null für aktuellen Benutzer
	 * @param bool $includeArchived Wenn true, werden archivierte Profile eingeschlossen
	 * @return array Liste von Profil-Datensätzen (assoziative Arrays)
	 */
	public function getProfiles(?int $userId = null, bool $includeArchived = false): array;


	/**
	 * Aktiviert ein bestimmtes Profil für den angegebenen Benutzer.
	 * Setzt alle anderen Profile des Benutzers auf inactive (active = 0).
	 *
	 * @param int $userId Benutzer-ID
	 * @param int $profileId Profil-ID, das aktiviert werden soll
	 * @return void
	 */
	public function setActiveProfile(int $userId, int $profileId): void;
}

