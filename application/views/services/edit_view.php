<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed') ?>
<a href="<?php echo $this->url_prefix; ?>/index.php/services">
<?php 
echo lang('undochanges') . "</a>";

if (($privileges['manager'] == 'y') OR ($privileges['admin'] == 'y')) {
	echo " | <a href=\"$this->url_prefix/index.php/services/vendor/$userserviceid\">
		" . lang('vendor_history') . "</a>";
}



// print form to edit the things in the options table
print "<h4>". lang('edit') .": $userserviceid $servicedescription ($service_org_name)".
"&nbsp;&nbsp;&nbsp; ". lang('createdon') .": $creationdate, ";
if ($removed == 'y') {
	print lang('removed') . ": $enddate</h4>";
} else {
	print lang('active') ."</h4>";
}
print "<form action=\"".$this->url_prefix."/index.php/services/save\" method=post><table width=720 ".
"cellpadding=5 cellspacing=1 border=0>\n";
print "<input type=hidden name=servicedescription ".
"value=\"$servicedescription\">\n<input type=hidden name=optionstable ".
"value=$optionstable><input type=hidden name=userserviceid ".
"value=$userserviceid>\n";

// check for optionstable, skip this step if there isn't one	
if ($optionstable <> '') 
{
	// get the data from the options table and put into variables
	$myvalues = $this->service_model->options_values($userserviceid, $optionstable);

	// list out the fields in the options table for that service
	$fields = $this->schema_model->columns($this->db->database, $optionstable);

	// initialize fieldlist
	$fieldlist = "";

	$i = 0;
	foreach($fields->result() as $v) {
		//echo "Name: $v->name ";
		//echo "Type: $v->type <br>";

		$fieldname = $v->COLUMN_NAME;
		$fieldflags = $v->DATA_TYPE;
		$fieldtype = $v->COLUMN_TYPE; // for enum has value: enum('1','2') etc.

		if ($fieldname <> "id" AND $fieldname <> "user_services") 
		{			
			if ($fieldflags == "enum") 
			{
				echo "<td bgcolor=\"ccccdd\"width=180><b>$fieldname</b></td>".
					"<td bgcolor=\"#ddddee\">\n";
				// print all the items listed in the enum
				$this->schema_model->enum_select($fieldtype, $fieldname, $myvalues[$fieldname]);

				echo "</td><tr>\n";
			} 
			elseif ($fieldflags == "text"
					OR $fieldflags == "blob"
					OR $fieldflags == "tinytext"
					OR $fieldflags == "tinyblob"
					OR $fieldflags == "mediumblob"
					OR $fieldflags == "mediumtext") 
			{
				echo "<td bgcolor=\"ccccdd\"width=180><b>$fieldname</b></td>".
					"<td bgcolor=\"#ddddee\"><textarea cols=40 rows=6 ".
					"name=\"$fieldname\">$myvalues[$fieldname]</textarea>";
				echo "</td><tr>\n";
			} 
			elseif ($fieldname == "description")
			{
				echo "<td bgcolor=\"ccccdd\"width=180><b>$fieldname</b></td>".
					"<td bgcolor=\"#ddddee\"><input size=40 maxlength=44 type=text name=\"$fieldname\" ".
					"value=\"$myvalues[$fieldname]\">";
				echo "</td><tr>\n";
			} 
			else 
			{				
				echo "<td bgcolor=\"ccccdd\"width=180><b>$fieldname</b></td>".
					"<td bgcolor=\"#ddddee\"><input type=text name=\"$fieldname\" ".
					" value=\"$myvalues[$fieldname]\">\n";	


				$j = $i + 1; // to get the next field for multi field queries
				$optionsurls = $this->service_model->options_urls($fieldname);
				foreach ($optionsurls as $urlmyresult) 
				{	  	    
					$myoptions = $this->service_model->options_attributes($userserviceid, $optionstable);

					// replace the s1 and s2 place holders with the actual variables
					$s1 = $myoptions[$i];
					$s2 = $myoptions[$j]; 
					$d1 = $myoptions[2]; // the primary service detail/first attribute
					$a1 = $this->account_number; // the services account_number
					$url = $urlmyresult['url'];
					$urlname = $urlmyresult['urlname'];
					$url = str_replace("%s1%", $s1, $url);
					$url = str_replace("%s2%", $s2, $url);
					$url = str_replace("%d1%", $d1, $url);
					$url = str_replace("%a1%", $a1, $url);
					if ($url) 
					{
						echo "&nbsp;&nbsp; <a href=# ".
							"onclick=\"popupPage('$url'); return false;\">$urlname</a>";
					}
				}

				echo "</td><tr>\n";

			}
			$fieldlist .= ',' . $fieldname;
		}
		$i++;
	}
	print "<input type=hidden name=fieldlist value=$fieldlist>";
	print "<td></td><td><input name=save type=submit ".
		"value=\"". lang('savechanges') ."\" class=smallbutton></form>&nbsp;&nbsp;&nbsp;\n";
}


