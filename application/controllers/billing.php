<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Billing extends App_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('customer_model');
		$this->load->model('billing_model');
		$this->load->model('module_model');
		$this->load->model('user_model');
	}		
	
    /*
     * ------------------------------------------------------------------------
     * Customer overview of everything
     * ------------------------------------------------------------------------
     */
    public function index()
    {
		// check permissions	
		$permission = $this->module_model->permission($this->user, 'billing');

		if ($permission['view'])
		{
			// load the module header common to all module views
			$this->load->view('module_header_view');
			
			// get the billing id
			$billing_id = $this->billing_model->default_billing_id($this->account_number);
			
			// show the billing information (name, address, etc)
			$data = $this->billing_model->record($billing_id);
			$this->load->view('billing/index_view', $data);
			
			// show any alternate billing types
			$data['alternate'] = $this->billing_model->alternates($this->account_number, $billing_id);
			$data['userprivileges'] = $this->user_model->user_privileges($this->user);
			$this->load->view('billing/alternate_view', $data);
			
			// the history listing tabs
			$this->load->view('historyframe_tabs_view');			
			
			// the html page footer
			$this->load->view('html_footer_view');
			
		}
		else
		{
			
			$this->module_model->permission_error();
			
		}	
		
	}
	
	public function edit($billing_id)
	{
		// check permissions
		$permission = $this->module_model->permission($this->user, 'billing');
		
		if ($permission['modify'])
		{
			// the module header common to all module views
			$this->load->view('module_header_view');
			
			// show the edit customer form
			$data = $this->billing_model->record($billing_id);
			$this->load->view('billing/edit_view', $data);

			// the history listing tabs
			$this->load->view('historyframe_tabs_view');			
			
			// show html footer
			$this->load->view('html_footer_view');
		}
		else
		{
			$this->module_model->permission_error();
		}
	}

	
	/*
	 * ------------------------------------------------------------------------
	 *  save the input from the edit billing view
	 * ------------------------------------------------------------------------
	 */
	public function save()
	{
		// check if there is a non-masked credit card number in the input
		// if the second cararcter is a * then it's already masked

		$newcc = FALSE; // set to false so we don't replace it unnecessarily

		// get some inputs that we need to process this input
		$billing_id = $this->input->post('billing_id');
		$billing_type = $this->input->post('billing_type');
		$from_date = $this->input->post('from_date');
		$creditcard_number = $this->input->post('creditcard_number');
		
		// check if the credit card entered already masked and not blank
		// eg: a replacement was not entered
		if ($creditcard_number[1] <> '*' AND $creditcard_number <> '')
		{			
			// destroy the output array before we use it again
			unset($encrypted);

			// load the encryption helper for use when calling gpg things
			$this->load->helper('encryption');

			// run the gpg command
			$encrypted = encrypt_command($this->config->item('gpg_command'), $creditcard_number);
			
			// if there is a gpg error, stop here
			if (substr($encrypted,0,5) == "error")
			{				
				die ("Credit Card Encryption Error: $encrypted");
			}

			// change the ouput array into ascii ciphertext block
			$encrypted_creditcard_number = $encrypted;

			// wipe out the middle of the creditcard_number before it gets inserted
			$length = strlen($creditcard_number);
			$firstdigit = substr($creditcard_number, 0,1);
			$lastfour = substr($creditcard_number, -4);
			$creditcard_number = "$firstdigit" . "***********" . "$lastfour";
			
			$newcc = TRUE;
		}
		
		// fill in the billing data array with new info
		$billing_data = array(
			'name' => $this->input->post('name'),
			'company' => $this->input->post('company'),
			'street' => $this->input->post('street'),
			'city' => $this->input->post('city'),
			'state'=> $this->input->post('state'),
			'zip' => $this->input->post('zip'),
			'country' => $this->input->post('country'),
			'phone' => $this->input->post('phone'),
			'fax' => $this->input->post('fax'),
			'billing_type' => $this->input->post('billing_type'),
			'creditcard_expire'=> $this->input->post('creditcard_expire'),
			'next_billing_date' => $this->input->post('next_billing_date'),
			'from_date' => $this->input->post('from_date'),
			'payment_due_date' => $this->input->post('payment_due_date'),
			'notes' => $this->input->post('notes'),
			'pastdue_exempt' => $this->input->post('pastdue_exempt'),
			'po_number'=> $this->input->post('po_number'),
			'automatic_receipt' => $this->input->post('automatic_receipt'), 
			'contact_email' => $this->input->post('contact_email')
			);

		// check if rerun_date should be NULL or not
		$rerun_date = $this->input->post('rerun_date');
		if ($rerun_date == "0000-00-00")
		{
			// rerun date is null
			$billing_data['rerun_date'] = NULL;
		}		
		else
		{
			// rerun date has something in it
			$billing_data['rerun_date'] = $rerun_date;
		}
		
		if ($newcc == TRUE)
		{
			// insert with a new credit card and encrypted ciphertext
			$billing_data['encrypted_creditcard_number'] = $encrypted_creditcard_number;
			$billing_data['creditcard_number'] = $creditcard_number;
			$billing_data['creditcard_expire'] = $this->input->post('creditcard_expire');
		}
		elseif ($creditcard_number == '')
		{
			$billing_data['encrypted_creditcard_number'] = NULL;
			$billing_data['creditcard_number'] = NULL;
			$billing_data['creditcard_expire'] = NULL;		
		}
		
		// save the data to the customer record
		$data = $this->billing_model->save_record($billing_id, $billing_data);			

		// set the to_date automatically
		$this->billing_model->automatic_to_date($from_date, $billing_type, $billing_id);

		// add a log entry that this billing record was edited
		$this->log_model->activity($this->user,$this->account_number,'edit','billing',$billing_id,'success');

		print "<h3>". lang('changessaved') ."<h3>";

		redirect('/billing');
	}


	/*
	 * -------------------------------------------------------------------------
	 *  add billing function, ask if the user wants to add a billing record
	 *  with a specific organization id
	 * -------------------------------------------------------------------------
	 */
	public function addbilling()
	{
		// check permissions
		$permission = $this->module_model->permission($this->user, 'billing');
		
		if ($permission['modify'])
		{
			// the module header common to all module views
			$this->load->view('module_header_view');
			
			// show the add billing record form
			$this->load->view('billing/add_billing_record_view');

			// the history listing tabs
			$this->load->view('historyframe_tabs_view');			
			
			// show html footer
			$this->load->view('html_footer_view');
		}
		else
		{
			$this->module_model->permission_error();
		}
	}
	

	/*
	 * ------------------------------------------------------------------------
	 *  create a new billing record, called by the prompt that asks if they
	 *  want to create a new billing record under a specific organization id
	 * ------------------------------------------------------------------------
	 */
	public function create()
	{
		// check permissions
		$permission = $this->module_model->permission($this->user, 'billing');

		$organization_id = $this->input->post('organization_id');		
		
		if ($permission['create'])
		{
			// create the billing record
			$this->billing_model->create_record($organization_id, $this->account_number);

			// log this record creation
			$this->log_model->activity($this->user,$this->account_number,'create',
					'billing',0,'success');

		}
		else
		{
			$this->module_model->permission_error();
		}
	}


	/*
	 * ------------------------------------------------------------------------
	 *  asks the user whether they are sure they want to cancel this customer
	 * ------------------------------------------------------------------------
	 */
	public function cancel()
	{
		// check permissions
		$permission = $this->module_model->permission($this->user, 'customer');

		if ($permission['remove'])
		{
			// load the service model to check carrier dependent
			$this->load->model('service_model');			

			// check if the services on the account are carrier_dependent
			// if it is carrier dependent, then send user to the
			// carrier dependent cancel form instead of the regular cancel system
			$dependent = $this->service_model->carrier_dependent($this->account_number);

			if ($dependent == true) {
				// print a message that this customer is carrier dependent
				echo "<h3>" . lang('carrierdependentmessage') . "</h3><p align=center>";

				// get the dependent_cancel_url from the settings table
				$query = "SELECT dependent_cancel_url FROM settings WHERE id = 1";
				$result = $this->db->query($query) or die ("$l_queryfailed");
				$myresult = $result->row();
				$dependent_cancel_url = $myresult->dependent_cancel_url;

				// print a link to the url to fill out the carrier dependent cancel form
				print "<a href=\"$dependent_cancel_url$this->account_number\" target=\"_BLANK\">" . lang('cancelcustomer') . "</a></p>";

			}

			// check if the user has manager privileges
			$userprivileges = $this->user_model->user_privileges($this->user);

			if ($dependent == false OR $user_privileges['manager'] == 'y') {
				// show the regular cancel form for non carrier dependent and for managers
				// ask if they are sure they want to cancel this customer
				print "<br><br>";
				print "<h4>" . lang('areyousurecancel') .": $this->account_number</h4>";
				print "<table cellpadding=15 cellspacing=0 border=0 width=720>";
				print "<td align=right width=240>";

				// if they hit yes, this will sent them into the delete.php file and remove the service on their next billing anniversary

				print "<form style=\"margin-bottom:0;\" action=\"" . $this->url_prefix . "/index.php/customer/whycancel\" method=post>";
				print "<input name=whycancel type=submit value=\"". lang('yes') . "\" class=smallbutton></form></td>";

				// if they hit no, send them back to the service edit screen

				print "<td align=left width=240>";
				print "<form style=\"margin-bottom:0;\" action=\"" . $this->url_prefix . "/index.php/customer\" method=post>";
				print "<input name=done type=submit value=\" ". lang('no') . " \" class=smallbutton>";
				print "</form></td>";

				// if they hit Remove Now, send them to delete.php and remove the 
				// service on the next available work date, the next valid billing date

				print "<td align=left width=240>";
				print "<form style=\"margin-bottom:0;\" action=\"" . $this->url_prefix . "/index.php/customer/whycancel/now\" method=post>";
				print "<input name=whycancel type=submit value=\"". lang('remove_now') . "\" class=smallbutton>";  
				print "</form></td>";

				print "</table>";
				print "</blockquote>";
			}

		}
		else 
		{
			$this->module_model->permission_error();
		}
	}


	/*
	 * -------------------------------------------------------------------------
	 *  asks the user why this customer is canceling their account
	 *  optionally add a /now to the input to cancel today intead of anniversary date
	 * -------------------------------------------------------------------------
	 */


	/*
	 * -------------------------------------------------------------------------
	 *  marks a customer record as canceled and moves their services to history
	 *  optionally add a /now to the input to cancel today intead of anniversary date
	 * -------------------------------------------------------------------------
	 */
	public function delete($now = NULL)
	{
		// check permissions
		$permission = $this->module_model->permission($this->user, 'customer');

		if ($permission['remove'])
		{
			// load the models for functions we use
			$this->load->model('billing_model');			
			$this->load->model('support_model');			
			$this->load->model('log_model');			
			$this->load->model('service_model');			

			// get the cancel reason input
			$cancel_reason = $this->input->post('cancel_reason');


			// set the removal date correctly for now or later
			if ($now == "on") {
				// they should be removed as immediately as possible
				//so use the next billing date as the removal date
				$removal_date = $this->billing_model->get_nextbillingdate();
			} else {
				// figure out the customer's current next billing anniversary date
				$query = "SELECT b.next_billing_date FROM customer c " .
					"LEFT JOIN billing b ON c.default_billing_id = b.id ".
					"WHERE c.account_number = '$this->account_number'";
				$result = $this->db->query($query) or die ("$query $l_queryfailed");
				$myresult = $result->row_array();
				$next_billing_date = $myresult['next_billing_date'];

				// split date into pieces
				$datepieces = explode('-', $next_billing_date);

				$myyear = $datepieces[0];
				$mymonth = $datepieces[1]; 
				$myday = $datepieces[2]; 

				// removal date is normally the anniversary billing date
				$removal_date  = $next_billing_date;

				// today's date
				$today  = date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d"), date("Y")));
				// if the next billing date is less than today, remove them next available day
				if ($removal_date < $today) 
				{
					$removal_date = $this->billing_model->get_nextbillingdate();
				}
			}

			// figure out all the services that the customer has and delete each one.
			$query = "SELECT * FROM user_services 
				WHERE account_number = '$this->account_number' AND removed <> 'y'";
			$result = $this->db->query($query) or die ("query failed");
			foreach ($result->result_array() as $myserviceresult) 
			{
				$userserviceid = $myserviceresult['id'];
				$this->service_model->delete_service($userserviceid,'canceled',
						$removal_date);
				$this->log_model->activity($this->user,$this->account_number,
						'delete','service',$userserviceid,'success');
			}

			// set cancel date and removal date of customer record
			$query = "UPDATE customer ".
				"SET cancel_date = CURRENT_DATE, ". 
				"cancel_reason = '$cancel_reason' ".
				"WHERE account_number = '$this->account_number'";
			$result = $this->db->query($query) or die ("query failed");

			// set next_billing_date to NULL since it normally won't be billed again
			$query = "UPDATE billing ".
				"SET next_billing_date = NULL ". 
				"WHERE account_number = '$this->account_number'";
			$result = $this->db->query($query) or die ("query failed");   

			// get the text of the cancel reason to use in the note
			$query = "SELECT * FROM cancel_reason " . 
				"WHERE id = '$cancel_reason'";
			$result = $this->db->query($query) or die ("query failed");
			$myresult = $result->row_array();
			$cancel_reason_text = $myresult['reason'];

			// add cancel ticket to customer_history
			// if they are carrier dependent, send a note to
			// the billing_noti
			$desc = lang('canceled') . ": $cancel_reason_text";
			$this->support_model->create_ticket($this->user, NULL, 
					$this->account_number, 'automatic', $desc);

			// get the billing_id for the customer's payment_history
			$query = "SELECT default_billing_id FROM customer " . 
				"WHERE account_number = '$this->account_number'";
			$result = $this->db->query($query) or die ("$l_queryfailed");
			$myresult = $result->row_array();
			$default_billing_id = $myresult['default_billing_id'];

			// add a canceled entry to the payment_history
			$query = "INSERT INTO payment_history ".
				"(creation_date, billing_id, status) ".
				"VALUES (CURRENT_DATE,'$default_billing_id','canceled')";
			$paymentresult = $this->db->query($query) or die ("$l_queryfailed");

			// log this customer being canceled/deleted
			$this->log_model->activity($this->user,$this->account_number,
					'cancel','customer',0,'success');

			// redirect them to the customer page	
			redirect('/customer');

		}
		else 
		{
			$this->module_model->permission_error();
		}


	}


	public function rerun($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		// clear any rerun date so it's ready for a new one	
		$data = $this->billing_model->clearrerundate($billing_id);	

		// load the reset addr view prompt
		$data['rerunitems'] = $this->billing_model->rerunitems($billing_id);	
		$this->load->view('billing/rerun_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}


	public function savererun()
	{
		// GET Variables
		$billing_id = $base->input['billing_id'];
		$fieldlist = $base->input['fieldlist'];

		// set the rerun date to the next available billing date
		$mydate = get_nextbillingdate();

		// make sure the rerun date is not set to the same as the next_billing_date
		$query = "SELECT next_billing_date FROM billing WHERE id = '$billing_id'";
		$DB->SetFetchMode(ADODB_FETCH_ASSOC);
		$result = $DB->Execute($query) or die ("$l_queryfailed");
		$myresult = $result->fields;	
		$next_billing_date = $myresult['next_billing_date'];

		if ($next_billing_date == $mydate) {
			echo "<h3>$l_rerundateerror</h3>".
				"<center><form style=\"margin-bottom:0;\" action=\"index.php\">".
				"<input name=done type=submit value=\" $l_ok  \" class=smallbutton>".
				"<p></center>";
		} else {
			$query = "UPDATE billing SET rerun_date = '$mydate' ".
				"WHERE id = '$billing_id'";
			$result = $DB->Execute($query) or die ("$l_queryfailed");

			// parse the fieldlist to set the rerun flag for the items chosen     
			// add the services to the user_services table and the options table
			$fieldlist = substr($fieldlist, 1); 

			// loop through post_vars associative/hash to get field values
			$array_fieldlist = explode(",",$fieldlist);

			foreach ($base->input as $mykey => $myvalue) {
				foreach ($array_fieldlist as $myfield) {
					//print "$mykey<br>";
					if ($myfield == $mykey) {
						$fieldvalues .= ',\'' . $myvalue . '\'';
						// set the rerun flag for this billing_detail id value to 'y'
						$query = "UPDATE billing_details SET rerun = 'y' ".
							"WHERE id = '$myvalue'";
						$result = $DB->Execute($query) or die ("$l_queryfailed");

						// TODO: do I need to unset all the other things rerun items too?  

					}
				}
			}

			print "<h3>$l_changessaved<h3>";
			redirect('/billing');

		}
	}




	public function resetaddr()
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		// load the reset addr view prompt
		$this->load->view('billing/resetaddr_view');

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}

	public function saveresetaddr()
	{
		// get the customer information
		$query = "SELECT * FROM customer WHERE account_number = $this->account_number";
		$result = $this->db->query($query) or die ("$l_queryfailed");
		$myresult = $result->row_array();

		$name = $myresult['name'];
		$company = $myresult['company'];
		$street = $myresult['street'];
		$city = $myresult['city'];
		$state = $myresult['state'];
		$zip = $myresult['zip'];
		$country = $myresult['country'];
		$phone = $myresult['phone'];
		$fax = $myresult['fax'];
		$contact_email = $myresult['contact_email'];
		$default_billing_id = $myresult['default_billing_id'];	

		// save billing address
		$query = "UPDATE billing 
			SET name = '$name',
				company = '$company',
				street = '$street',
				city = '$city',
				state = '$state',
				zip = '$zip',
				country = '$country',
				phone = '$phone',
				fax = '$fax',
				contact_email = '$contact_email' WHERE id = $default_billing_id";
		$result = $this->db->query($query) or die ("$l_queryfailed");
		print "<h3>".lang('changessaved')."<h3>";

		redirect ('/billing');	
	}



	/*
	 * --------------------------------------------------------------------------------
	 *  ask the user if they are sure they want to turnoff this customer
	 * --------------------------------------------------------------------------------
	 */
	public function turnoff($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;
		$this->load->view('billing/turnoff_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}


	/*
	 * --------------------------------------------------------------------------------
	 *  set the account to the turnoff status when that is chosen
	 * --------------------------------------------------------------------------------
	 */
	public function saveturnoff()
	{
		// get id input from form
		$billing_id = $this->input->post('billing_id');
	
		// set the payment history to turnoff
		$this->billing_model->turnedoff_status($billing_id);	

		redirect('/billing');
	}


	/*
	 * --------------------------------------------------------------------------------
	 *  ask the user if they are sure they want to set this status to waiting
	 * --------------------------------------------------------------------------------
	 */
	public function waiting($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;
		$this->load->view('billing/waiting_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}


	/*
	 * --------------------------------------------------------------------------------
	 *  set the account to the waiting status when that is chosen
	 * --------------------------------------------------------------------------------
	 */
	public function savewaiting()
	{
		// get the it from the input form
		$billing_id = $this->input->post('billing_id');

		// set the payment history to waiting
		$this->billing_model->waiting_status($billing_id);	

		redirect('/billing');
	}



	/*
	 * --------------------------------------------------------------------------------
	 *  ask the user if they are sure they want to set authorized this customer
	 * --------------------------------------------------------------------------------
	 */
	public function authorized($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;
		$this->load->view('billing/authorized_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}


	/*
	 * --------------------------------------------------------------------------------
	 *  set the account to the authorized status when that is chosen
	 * --------------------------------------------------------------------------------
	 */
	public function saveauthorized()
	{
		// get id input from form
		$billing_id = $this->input->post('billing_id');
	
		// set the payment history to turnoff
		$this->billing_model->authorized_status($billing_id);	

		redirect('/billing');
	}



	/*
	 * --------------------------------------------------------------------------------
	 *  ask the user if they are sure they want to set authorized this customer
	 * --------------------------------------------------------------------------------
	 */
	public function cancelwfee($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['cancel_date'] = $this->billing_model->check_canceled($this->account_number);
		$data['billing_id'] = $billing_id;

		$this->load->view('billing/cancelwfee_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}


	/*
	 * --------------------------------------------------------------------------------
	 *  set the account to the authorized status when that is chosen
	 * --------------------------------------------------------------------------------
	 */
	public function savecancelwfee()
	{
		// get id input from form
		$billing_id = $this->input->post('billing_id');
	
		// set the payment history to cancelwfee
		$this->billing_model->cancelwfee_status($billing_id);	

		redirect('/billing');
	}



	/*
	 * --------------------------------------------------------------------------------
	 *  ask the user if they are sure they want to set collections this customer
	 * --------------------------------------------------------------------------------
	 */
	public function collections($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['cancel_date'] = $this->billing_model->check_canceled($this->account_number);
		$data['cancel_date'] = "2011-11-11";
		$data['billing_id'] = $billing_id;

		$this->load->view('billing/collections_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}


	/*
	 * --------------------------------------------------------------------------------
	 *  set the account to the collections status when that is chosen
	 * --------------------------------------------------------------------------------
	 */
	public function savecollections()
	{
		// get id input from form
		$billing_id = $this->input->post('billing_id');
	
		// set the payment history to cancelwfee
		$this->billing_model->collections_status($billing_id);	

		redirect('/billing');
	}


	public function createinvoice($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;

		$this->load->view('billing/createinvoice_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}

	public function savecreateinvoice()
	{
		// get id input from form
		$billing_id = $this->input->post('billing_id');

		$method = $this->billing_model->get_billing_method($billing_id);

		/*--------------------------------------------------------------------*/
		// Create the billing data
		/*--------------------------------------------------------------------*/

		// determine the next available batch number
		$batchid = $this->billing_model->get_nextbatchnumber();

		$numtaxes = $this->billing_model->add_taxdetails(NULL, $billing_id,
				$method, $batchid, NULL);
		$numservices = $this->billing_model->add_servicedetails(NULL, $billing_id,
				$method, $batchid, NULL);

		// create billinghistory
		$this->billing_model->create_billinghistory($batchid, $method, $this->user);	

		echo lang('createdinvoice') . " $billing_id";

		redirect('/billing');

	}


	public function cancelnotice($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;

		// load the date helper for human format dates	
		$this->load->helper('date');

		// returns cancel_date and turnoff_date
		$myresult = $this->billing_model->notice_dates($billing_id);

		$data['cancel_date'] = $myresult['cancel_date'];

		$data['human_cancel_date'] = humandate($data['cancel_date']);

		$this->load->view('billing/cancelnotice_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}

	public function savecancelnotice()
	{
		// get input from form
		$billing_id = $this->input->post('billing_id');
		$cancel_date = $this->input->post('cancel_date');

		// load the Notice library
		$config = array (
				'notice_type' => 'cancel', 
				'billing_id' => $billing_id, 
				'method' => 'both', 
				'payment_due_date' => $cancel_date, 
				'turnoff_date' => $cancel_date, 
				'cancel_date' => $cancel_date
				);
		$this->load->library('Notice', $config);    

		// print link to the pdf to download
		$linkname = $this->notice->pdfname;
		$contactemail = $this->notice->contactemail;
		$linkurl = "index.php/tools/downloadfile/$linkname";

		echo "<p>".lang('sent_cancel_notice')." $contactemail</p>";
		echo "<p>".lang('download_pdf').": <a href=\"$linkurl\">$linkname</a></p>";
	}


	public function shutoffnotice($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;

		// load the date helper for human format dates	
		$this->load->helper('date');

		// returns cancel_date and turnoff_date
		$myresult = $this->billing_model->notice_dates($billing_id);

		$data['cancel_date'] = $myresult['cancel_date'];
		$data['turnoff_date'] = $myresult['turnoff_date'];

		$data['human_cancel_date'] = humandate($data['cancel_date']);

		$this->load->view('billing/shutoffnotice_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}

	public function saveshutoffnotice()
	{
		$mynotice = new notice('shutoff',$billing_id, 'both', $turnoff_date, $turnoff_date, $cancel_date);

		// print link to the pdf to download
		$linkname = $mynotice->pdfname;
		$contactemail = $mynotice->contactemail;
		$linkurl = "index.php?load=tools/downloadfile&type=dl&filename=$linkname";

		echo "<p>$l_sent_shutoff_notice_answer $contactemail</p>";
		echo "<p>$l_download_pdf: <a href=\"$linkurl\">$linkname</a></p>";

	}

	public function collectionsnotice($billing_id)
	{
		// load the module header common to all module views
		$this->load->view('module_header_view');

		$data['billing_id'] = $billing_id;

		// load the date helper for human format dates	
		$this->load->helper('date');

		// returns cancel_date and turnoff_date
		$myresult = $this->billing_model->notice_dates($billing_id);

		$data['cancel_date'] = $myresult['cancel_date'];
		$data['turnoff_date'] = $myresult['turnoff_date'];

		$data['human_cancel_date'] = humandate($data['cancel_date']);

		$this->load->view('billing/collectionsnotice_view', $data);

		// the history listing tabs
		$this->load->view('historyframe_tabs_view');			

		// the html page footer
		$this->load->view('html_footer_view');

	}

	public function savecollectionsnotice()
	{

		echo "<pre>";
		$mynotice = new notice('collections',$billing_id, 'both', $turnoff_date, $turnoff_date, $cancel_date);
		echo "</pre>";

		// print link to the pdf to download
		$linkname = $mynotice->pdfname;
		$contactemail = $mynotice->contactemail;
		$linkurl = "index.php?load=tools/downloadfile&type=dl&filename=$linkname";

		echo "<p>$l_sent_collections_notice_answer $contactemail</p>";
		echo "<p>$l_download_pdf: <a href=\"$linkurl\">$linkname</a></p>";

	}


	/*
	 * ------------------------------------------------------------------------
	 *  Invoice Maintenance view
	 *  shows list of customer invoices and links to print, delete, and pay
	 * ------------------------------------------------------------------------
	 */
	public function invmaint($billing_id, $showall = NULL)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		// load the reset addr view prompt
		$data['invoicelist'] = 
			$this->billing_model->list_invoices($billing_id, $showall);	
		$data['billingid'] = $billing_id;
		$data['showall'] = $showall;
		$this->load->view('billing/invmaint_view', $data);

	}


	/*
	 * ------------------------------------------------------------------------
	 *  call the invoice model to delete the indicated invoice
	 * ------------------------------------------------------------------------
	 */
	function deleteinvoice() 
	{
		// get the invoicenum input
		$invoicenum = $this->input->post('invoicenum');

		// Delete the invoice, delete from billing history where id = $invoicenum
		$query = "DELETE FROM billing_history WHERE id = $invoicenum";
		$result = $DB->Execute($query) or die ("$l_queryfailed");

		// delete from billing_details where invoice_number = $invoicenum
		$query = "DELETE FROM billing_details ".
			"WHERE invoice_number = $invoicenum";                                          
		$result = $DB->Execute($query) or die ("$l_queryfailed");

		print "$l_deleted $invoicenum";
	}


	/*
	 * ------------------------------------------------------------------------
	 *  Prompt the user to ask if they are sure they want to remove the invoice
	 * ------------------------------------------------------------------------
	 */
	function removeinvoice($invoicenum) 
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		// get the invoicenum input
		$data['invoicenum'] = $invoicenum;

		$this->load->view('billing/removeinvoice_view', $data);
	}


	/*
	 * ------------------------------------------------------------------------
	 *  Prompt the user to change the due date of the invoice
	 * ------------------------------------------------------------------------
	 */
	function editinvoiceduedate($billingid, $invoicenum, $duedate) 
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		// get the invoicenum input, and billing id
		$data['billingid'] = $billingid;
		$data['invoicenum'] = $invoicenum;
		$data['duedate'] = $duedate;

		$this->load->view('billing/editinvoiceduedate_view', $data);
	}



	/*
	 * ------------------------------------------------------------------------
	 *  save the new due date invoice for that invoice number
	 * ------------------------------------------------------------------------
	 */
	function saveinvoiceduedate() 
	{
		// get the invoicenum input, and billing id
		$invoicenum = $this->input->post('invoicenum');
		$billingid = $this->input->post('billingid');
		$duedate = $this->input->post('duedate');

		// update the invoice due date for this invoice
		$this->billing_model->update_invoice_duedate($duedate, $invoicenum);

		// redirect back to the services record for their account
		redirect("/billing/invmaint/$billingid");

	}


	/*
	 * ------------------------------------------------------------------------ 
	 *  show the refund report for the customer
	 * ------------------------------------------------------------------------ 
	 */
	function refund($billing_id)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$data['details'] = 
			$this->billing_model->billing_details($billing_id);	
		$data['billingid'] = $billing_id;
		$this->load->view('billing/refund_view', $data);

	}


	/*
	 * ------------------------------------------------------------------------ 
	 *  prompt the customer for the amount to refund for the chosen item
	 * ------------------------------------------------------------------------ 
	 */
	function refunditem($detailitem, $method, $billingid) 
	{	
		if ($method <> 'creditcard') {
			echo "<h2 style=\"color: red;\">$l_method_warning</h2>";
		}

		$data['detailid'] = $detailitem;
		$data['method'] = $method;
		$data['billingid'] = $billingid;

		// get the list of billing items that could be refunded
		$myresult = $this->billing_model->billing_detail_item($detailitem);

		$data['id'] = $myresult['d_id'];
		$data['date'] = $myresult['d_creation_date'];
		if ($myresult['d_taxed_services_id']) 
		{ 
			// it's a tax
			$data['description'] = $myresult['r_description'];
		} 
		else 
		{
			// it's a service
			$data['description'] = $myresult['m_description'];
		}
		$data['invoice'] = $myresult['d_invoice_number'];
		$data['billedamount'] = $myresult['d_billed_amount'];
		$data['paidamount'] = $myresult['d_paid_amount'];
		$data['refundamount'] = $myresult['d_refund_amount'];
		$data['refunded'] = $myresult['d_refunded'];

		// show refund item view
		$this->load->view('billing/refund_item_view', $data);
	}


	/*
	 * ------------------------------------------------------------------------ 
	 *  save the input from the amount to refund for the chosen item
	 * ------------------------------------------------------------------------ 
	 */
	function saverefunditem()
	{
		$billingid = $this->input->post('billingid');
		$detailid = $this->input->post('detailid');
		$refundamount = $this->input->post('refundamount');
		$method = $this->input->post('method');

		// reset the refund if amount entered is zero
		if ($refundamount == 0) 
		{
			$this->billing_model->reset_detail_refund_amount($detailid);
		} 
		else 
		{
			$this->billing_model->update_detail_refund_amount($detailid, $refundamount);
		}

		// if billing method is not credit card they must be done manually
		// just mark the amount as refunded in the database
		if ($method <> 'creditcard') 
		{
		$this->billing_model->manual_detail_refund_amount($detailid, $refundamount);

			print "<h2 style=\"color: red;\">".lang('method_warning')."</h2>";
		}

		print "<h3>".lang('changessaved')."<h3>";

		redirect("/billing/refund/$billingid");
	}


	public function asciiarmor($billing_id)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$data = $this->billing_model->get_ascii_armor($billing_id);
		$data['billing_id'] = $billing_id;

		$this->load->view('billing/asciiarmor_view', $data);
	}


	function saveasciiarmor()
	{
		$billing_id = $this->input->post('billing_id');
		$creditcard_number = $this->input->post('creditcard_number');
		$creditcard_expire = $this->input->post('creditcard_expire');
		$encrypted = $this->input->post('encrypted');

		// make sure the first lines says -----BEGIN PGP MESSAGE-----
		// make sure the last line says -----END PGP MESSAGE-----

		$encrypted_lines = explode("\n", $encrypted);
		$firstline = rtrim($encrypted_lines[0]); // rtrim to remove the newline character at the end
		$lastline = array_pop($encrypted_lines); // do not rtrim since no newline should be here

		if ($firstline <> "-----BEGIN PGP MESSAGE-----") 
		{
			echo "\"$firstline\" ";
			die ("Error first line of ciphertext format");
		}
	
		if ($lastline <> "-----END PGP MESSAGE-----") 
		{
			echo "\"$lastline\" ";
			die ("Error last line of  ciphertext format");
		}

		// make sure each line is no more than 65 characters long (includes newline)
		foreach ($encrypted_lines as $line) 
		{
			$length = strlen($line);
			//echo "$line<br>\n";
			if ($length > 65) 
			{
				die ("Error in ciphertext format lines");
			}
		}

		$this->billing_model->input_ascii_armor($encrypted, $creditcard_number, 
				$creditcard_expire, $billing_id);

		// add a log entry that this billing record was edited
		$this->log_model->activity($this->user,$this->account_number,'edit','creditcard',$billing_id,'success');  

		redirect('/billing');

	}





	/*
	 * ------------------------------------------------------------------------
	 *  show the billing history tab content for this customer
	 * ------------------------------------------------------------------------
	 */
	function billinghistory($all = NULL)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		if($all)
		{
			$data['history'] = $this->billing_model->allbillinghistory($this->account_number);

		}
		else
		{
			$data['history'] = $this->billing_model->billinghistory($this->account_number);
		}

		$this->load->view('billing/billinghistory_view', $data);
	}


	/*
	 * ------------------------------------------------------------------------
	 *  show the payment history tab content for this customer
	 * ------------------------------------------------------------------------
	 */
	function paymenthistory($all = NULL)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		if($all)
		{
			$data['history'] = $this->billing_model->allpaymenthistory($this->account_number);
		}
		else
		{
			$data['history'] = $this->billing_model->paymenthistory($this->account_number);
		}

		// check user privileges to see if we show them things like nsf or delete payment links
		$data['userprivileges'] = $this->user_model->user_privileges($this->user);
		
		$this->load->view('billing/paymenthistory_view', $data);
	}


	/*
	 * ------------------------------------------------------------------------
	 *  show the billing details history tab content for this customer
	 * ------------------------------------------------------------------------
	 */
	function detailhistory($all = NULL)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		if($all)
		{
			$data['history'] = $this->billing_model->alldetailhistory($this->account_number);
		}
		else
		{
			$data['history'] = $this->billing_model->detailhistory($this->account_number);
		}

		$this->load->view('billing/detailhistory_view', $data);
	}

	
	public function nsf($paymentid, $invoice_number, $amount, $billingid)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$data['paymentid'] = $paymentid;
		$data['invoice_number'] = $invoice_number;
		$data['amount'] = $amount;
		$data['billingid'] = $billingid;

		$this->load->view('billing/nsf_view', $data);
	}

	public function savensf()
	{
		// GET Variables
		$paymentid = $this->input->post('paymentid');
		$amount = $this->input->post('amount');
		$invoice_number = $this->input->post('invoicenum');
		$billingid = $this->input->post('billingid');

		$this->billing_model->set_nsf_funds($paymentid, $amount, $invoicenum, $billingid);

		// redirect back to the billing screen
		redirect('/billing');
	}

	public function deletepayment($paymentid)
	{
		// load the header without the sidebar to get the stylesheet in there
		$this->load->view('header_no_sidebar_view');

		$data['paymentid'] = $paymentid;

		$this->load->view('billing/deletepayment_view', $data);
	}

	function savedeletepayment()
	{
		// GET Variables
		$paymentid = $this->input->post('paymentid');

		$this->billing_model->delete_payment($paymentid);

		// redirect back to the billing screen
		redirect('/billing');
	}

	public function receipt()
	{
		if ($pallow_modify)
		{
			include('./modules/billing/receipt.php');
		}  else permission_error();
	}

}

/* End of file billing */
/* Location: ./application/controllers/billing.php */
