<?php
	
/**
 * AgileBill - Open Billing Software
 *
 * This body of work is free software; you can redistribute it and/or
 * modify it under the terms of the Open AgileBill License
 * License as published at http://www.agileco.com/agilebill/license1-4.txt
 * 
 * For questions, help, comments, discussion, etc., please join the
 * Agileco community forums at http://forum.agileco.com/ 
 *
 * @link http://www.agileco.com/
 * @copyright 2004-2008 Agileco, LLC.
 * @license http://www.agileco.com/agilebill/license1-4.txt
 * @author Tony Landis <tony@agileco.com> 
 * @package AgileBill
 * @version 1.4.93
 */
	
class import_plugin extends import
{
	function import_plugin()
	{
		# Configure the location of the HostAdmin salt file:
		$this->salt = 'C:\\Documents and Settings\\Tony\\My Documents\\WWW\\sites\\hostadmin\\salt.php';
		
		# Configure the database name, host, and login:
		$this->host	= 'localhost';
		$this->db	= 'hostadmin';
		$this->user = 'root';
		$this->pass = ''; 
		$this->type	= 'mysql';
		
		# If importing CC details, enter the gateway plugin to use for recurring charges:
		$this->gateway = 'AUTHORIZE_NET';
		
		# Do not change anything past this line:
		$this->name 		= 'hostadmin';
		$this->plugin		= 'hostadmin';
		$this->select_limit	= 50;
		
		$this->instructions = '<P><B>Preliminary Instructions:</B><P>
								
								<P>1) Open '. __FILE__ .' and edit the database and salt file settings...</P>
								
