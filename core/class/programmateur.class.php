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

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class programmateur extends eqLogic {
	/* **************************Attributs****************************** */


	/* ***********************Methode static*************************** */

	public static function nextprog_on($_params) {
		// Suppression des crons Off associés
		$crons = cron::searchClassAndFunction('programmateur','nextprog_off','"eq_id":' . $_params['eq_id']);
		if (is_array($crons) && count($crons) > 0) {
			foreach ($crons as $cron) {
				if ($cron->getState() != 'run') {
					log::add('programmateur','debug','- Suppression du cron Nextprog_off : '.$cron->getSchedule());
					$cron->remove();
				}
			}
		}
		$eqLogic = eqLogic::byId($_params['eq_id']);
		if (is_object($eqLogic)) {
			log::add('programmateur','debug','Exécution de la fonction Nextprog_on pour l\'équipement ' . $eqLogic->getHumanName());
			$cmd_state = $eqLogic->getCmd(null, 'etat');
			if (!is_object($cmd_state) || $cmd_state->execCmd() != 1) {// On s'assure que la planification est toujours sur on sinon on quitte
				return;
			}
			if (isset($_params['action1']) && $_params['action1'] != '') {
				if ($_params['typeaction1'] == 'Commande') {
					$cmd = cmd::byId(str_replace('#','',$_params['action1']));
					if (is_object($cmd)) {
						$cmd->execCmd();
						$name = $cmd->getHumanName();
					}
				} else if ($_params['typeaction1'] == 'Scenario') {
					$actionscenario = scenario::byId(str_replace('scenario','',str_replace('#','',$_params['action1'])));
					if (is_object($actionscenario)) {
						if (isset($_params['tagaction1']) && $_params['tagaction1'] != '') {
							$tags = array();
							$args = arg2array($_params['tagaction1']);
							foreach ($args as $key => $value) {
								$tags['#' . trim(trim($key), '#') . '#'] = trim($value);
							}
							$actionscenario->setTags($tags);
						}
						$actionscenario->launch();
						$name = $actionscenario->getHumanName();
					}
				}
				log::add('programmateur','info','Nextprog - ' . $_params['eq_id'] . ' - Action 1 - '. $name);
			}
			if (isset($_params['delay']) && $_params['delay'] > 0 && isset($_params['action2']) && $_params['action2'] != '') {
				$heure_timestamp = time() + $_params['delay'];
				log::add('programmateur','info','Nextprog - ' . $_params['eq_id'] . ' - Délai - '.($_params['delay']/60).' minutes ('.cron::convertDateToCron($heure_timestamp).')');
				$cron = new cron();
				$cron->setClass('programmateur');
				$cron->setFunction('nextprog_off');
				$cron->setOption(array('eq_id' => $_params['eq_id'],'typeaction2' => $_params['typeaction2'],'action2' => $_params['action2'], 'tagaction2' => $_params['tagaction2']));
				$cron->setOnce(1);
				$cron->setSchedule(cron::convertDateToCron($heure_timestamp));
				$cron->save();
			} else {
				$eqLogic->setConfiguration('RepeatCount',$eqLogic->getConfiguration('RepeatCount')+1)->save();// Mise du compteur à +1
				if ($eqLogic->getConfiguration('NoRepeat') == 1 && $eqLogic->getConfiguration('RepeatCount') > 0) {
					$cmd_state->event(0);
				}
			}
		}
	}

	public static function nextprog_off($_params) {
		$eqLogic = eqLogic::byId($_params['eq_id']);
		if (is_object($eqLogic)) {
			log::add('programmateur','debug','Exécution de la fonction Nextprog_off pour l\'équipement ' . $eqLogic->getHumanName());
			$cmd_state = $eqLogic->getCmd(null,'etat');
			if (!is_object($cmd_state)) {// On s'assure que la commande etat existe sinon on quitte
				return;
			}
			if (isset($_params['action2']) && $_params['action2'] != '') {
				if ($_params['typeaction2'] == 'Commande') {
					$cmd = cmd::byId(str_replace('#','',$_params['action2']));
					if (is_object($cmd)) {
						$cmd->execCmd();
						$name = $cmd->getHumanName();
					}
				} else if ($_params['typeaction2'] == 'Scenario') {
					$actionscenario = scenario::byId(str_replace('scenario','',str_replace('#','',$_params['action2'])));
					if (is_object($actionscenario)) {
						if (isset($_params['tagaction2']) && $_params['tagaction2'] != '') {
							$tags = array();
							$args = arg2array($_params['tagaction2']);
							foreach ($args as $key => $value) {
								$tags['#' . trim(trim($key), '#') . '#'] = trim($value);
							}
							$actionscenario->setTags($tags);
						}
						$actionscenario->launch();
						$name = $actionscenario->getHumanName();
					}
				}
				log::add('programmateur','info','Nextprog - ' . $_params['eq_id'] . ' - Action 2 - '. $name);
			}
			$eqLogic->setConfiguration('RepeatCount',$eqLogic->getConfiguration('RepeatCount')+1)->save();// Mise du compteur à +1
			if ($eqLogic->getConfiguration('NoRepeat') == 1 && $eqLogic->getConfiguration('RepeatCount') > 0) {
				$cmd_state->event(0);
			}
		}
	}

	public static function nextprog($equipement) {
		$programmateur = eqLogic::byId($equipement);
		if (is_object($programmateur)) {
			log::add('programmateur','debug','- Appel de la fonction Nextprog par ' . $programmateur->getHumanName() . ' :');
			if ($programmateur->getIsEnable() == 1) { // Vérification que l'équipement est actif
				$cmd = $programmateur->getCmd(null,'etat');// Retourne la commande "etat" si elle existe
				if (is_object($cmd)) {//Si la commande existe
					$cmdValue = $cmd->execCmd();
					if ($cmdValue == 1) {// Programmateur sur On
						$today = date('N');
						$lundi = $programmateur->getCmd(null,'lundi')->execCmd();
						$mardi = $programmateur->getCmd(null,'mardi')->execCmd();
						$mercredi = $programmateur->getCmd(null,'mercredi')->execCmd();
						$jeudi = $programmateur->getCmd(null,'jeudi')->execCmd();
						$vendredi = $programmateur->getCmd(null,'vendredi')->execCmd();
						$samedi = $programmateur->getCmd(null,'samedi')->execCmd();
						$dimanche = $programmateur->getCmd(null,'dimanche')->execCmd();

						$JF = 0;
						$JF_box = $programmateur->getConfiguration('JF');
						if ($programmateur->getConfiguration('CommandeJF') != '') {
							if (substr($programmateur->getConfiguration('CommandeJF'),1,8) == 'variable') {
								$JF = scenario::getData(substr($programmateur->getConfiguration('CommandeJF'),10,-2));
							} else {
								$cmd = cmd::byId(str_replace('#','',$programmateur->getConfiguration('CommandeJF')));
								if (is_object($cmd)) {
									$JF = $cmd->execCmd();
								}
							}
						}
						log::add('programmateur','debug','  - JF : Actif : ' . $JF_box . ' - Critère respecté : ' . $JF);
						$Mode = 0;
						$Mode_box = $programmateur->getConfiguration('Mode');
						if ($programmateur->getConfiguration('CommandeMode') != '') {
							$cmd = cmd::byId(str_replace('#','',$programmateur->getConfiguration('CommandeMode')));
							if (is_object($cmd)) {
								$Mode = $cmd->execCmd();
								if ($Mode == $programmateur->getConfiguration('ExclMode')) {
									$Mode = 1;
								} else {$Mode = 0;}
							}
						}
						log::add('programmateur','debug','  - Mode : Actif : ' . $Mode_box . ' - Critère respecté : ' . $Mode);

						$heure = $programmateur->getCmd(null,'horaire')->execCmd();
						$heure = substr('000'.$heure,-4);//Traitement des 0 sur les heures < 10:00
						$heure_timestamp = strtotime(date('d-m-Y') . ' ' . $heure);
						$duree = (int) $programmateur->getCmd(null,'duree')->execCmd();
						if ($duree < 0) {
							$heure_timestamp = $heure_timestamp + $duree * 60;
							$duree = - $duree;
						}

						$array = array('eq_id' => intval($programmateur->getId()),'delay' => $duree*60,'typeaction1' => $programmateur->getConfiguration('TypeAction1'),'action1' => $programmateur->getConfiguration('Action1'),'typeaction2' => $programmateur->getConfiguration('TypeAction2'),'action2' => $programmateur->getConfiguration('Action2'),'timestamp' => $heure_timestamp, 'tagaction1' => $programmateur->getConfiguration('TagAction1'), 'tagaction2' => $programmateur->getConfiguration('TagAction2'));
						// Si on doit programmer un cron
						if (($heure_timestamp > strtotime(date('H:i'))) && (($JF_box == 1 && $JF == 0) || $JF_box == 0) && (($Mode_box == 1 && $Mode == 0) || $Mode_box == 0) && (($today == 1 && $lundi == 1)||($today == 2 && $mardi == 1)||($today == 3 && $mercredi == 1)||($today == 4 && $jeudi == 1)||($today == 5 && $vendredi == 1)||($today == 6 && $samedi == 1)||($today == 7 && $dimanche == 1))) {
							log::add('programmateur','debug','  - Nouveau cron à '.date('H:i',$array['timestamp']));
							$cron = new cron();
							$cron->setClass('programmateur');
							$cron->setFunction('nextprog_on');
							$cron->setOption($array);
							$cron->setOnce(1);
							$cron->setSchedule(cron::convertDateToCron($array['timestamp']));
							$cron->save();
						} else {
							log::add('programmateur','debug','  - Pas de programmation à mettre en place');
						}
					} else {
						log::add('programmateur','debug','  Action de fin sur mise sur off de l\'équipement');
						$EndOnOff = $programmateur->getConfiguration('EndOnOff');
						log::add('programmateur','debug','  - EndOnOff : ' . $EndOnOff);
						if ($EndOnOff == 1){
							// Suppression des crons Off éventuels
							$crons = cron::searchClassAndFunction('programmateur','nextprog_off','"eq_id":' . $equipement);
							if (is_array($crons) && count($crons) > 0) {
								foreach ($crons as $cron) {
									if ($cron->getState() != 'run') {
										log::add('programmateur','debug','  - Suppression du cron Nextprog_off : '.$cron->getSchedule());
										$cron->remove();
									}
								}
								// Exécution immédiate de Nextprog_off
								log::add('programmateur','debug','  - Exécution immédiate de Nextprog_off');
								$array = array('eq_id' => intval($programmateur->getId()),'typeaction2' => $programmateur->getConfiguration('TypeAction2'),'action2' => $programmateur->getConfiguration('Action2'), 'tagaction2' => $programmateur->getConfiguration('TagAction2'));
								programmateur::nextprog_off($array);
							}
						}
					}
				}
			}
		}
	}

	public static function forced($equipement) {
		$programmateur = eqLogic::byId($equipement);
		if (is_object($programmateur)) {
			if ($programmateur->getIsEnable() == 1) { // Vérification que l'équipement est actif
				$duree = (int) $programmateur->getCmd(null,'duree')->execCmd();
				$fin = strtotime('now') + abs($duree) * 60;
				log::add('programmateur','debug','  - Fin de la marche forcée prévue : ' . date('d/m/Y à H:i', $fin + 60));
				if ($duree > 0) {
					$on = 1;
					$off = 2;
				} else {
					$on = 2;
					$off = 1;
				}
				$_params = array('eq_id' => intval($programmateur->getId()),'typeaction1' => $programmateur->getConfiguration('TypeAction'.$on),'action1' => $programmateur->getConfiguration('Action'.$on),'typeaction2' => $programmateur->getConfiguration('TypeAction'.$off),'action2' => $programmateur->getConfiguration('Action'.$off),'tagaction1' => $programmateur->getConfiguration('TagAction'.$on),'tagaction2' => $programmateur->getConfiguration('TagAction'.$off));

				if (isset($_params['action1']) && $_params['action1'] != '') {
					if ($_params['typeaction1'] == 'Commande') {
						$cmd = cmd::byId(str_replace('#','',$_params['action1']));
						if (is_object($cmd)) {
							$cmd->execCmd();
							$name = $cmd->getHumanName();
						}
					} else if ($_params['typeaction1'] == 'Scenario') {
						$actionscenario = scenario::byId(str_replace('scenario','',str_replace('#','',$_params['action1'])));
						if (is_object($actionscenario)) {
							if (isset($_params['tagaction1']) && $_params['tagaction1'] != '') {
								$tags = array();
								$args = arg2array($_params['tagaction1']);
								foreach ($args as $key => $value) {
									$tags['#' . trim(trim($key), '#') . '#'] = trim($value);
								}
								$actionscenario->setTags($tags);
							}
							$actionscenario->launch();
							$name = $actionscenario->getHumanName();
						}
					}
					log::add('programmateur','info','  - Action 1 - '. $name);
				}
				if (isset($_params['action2']) && $_params['action2'] != '') { // Si on doit programmer un cron
					log::add('programmateur','debug','  - Nouveau cron mis en place : ' . date('d/m/Y à H:i',$fin + 60));
					$cron = new cron();
					$cron->setClass('programmateur');
					$cron->setFunction('forced_off');
					$cron->setOption($_params);
					$cron->setOnce(1);
					$cron->setSchedule(cron::convertDateToCron($fin));
					$cron->save();
				}
			}
		}
	}

	public static function forced_off($_params) {
		$eqLogic = eqLogic::byId($_params['eq_id']);
		if (is_object($eqLogic)) {
			log::add('programmateur','debug','Exécution de la fonction Forced_off pour l\'équipement ' . $eqLogic->getHumanName());
			if (isset($_params['action2']) && $_params['action2'] != '') {
				if ($_params['typeaction2'] == 'Commande') {
					$cmd = cmd::byId(str_replace('#','',$_params['action2']));
					if (is_object($cmd)) {
						$cmd->execCmd();
						$name = $cmd->getHumanName();
					}
				} else if ($_params['typeaction2'] == 'Scenario') {
					$actionscenario = scenario::byId(str_replace('scenario','',str_replace('#','',$_params['action2'])));
					if (is_object($actionscenario)) {
						if (isset($_params['tagaction2']) && $_params['tagaction2'] != '') {
							$tags = array();
							$args = arg2array($_params['tagaction2']);
							foreach ($args as $key => $value) {
								$tags['#' . trim(trim($key), '#') . '#'] = trim($value);
							}
							$actionscenario->setTags($tags);
						}
						$actionscenario->launch();
						$name = $actionscenario->getHumanName();
					}
				}
				log::add('programmateur','info','  - Action 2 - '. $name);
			}
		}
	}

	/* Fonction exécutée automatiquement toutes les minutes par Jeedom
	public static function cron() {

	}
	*/

	/* Fonction exécutée automatiquement toutes les heures par Jeedom
	public static function cronHourly() {

	}
	*/

	/* Fonction exécutée automatiquement tous les jours par Jeedom */
	public static function cronDaily() {
		log::add('programmateur','debug','Exécution de la fonction CronDaily');
		foreach (self::byType('programmateur') as $programmateur) {// Parcours tous les équipements du plugin
			programmateur::nextprog($programmateur->getId());
		}
	}

	/* *********************Méthodes d'instance************************* */

	public function preInsert() {

	}

	public function postInsert() {

	}

	public function preSave() {

	}

	public function postSave() {
		log::add('programmateur','debug','Exécution de la fonction postSave');
		//Création des commandes
		$order = 0;
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new programmateurCmd();
			$refresh->setLogicalId('refresh');
			$refresh->setName(__('Rafraichir', __FILE__));
		}
		$refresh->setOrder($order++);
		$refresh->setEqLogic_id($this->getId());
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->save();

		$info = $this->getCmd(null, 'etat');
		if (!is_object($info)) {
			$info = new programmateurCmd();
			$info->setLogicalId('etat');
			$info->setName(__('Etat', __FILE__));
			$info->setIsVisible(0);
			$info->setIsHistorized(1);
		}
		$info->setOrder($order++);
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('binary');
		$info->save();

		$action = $this->getCmd(null, 'on');
		if (!is_object($action)) {
			$action = new programmateurCmd();
			$action->setLogicalId('on');
			$action->setName(__('On', __FILE__));
			$action->setTemplate('dashboard','core::binarySwitch');
			$action->setTemplate('mobile','core::binarySwitch');
			$action->setDisplay('showNameOndashboard','0');
			$action->setDisplay('showNameOnmobile','0');
			$action->setDisplay('forceReturnLineAfter','1');
		}
		$action->setOrder($order++);
		$action->setEqLogic_id($this->getId());
		$action->setValue($info->getId());
		$action->setConfiguration('updateCmdId', $info->getId());
		$action->setConfiguration('updateCmdToValue', 1);
		$action->setType('action');
		$action->setSubType('other');
		$action->save();

		$action = $this->getCmd(null, 'off');
		if (!is_object($action)) {
			$action = new programmateurCmd();
			$action->setLogicalId('off');
			$action->setName(__('Off', __FILE__));
			$action->setTemplate('dashboard','core::binarySwitch');
			$action->setTemplate('mobile','core::binarySwitch');
			$action->setDisplay('showNameOndashboard','0');
			$action->setDisplay('showNameOnmobile','0');
			$action->setDisplay('forceReturnLineAfter','1');
		}
		$action->setOrder($order++);
		$action->setEqLogic_id($this->getId());
		$action->setValue($info->getId());
		$action->setConfiguration('updateCmdId', $info->getId());
		$action->setConfiguration('updateCmdToValue', 0);
		$action->setType('action');
		$action->setSubType('other');
		$action->save();

		// Tableau des jours
		$jours = [
			['logicalId' => 'lundi',    'short' => 'lun', 'label' => __('Lundi', __FILE__)],
			['logicalId' => 'mardi',    'short' => 'mar', 'label' => __('Mardi', __FILE__)],
			['logicalId' => 'mercredi', 'short' => 'mer', 'label' => __('Mercredi', __FILE__)],
			['logicalId' => 'jeudi',    'short' => 'jeu', 'label' => __('Jeudi', __FILE__)],
			['logicalId' => 'vendredi', 'short' => 'ven', 'label' => __('Vendredi', __FILE__)],
			['logicalId' => 'samedi',   'short' => 'sam', 'label' => __('Samedi', __FILE__)],
			['logicalId' => 'dimanche', 'short' => 'dim', 'label' => __('Dimanche', __FILE__)]
		];

		foreach ($jours as $jour) {
			// Info
			$info = $this->getCmd(null, $jour['logicalId']);
			if (!is_object($info)) {
				$info = new programmateurCmd();
				$info->setLogicalId($jour['logicalId']);
				$info->setName($jour['label']);
				$info->setIsVisible(0);
			}
			$info->setOrder($order++);
			$info->setEqLogic_id($this->getId());
			$info->setType('info');
			$info->setSubType('binary');
			$info->save();

			// Action ON
			$actionOn = $this->getCmd(null, $jour['short'] . '_on');
			if (!is_object($actionOn)) {
				$actionOn = new programmateurCmd();
				$actionOn->setLogicalId($jour['short'] . '_on');
				$actionOn->setName(__($jour['label'].'_On', __FILE__));
				$actionOn->setTemplate('dashboard','programmateur::day');
				$actionOn->setTemplate('mobile','programmateur::day');
				$actionOn->setDisplay('showNameOndashboard','0');
				$actionOn->setDisplay('showNameOnmobile','0');
			}
			$actionOn->setOrder($order++);
			$actionOn->setEqLogic_id($this->getId());
			$actionOn->setValue($info->getId());
			$actionOn->setConfiguration('updateCmdId', $info->getId());
			$actionOn->setConfiguration('updateCmdToValue', 1);
			$actionOn->setType('action');
			$actionOn->setSubType('other');
			$actionOn->save();

			// Action OFF
			$actionOff = $this->getCmd(null, $jour['short'] . '_off');
			if (!is_object($actionOff)) {
				$actionOff = new programmateurCmd();
				$actionOff->setLogicalId($jour['short'] . '_off');
				$actionOff->setName(__($jour['label'].'_Off', __FILE__));
				$actionOff->setTemplate('dashboard','programmateur::day');
				$actionOff->setTemplate('mobile','programmateur::day');
				$actionOff->setDisplay('showNameOndashboard','0');
				$actionOff->setDisplay('showNameOnmobile','0');
			}
			$actionOff->setOrder($order++);
			$actionOff->setEqLogic_id($this->getId());
			$actionOff->setValue($info->getId());
			$actionOff->setConfiguration('updateCmdId', $info->getId());
			$actionOff->setConfiguration('updateCmdToValue', 0);
			$actionOff->setType('action');
			$actionOff->setSubType('other');
			$actionOff->save();
		}

		$info = $this->getCmd(null, 'horaire');
		if (!is_object($info)) {
			$info = new programmateurCmd();
			$info->setLogicalId('horaire');
			$info->setName(__('Horaire', __FILE__));
			$info->setIsVisible(0);
			$info->setIsHistorized(1);
			$info->setConfiguration('minValue', 0);
			$info->setConfiguration('maxValue', 2359);
		}
		$info->setOrder($order++);
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->save();

		$action = $this->getCmd(null, 'var_horaire');
		if (!is_object($action)) {
			$action = new programmateurCmd();
			$action->setLogicalId('var_horaire');
			$action->setName(__('Var_Horaire', __FILE__));
			$action->setConfiguration('minValue', 0);
			$action->setConfiguration('maxValue', 2359);
			$action->setTemplate('dashboard','programmateur::time');
			$action->setTemplate('mobile','programmateur::time');
			$action->setDisplay('showNameOndashboard','0');
			$action->setDisplay('showNameOnmobile','0');
			$action->setDisplay('forceReturnLineBefore','1');
		}
		$action->setOrder($order++);
		$action->setEqLogic_id($this->getId());
		$action->setConfiguration('infoName', $info->getId());
		$action->setValue($info->getId());
		$action->setType('action');
		$action->setSubType('slider');
		$action->save();

		$info = $this->getCmd(null, 'duree');
		if (!is_object($info)) {
			$info = new programmateurCmd();
			$info->setLogicalId('duree');
			$info->setName(__('Durée', __FILE__));
			$info->setIsVisible(0);
			$info->setIsHistorized(1);
			$info->setConfiguration('minValue', -1440);
			$info->setConfiguration('maxValue', 1440);
		}
		$info->setOrder($order++);
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('numeric');
		$info->setUnite('min');
		$info->save();

		$action = $this->getCmd(null, 'var_duree');
		if (!is_object($action)) {
			$action = new programmateurCmd();
			$action->setLogicalId('var_duree');
			$action->setName(__('Var_Durée', __FILE__));
			$action->setConfiguration('minValue', -1440);
			$action->setConfiguration('maxValue', 1440);
			$action->setTemplate('dashboard','programmateur::delay');
			$action->setTemplate('mobile','programmateur::delay');
			$action->setDisplay('showNameOndashboard','0');
			$action->setDisplay('showNameOnmobile','0');
				$arr = [];
				$arr['step'] = 10;
				$arr['big_change'] = 'Oui';
			$action->setDisplay('parameters', $arr);
			$action->setDisplay('forceReturnLineBefore','1');
		}
		$action->setOrder($order++);
		$action->setEqLogic_id($this->getId());
		$action->setConfiguration('infoName', $info->getId());
		$action->setValue($info->getId());
		$action->setType('action');
		$action->setSubType('slider');
		$action->save();

		$action = $this->getCmd(null, 'forced');
		if (!is_object($action)) {
			$action = new programmateurCmd();
			$action->setLogicalId('forced');
			$action->setName(__('Marche forcée', __FILE__));
			$action->setDisplay('showNameOndashboard','0');
			$action->setDisplay('showNameOnmobile','0');
			$action->setDisplay('forceReturnLineBefore','1');
		}
		$action->setOrder($order++);
		$action->setEqLogic_id($this->getId());
		$action->setType('action');
		$action->setSubType('other');
		$action->save();
	}

	public function preUpdate() {

	}

	public function postUpdate() {

	}

	public function preRemove() {

	}

	public function postRemove() {

	}

	/* Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
	public function toHtml($_version = 'dashboard') {

	}
	*/

	/* Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
	public static function postConfig_<Variable>() {

	}
	*/

	/* Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
	public static function preConfig_<Variable>() {

	}
	*/

	/* **********************Getteur Setteur*************************** */
}