//
$removed = $removedstatus['removed'];

if (!isset($removedstatus['cancel_date'])) {
	$removedstatus['cancel_date'] = "";
}
$cancel_date = $removedstatus['cancel_date'];

if ($removed == 'y' AND $cancel_date == '') 
{
	// print the undelete button
	print "<p><form style=\"margin-bottom:0;\" 
		action=\"".$this->url_prefix."/index.php/services/undelete\" method=post>".
		"<input type=hidden name=optionstable value=$optionstable>";
	print "<input type=hidden name=userserviceid value=$userserviceid>";
	print "<input type=hidden name=load value=services>";
	print "<input type=hidden name=type value=module>";
	print "<input type=hidden name=delete value=on>";
	print "<input name=undeletenow type=submit value=\" ". lang('undelete') . "\" ".
		"class=smallbutton></form>";
} 
else 
{
	// print the delete button
	print "<p><form 
		action=\"".$this->url_prefix."/index.php/services/delete\" method=post>".
		"<input type=hidden name=servicedescription value=\"$servicedescription\">".
		"<input type=hidden name=userserviceid value=$userserviceid>".
		"<input name=delete type=submit value=\"" . lang('deleteservice') . "\" ".
		"class=smallbutton></form>";
}

// print the open ticket button
print "<form style=\"margin-bottom:0;\" action=\"".$this->url_prefix."/index.php/support/\" method=post>";
print "<input type=hidden name=serviceid value=\"$userserviceid\">\n";
if ($support_notify) {
	print "<input name=openticket type=submit value=\"".lang('notify')." $support_notify\" ".
		"class=smallbutton></form></td></table><p></blockquote>\n";  	
} else {
	print "<input name=openticket type=submit value=\"".lang('openticket')."\" ".
		"class=smallbutton></form></td></table><p></blockquote>\n";     
}

/*----------------------------------------------------------------------*/
// print the field inventory assigned to this service with ability to
// edit the shipping info and return info
/*----------------------------------------------------------------------*/

print "<table width=720 cellpadding=5 cellspacing=1 border=0>\n";

foreach ($fieldinventory as $myresult) 
{
	$item_id = $myresult['id'];
	$description = $myresult['description'];
	$creation_date = $myresult['creation_date'];
	$serial_number = $myresult['serial_number'];
	$status = $myresult['status'];
	$sale_type = $myresult['sale_type'];
	$tracking_number = $myresult['shipping_tracking_number'];
	$shipping_date = $myresult['shipping_date'];
	$return_date = $myresult['return_date'];
	$return_notes = $myresult['return_notes'];

	// grey out the returned items
	if ($status == 'returned') {
		$labelcolor = "#dddddd";
		$valuecolor = "#eeeeee";
		$textcolor = "#888888";
	} else {
		$labelcolor = "#ccccdd";
		$valuecolor = "#ddddee";
		$textcolor = "#000000";
	}

	print "<tr><td bgcolor=\"$labelcolor\" width=180><b style=\"color:$textcolor;\">
		$description</b></td>";
	print "<td bgcolor=\"$valuecolor\"><table><td style=\"color:$textcolor;\">
		<b style=\"color:$textcolor;\">".lang('status').":</b> $status</td>
		<td style=\"color:$textcolor;\"><b style=\"color:$textcolor;\">".
		lang('serialnumber').":</b></td><td style=\"color:$textcolor;\"> $serial_number</td>".
		"<td style=\"color:$textcolor;\"><b style=\"color:$textcolor;\">".lang('saletype').":</b></td> 
		<td style=\"color:$textcolor;\">$sale_type</td> <tr>";
	if ($status == 'returned') {
		print "<td style=\"color:$textcolor;\">".lang('returndate').": 
			$return_date, $return_notes</td></table>";
	} else {
		print "<td><b>".lang('trackingnumber')."</b></td><td>
			<a href=\"".$this->config->item('tracking_url')."$tracking_number\">$tracking_number</a></td> ".
			"<td><b>".lang('shippingdate').":</b></td> <td>$shipping_date</td><td>
			<a href=\"$this->url_prefix/index.php/services/returnfieldasset/$item_id/$userserviceid\">".
			lang('returndevice')."</a></td></table>";
	}
}

print "</table>\n";


