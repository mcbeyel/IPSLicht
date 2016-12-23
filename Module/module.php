<?

/**
 *
 * Author: Timo Beyel
 *
 * Description:
 */
class Licht extends IPSModule
{

	public function Create()
	{
		//Never delete this line!
		parent::Create();
		$this->RegisterPropertyInteger("Instanz", 0);
		$this->RegisterPropertyInteger("AutoOff", 0);

	}

	public function setStatus($Value)
	{
		$instanceID = $this->ReadPropertyInteger("Instanz");

		if ($instanceID) {
			$variableID = IPS_GetObjectIDByIdent("Value", $instanceID);
			$variableObject = IPS_GetObject($variableID);
			$variable = IPS_GetVariable($variableID);
			$ipsValue = $Value;


			// request associated action for the specified variable and value
			if ($variable["VariableCustomAction"] > 0) {
				IPS_RunScriptEx($variable["VariableCustomAction"], array("VARIABLE" => $variableID,
					"VALUE" => $ipsValue));
			} else {
				IPS_RequestAction($variableObject["ParentID"], $variableObject["ObjectIdent"], $ipsValue);
			}
		}
	}


	public function createGroupWithID($name, $id, $parentID)
	{
		$group = @IPS_GetObjectIDByIdent($id, $parentID);
		if (!$group) {
			$group = IPS_CreateCategory();
			IPS_SetName($group, $name);
			IPS_SetParent($group, $parentID);
			IPS_SetIdent($group, $id);
		}

		return $group;
	}

	/**Erzeugt die Events, die auf Schalteränderungen reagieren*/
	public function createEvent($varID)
	{
		$event = @IPS_GetObjectIDByIdent("Event" . $varID, $this->GetIDForIdent("Schalte"));
		if (!$event) {
			IPS_LogMessage("Licht","Erstelle Event für $varID");
			$event = IPS_CreateEvent(0);
			IPS_SetEventTrigger($event, 1, $varID);
			IPS_SetIdent($event, "Event" . $varID);
			IPS_SetEventActive($event, true);
			IPS_SetParent($event, $this->GetIDForIdent("Schalte"));
		}
	}


	/**Erzeugt das Script, welches den Status schaltet*/
	protected function createScript()
	{
		$script = @IPS_GetObjectIDByIdent("Schalte", $this->InstanceID);
		if ($script) {
			$childs = IPS_GetChildrenIDs($script);
			if ($childs) {
				foreach ($childs as $child) {
					IPS_DeleteEvent($child);
				}
			}
			IPS_DeleteScript($script, true);
		}

		$instance = $this->ReadPropertyInteger("Instanz");
		$varID = IPS_GetObjectIDByIdent("Value", $instance);
		$content = "<?php LICHT_setStatus($this->InstanceID,!GetValue($varID)); ?>";
		$this->RegisterScript("Schalte", "Schalte", $content);
	}

	/*Füge ein Event hinzu, welches auf die Variablenänderung der Aktoren-Instanz reagiert, um ggf. den AutoOff
	Timer zu aktivieren*/
	protected function createAutoOffEvent($interval)
	{
		$instance = $this->ReadPropertyInteger("Instanz");
		if (!$instance) return;
		$varID = @IPS_GetObjectIDByIdent("Value", $instance);
		$event = @IPS_GetObjectIDByIdent("AutoOff" . $varID, $varID);

		$timer = @IPS_GetObjectIDByIdent("AutoOffTimer", $varID);
		if (!$timer) {
			$timer = IPS_CreateEvent(1);
			IPS_SetIdent($timer, "AutoOffTimer");
			IPS_SetEventCyclic($timer, 0, 0, 0, 0, 0, 0);
			IPS_SetParent($timer, $varID);
			IPS_SetEventScript($timer, "LICHT_setStatus($this->InstanceID,FALSE);");

		}


		if ($event) {
			IPS_DeleteEvent($event);
		}

		if ($interval) {

			$event = IPS_CreateEvent(0);
			IPS_SetEventTrigger($event, 1, $varID);
			IPS_SetIdent($event, "AutoOff" . $varID);
			IPS_SetEventActive($event, true);
			IPS_SetParent($event, $varID);
			IPS_SetEventScript($event, '$d=new DateTime();
$d=$d->add(new DateInterval("PT' . $interval . 'M"));
$varID=@IPS_GetObjectIDByIdent("AutoOffTimer", $_IPS["VARIABLE"]);
IPS_SetEventCyclicTimeFrom($varID,$d->format("H"),$d->format("i"),$d->format("s"));
IPS_SetEventActive($varID,$_IPS["VALUE"]);');
		}



	}

	/**Aktualisiert die Schalter**/
	public function Update()
	{
		$group = $group = @IPS_GetObjectIDByIdent("Schalter", $this->InstanceID);
		$childs = IPS_GetChildrenIDs($group);
		$this->createScript();

		if ($childs) {

			foreach ($childs as $child) {


				$link = @IPS_GetLink($child);
				if ($link) {

					$variableID = $link["TargetID"];

					$this->createEvent($variableID);

				}
			}
		}
	}

	public function ApplyChanges()
	{
		//Never delete this line!
		parent::ApplyChanges();
		$this->createGroupWithID("Schalter", "Schalter", $this->InstanceID);
		$this->createAutoOffEvent($this->ReadPropertyInteger("AutoOff"));


	}


}

?>