class programmateurCmd extends cmd {
	/* *************************Attributs****************************** */


	/* ***********************Methode static*************************** */


	/* *********************Methode d'instance************************* */

	/* Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
	public function dontRemoveCmd() {
		return true;
	}
	*/

	public function execute($_options = array()) {
		log::add('programmateur','debug','Exécution de la fonction Execute');
		$eqlogic = $this->getEqLogic()->getId();
		if ($this->getLogicalId() == 'forced') {// Si marche forcée :
			log::add('programmateur','debug','- Action sur Marche forcée par ' . eqLogic::byId($eqlogic)->getHumanName());
			// Modification donc on supprime tous les précédents crons de l'équipement
			$crons = cron::searchClassAndFunction('programmateur','forced_off','"eq_id":' . $eqlogic);
			if (is_array($crons) && count($crons) > 0) {
				foreach ($crons as $cron) {
					if ($cron->getState() != 'run') {
						log::add('programmateur','debug','- Suppression du cron Forced_off : '.$cron->getSchedule());
						$cron->remove();
					}
				}
			}
			programmateur::forced($eqlogic);
		} else {// Si la modification ne concerne pas la marche forcée
			// Modification donc on supprime tous les précédents crons de l'équipement
			$crons = cron::searchClassAndFunction('programmateur','nextprog_on','"eq_id":' . $eqlogic);
			if (is_array($crons) && count($crons) > 0) {
				foreach ($crons as $cron) {
					if ($cron->getState() != 'run') {
						log::add('programmateur','debug','- Suppression du cron Nextprog_on : '.$cron->getSchedule());
						$cron->remove();
					}
				}
			}
			// Réinitialisation du compteur de répétition
			eqLogic::byId($eqlogic)->setConfiguration('RepeatCount',0)->save();
			// Action sur refresh
			if ($this->getLogicalId() == 'refresh') {
				$this->getEqLogic()->refresh();
				//return;
			}
			// Action sur modification du slider
			switch ($this->getSubType()) {
				case 'other':
					log::add('programmateur','debug','- Action sur Other');
					$virtualCmd = cmd::byId($this->getConfiguration('updateCmdId'));
					$value = $this->getConfiguration('updateCmdToValue');
					$result = jeedom::evaluateExpression($value);
					if (is_object($virtualCmd)) {
						$virtualCmd->event($result);
					}
				break;
				case 'slider':
					log::add('programmateur','debug','- Action sur Slider');
					$virtualCmd = cmd::byId($this->getConfiguration('infoName'));
					$value = $_options['slider'];
					$result = jeedom::evaluateExpression($value);
					if (is_object($virtualCmd)) {
						$virtualCmd->event($result);
					}
				break;
			}
			programmateur::nextprog($eqlogic);
        }
	}

	/* **********************Getteur Setteur*************************** */
}