/*----------------------------------------------------------------------*/
// print the add field assets form section
// only show choices if field asset items are compatible with the service category
if ($field_asset_result->num_rows() > 0) {

	print "<form style=\"margin-bottom:0;\" 
		action=\"".$this->url_prefix."/index.php/services/shipfieldassets\" method=post>".
		"<table width=720 cellpadding=5 cellspacing=1 border=0>".
		"<input type=hidden name=optionstable value=$optionstable>";
	print "<input type=hidden name=userserviceid value=$userserviceid>";

	print "<tr><td bgcolor=\"#ccccdd\" width=180><b>".lang('shipfieldasset')."</b></td>";

	// TODO: print drop down menu to pick new field assets from master_field_assets
	print "<td bgcolor=\"#ddddee\"><select name=master_field_assets_id><option selected>".
		"".lang('choose')."</option>\n";

	foreach ($field_asset_result->result_array() as $myresult) {
		$master_field_assets_id = $myresult['id'];
		$description = $myresult['description'];
		print "<option value=$master_field_assets_id>$description</option>\n";
	}

	echo "</select></td><tr>\n";    

	// print submit button
	print "<td></td><td><input name=fieldassets type=submit value=\"".lang('ship')."\" ".
		"class=smallbutton></td></table></form><p>";
}

/*----------------------------------------------------------------------*/
// print the usage multiple form section
print "<form action=\"".$this->url_prefix."/index.php/services/usage\" method=post>
<table width=720 cellpadding=5 cellspacing=1 border=0>";
print "<input type=hidden name=userserviceid value=$userserviceid>";

// print the usage_multiple entry field
// if there is a usage label, use that instead of the generic name
if($usage_label) {
	print "<tr><td bgcolor=\"#ccccdd\" width=180><b>$usage_label</b></td>";
} else {
	print "<tr><td bgcolor=\"#ccccdd\" width=180><b>".lang('usagemultiple')."</b></td>";
}
echo "<td bgcolor=\"#ddddee\"><input type=text name=usage_multiple ".
"value=$usage_multiple></td><tr>\n";
print "<td></td><td><input name=usage type=submit value=\"". lang('change') ."\" ".
"class=smallbutton></td></table></form>";

/*----------------------------------------------------------------------*/
// change the billing ID	
print "<form action=\"".$this->url_prefix."/index.php/services/changebillingid\" method=post>
<table width=720 cellpadding=5 cellspacing=1 border=0>";
print "<input type=hidden name=servicedescription ".
"value=\"$servicedescription\">";
print "<input type=hidden name=optionstable value=$optionstable>";
print "<input type=hidden name=userserviceid value=$userserviceid>";
echo "<td bgcolor=\"ccccdd\"width=180><b>". lang('billingid') ."</b></td>".
"<td bgcolor=\"#ddddee\">";

// print a list of billing id's that have the account number associated with
// them and match the organization_id for this service
print "<select name=billing_id><option selected value=$billing_id>".
"$billing_id</option>";

foreach ($org_billing_types->result_array() as $myresult) 
{
	$mybilling_id = $myresult['id'];
	$org_name = $myresult['org_name'];
	$billing_type = $myresult['name'];
	print "<option value=$mybilling_id>$mybilling_id ($org_name) $billing_type".
		"</option>";
}

echo "</select></td><tr>\n";

print "<td></td><td><input name=billing type=submit value=\"". lang('change') ."\" ".
"class=smallbutton></td></table></form>";

/*----------------------------------------------------------------------*/
// print the change service type change function only if there is an
// options table to keep the same attributes with a service

if ($optionstable) {

	print "<form action=\"".$this->url_prefix."/index.php/services/changeservicetype\" method=post>
		<table width=720 cellpadding=5 ".
		"cellspacing=1 border=0>\n";
	print "<input type=hidden name=load value=services>\n";
	print "<input type=hidden name=type value=module>\n";
	print "<input type=hidden name=edit value=on>\n";
	print "<input type=hidden name=servicedescription ".
		"value=\"$servicedescription\">\n";
	print "<input type=hidden name=optionstable value=$optionstable>\n";
	print "<input type=hidden name=userserviceid value=$userserviceid>\n";
	print "<input type=hidden name=billing_id value=$billing_id>\n";
	print "<input type=hidden name=usage_multiple value=$usage_multiple>\n";
	echo "<td bgcolor=\"ccccdd\"width=180><b>". lang('changeservice') ."</b></td>".
		"<td bgcolor=\"#ddddee\">\n";

	// print a list of services that share the same attributes and organization_id
	print "<select name=master_service_id><option selected ".
		"value=$master_service_id>$servicedescription (".lang('current').")</option>\n";

	$sharesoptions = $this->service_model->services_sharing_options($optionstable, $service_org_id);
	foreach ($sharesoptions as $myresult) 
	{
		$new_master_service_id = $myresult['id'];
		$service_description = $myresult['service_description'];
		// print listing without showing the current service
		if ($new_master_service_id <> $master_service_id) 
		{
			print "<option value=$new_master_service_id>$service_description</option>\n";
		}
	}

	echo "</select></td><tr>\n";

	print "<td></td><td><input name=servicetype type=submit ".
		"value=\"".lang('change')."\" class=smallbutton></td></table></form>";
} // end if options_table



