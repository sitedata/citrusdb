<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Portal API Controller
 *
 * This is the portal API that allows the customer self-service portal to
 * communicate with the main citrusdb system
 *
 * view customer profile 
 * view billing info (without encrypted card data)
 * view services list and attributes
 * view invoice history
 * view past invoices as text or pdf format
 * view payment history
 * request new services
 * update billing info 
 */

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH.'/libraries/REST_Controller.php';

class Portal extends REST_Controller
{
	/*
	 * -------------------------------------------------------------------------
	 *  get customer profile
	 * ------------------------------------------------------------------------
	 */
	function customer_get()
	{
		// load the customer model we are about to use
		$this->load->model('customer_model');

		// grab customer information (name, address, etc)
		// use the authuser var we set after authentication
		$data = $this->customer_model->record($this->authuser);
		$this->response($data);
		
	}


	/*
	 * -------------------------------------------------------------------------
	 *  get list of billing profile
	 * ------------------------------------------------------------------------
	 */
	function listbilling_get()
	{
		// load the customer model we are about to use
		$this->load->model('billing_model');

		if(!$this->get('id'))
		{
			$this->response(NULL, 400);
		}
		
		// grab list of billing record data information
		// will grab more than one billing id if they have multiple
		$data['record'] = $this->billing_model->record_list($this->account_number);
		$this->response($data);
		
	}
	function user_get()
    {
        if(!$this->get('id'))
        {
        	$this->response(NULL, 400);
        }

        // $user = $this->some_model->getSomething( $this->get('id') );
    	$users = array(
			1 => array('id' => 1, 'name' => 'Some Guy', 'email' => 'example1@example.com', 'fact' => 'Loves swimming'),
			2 => array('id' => 2, 'name' => 'Person Face', 'email' => 'example2@example.com', 'fact' => 'Has a huge face'),
			3 => array('id' => 3, 'name' => 'Scotty', 'email' => 'example3@example.com', 'fact' => 'Is a Scott!'),
		);
		
    	$user = @$users[$this->get('id')];
    	
        if($user)
        {
            $this->response($user, 200); // 200 being the HTTP response code
        }

        else
        {
            $this->response(array('error' => 'User could not be found'), 404);
        }
    }
    
    function user_post()
    {
        //$this->some_model->updateUser( $this->get('id') );
        $message = array('id' => $this->get('id'), 'name' => $this->post('name'), 'email' => $this->post('email'), 'message' => 'ADDED!');
        
        $this->response($message, 200); // 200 being the HTTP response code
    }
    
    function user_delete()
    {
    	//$this->some_model->deletesomething( $this->get('id') );
        $message = array('id' => $this->get('id'), 'message' => 'DELETED!');
        
        $this->response($message, 200); // 200 being the HTTP response code
    }
    
    function users_get()
    {
        //$users = $this->some_model->getSomething( $this->get('limit') );
        $users = array(
			array('id' => 1, 'name' => 'Some Guy', 'email' => 'example1@example.com'),
			array('id' => 2, 'name' => 'Person Face', 'email' => 'example2@example.com'),
			array('id' => 3, 'name' => 'Scotty', 'email' => 'example3@example.com'),
		);
        
        if($users)
        {
            $this->response($users, 200); // 200 being the HTTP response code
        }

        else
        {
            $this->response(array('error' => 'Couldn\'t find any users!'), 404);
        }
    }
}
