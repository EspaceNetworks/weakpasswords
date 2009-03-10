<?php 
/* $Id: */
//Copyright (C) 2009 Ethan Schreoder (ethan.schroeder@schmoozecom.com)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of version 2 of the GNU General Public
//License as published by the Free Software Foundation.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

function weakpasswords_get_config($engine) {
        switch($engine) {
                case "asterisk":
			// Clear all weak password notifications
			$nt = notifications::create($db);
			$security_notifications = $nt->list_security();
			foreach($security_notifications as $notification)  {
				if($notification['module'] == "weakpasswords")  {
					$nt->delete($notification['module'],$notification['id']);
				}
			}
			// Generate new notifications
			$weak = weakpasswords_get_users();
			if(sizeof($weak) > 0)  {
				foreach($weak as $details)  {
					$extended_text = "Warning: The use of SIP/IAX passwords that are weak can allow hackers to make brute force registrations and possibly make calls through your PBX.  It is strongly recommended, you choose strong secrets.".$details['deviceortrunk']." ".$details['name']." has a weak secret of ".$details['secret'].": ".$details['message'];
					$nt->add_security("weakpasswords", $details['name'], $details['deviceortrunk']." ".$details['name'].": ".$details['message'],$extended_text);
				}

			}
		break;
	}
}

function weakpasswords_get_users()  {
	global $db;

	$sql = "SELECT 'SIP' as tech,s.id as id, s2.data as device,s.data as secret FROM sip s LEFT JOIN sip s2 ON s.id=s2.id AND s2.keyword='account' WHERE s.keyword='secret'";
	$sipsecrets = sql($sql,"getAll",DB_FETCHMODE_ASSOC);
	$sql = "SELECT 'IAX' as tech,s.id as id, s2.data as device,s.data as secret FROM iax s LEFT JOIN iax s2 ON s.id=s2.id AND s2.keyword='account' WHERE s.keyword='secret'";
	$iaxsecrets = sql($sql,"getAll",DB_FETCHMODE_ASSOC);
	$secrets = array_merge($sipsecrets,$iaxsecrets);
	$weak = array();
	foreach($secrets as $arr)  {
		$name = $arr['device'];
		$id = $arr['id'];
		$secret = $arr['secret'];
		$tech = $arr['tech'];

		if($id == $name)  {
			$deviceortrunk = "Extension";
		}
		else  {
			$deviceortrunk = "$tech Trunk";
		}
		$reversed = strrev($secret);
		$match = "0123456789";
		if(strpos($match,$secret) || strpos($match,$reversed))  {
			$weak[] = array("deviceortrunk" => $deviceortrunk, "name" => $name, "message" => "Secret has sequential digits", "secret" => $secret);
		}
		else if($device == $secret)  {
			$weak[] = array("deviceortrunk" => $deviceortrunk, "name" => $name, "message" => "Secret same as device", "secret" => $secret);
		}
		else if(preg_match("/(.)\\1{3,}/",$secret,$regs))  {
			$weak[] = array("deviceortrunk" => $deviceortrunk, "name" => $name, "message" => "Secret has consecutive digit ".$regs[1], "secret" => $secret);
		}
		else if(strlen($secret) < 6)  {
			$weak[] = array("deviceortrunk" => $deviceortrunk, "name" => $name, "message" => "Secret less than 6 digits", "secret" => $secret);
		}
	}
	return $weak;
}
?>