								<P>2) If you will be importing credit card details, paste the Checkout Plugin name from the checkout plugin 
								list page to the "$this->gateway" value that will be used to process all recurring charges... 
								this should be a gateway such as AUTHORIZE_NET or LINKPOINT.</P>
								 
																
								<p>3) Before starting with the import, you must be sure your currency settings,
								checkout plugins, etc,. are all configured to the proper defaults.</p>
								
								<p>4) You can then run steps 1 - 6 below...</p>
								
								<p>5) IMPORTANT: After completing step 6 below and BEFORE running any of the other steps,
								go to Hosting Setup > List Servers and select the proper Provisioning Plugin and enter your
								IP for Name based accounts, as this is the IP that will be assigned to the imported hosting services.
								Also, go to Products > List and for every imported hosting plan, click on "Hosting" and setup
								the hosting details. These are the settings that will be assigned to the imported hosting plans.</p>
								  
								<p>6) You can now continue with steps 7 - 8.</p>		
		';
						
		$this->actions[]	= Array (	'name' => 'test',
										'desc' => '<b>Step 1:</b> Test the database connection',
										'depn' => false );
																				
		$this->actions[]	= Array (	'name' => 'accounts',
										'desc' => '<b>Step 2:</b> Import the HostAdmin accounts',
										'depn' => Array('test') );

		$this->actions[]	= Array (	'name' => 'billing',
										'desc' => '<b>Step 3:</b> Import the HostAdmin account billing details',
										'depn' => Array('accounts') );
			 
		$this->actions[]	= Array (	'name' => 'servers',
										'desc' => '<b>Step 4:</b> Import the HostAdmin servers',
										'depn' => Array('accounts','billing') );
																									
		$this->actions[]	= Array (	'name' => 'products',
										'desc' => '<b>Step 5:</b> Import the HostAdmin hosting packages',
										'depn' => Array('accounts','billing','servers') );
										
		$this->actions[]	= Array (	'name' => 'host_tld',
										'desc' => '<b>Step 6:</b> Import the HostAdmin TLD settings',
										'depn' => Array('accounts','billing','servers','products') );										
 					   
		$this->actions[]	= Array (	'name' => 'invoices',
										'desc' => '<b>Step 7:</b> Import the HostAdmin invoices',
										'depn' => Array('accounts','billing','products','host_tld') );

		$this->actions[]	= Array (	'name' => 'services',
										'desc' => '<b>Step 8:</b> Import the HostAdmin hosting subscriptions and domain records',
										'depn' => Array('accounts','billing','products','host_tld','invoices') );	
 

	}
	
	# test remote database connectivity
	function test()
	{
		global $C_debug, $VAR;
		
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db);  		 
		if( empty($this->host) || empty($this->user) || empty($this->db) || $dbr === false || @$dbr->_errorMsg != "") {  
			$C_debug->alert('Failed: ' . $dbr->_errorMsg);
		}  else {
			$C_debug->alert('Connected OK!'); 
			$db  = &DB();
			$id  = $db->GenID(AGILE_DB_PREFIX.'import_id');
        	$sql = "INSERT INTO ".AGILE_DB_PREFIX."import 
        			SET
        			id 			= $id,
        			site_id 	= ".DEFAULT_SITE.",
        			date_orig	= ".time().",
					plugin 		= ".$db->qstr($VAR['plugin']).",
					action 		= ".$db->qstr($VAR['action']);
        	$db->Execute($sql); 
		} 
		
		# return to main import page
		echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";
	}
	
	
	
	
	
	# import the account and billing details 
	function accounts()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		  
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 
		  
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;
 
		# select each account from HostAdmin
		$sql = "SELECT * FROM account";
		$rs = $dbr->SelectLimit($sql, $this->select_limit, $VAR['offset']);
		if($rs === false) {
			$C_debug->alert("Query to the table 'account' failed!");	
			return false;
		}		
		
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		}
		 
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each remote account
		while(!$rs->EOF)
		{
			$msg.= "<BR>Processing account: {$rs->fields['account_email']}...";
			
			# start a new transaction for the insert:
			$db = &DB();
			$db->StartTrans();
			
			# Get a local account id
			$id = $db->GenID($p.'account_id');
			
			# Get orig date
			if(!empty($rs->fields['orig_date'])) {
				$date = explode('-', $rs->fields['orig_date']);
				$date_orig = mktime(0,0,0,$date[1], $date[2], $date[0]);
			} else {
				$date_orig = time();
			}
			
			# Get the first/last name
			$name = explode(' ', $rs->fields['account_name']);
			@$firstn = $name[0];
			@$c = count($name) -1;
			@$lastn = $name[$c];
			
			# Insert the account
			$sql = "INSERT INTO {$p}account SET
					id 			= $id,
					site_id		= $s,
					date_orig	= $date_orig,
					date_last	= ".time().",
					language_id	= ".$db->qstr(DEFAULT_LANGUAGE).",
					currency_id	= ".DEFAULT_CURRENCY.",
					theme_id	= ".$db->qstr(DEFAULT_THEME).",
					username	= ".$db->qstr($rs->fields['account_email']).",
					password	= ".$db->qstr(md5($rs->fields['account_password'])).",
					status		= 1,
					country_id	= {$rs->fields['account_country']},
					first_name	= ".$db->qstr($firstn).",
					last_name	= ".$db->qstr($lastn).",
					company		= ".$db->qstr($rs->fields['account_company']).",
					address1	= ".$db->qstr($rs->fields['account_address']).",
					city		= ".$db->qstr($rs->fields['account_city']).",
					state		= ".$db->qstr($rs->fields['account_state']).",
					zip			= ".$db->qstr($rs->fields['account_zip']).",
					email		= ".$db->qstr($rs->fields['account_email']).",
					email_type	= 0";
			$db->Execute($sql);
			
			# Insert the import record
			$this->import_transaction($this->plugin, $VAR['action'], 'account', $id, 'account', $rs->fields['account_id'], &$db);		
 	 			
			# Complete the transaction
        	$db->CompleteTrans(); 
			$rs->MoveNext();
		}	

		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			  setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1200);
			 </script>"; 
	}
	
	
	
	
	
	### Import the billing details for each account
	function billing()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		
		# validate the salt file...
		if(!is_file($this->salt)) {
			$C_debug->alert('The path to the salt file set in the plugin script '. __FILE__.' is incorrect');
			return;
		}
		
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;		
		
		### Select from the imported accounts
		$db = &DB();
		$sql = "SELECT * FROM {$p}import WHERE
				plugin 		= '{$this->plugin}' AND
				action 		= 'accounts' AND
				ab_table 	= 'account' AND
				site_id		= $s";
		$rs = $db->SelectLimit($sql, $offset);
		if($rs === false) {
			$C_debug->alert("Query to the table 'import' failed!");	
			return false;
		}	
  
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		} 
		
		### Include AB Encryption class:
		include_once(PATH_CORE.'crypt.inc.php');
		
		
		### Get the default checkout plugin id:		
		$sql = "SELECT id FROM {$p}checkout WHERE site_id = $s AND checkout_plugin = '{$this->gateway}'";
		$ch = $db->Execute($sql);		
		$checkout_plugin_id = $ch->fields['id'];
				
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each remote billing record
		while(!$rs->EOF)
		{
			$msg.= "<BR>Processing Account Id: {$rs->fields['ab_id']}...";
			
			# start a new transaction for the insert: 
			$db->StartTrans();
			
			# Get the local account id
			$ab_account_id = $rs->fields['ab_id'];
			$remote_account_id = $rs->fields['remote_id'];
			 
			# Connect to the remote DB and get all billing records for this
			# account, where the cc_num is not blank
			$dbr = &NewADOConnection($this->type);
			$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 		
			$sql = "SELECT * FROM billing WHERE
					billing_account_id = $remote_account_id AND
					billing_cc_num != ''";
			$billing = $dbr->Execute($sql);
			if($billing != false && $billing->RecordCount() > 0)
			{
				while(!$billing->EOF)
				{ 
					# Get local billing id
					$db = &DB();
					$id = $db->GenID($p.'account_billing_id');

 			
					# Decrypt the remote CC 
					$cc_num_plain = $this->RC4($billing->fields['billing_cc_num'], 'de');
					
					# Encrypt to local algorythm
					$card_num = CORE_encrypt ($cc_num_plain);
							 		
					# get the last 4 digits:
					$last_four = preg_replace('/^............/', '', $cc_num_plain);
					 
					# Identify the card type:
					$card_type = $this->cc_identify($cc_num_plain);
					
					# Get the month  & year
					$exp = explode('20', trim($billing->fields['billing_cc_exp']));
					$exp_month = @$exp[0]; 
					$exp_year = @$exp[1];
					 
					if($card_type != '') 
					{ 
						# Start transaction
						$db->StartTrans();
											
						# Insert local billing record 
						$sql = "INSERT INTO {$p}account_billing SET
								id 					= $id,
								site_id				= $s,  
								account_id			= $ab_account_id,
								checkout_plugin_id 	= $checkout_plugin_id,
								card_type			= '$card_type',
								card_num			= ".$db->qstr($card_num).",
								card_num4			= '$last_four',
								card_exp_month		= '$exp_month',
								card_exp_year		= '$exp_year'";
						$db->Execute($sql);
						
						# Insert the import record
						$this->import_transaction($VAR['plugin'], $VAR['action'], 'account_billing', $id, 'billing', $billing->fields['billing_id'], &$db);		
			 	 			
						# Complete the transaction
			        	$db->CompleteTrans(); 
					}
					$billing->MoveNext(); 
				}
			}  
			$rs->MoveNext();
		}	

		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			 setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1500);
			 </script>"; 		
	}
	
	
	 
	 
	
	# Import any servers  
	function servers()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		  
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 
		  
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;
 
		# select each hosting server
		$sql = "SELECT 
					servers.*,
					provisioning.name AS plugin_name
				FROM 
					servers,provisioning
				WHERE
					servers.provisioning_id = provisioning.provisioning_id";
		$rs = $dbr->SelectLimit($sql, $this->select_limit, $VAR['offset']);
		if($rs === false) {
			$C_debug->alert("Query to the table 'servers' failed!");	
			return false;
		}		
		
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		}
		 
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each hosting server
		while(!$rs->EOF)
		{ 
			$msg.= "<BR>Processing Server: {$rs->fields['name']}...";
			 		
			# start a new transaction for the insert:
			$db = &DB();
			$db->StartTrans();
			
			# Determine the plugin type
			if(preg_match("/ENSIM/i", $rs->fields['plugin_name'])) {
				$plugin = 'ENSIM_LINUX_3';
			} else if(preg_match("/WHM/i", $rs->fields['plugin_name'])) {
				$plugin = 'WHM';
			} else if(preg_match("/PLESK/i", $rs->fields['plugin_name'])) {
				$plugin = 'PLESK_LINUX_6'; 
			} else {
				$plugin = 'MANUAL';				
			}  
			 			
			# Create the server record in AB now:
			$host_server_id = $db->GenID($p.'host_server_id');
			$sql = "INSERT INTO {$p}host_server SET
					id 				= {$host_server_id},
					site_id 		= {$s},						  
					name 			= ".$db->qstr($rs->fields['name']).",
					status 			= 1,
					debug 			= 1,
					provision_plugin= ".$db->qstr($plugin).",
					notes 			= ".$db->qstr($rs->fields['description']).",
					ip_based_ip		= ".$db->qstr($rs->fields['ip_list']).",
					name_based_ip 	= ".$db->qstr($rs->fields['ip_virt']).",
					name_based 		= 1";
			$db->Execute($sql);
			$this->import_transaction($this->plugin, $VAR['action'], 'host_server', $host_server_id, 'servers', $rs->fields['server_id'], &$db);
	 
			# Complete the transaction
        	$db->CompleteTrans(); 
			$rs->MoveNext();
		}	

		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			  setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1200);
			 </script>";		
	}	
		 
	 
	
	# Import any products  
	function products()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		  
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 
		  
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;
 
		# select each product from HostAdmin 
		$sql = "SELECT * FROM membership ";
		$rs = $dbr->SelectLimit($sql, $this->select_limit, $VAR['offset']);
		if($rs === false) {
			$C_debug->alert("Query to the table 'membership' failed!");	
			return false;
		}		
		
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		}
		 
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each remote account
		while(!$rs->EOF)
		{
			$msg.= "<BR>Processing Product: {$rs->fields['membership_name']}...";
			
			# start a new transaction for the insert:
			$db = &DB();
			$db->StartTrans();
						
			if($rs->fields['membership_active'] == "Y")
			$status = 1;
			else
			$status = 0;
			
			# set category 
			$categories = serialize ( Array( 0,1,2 ) );
			
			# price type (trial, one-time, recurring)
			if($rs->fields['membership_recurring'] == "Y") {
				# recurring
				$price_type = '1';
			} else {
				# one-time
				$price_type = '0';
			}
			
            # defaults for 'recurring' product
            if($price_type == "1")
            { 
                # Determine the recurring schedule:
                $freq = $rs->fields['membership_frequency']; 
				if ($freq=="7") 		{ $price_recurr_schedule = "0"; }	// weekly
				elseif ($freq=="14") 	{ $price_recurr_schedule = "0"; }	// Bi-Weekly
				elseif ($freq=="30")    { $price_recurr_schedule = "1"; }	// Monthly
				elseif ($freq=="31")  	{ $price_recurr_schedule = "1"; }	// Monthly
				elseif ($freq=="60")  	{ $price_recurr_schedule = "1"; }	// Bi-Monthly
				elseif ($freq=="90")  	{ $price_recurr_schedule = "2"; }	// Quarterly
				elseif ($freq=="180") 	{ $price_recurr_schedule = "3"; }	// Semi-Annually
				elseif ($freq=="360") 	{ $price_recurr_schedule = "4"; }	// Annually
				elseif ($freq=="365") 	{ $price_recurr_schedule = "4"; }	// Annually 
				else { $price_recurr_schedule = '1'; } 						// monthly
				
				
                $price_recurr_type 		= "0"; 
                $price_recurr_week 		= "1";
                $price_recurr_weekday 	= "1";				
				$price_recurr_default 	= $price_recurr_schedule; 
                
				
                # Set default recurring prices: (monthly only) 
                $sql    = 'SELECT id FROM ' . AGILE_DB_PREFIX . 'group WHERE
                            site_id         	= ' . $db->qstr(DEFAULT_SITE) . ' AND
                            pricing		        = ' . $db->qstr('1');
                $rsg = $db->Execute($sql); 
                while(!$rsg->EOF) { 
                	$i = $rsg->fields['id'];  
	                $recur_price[0][$i]['price_base']  = $rs->fields['membership_price'];
					$recur_price[0][$i]['price_setup'] = $rs->fields['membership_setup']; 
					@$recur_price[1][$i]['price_base'] = $rs->fields['membership_price'];
					@$recur_price[1][$i]['price_setup']= $rs->fields['membership_setup']; 
	                $recur_price[2][$i]['price_base']  = $rs->fields['membership_price'];
					$recur_price[2][$i]['price_setup'] = $rs->fields['membership_setup']; 
	                $recur_price[3][$i]['price_base']  = $rs->fields['membership_price'];
					$recur_price[3][$i]['price_setup'] = $rs->fields['membership_setup']; 
	                $recur_price[4][$i]['price_base']  = $rs->fields['membership_price'];
					$recur_price[4][$i]['price_setup'] = $rs->fields['membership_setup'];
	                $recur_price[5][$i]['price_base']  = $rs->fields['membership_price'];
					$recur_price[5][$i]['price_setup'] = $rs->fields['membership_setup'];
                	$rsg->MoveNext();	
                } 
                
                $recur_price[0]['show'] = "0"; 					
                $recur_price[1]['show'] = "0";
                $recur_price[2]['show'] = "0"; 
                $recur_price[3]['show'] = "0"; 
                $recur_price[4]['show'] = "0"; 
                $recur_price[5]['show'] = "0";   
                $recur_price[$price_recurr_schedule]['show'] = "1";                 
            }			
             
				
            # Get associated server  
            $sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
						ab_table = 'host_server' AND
						plugin = '{$this->plugin}' AND
						remote_id = '{$rs->fields['server']}'";
            $srvrs = $db->Execute($sql);  
            $host_server_id = $srvrs->fields['ab_id'];
           
             
			# Get a local id
			$id = $db->GenID($p.'product_id');            
	  
			# Insert the record
			$sql = "INSERT INTO {$p}product SET
					id 			= $id,
					site_id		= $s, 
					sku			= 'HA-$id',
					taxable		= 0, 
					active		= $status,
					  
					price_type		= '$price_type',
					price_base		= '{$rs->fields['membership_price']}',
					price_setup		= '{$rs->fields['membership_setup']}',
					price_group		= ".$db->qstr( serialize(@$recur_price) ).",	 
					
					price_recurr_default 	= '".@$price_recurr_default."',
					price_recurr_type		= '".@$price_recurr_type."',
					price_recurr_weekday 	= '".@$price_recurr_weekday."',
					price_recurr_week		= '".@$price_recurr_week."',
					price_recurr_schedule 	= '".@$price_recurr_schedule."',
					price_recurr_cancel 	= 1,
										 
					host					= 1,
					host_server_id			= '$host_server_id',
					host_provision_plugin_data = '',
					host_allow_domain 		= 1,
										 
					avail_category_id 		= ".$db->qstr($categories);
			$db->Execute($sql);
			 
			# Insert the import record
			$this->import_transaction($this->plugin, $VAR['action'], 'product', $id, 'membership', $rs->fields['membership_id'], &$db);		
			  
			
			### Insert the description:
			$idx = $db->GenID($p.'product_translate_id');
			
			$sql = "INSERT INTO {$p}product_translate SET
					id 					= $idx,
					site_id				= $s, 
					product_id			= $id,
					language_id 		= '".DEFAULT_LANGUAGE."',  
					name				= ".$db->qstr( $rs->fields['membership_name'] ).",
					description_short	= ".$db->qstr( $rs->fields['membership_desc'] ).", 
					description_full	= ".$db->qstr( $rs->fields['membership_desc'] ) ;
			$db->Execute($sql);

			# Insert the import record
			$this->import_transaction($this->plugin, $VAR['action'], 'product_translate', $idx, 'membership', $rs->fields['membership_id'], &$db);
				 
			# Complete the transaction
        	$db->CompleteTrans(); 
			$rs->MoveNext();
		}	

		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			  setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1200);
			 </script>";		
	}
	 
	
	
	# import the account and billing details 
	function host_tld()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		  
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 
		  
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;
 
		# select each account from remote db
		$sql = "SELECT * FROM domain_type ";
		$rs = $dbr->SelectLimit($sql, $this->select_limit, $VAR['offset']);
		if($rs === false) {
			$C_debug->alert("Query to the table 'domain_type' failed!");	
			return false;
		}		
		
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		}
		 
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each remote account
		while(!$rs->EOF)
		{
			$msg.= "<BR>Processing TLD: {$rs->fields['domain_type_extension']}...";
			
			# start a new transaction for the insert:
			$db = &DB();
			$db->StartTrans();
			
			# delete TLD if it exists already:
			$sql = "DELETE FROM {$p}host_tld WHERE site_id = {$s} AND name = '{$rs->fields['domain_type_extension']}'";
			$db->Execute($sql);
			
			# Get a local id
			$id = $db->GenID($p.'host_tld_id');
			
			# determine whois_plugin_data
			$whois_plugin_data = serialize( array( 'whois_server' => $rs->fields['domain_type_url'], 'avail_response' => $rs->fields['domain_type_response']) );
 
			# determine price group 
			$start = false;
			empty($price_group);
			$price_group = Array();
			for($i=1; $i<=10; $i++)
			{ 
				# Set default recurring prices:  
				$cost = $rs->fields["domain_type_pwo_{$i}"];
				if($cost != "") {
					$db = &DB();
					$sql    = 'SELECT id FROM ' . AGILE_DB_PREFIX . 'group WHERE
		                            site_id         	= ' . $db->qstr(DEFAULT_SITE) . ' AND
		                            pricing		        = ' . $db->qstr('1');
					$rsg = $db->Execute($sql);
					while(!$rsg->EOF) {
						$group = $rsg->fields['id'];
						$price_group["$i"]["show"] = 1;
						$price_group["$i"]["$group"]["register"] 	= round( $cost, 2);
						$price_group["$i"]["$group"]["renew"] 		= '';
						$price_group["$i"]["$group"]["transfer"] 	= '';
						$rsg->MoveNext();
					}
					if($start == false) $start = $i;
				}
			}
				 
			# Insert the record
			$sql = "INSERT INTO {$p}host_tld SET
					id 						= $id,
					site_id					= $s, 
					status 					= '1',
					name					= '{$rs->fields['domain_type_extension']}',
					taxable					= '1',
					whois_plugin			= 'DEFAULT', 
					whois_plugin_data 		= ".$db->qstr( $whois_plugin_data ).",
					registrar_plugin_id 	= 1,
					registrar_plugin_data 	= ".$db->qstr( serialize(array())).",
					auto_search 			= 1,
					default_term_new 		= $start, 
					price_group 			= ".$db->qstr( serialize( $price_group ) ); 
			$db->Execute($sql);
			 
			# Insert the import record
			$this->import_transaction($this->plugin, $VAR['action'], 'host_tld', $id, 'domain_type', $rs->fields['domain_type_id'], &$db);		
 	 			 
			# Complete the transaction
        	$db->CompleteTrans(); 
			$rs->MoveNext(); 
		}	

		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			  setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1200);
			 </script>"; 
	}	

	
	
	
	
	### Import all invoices from HostAdmin
	function invoices()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		  
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 
		  
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;
 
		# select each account from HostAdmin
		$sql = "SELECT * FROM orders";
		$rs = $dbr->SelectLimit($sql, $this->select_limit, $VAR['offset']);
		if($rs === false) {
			$C_debug->alert("Query to the table 'orders' failed!");	
			return false;
		}		
		
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		}
		 
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each remote account
		while(!$rs->EOF)
		{
			$msg.= "<BR>Processing Order: {$rs->fields['order_id']}...";
			
			# start a new transaction for the insert:
			$db = &DB();
			$db->StartTrans();
			
			# Get a local id
			$id = $db->GenID($p.'invoice_id');
			
			# Get orig date
			if(!empty($rs->fields['order_date'])) {
				$date = explode('-', $rs->fields['order_date']);
				$date_orig = mktime(0,0,0,$date[1], $date[2], $date[0]);
			} else {
				$date_orig = time();
			} 
		
			### Get the default checkout plugin id:		
			$sql = "SELECT id FROM {$p}checkout WHERE site_id = $s AND checkout_plugin = '{$this->gateway}'";
			$ch = $db->Execute($sql);		
			$checkout_plugin_id = $ch->fields['id'];
						 
			# get the process & billing status
			if($rs->fields['order_status'] == 1)
			{
				$process_status = 1;
				$billing_status = 1;
				$billed_amt		= $rs->fields['order_amount'];
			}
			else 
			{
				$process_status = 0;
				$billing_status = 0;
				$billed_amt		= 0;				
			} 
			
			# get the account id 
			$sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
						ab_table = 'account' AND
						plugin = '{$this->plugin}' AND
						remote_id = '{$rs->fields['order_account_id']}'";
			$account = $db->Execute($sql); 
			$account_id = $account->fields['ab_id'];
			
			# get the billing id
			$sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
						ab_table = 'account_billing' AND
						plugin = '{$this->plugin}' AND
						remote_id = '{$rs->fields['order_billing_id']}'";
			$billing = $db->Execute($sql); 
			$billing_id = $billing->fields['ab_id'];			 
	  		
			# Insert the record
			$sql = "INSERT INTO {$p}invoice SET
					id 					= $id,
					site_id				= $s, 
					date_orig			= ".$db->qstr($date_orig).",
					date_last			= ".$db->qstr(time()).", 
					
					process_status		= ".$db->qstr(@$process_status).",
					billing_status		= ".$db->qstr(@$billing_status).",
					account_id			= ".$db->qstr(@$account_id).",
					account_billing_id 	= ".$db->qstr(@$billing_id).", 
					checkout_plugin_id 	= ".$db->qstr(@$checkout_plugin_id).", 
					
					tax_amt				= 0,
					discount_amt 		= 0,
					total_amt			= ".$db->qstr(@$rs->fields['order_amount']).",
					billed_amt			= ".$db->qstr(@$billed_amt).",
					billed_currency_id 	= ".$db->qstr(DEFAULT_CURRENCY).",
					actual_billed_amt 	= ".$db->qstr(@$billed_amt).",
					actual_billed_currency_id = ".$db->qstr(DEFAULT_CURRENCY).",
					
					notice_count		= 0,
					notice_max 			= 1,
					notice_next_date	= ".$db->qstr(time() + 86400).",
					grace_period		= ".GRACE_PERIOD.",
					due_date 			= ".$db->qstr(time());
			$db->Execute($sql);
			 
			# Insert the import record
			$this->import_transaction($this->plugin, $VAR['action'], 'invoice', $id, 'invoices', $rs->fields['order_id'], &$db);		
					   
			# Complete the transaction
        	$db->CompleteTrans(); 
			$rs->MoveNext();
		}	

		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			  setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1200);
			 </script>";		
	}
	
	
	
	
	## Import DA subscriptions & line items
	function services()
	{
		global $VAR, $C_debug;
		$p = AGILE_DB_PREFIX;
		$s = DEFAULT_SITE;
		  
		### Connect to the remote Db;
		$dbr = &NewADOConnection($this->type);
		$dbr->Connect($this->host, $this->user, $this->pass, $this->db); 
		  
		### Determine the offset for the account
		if(empty($VAR['offset'])) $VAR['offset'] = 0;
		@$offset = $VAR['offset'].",".$this->select_limit;
 
		# select each account from HostAdmin
		$sql = "SELECT domains.*,account.account_password 
				FROM 
				domains,account
				WHERE
				domains.domain_account_id = account.account_id";
		$rs = $dbr->SelectLimit($sql, $this->select_limit, $VAR['offset']);
		if($rs === false) {
			$C_debug->alert("Query to the table 'domains' failed!");	
			return false;
		}		
		
		if($rs->RecordCount() == 0) {
			$C_debug->alert("No more records to process!");	
			echo "<script language=javascript>setTimeout('document.location=\'?_page=import:import&plugin={$VAR['plugin']}\'', 1500); </script>";			
			return;
		}
		 
		$msg = "Processing ".$rs->RecordCount()." Records...<BR>";
		
		# loop through each remote account
		while(!$rs->EOF)
		{
			$msg.= "<BR>Processing Subscription: {$rs->fields['domain_id']}...";
			
			# start a new transaction for the insert:
			$db = &DB();
			$db->StartTrans();
			 
			
			# Get orig date
			if(!empty($rs->fields['domain_start_date'])) {
				$date = explode('-', $rs->fields['domain_start_date']);
				$date_orig = mktime(0,0,0,$date[1], $date[2], $date[0]);
			} else {
				$date_orig = time();
			} 
								 
			### Get the default checkout plugin id:		
			$sql = "SELECT id FROM {$p}checkout WHERE
					site_id = $s AND
					checkout_plugin = '{$this->gateway}'";
			$ch = $db->Execute($sql);		
			$checkout_plugin_id = $ch->fields['id'];
				 
			# get the account id 
			$sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
						ab_table = 'account' AND
						plugin = '{$this->plugin}' AND
						remote_id = '{$rs->fields['domain_account_id']}'";
			$account = $db->Execute($sql); 
			$account_id = $account->fields['ab_id'];
			
			# get the billing id
			$sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
						ab_table = 'account_billing' AND
						plugin = '{$this->plugin}' AND
						remote_id = '{$rs->fields['domain_billing_id']}'";
			$billing = $db->Execute($sql); 
			$billing_id = $billing->fields['ab_id'];	

			# get the invoice id
			$sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
						ab_table = 'invoice' AND
						plugin = '{$this->plugin}' AND
						remote_id = '{$rs->fields['domain_order_id']}'";
			$invoice = $db->Execute($sql); 
			$invoice_id = $invoice->fields['ab_id'];	

			 
			# Status
			if($rs->fields['domain_host_status'] == 1) {
				$active = 1;
				$suspend = 0;
			} else {
				$active = 0;
				$suspend = 1;				
			}
			
			$term   = $rs->fields['domain_years'];
			$domain_name = strtolower($rs->fields['domain_name']);
			$arr = explode('\.', $domain_name);
			$tld = '';
			$domain =  $arr[0];
			for($i=0; $i<count($arr); $i++)  {
				if($i>0) {
					if($i>1) 
					$tld .= ".";
					$tld .= $arr[$i];
				}
			}
						
			# Determine the tld_id 
			$sql = "SELECT id,registrar_plugin_id FROM {$p}host_tld WHERE site_id = {$s} AND name= '{$tld}'";
			$tldrs = $db->Execute($sql); 
			$domain_host_tld_id = $tldrs->fields['id'];	
			$domain_host_registrar_id = $tldrs->fields['registrar_plugin_id'];	
				 														
			# Determine the SKU for the DOMAIN SERVICE record
			if($rs->fields['domain_years'] > 0)  {
				# Domain transfer / hosting only
				$sku		 = 'DOMAIN-REGISTER';
				$domain_type = 'register'; 
				
				# Get the price
				$dbr = &NewADOConnection($this->type);
				$dbr->Connect($this->host, $this->user, $this->pass, $this->db);   
				$sql = "SELECT * FROM domain_type WHERE domain_type_id = {$rs->fields['domain_type_id']}";
				$domainrs = $dbr->SelectLimit($sql, $offset); 
				if(empty($rs->fields['domain_host_id']))  
				$price = $domainrs->fields["domain_type_pwo{$term}"];
				else
				$price = $domainrs->fields["domain_type_p{$term}"]; 
				$db = &DB();
			}  else  {
				# Domain registration + Hosting
				$sku = 'DOMAIN-TRANSFER';
				$domain_type = 'ns_transfer';		
				$price		 = 0;		
			}
			 
			
			### Create the DOMAIN service records: 
			if($sku != 'DOMAIN-TRANSFER') 
			{ 
				# Determine the domain expire date:
				$date_expire = $date_orig + (86400*365* $rs->fields['domain_years']);
							
				# Insert the service record
				$id = $db->GenID($p.'service_id');
				$sql = "INSERT INTO {$p}service SET
						id 					= $id,
						site_id				= $s, 
						queue				= 'none',
						date_orig			= ".$db->qstr($date_orig).",
						date_last			= ".$db->qstr(time()).",  
						invoice_id			= ".$db->qstr(@$invoice_id).", 
						account_id			= ".$db->qstr(@$account_id).",
						account_billing_id	= ".$db->qstr(@$billing_id).",
						product_id			= ".$db->qstr(@$product_id).",
						sku					= ".$db->qstr($sku).", 
						type				= ".$db->qstr('domain').", 
						active				= 1,  
						price				= ".$db->qstr($price).",
						price_type			= ".$db->qstr('0').",
						taxable				= ".$db->qstr('0').", 
						
						domain_date_expire	= ".$db->qstr( $date_expire ).",
						domain_host_tld_id	= ".$db->qstr( $domain_host_tld_id ).",
						domain_host_registrar_id = ".$db->qstr( $domain_host_registrar_id ).",
						
						domain_name 		= ".$db->qstr( $domain ).",
						domain_term		  	= ".$db->qstr( $term ).",
						domain_tld  		= ".$db->qstr( $tld ).",
						domain_type			= ".$db->qstr( $domain_type ); 										
				$db->Execute($sql);
				 
				# Insert the import record
				$this->import_transaction($this->plugin, $VAR['action'], 'service', $id, 'domains', $rs->fields['domain_id'], &$db);		
			}
						   
			# Insert the DOMAIN invoice_item record:
			$idx = $db->GenID($p.'invoice_item_id'); 
			$sql = "INSERT INTO {$p}invoice_item SET
						id 					= $idx,
						site_id				= $s,  
						invoice_id			= ".$db->qstr(@$invoice_id).",  
						date_orig			= ".$db->qstr($date_orig).", 
						sku					= ".$db->qstr($sku).",
						quantity			= 1,
						item_type			= 2,
						price_type			= 0,
						price_base			= ".$db->qstr( $price ).", 
						domain_name 		= ".$db->qstr( $domain ).",
						domain_term		  	= ".$db->qstr( $term ).",
						domain_tld  		= ".$db->qstr( $tld ).",
						domain_type			= ".$db->qstr( $domain_type ); 				 
			$db->Execute($sql);
			
			# Insert the import record
			$this->import_transaction($this->plugin, $VAR['action'], 'invoice_item', $idx, 'domains', $rs->fields['domain_id'], &$db);		
			   
			 
			#### HOSTING Service & Item insertion
			if(!empty($rs->fields['domain_host_id']))  
			{				 
				# get the product id
				$sql = "SELECT ab_id FROM {$p}import WHERE site_id = {$s} AND
							ab_table = 'product' AND
							plugin = '{$this->plugin}' AND
							remote_id = '{$rs->fields['domain_host_id']}'";
				$product = $db->Execute($sql); 
				$product_id = $product->fields['ab_id'];
				
				# Get the product details	
				$sql = "SELECT * FROM {$p}product WHERE site_id = {$s} AND id = {$product_id}";
				$product = $db->Execute($sql);  
				
				$sku = $product->fields['sku'];			
				
				
				# Get last billed date date
				if(!empty($rs->fields['domain_host_last_billed'])) {
					$date = explode('-', $rs->fields['domain_host_last_billed']);
					$date_last = mktime(0,0,0,$date[1], $date[2], $date[0]);
				} else {
					$date_last = $date_orig;
				} 
							
				# Calculate next bill date:  
				include_once(PATH_MODULES . 'service/service.inc.php');
				$service = new service;
				$date_next = $service->calcNextInvoiceDate( $date_last,
														    $product->fields['price_recurr_default'],
															$product->fields['price_recurr_type'],
															$product->fields['price_recurr_weekday'],
															$product->fields['price_recurr_week'] );			
				
				### Create the HOSTING service records:   
				$id = $db->GenID($p.'service_id');
			 	$sql = "INSERT INTO {$p}service SET
						id 					= $id,
						site_id				= $s, 
						queue				= 'none',
						date_orig			= ".$db->qstr($date_orig).",
						date_last			= ".$db->qstr(time()).",  
						invoice_id			= ".$db->qstr(@$invoice_id).", 
						account_id			= ".$db->qstr(@$account_id).",
						account_billing_id	= ".$db->qstr(@$billing_id).",
						product_id			= ".$db->qstr(@$product_id).", 
						sku					= ".$db->qstr($sku).",  
						type				= ".$db->qstr('host').", 
						
						active				= ".$db->qstr($active).", 
						suspend_billing		= ".$db->qstr($suspend).", 
						
						date_last_invoice	= ".$db->qstr($date_orig).",
						date_next_invoice	= ".$db->qstr($date_next).",
						 
						price				= ".$db->qstr( $product->fields['price_base'] ).",
						price_type			= 1,
						taxable				= 1,	
										
						recur_type			= ".$db->qstr($product->fields['price_recurr_type']).",
						recur_schedule		= ".$db->qstr($product->fields['price_recurr_schedule']).",
						recur_weekday		= ".$db->qstr($product->fields['price_recurr_weekday']).",
						recur_week			= ".$db->qstr($product->fields['price_recurr_week']).",
						recur_cancel		= ".$db->qstr($product->fields['price_recurr_cancel']).",
						recur_schedule_change = ".$db->qstr($product->fields['price_recurr_modify']).",

						host_username		= ".$db->qstr( $rs->fields['cp_login'] ).",
						host_password		= ".$db->qstr( $rs->fields['account_password'] ).",
						
						host_server_id				= ".$db->qstr( $product->fields['host_server_id'] ).",
						host_provision_plugin_data 	= ".$db->qstr( $product->fields['host_provision_plugin_data'] ).",
						host_ip						= ".$db->qstr( $rs->fields['ip'] ).",
						 
						domain_host_tld_id			= ".$db->qstr( $domain_host_tld_id ).",
						domain_host_registrar_id 	= ".$db->qstr( $domain_host_registrar_id ).",
						
						domain_name 		= ".$db->qstr( $domain ).", 
						domain_tld  		= ".$db->qstr( $tld ) ;	

				$db->Execute($sql);

				# Insert the import record
				$this->import_transaction($this->plugin, $VAR['action'], 'service', $id, 'domains', $rs->fields['domain_id'], &$db);

							  
				 
				# Insert the HOSTING invoice_item record:
				$idx = $db->GenID($p.'invoice_item_id'); 
				$sql = "INSERT INTO {$p}invoice_item SET
						id 					= $idx,
						site_id				= $s,  
						invoice_id			= ".$db->qstr(@$invoice_id).", 
						product_id			= ".$db->qstr(@$product_id).",
						date_orig			= ".$db->qstr($date_orig).",  
						sku					= ".$db->qstr($sku).",
						quantity			= 1,
						item_type			= 1, 
						
						price_type			= 1,
						price_base			= ".$db->qstr( $product->fields['price_base'] ).", 
						domain_name 		= ".$db->qstr( $domain ).", 
						domain_tld  		= ".$db->qstr( $tld ).", 	 
						
						recurring_schedule  = ".$db->qstr( $product->fields['price_recurr_schedule'] ); 				 
				$db->Execute($sql);
				
				# Insert the import record
				$this->import_transaction($this->plugin, $VAR['action'], 'invoice_item', $idx, 'domains', $rs->fields['domain_id'], &$db);		
			}
 
			 
			# Complete the transaction
        	$db->CompleteTrans(); 
			$rs->MoveNext();
		}	
  
		
		$C_debug->alert($msg);	
		$offset =  $VAR['offset'] + $this->select_limit;
		echo "<script language=javascript> 
			  setTimeout('document.location=\'?_page=core:blank&offset={$offset}&action={$VAR['action']}&plugin={$VAR['plugin']}&do[]=import:do_action\'', 1200);
			 </script>";				
	}
	
	
	
	
	// decryption function for old DA credit cards
	function RC4($data, $case) {
 
		include($this->salt);

		if ($case == 'de') {
			$data = urldecode($data);
		}
		$key[] = "";
		$box[] = "";
		$temp_swap = "";
		$pwd_length = 0;
		$pwd_length = strlen($pwd);

		for ($i = 0; $i <= 255; $i++) {
			$key[$i] = ord(substr($pwd, ($i % $pwd_length), 1));
			$box[$i] = $i;
		}
		$x = 0;

		for ($i = 0; $i <= 255; $i++) {
			$x = ($x + $box[$i] + $key[$i]) % 256;
			$temp_swap = $box[$i];
			$box[$i] = $box[$x];
			$box[$x] = $temp_swap;
		}
		$temp = "";
		$k = "";
		$cipherby = "";
		$cipher = "";
		$a = 0;
		$j = 0;
		for ($i = 0; $i < strlen($data); $i++) {
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$temp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $temp;
			$k = $box[(($box[$a] + $box[$j]) % 256)];
			$cipherby = ord(substr($data, $i, 1)) ^ $k;
			$cipher .= chr($cipherby);
		}

		if ($case == 'de') {
			$cipher = urldecode(urlencode($cipher));
		} else {
			$cipher = urlencode($cipher);
		}
		return $cipher;
	}	
	
	
	// DETERMINE CREDIT CARD TYPE
	function cc_identify($cc_no)     {
         $cc_no = preg_replace ('/[^0-9]+/', '', $cc_no);

        // Get card type based on prefix and length of card number
        if (preg_match ('/^4(.{12}|.{15})$/', $cc_no)) {
            return 'visa';
        } elseif (preg_match ('/^5[1-5].{14}$/', $cc_no)) {
            return 'mc';
        } elseif (preg_match ('/^3[47].{13}$/', $cc_no)) {
            return 'amex';
        } elseif (preg_match ('/^3(0[0-5].{11}|[68].{12})$/', $cc_no)) {
            return 'diners';
        } elseif (preg_match ('/^6011.{12}$/', $cc_no)) {
            return 'discover';
        } elseif (preg_match ('/^(3.{15}|(2131|1800).{11})$/', $cc_no)) {
            return 'jcb';
        } elseif (preg_match ('/^2(014|149).{11})$/', $cc_no)) {
            return 'enrout';
       } else {
 		 return "";
       }
	}	
}		 		
?>