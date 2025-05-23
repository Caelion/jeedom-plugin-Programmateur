<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

/*
 * Fonction appelée à l'installation du plugin.
 * Laisser vide si aucune action spécifique n'est requise.
*/
function programmateur_install() {
	
}

/*
 * Fonction appelée lors d'une mise à jour du plugin.
 * Ajouter ici les migrations de données ou évolutions de structure si nécessaire.
*/
function programmateur_update() {
	
}

/*
 * Fonction appelée à la suppression du plugin.
 * Ajouter ici le nettoyage éventuel (crons, objets orphelins...).
*/
function programmateur_remove() {
	
}

?>