/*--------------------------------------------------------------------------*/
//
// print the whole customer history for this service
//
/*--------------------------------------------------------------------------*/
echo "<table cellspacing=0 cellpadding=0 border=0>".
"<td bgcolor=\"#ccccdd\" style=\"padding: 4px;\" width=60>".
"<b>".lang('ticketnumber')."</b></td>".
"<td bgcolor=\"#ccccdd\" style=\"padding: 4px;\" width=150>".
"<b>".lang('datetime')."</b></td>".
"<td bgcolor=\"#ccccdd\" style=\"padding: 4px;\" width=70>".
"<b>".lang('createdby')."</b></td>".
"<td bgcolor=\"#ccccdd\" style=\"padding: 4px;\" width=70>".
"<b>".lang('notify')."</b></td>".
"<td bgcolor=\"#ccccdd\" style=\"padding: 4px;\" width=60>".
"<b>".lang('status')."</b></td>".
"<td bgcolor=\"#ccccdd\" style=\"padding: 4px;\" width=261>".
"<b>".lang('service')."</b></td>";

$linecount = 0;
foreach ($servicehistory as $myresult) 
{
	$id = $myresult['id'];
	$creation_date = $myresult['creation_date'];
	$created_by = $myresult['created_by'];
	$notify = $myresult['notify'];
	$status = $myresult['status'];
	$description = $myresult['description'];
	$linkname = $myresult['linkname'];
	$linkurl = $myresult['linkurl'];
	$serviceid = $myresult['user_services_id'];
	$service_description = $myresult['service_description'];

	// translate the status
	switch($status) {
		case 'automatic':
			$status = lang('automatic');
			break;
		case 'not done':
			$status = lang('notdone');
			break;
		case 'pending':
			$status = lang('pending');
			break;
		case 'completed':
			$status = lang('completed');
			break;
	}

	// alternate line colors
	if ($linecount & 1) {
		print "<tr bgcolor=\"#ddddee\">";
	} else {
		print "<tr bgcolor=\"#ddddff\">";
	}


	print "<td style=\"border-top: 1px solid grey; padding-top: 2px; ".
		"padding-bottom: 2px; font-size: 9pt; font-weight: bold;\"><a target=\"_parent\" ". 
		"href=\"$this->url_prefix/index.php/support/editticket/$id\">".
		"$id</a> &nbsp;</td>";
	print "<td style=\"border-top: 1px solid grey; padding-top: 2px; ".
		"padding-bottom: 2px; font-size: 9pt; font-weight: bold;\">$creation_date &nbsp;</td>";
	print "<td style=\"border-top: 1px solid grey; padding-top: 2px; ".
		"padding-bottom: 2px; font-size: 9pt; font-weight: bold;\">$created_by &nbsp;</td>";
	print "<td style=\"border-top: 1px solid grey; padding-top: 2px; ".
		"padding-bottom: 2px; font-size: 9pt;font-weight: bold;\">$notify &nbsp;</td>";
	print "<td style=\"border-top: 1px solid grey; padding-top: 2px; ".
		"padding-bottom: 2px; font-size: 9pt; font-weight: bold;\">$status &nbsp;</td>";
	print "<td style=\"border-top: 1px solid grey; padding-top: 2px; ".
		"padding-bottom: 2px; font-size: 9pt; font-weight: bold;\">".
		"<a href=\"$this->url_prefix/index.php/services/edit/$serviceid\" target=\"_parent\">
		$serviceid $service_description</a> &nbsp; ";

	if ($linkurl) {
		print "<a href=\"$linkurl\">$linkname</a>";
	}

	print "</td>";

	// alternate line colors
	if ($linecount & 1) {
		print "<tr bgcolor=\"#ddddee\">";
	} else {
		print "<tr bgcolor=\"#ddddff\">";
	}

	// add br tags to line breaks with nl2br
	print "<td colspan=6 style=\"font-size: 10pt; padding-bottom: 5px;\">&nbsp;";
	echo nl2br($description);

	// get the sub_history printed here
	$sub_history = $this->support_model->get_sub_history($id);
	foreach($sub_history as $mysubresult) 
	{
		$mydatetime = $mysubresult['month']."/".$mysubresult['day']." ".$mysubresult['hour'].":".$mysubresult['minute'];
		$sub_created_by = $mysubresult['created_by'];
		$sub_description = $mysubresult['description'];

		// if today, show time
		// if creation date not today, show date/time

		// add br tags to line breaks
		print "<br>$mydatetime $sub_created_by: ";
		echo nl2br($sub_description);
	}

	echo "</td>";

	// increment line count to make even/odd coloring
	$linecount++;
}

echo '</table>';
